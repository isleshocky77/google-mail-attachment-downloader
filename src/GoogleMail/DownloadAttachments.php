<?php

declare(strict_types=1);

namespace GmailFileDownloader\GoogleMail;

use Google\Service\Gmail\MessagePartHeader;
use Google_Client;
use Google_Service_Gmail;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

class DownloadAttachments
{
    private const FILE_DOWNLOAD_DIR = 'attachments';

    private const AUTH_FILES_DIR = 'auth';

    private const CREDENTIALS_FILENAME = 'credentials.json';

    private const TOKEN_FILENAME = 'token.json';

    /** @var Google_Service_Gmail */
    private $service;

    /** @var Filesystem */
    private $filesystem;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->filesystem = new Filesystem();

        $client = $this->getClient();
        $this->service = new Google_Service_Gmail($client);
    }

    public function do(
        array $query = [],
        string $startingPageToken = null
    ): void {
        $user = 'me';

        $pageToken = $startingPageToken;
        $page = 0;
        do {
            $results = $this->service->users_messages->listUsersMessages($user, [
                'pageToken' => $pageToken,
                'q' => $query,
            ]);
            $page++;

            if (count($results->getMessages()) === 0) {
                $this->logger->warning('No messages found');
            } else {
                if ($pageToken === null) {
                    $this->logger->info("Messages ({$results->getResultSizeEstimate()})");
                }

                $this->logger->debug(sprintf('=== Begin of page #%s / pageToken %s ===', $page, $pageToken ?? 'NONE'));
                foreach ($results->getMessages() as $message) {
                    $messageId = $message->getId();
                    $this->logger->debug(sprintf('- message id: %s', $messageId));

                    $detailedMessage = $this->service->users_messages->get($user, $messageId);

                    $headers = $detailedMessage->getPayload()->getHeaders();
                    $subject = array_values(array_filter($headers, static function (MessagePartHeader $header): bool {
                        return $header->getName() === 'Subject';
                    }))[0]->value ?? '';
                    $date = array_values(array_filter($headers, static function (MessagePartHeader $header): bool {
                        return $header->getName() === 'Date';
                    }))[0]->value ?? '';
                    $this->logger->debug(sprintf('-- %s %s', $date, $subject));

                    if ($detailedMessage->getPayload()->getFilename()) {
                        $this->saveAttachment(
                            $user,
                            $messageId,
                            $detailedMessage->getPayload()->getFilename(),
                            $detailedMessage->getPayload()->getBody()->getAttachmentId()
                        );
                    }

                    $parts = $detailedMessage->getPayload()->getParts();
                    foreach ($parts as $part) {
                        if ($part->getFilename()) {
                            $this->saveAttachment(
                                $user,
                                $messageId,
                                $part->getFilename(),
                                $part->getBody()->getAttachmentId()
                            );
                        }
                    }
                }
                $this->logger->debug(sprintf('=== End of page #%s / pageToken %s ==', $page, $pageToken ?? 'NONE'));

                $pageToken = $results->getNextPageToken();
            }
        } while ($pageToken !== null);
    }

    private function saveAttachment(
        string $user,
        string $messageId,
        string $attachmentFilename,
        string $attachmentId
    ): void {
        if ($this->filesystem->exists(self::FILE_DOWNLOAD_DIR . '/' . $attachmentFilename)) {
            $this->logger->warning('attachment already saved');

            return;
        }

        $this->logger->info(sprintf('--- saving attachment %s', $attachmentId));

        $attachment = $this->service->users_messages_attachments->get($user, $messageId, $attachmentId);
        // translate data to standard RFC 4648 base64-encoding
        $data = base64_decode(strtr($attachment->getData(), ['-' => '+', '_' => '/']));
        $this->filesystem->dumpFile(self::FILE_DOWNLOAD_DIR . '/' . $attachmentFilename, $data);
    }

    private function getClient(): Google_Client
    {
        $client = new Google_Client();
        $client->setApplicationName('Google Mail Attachment Downloader');
        $client->setScopes(Google_Service_Gmail::GMAIL_READONLY);
        $client->setAuthConfig(self::AUTH_FILES_DIR . '/' . self::CREDENTIALS_FILENAME);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.
        $tokenPath = self::AUTH_FILES_DIR . '/' . self::TOKEN_FILENAME;

        if ($this->filesystem->exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true, 512, JSON_THROW_ON_ERROR);
            $client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $redirectUri = 'http://localhost';
                $client->setRedirectUri($redirectUri);
                $authUrl = $client->createAuthUrl();

                printf("Open the following link in your browser:\n%s\n", $authUrl);
                echo 'Enter verification code: ';
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new \Exception(implode(', ', $accessToken));
                }
            }

            // Save the token to a file.
            if ($this->filesystem->exists(dirname($tokenPath)) === false) {
                $this->filesystem->mkdir(dirname($tokenPath), 0700);
            }
            $this->filesystem->dumpFile($tokenPath, json_encode($client->getAccessToken(), JSON_THROW_ON_ERROR));
        }

        return $client;
    }
}
