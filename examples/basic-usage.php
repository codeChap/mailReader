<?php

require __DIR__ . '/../vendor/autoload.php';

use Codechap\MailReader\MailReader;
use Codechap\MailReader\Exceptions\ConnectionException;
use Codechap\MailReader\Exceptions\MailReaderException;

// Replace with your email server details
$dsn = 'imap://user@example.com:password123@mail.example.com:993/ssl';

try {
    echo "=== Mail Reader Example ===\n\n";
    
    // Create mail reader instance
    $mailReader = new MailReader($dsn);
    
    // Connect to the mail server
    echo "Connecting to mail server...\n";
    $mailReader->connect();
    echo "Connected successfully!\n\n";
    
    // Get available mailboxes
    echo "Available mailboxes:\n";
    $mailboxes = $mailReader->getMailboxes();
    foreach ($mailboxes as $mailbox) {
        echo "- $mailbox\n";
    }
    echo "\n";
    
    // Select INBOX mailbox
    echo "Selecting INBOX mailbox...\n";
    $mailReader->selectMailbox('INBOX');
    
    // Get unread emails
    echo "Fetching unread emails...\n";
    $unreadEmails = $mailReader->getEmails(true, 5); // true = only unread, limit to 5
    
    echo "Found " . count($unreadEmails) . " unread email(s)\n\n";
    
    // Display email information
    foreach ($unreadEmails as $index => $email) {
        echo "Email #" . ($index + 1) . "\n";
        echo "Subject: " . $email['subject'] . "\n";
        echo "From: " . $email['from'] . "\n";
        echo "Date: " . $email['date'] . "\n";
        
        // Check for attachments
        if ($email['has_attachments']) {
            echo "Attachments: " . count($email['attachments']) . "\n";
            foreach ($email['attachments'] as $attachment) {
                echo "  - " . $attachment['filename'] . " (" . $attachment['size'] . " bytes)\n";
            }
        }
        
        echo "\n";
    }
    
    // Search for emails with specific subject
    echo "Searching for emails with 'Important' in subject...\n";
    $searchResults = $mailReader->search('Important', 'SUBJECT');
    echo "Found " . count($searchResults) . " matching email(s)\n\n";
    
    // Search for recent emails
    echo "Searching for emails from the last 7 days...\n";
    $since = new DateTime('7 days ago');
    $dateResults = $mailReader->searchByDate($since);
    echo "Found " . count($dateResults) . " email(s) from the last week\n\n";
    
    // If we have an email with attachments, download the first attachment
    $emailWithAttachment = null;
    foreach ($unreadEmails as $email) {
        if ($email['has_attachments']) {
            $emailWithAttachment = $email;
            break;
        }
    }
    
    if ($emailWithAttachment) {
        echo "Downloading attachment from email: " . $emailWithAttachment['subject'] . "\n";
        $attachment = $emailWithAttachment['attachments'][0];
        $savePath = __DIR__ . '/downloads/' . $attachment['filename'];
        
        $result = $mailReader->downloadAttachment(
            $emailWithAttachment['id'],
            $attachment['index'],
            $savePath
        );
        
        echo "Downloaded: " . $result['filename'] . " to " . $result['path'] . "\n\n";
    }
    
    // Disconnect from the server
    echo "Disconnecting from mail server...\n";
    $mailReader->disconnect();
    echo "Disconnected successfully!\n";
    
} catch (ConnectionException $e) {
    echo "Connection error: " . $e->getMessage() . "\n";
} catch (MailReaderException $e) {
    echo "Mail reader error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
}