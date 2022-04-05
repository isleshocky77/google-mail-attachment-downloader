<?php

declare(strict_types=1);

namespace GmailFileDownloader\Command;

use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class DownloadAttachments extends Command
{
    protected static $defaultName = 'gmail-file-downloader:download-attachments';

    protected function configure(): void
    {
        $this->addOption('starting-page-token', null, InputOption::VALUE_OPTIONAL, 'The token of first page to start downloading');
        $this->addOption('query', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Queries to use in the query');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatLevelMap = [
            LogLevel::CRITICAL => ConsoleLogger::ERROR,
            LogLevel::DEBUG => ConsoleLogger::INFO,
        ];
        $logger = new ConsoleLogger($output, [], $formatLevelMap);
        $downloader = new \GmailFileDownloader\GoogleMail\DownloadAttachments($logger);

        try {
            $downloader->do(
                $input->getOption('query'),
                $input->getOption('starting-page-token')
            );
        } catch (\Throwable $e) {
            $logger->error('error doing, message: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
