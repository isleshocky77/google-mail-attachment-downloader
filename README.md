# Google Mail Attachment Downloader

This script downloads all attachments from Google Mail (Gmail) Emails for a given query.

## Installation

```shell
git clone https://github.com/isleshocky77/google-mail-attachment-downloader.git
cd google-mail-attachment-downloader
composer install
```

### Google Apps Authentication

You must create a [Google Cloud Application](https://console.cloud.google.com/), get the credentials and place them at `auth/credentials.json`.
On first run it will ask you to go a url to authorize the application with instructions.

## Usage

```shell
./bin/console -vvv gmail-file-downloader:download-attachments --query="label:emails-i-need"

# Multiple query criteria
./bin/console -vvv gmail-file-downloader:download-attachments --query="label:emails-i-need" --query="from:john@example.org"
```

### Continuing

The verbose email will print out page numbers with a page token. If you need to stop or there is an error
you can pick up where it left with the following

```shell
./bin/console -vvv gmail-file-downloader:download-attachments --query="label:emails-i-need" --starting-page-token=06638643197320647210
```

## Development

### Tools

```shell
./bin/php-cs-fixer fix
./bin/phpstan
./bin/phpmd src/ text phpmd.xml.dist
./bin/psalm  --show-info=true
```
