<?php

require __DIR__ . '/../vendor/autoload.php';

use Codechap\MailReader\MailReader;
use Codechap\MailReader\Exceptions\ConnectionException;
use Codechap\MailReader\Exceptions\MailReaderException;

// This example demonstrates advanced usage of the MailReader package
// including the GetSet trait and proper exception handling

echo "=== Advanced Mail Reader Example ===\n\n";

try {
    // Create MailReader instance without DSN
    $mailReader = new MailReader();
    
    // Use the GetSet trait to set properties
    echo "Setting DSN using GetSet trait...\n";
    $mailReader->set('dsn', 'imap://user@example.com:password123@mail.example.com:993/ssl');
    
    // You can also get properties
    echo "Current mailbox: " . $mailReader->get('mailbox') . "\n\n";
    
    // Setting an invalid property would throw an exception
    try {
        $mailReader->set('invalidProperty', 'value');
    } catch (\InvalidArgumentException $e) {
        echo "Expected error: " . $e->getMessage() . "\n\n";
    }
    
    // Connect to the server
    echo "Connecting to server...\n";
    $mailReader->connect();
    
    // Advanced pattern: Connection reuse with multiple mailboxes
    echo "Working with multiple mailboxes...\n";
    $mailboxes = [
        'INBOX',
        'INBOX.Sent',
        'INBOX.Drafts'
    ];
    
    foreach ($mailboxes as $mailbox) {
        try {
            echo "\nSelecting mailbox: $mailbox\n";
            $mailReader->selectMailbox($mailbox);
            
            // Get mailbox statistics
            $emails = $mailReader->getEmails(false, 1); // Just get 1 email to check count
            echo "- $mailbox contains " . count($emails) . " emails\n";
            
            // Check specifically for unread emails
            $unreadEmails = $mailReader->getEmails(true, 1);
            echo "- $mailbox contains " . count($unreadEmails) . " unread emails\n";
        } catch (MailReaderException $e) {
            echo "- Error with mailbox $mailbox: " . $e->getMessage() . "\n";
            // Continue with next mailbox instead of aborting
            continue;
        }
    }
    
    // Advanced searching
    echo "\nPerforming advanced searches...\n";
    
    // Search for emails from a specific sender
    echo "Emails from specific sender:\n";
    $fromEmails = $mailReader->search('example.com', 'FROM', false, 3);
    foreach ($fromEmails as $email) {
        echo "- {$email['subject']} (from: {$email['from']})\n";
    }
    
    // Search emails from the last month
    echo "\nEmails from last month:\n";
    $lastMonth = new DateTime('1 month ago');
    $today = new DateTime();
    $recentEmails = $mailReader->searchByDate($lastMonth, $today, false);
    echo "Found " . count($recentEmails) . " emails from last month\n";
    
    // Demonstrate error handling
    echo "\nDemonstrating error handling:\n";
    
    // Invalid DSN format
    try {
        $invalidReader = new MailReader();
        $invalidReader->set('dsn', 'invalid-dsn-format');
        $invalidReader->connect();
    } catch (ConnectionException $e) {
        echo "Connection error (expected): " . $e->getMessage() . "\n";
    }
    
    // Invalid message ID
    try {
        $invalidMessageId = 999999; // A message ID that likely doesn't exist
        $mailReader->getEmail($invalidMessageId);
    } catch (MailReaderException $e) {
        echo "Message error (expected): " . $e->getMessage() . "\n";
    }
    
    // Invalid attachment
    try {
        $mailReader->downloadAttachment(1, 999, '/tmp/nonexistent.txt');
    } catch (MailReaderException $e) {
        echo "Attachment error (expected): " . $e->getMessage() . "\n";
    }
    
    // Clean disconnection
    echo "\nDisconnecting from mail server...\n";
    $mailReader->disconnect();
    
    // Illustrate an attempt to use after disconnection
    try {
        $mailReader->getMailboxes();
    } catch (ConnectionException $e) {
        echo "Post-disconnect error (expected): " . $e->getMessage() . "\n";
    }
    
} catch (ConnectionException $e) {
    echo "Fatal connection error: " . $e->getMessage() . "\n";
} catch (MailReaderException $e) {
    echo "Fatal mail reader error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "Unexpected error: " . $e->getMessage() . "\n";
}

echo "\nAdvanced example completed.\n";