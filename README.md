# Mail Reader

A PHP package for reading emails from mail servers via IMAP. This package follows PSR-4 standards and provides a clean, object-oriented interface for email operations.

## Requirements

- PHP 8.0 or higher
- PHP IMAP extension (make sure it's enabled in your PHP configuration)

## Installation

Install via Composer:

```bash
composer require codechap/mail-reader dev-master
```

Or add to your composer.json:

```json
{
    "require": {
        "codechap/mail-reader": "dev-master"
    }
}
```

Make sure the PHP IMAP extension is installed:

```bash
# On Debian/Ubuntu
sudo apt-get install php-imap

# On CentOS/RHEL
sudo yum install php-imap

# On macOS with Homebrew
brew install php && brew install php-imap
```

## Usage

This package provides a simple, object-oriented interface for reading emails:

```php
<?php

require 'vendor/autoload.php';

use Codechap\MailReader\MailReader;

// Create a new MailReader instance with DSN
$mailReader = new MailReader('imap://user@example.com:password@mail.example.com:993/ssl');

// Connect to the server
$mailReader->connect();

// Get available mailboxes
$mailboxes = $mailReader->getMailboxes();
print_r($mailboxes);

// Get unread emails
$emails = $mailReader->getEmails(true); // true = only unread
foreach ($emails as $email) {
    echo "Subject: " . $email['subject'] . "\n";
    echo "From: " . $email['from'] . "\n";
    echo "Date: " . $email['date'] . "\n";
    
    // Check for attachments
    if ($email['has_attachments']) {
        echo "Has " . count($email['attachments']) . " attachments\n";
    }
}

// Search for specific emails
$results = $mailReader->search('invoice', 'SUBJECT');

// Download an attachment
$attachment = $mailReader->downloadAttachment(123, 0, '/path/to/save/file.pdf');
echo "Downloaded " . $attachment['filename'] . " to " . $attachment['path'];

// Disconnect when done
$mailReader->disconnect();
```

## Key Features

### DSN Connection Strings

Use Data Source Name (DSN) strings for connection:

```php
// Set DSN during instantiation
$mailReader = new MailReader('imap://user@example.com:password@mail.example.com:993/ssl');

// Or set it later using the GetSet trait
$mailReader = new MailReader();
$mailReader->set('dsn', 'imap://user@example.com:password@mail.example.com:993/ssl');
```

DSN Format:
```
protocol://username:password@host:port/security
```

Examples:
- `imap://user@example.com:password123@mail.example.com:993/ssl`
- `pop3://user@example.com:password123@mail.example.com:995/ssl`

### Email Operations

```php
// Select a different mailbox
$mailReader->selectMailbox('Sent');

// Get all emails (with optional limit)
$allEmails = $mailReader->getEmails(false, 10); // false = include read, limit to 10

// Get a specific email
$email = $mailReader->getEmail(123); // message ID

// Search by date
$since = new DateTime('7 days ago');
$before = new DateTime('today');
$recentEmails = $mailReader->searchByDate($since, $before);
```

## Working with Properties

The package uses the `GetSet` trait for property access:

```php
// Set a property
$mailReader->set('mailbox', 'INBOX.Sent');

// Get a property
$currentMailbox = $mailReader->get('mailbox');
```

## Exception Handling

The package uses specific exceptions for different error types:

```php
use Codechap\MailReader\Exceptions\ConnectionException;
use Codechap\MailReader\Exceptions\MailReaderException;

try {
    $mailReader = new MailReader('imap://user:pass@example.com');
    $mailReader->connect();
    // ...
} catch (ConnectionException $e) {
    // Handle connection issues
    echo "Connection error: " . $e->getMessage();
} catch (MailReaderException $e) {
    // Handle other mail reader errors
    echo "Mail reader error: " . $e->getMessage();
}
```

## License

This package is licensed under the MIT License.