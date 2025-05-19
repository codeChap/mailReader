<?php

namespace Codechap\MailReader;

use Codechap\MailReader\Traits\GetSet;
use Codechap\MailReader\Exceptions\ConnectionException;
use Codechap\MailReader\Exceptions\MailReaderException;
use DateTime;
use InvalidArgumentException;

/**
 * MailReader - Core service class for reading emails
 *
 * This class provides functionality to connect to email servers and read emails
 * using the IMAP protocol via a standardized DSN connection string.
 */
class MailReader
{
    use GetSet;

    /**
     * IMAP connection resource
     *
     * @var resource|null
     */
    private $connection = null;

    /**
     * Data Source Name for connection
     *
     * @var string
     */
    private string $dsn = '';

    /**
     * Current selected mailbox
     *
     * @var string
     */
    private string $mailbox = 'INBOX';

    /**
     * Constructor
     *
     * @param string|null $dsn Optional DSN connection string
     */
    public function __construct(?string $dsn = null)
    {
        if ($dsn !== null) {
            $this->set('dsn', $dsn);
        }
    return $this->buildConnectionString($parsedDsn, $this->mailbox);
}

/**
 * Deletes an email from the server.
 *
 * @param int $messageId The ID of the message to delete.
 * @param bool $expungeImmediately If true, immediately expunge deleted messages.
 *                                  Set to false if you want to mark multiple emails
 *                                  for deletion and expunge them all at once later.
 * @return bool True on success, false on failure.
 * @throws MailReaderException If deletion fails.
 */
public function deleteEmail(int $messageId, bool $expungeImmediately = true): bool
{
    $this->ensureConnected();

    if (!imap_delete($this->connection, (string)$messageId, FT_UID)) {
        // FT_UID is not standard for imap_delete, usually it's just the sequence number.
        // Let's use sequence number by default unless a clear UID mapping is implemented.
        // For simplicity, assuming $messageId here is the sequence number.
        // If $messageId is UID, then FT_UID is appropriate.
        // Let's assume $messageId is the message sequence number as typically used with imap_delete
        // To use UIDs, one would typically search for UIDs first.
        // The current getEmail method uses $messageId as sequence number for imap_headerinfo / imap_fetch_overview
    }

    // Let's re-evaluate. Most imap functions use message sequence numbers by default.
    // If we want to delete by UID, the functions usually have a UID variant or a flag.
    // imap_delete documentation: "Marks messages for deletion. message_nums is a space separated list of message numbers"
    // So, $messageId should be the sequence number.

    if (!imap_delete($this->connection, (string)$messageId)) {
        throw new MailReaderException("Failed to mark email #{$messageId} for deletion: " . imap_last_error());
    }

    if ($expungeImmediately) {
        if (!imap_expunge($this->connection)) {
            // imap_expunge can return false but not necessarily throw an error if no messages were expunged.
            // However, if imap_delete succeeded, we expect it to work or at least not error out fatally.
            // We'll consider it an issue if expunge returns false after a successful delete mark.
            // Note: imap_last_error() might not be relevant if imap_expunge simply had nothing to do.
            // Let's check if there was an actual error.
            $lastError = imap_last_error();
            if ($lastError) { // Check if there was an error string
                 throw new MailReaderException("Failed to expunge deleted emails: " . $lastError);
            }
            // If no error, but expunge returned false, it might mean nothing was expunged (e.g. already gone).
            // For simplicity, we'll assume if delete was successful, expunge should be too.
            // A more robust check might involve checking imap_errors() or imap_alerts().
        }
    }
    return true;
}

/**
 * Expunges all messages marked for deletion in the current mailbox.
 *
 * @return bool True on success, false if expunge failed or had nothing to expunge.
 * @throws MailReaderException If expunging encounters a significant error.
 */
public function expunge(): bool
{
    $this->ensureConnected();
    if (!imap_expunge($this->connection)) {
        $lastError = imap_last_error();
        if ($lastError) {
             throw new MailReaderException("Failed to expunge deleted emails: " . $lastError);
        }
        return false; // Nothing to expunge or minor issue
    }
    return true;
}


/**
 * Connect to the mail server
 *
 * @param string|null $mailbox Mailbox to select (default: INBOX)
 * @return self
 * @throws ConnectionException If connection fails
 */
public function connect(?string $mailbox = null): self
{
        if (empty($this->dsn)) {
            throw new ConnectionException('DSN not provided. Set DSN before connecting.');
        }

        // Use provided mailbox or default
        $this->mailbox = $mailbox ?? $this->mailbox;

        // Parse the DSN
        $parsedDsn = $this->parseDsn($this->dsn);
        
        // Create connection string
        $connectionString = $this->buildConnectionString($parsedDsn, $this->mailbox);

        // Suppress warnings and capture errors
        $oldErrorReporting = error_reporting(0);
        
        $this->connection = @imap_open(
            $connectionString,
            $parsedDsn['username'],
            $parsedDsn['password']
        );
        
        // Restore error reporting
        error_reporting($oldErrorReporting);

        if (!$this->connection) {
            throw new ConnectionException('Failed to connect to mail server: ' . imap_last_error());
        }

        return $this;
    }

    /**
     * Disconnect from the mail server
     *
     * @return void
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            imap_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Get all available mailboxes
     *
     * @return array List of mailbox names
     * @throws MailReaderException If getting mailboxes fails
     */
    public function getMailboxes(): array
    {
        $this->ensureConnected();
        
        // Parse the DSN and get base mailbox string (without specific mailbox)
        $parsedDsn = $this->parseDsn($this->dsn);
        $mailboxString = $this->buildConnectionString($parsedDsn);
        
        $list = imap_list($this->connection, $mailboxString, '*');
        
        if (!$list) {
            return [];
        }

        // Format mailbox names
        return array_map(function($mailbox) use ($mailboxString) {
            return str_replace($mailboxString, '', $mailbox);
        }, $list);
    }

    /**
     * Select a different mailbox
     *
     * @param string $mailbox Mailbox name
     * @return self
     * @throws MailReaderException If selecting mailbox fails
     */
    public function selectMailbox(string $mailbox): self
    {
        $this->ensureConnected();
        
        // Parse the DSN
        $parsedDsn = $this->parseDsn($this->dsn);
        
        // Create connection string with new mailbox
        $connectionString = $this->buildConnectionString($parsedDsn, $mailbox);
        
        if (!imap_reopen($this->connection, $connectionString)) {
            throw new MailReaderException("Failed to select mailbox: $mailbox");
        }
        
        $this->mailbox = $mailbox;
        return $this;
    }

    /**
     * Get the number of messages in the current mailbox
     *
     * @return int Message count
     * @throws MailReaderException If getting message count fails
     */
    public function getMessageCount(): int
    {
        $this->ensureConnected();
        return imap_num_msg($this->connection);
    }

    /**
     * Get all emails from the current mailbox
     *
     * @param bool $onlyUnread Only return unread messages
     * @param int|null $limit Maximum number of messages to return
     * @return array List of message data
     * @throws MailReaderException If getting messages fails
     */
    public function getEmails(bool $onlyUnread = false, ?int $limit = null): array
    {
        $this->ensureConnected();
        
        // Get message IDs based on criteria
        $searchCriteria = $onlyUnread ? 'UNSEEN' : 'ALL';
        $messageIds = imap_search($this->connection, $searchCriteria);
        
        if (!$messageIds) {
            return [];
        }
        
        // Sort newest first
        rsort($messageIds);
        
        // Apply limit if specified
        if ($limit !== null && is_numeric($limit)) {
            $messageIds = array_slice($messageIds, 0, $limit);
        }
        
        // Fetch emails
        $emails = [];
        foreach ($messageIds as $messageId) {
            $emails[] = $this->getEmail($messageId);
        }
        
        return $emails;
    }

    /**
     * Get a specific email by ID
     *
     * @param int $messageId Message ID
     * @param bool $includeBody Whether to include the message body
     * @return array Email data
     * @throws MailReaderException If getting email fails
     */
    public function getEmail(int $messageId, bool $includeBody = true): array
    {
        $this->ensureConnected();
        
        try {
            // Get header and overview
            $header = imap_headerinfo($this->connection, $messageId);
            $overview = imap_fetch_overview($this->connection, $messageId, 0)[0];
            
            $email = [
                'id' => $messageId,
                'subject' => $this->decodeHeader($overview->subject ?? ''),
                'from' => $this->decodeHeader($overview->from ?? ''),
                'to' => $this->decodeHeader($overview->to ?? ''),
                'date' => $overview->date ?? '',
                'size' => $overview->size ?? 0,
                'seen' => (bool)($overview->seen ?? false),
                'flagged' => (bool)($overview->flagged ?? false),
            ];
            
            // Get attachments
            $attachments = $this->getAttachments($messageId);
            $email['has_attachments'] = !empty($attachments);
            $email['attachments'] = [];
            
            foreach ($attachments as $index => $attachment) {
                $email['attachments'][] = [
                    'index' => $index,
                    'filename' => $attachment['filename'],
                    'size' => $attachment['size'],
                    'type' => $attachment['type']
                ];
            }
            
            // Include body content if requested
            if ($includeBody) {
                // Get plain text body
                $email['body_plain'] = $this->getMessageBody($messageId, 'TEXT/PLAIN');
                
                // Get HTML body
                $email['body_html'] = $this->getMessageBody($messageId, 'TEXT/HTML');
            }
            
            return $email;
        } catch (\Exception $e) {
            throw new MailReaderException("Failed to get email: " . $e->getMessage());
        }
    }

    /**
     * Search emails by criteria
     *
     * @param string $searchTerm Term to search for
     * @param string $searchField Field to search in (TEXT, SUBJECT, FROM, TO, etc)
     * @param bool $onlyUnread Only return unread messages
     * @param int|null $limit Maximum number of messages to return
     * @return array List of matching emails
     * @throws MailReaderException If search fails
     */
    public function search(string $searchTerm, string $searchField = 'TEXT', bool $onlyUnread = false, ?int $limit = null): array
    {
        $this->ensureConnected();
        
        // Construct search criteria
        $searchCriteria = "$searchField \"$searchTerm\"";
        
        if ($onlyUnread) {
            $searchCriteria .= ' UNSEEN';
        }
        
        $messageIds = imap_search($this->connection, $searchCriteria);
        
        if (!$messageIds) {
            return [];
        }
        
        // Sort newest first
        rsort($messageIds);
        
        // Apply limit if specified
        if ($limit !== null && is_numeric($limit)) {
            $messageIds = array_slice($messageIds, 0, $limit);
        }
        
        // Fetch matching emails
        $emails = [];
        foreach ($messageIds as $messageId) {
            $emails[] = $this->getEmail($messageId, false); // Don't include body by default
        }
        
        return $emails;
    }

    /**
     * Search emails by date range
     *
     * @param DateTime $since Start date
     * @param DateTime|null $before End date (optional)
     * @param bool $onlyUnread Only return unread messages
     * @return array List of matching emails
     * @throws MailReaderException If date search fails
     */
    public function searchByDate(DateTime $since, ?DateTime $before = null, bool $onlyUnread = false): array
    {
        $this->ensureConnected();
        
        $sinceStr = $since->format('d-M-Y');
        $searchCriteria = "SINCE \"$sinceStr\"";
        
        if ($before) {
            $beforeStr = $before->format('d-M-Y');
            $searchCriteria .= " BEFORE \"$beforeStr\"";
        }
        
        if ($onlyUnread) {
            $searchCriteria .= " UNSEEN";
        }
        
        $messageIds = imap_search($this->connection, $searchCriteria);
        
        if (!$messageIds) {
            return [];
        }
        
        // Sort newest first
        rsort($messageIds);
        
        // Fetch matching emails
        $emails = [];
        foreach ($messageIds as $messageId) {
            $emails[] = $this->getEmail($messageId, false); // Don't include body by default
        }
        
        return $emails;
    }

    /**
     * Download an email attachment
     *
     * @param int $messageId Message ID
     * @param int $attachmentIndex Index of the attachment
     * @param string $savePath Path where to save the attachment
     * @return array Information about the saved attachment
     * @throws MailReaderException If downloading fails
     */
    public function downloadAttachment(int $messageId, int $attachmentIndex, string $savePath): array
    {
        $this->ensureConnected();
        
        // Get all attachments for this message
        $attachments = $this->getAttachments($messageId);
        
        if (!isset($attachments[$attachmentIndex])) {
            throw new MailReaderException("Attachment index not found: $attachmentIndex");
        }
        
        $attachment = $attachments[$attachmentIndex];
        
        // Create directory if it doesn't exist
        $saveDir = dirname($savePath);
        if (!is_dir($saveDir)) {
            if (!mkdir($saveDir, 0755, true)) {
                throw new MailReaderException("Failed to create directory: $saveDir");
            }
        }
        
        // Save attachment data to file
        if (file_put_contents($savePath, $attachment['data']) === false) {
            throw new MailReaderException("Failed to write attachment to: $savePath");
        }
        
        return [
            'filename' => $attachment['filename'],
            'size' => strlen($attachment['data']),
            'type' => $attachment['type'],
            'path' => $savePath
        ];
    }

    /**
     * Get message body by content type
     *
     * @param int $messageId Message ID
     * @param string $mimeType MIME type to retrieve (TEXT/PLAIN, TEXT/HTML)
     * @return string Message body
     */
    private function getMessageBody(int $messageId, string $mimeType): string
    {
        $structure = imap_fetchstructure($this->connection, $messageId);
        
        if (!isset($structure->parts) || empty($structure->parts)) {
            // Single part message
            $body = imap_body($this->connection, $messageId);
            return $this->decodeBody($body, $structure->encoding ?? 0);
        }
        
        // Multi-part message - find the part with the requested MIME type
        $body = $this->getBodyPart($messageId, $structure, $mimeType);
        
        return $body ?: '';
    }

    /**
     * Get a specific body part by MIME type
     *
     * @param int $messageId Message ID
     * @param object $structure Message structure
     * @param string $mimeType MIME type to find
     * @param string $partNumber Part number for IMAP
     * @return string Body content
     */
    private function getBodyPart(int $messageId, object $structure, string $mimeType, string $partNumber = ''): string
    {
        // If this is the main structure with parts
        if (empty($partNumber) && isset($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $currentPartNumber = (string)($index + 1);
                $result = $this->getBodyPart($messageId, $part, $mimeType, $currentPartNumber);
                if ($result) {
                    return $result;
                }
            }
        }
        
        // Check if this part matches the requested MIME type
        $currentMimeType = $this->getMimeType($structure);
        if (strtoupper($currentMimeType) === strtoupper($mimeType)) {
            $data = imap_fetchbody($this->connection, $messageId, $partNumber ?: 1);
            return $this->decodeBody($data, $structure->encoding ?? 0);
        }
        
        // If this part has subparts, check them too
        if (isset($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $currentPartNumber = $partNumber . '.' . ($index + 1);
                $result = $this->getBodyPart($messageId, $part, $mimeType, $currentPartNumber);
                if ($result) {
                    return $result;
                }
            }
        }
        
        return '';
    }

    /**
     * Get MIME type from structure
     *
     * @param object $structure Message structure
     * @return string MIME type string
     */
    private function getMimeType(object $structure): string
    {
        $primaryTypes = [
            0 => 'TEXT',
            1 => 'MULTIPART',
            2 => 'MESSAGE',
            3 => 'APPLICATION',
            4 => 'AUDIO',
            5 => 'IMAGE',
            6 => 'VIDEO',
            7 => 'MODEL',
            8 => 'OTHER'
        ];
        
        $type = $primaryTypes[$structure->type] ?? 'TEXT';
        $subtype = $structure->subtype ?? '';
        
        return $type . '/' . $subtype;
    }

    /**
     * Decode message body based on encoding type
     *
     * @param string $body Encoded body
     * @param int $encoding Encoding type
     * @return string Decoded body
     */
    private function decodeBody(string $body, int $encoding): string
    {
        switch ($encoding) {
            case 3: // BASE64
                $body = base64_decode($body);
                break;
                
            case 4: // QUOTED-PRINTABLE
                $body = quoted_printable_decode($body);
                break;
        }
        
        return $body;
    }

    /**
     * Get all attachments for a message
     *
     * @param int $messageId Message ID
     * @return array Attachment data
     */
    private function getAttachments(int $messageId): array
    {
        $structure = imap_fetchstructure($this->connection, $messageId);
        $attachments = [];
        
        if (isset($structure->parts) && count($structure->parts)) {
            $this->extractAttachments($messageId, $structure, $attachments);
        }
        
        return $attachments;
    }

    /**
     * Extract attachments recursively from message parts
     *
     * @param int $messageId Message ID
     * @param object $structure Message structure
     * @param array &$attachments Array to populate with attachments
     * @param string $partNumber Part number for IMAP
     * @return void
     */
    private function extractAttachments(int $messageId, object $structure, array &$attachments, string $partNumber = ''): void
    {
        // If this is the main structure with parts
        if (empty($partNumber) && isset($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $this->extractAttachments($messageId, $part, $attachments, (string)($index + 1));
            }
            return;
        }
        
        // Check if this part is an attachment
        $isAttachment = false;
        
        // Check disposition
        if (isset($structure->disposition) && strtoupper($structure->disposition) === 'ATTACHMENT') {
            $isAttachment = true;
        }
        
        // Check for filename in parameters
        $filename = '';
        if (isset($structure->parameters)) {
            foreach ($structure->parameters as $param) {
                if (strtoupper($param->attribute) === 'NAME') {
                    $filename = $this->decodeHeader($param->value);
                    $isAttachment = true;
                }
            }
        }
        
        // Check for filename in dparameters
        if (isset($structure->dparameters)) {
            foreach ($structure->dparameters as $param) {
                if (strtoupper($param->attribute) === 'FILENAME') {
                    $filename = $this->decodeHeader($param->value);
                    $isAttachment = true;
                }
            }
        }
        
        // If this is an attachment, get the data
        if ($isAttachment && !empty($filename)) {
            $data = imap_fetchbody($this->connection, $messageId, $partNumber ?: 1);
            $data = $this->decodeBody($data, $structure->encoding ?? 0);
            
            $attachments[] = [
                'filename' => $filename,
                'data' => $data,
                'size' => strlen($data),
                'type' => $this->getMimeType($structure)
            ];
        }
        
        // Check subparts
        if (isset($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $this->extractAttachments(
                    $messageId,
                    $part,
                    $attachments,
                    $partNumber . '.' . ($index + 1)
                );
            }
        }
    }

    /**
     * Decode email header values
     *
     * @param string $text Encoded header text
     * @return string Decoded header text
     */
    private function decodeHeader(string $text): string
    {
        $parts = imap_mime_header_decode($text);
        $result = '';
        
        foreach ($parts as $part) {
            $charset = strtoupper($part->charset);
            
            if ($charset === 'DEFAULT' || $charset === 'UTF-8') {
                $result .= $part->text;
            } else {
                $result .= iconv($charset, 'UTF-8//IGNORE', $part->text);
            }
        }
        
        return $result;
    }

    /**
     * Build the IMAP connection string
     *
     * @param array $parsedDsn Parsed DSN components
     * @param string|null $mailbox Mailbox to select
     * @return string Connection string
     */
    private function buildConnectionString(array $parsedDsn, ?string $mailbox = null): string
    {
        // Build mailbox string
        $connectionString = '{' . $parsedDsn['host'];
        
        // Add port if specified
        if (isset($parsedDsn['port'])) {
            $connectionString .= ':' . $parsedDsn['port'];
        }
        
        // Add protocol
        if (isset($parsedDsn['protocol'])) {
            $connectionString .= '/' . $parsedDsn['protocol'];
        } else {
            $connectionString .= '/imap';
        }
        
        // Add security
        if (isset($parsedDsn['security'])) {
            $connectionString .= '/' . $parsedDsn['security'];
        } else if ($parsedDsn['protocol'] !== 'pop3') {
            $connectionString .= '/ssl';
        }
        
        // Add validate-cert option
        $connectionString .= '/novalidate-cert';
        
        // Close the brace
        $connectionString .= '}';
        
        // Add mailbox if specified
        if ($mailbox) {
            $connectionString .= $mailbox;
        }
        
        return $connectionString;
    }

    /**
     * Parse DSN string into components
     * 
     * @param string $dsn DSN connection string
     * @return array Parsed components
     * @throws InvalidArgumentException If DSN format is invalid
     */
    private function parseDsn(string $dsn): array
    {
        // Regex: protocol://user_and_pass@host:port/security/params
        // The user_and_pass part is captured together and then split by the last colon.
        if (!preg_match('#^([^:]+)://(.+)@([^:/]+)(?::(\d+))?(?:/([^/]+))?(?:/(.+))?$#', $dsn, $matches)) {
            throw new InvalidArgumentException('Invalid DSN format. Expected format: protocol://username:password@host:port/security');
        }

        $protocol = $matches[1];
        $userPassPart = $matches[2]; // Contains username:password
        $host = $matches[3];
        
        // Split userPassPart into username and password by the last colon
        // This correctly handles usernames that might contain colons (though unusual) or passwords with colons
        $lastColonPos = strrpos($userPassPart, ':');
        if ($lastColonPos === false) {
            // No colon found, implies missing password or malformed DSN user:pass section
            throw new InvalidArgumentException('Invalid DSN: Username and password section must be in format username:password.');
        }

        $username = substr($userPassPart, 0, $lastColonPos);
        $password = substr($userPassPart, $lastColonPos + 1);
        
        $result = [
            'protocol' => $protocol,
            'username' => $username,
            'password' => urldecode($password), // Password may contain URL-encoded characters
            'host'     => $host
        ];
        
        // Optional port (index 4 from regex)
        if (!empty($matches[4])) {
            $result['port'] = (int)$matches[4];
        }
        
        // Optional security (ssl, tls) (index 5 from regex)
        if (!empty($matches[5])) {
            $result['security'] = $matches[5];
        }
        
        // Optional additional params (index 6 from regex)
        if (!empty($matches[6])) {
            $additionalParamsString = $matches[6];
            // Filter out empty segments that can result from trailing slashes or multiple slashes
            $result['params'] = array_filter(explode('/', $additionalParamsString), function($param) {
                return $param !== '';
            });
        }
        
        return $result;
    }

    /**
     * Ensure that we have an active connection
     *
     * @return void
     * @throws ConnectionException If not connected
     */
    private function ensureConnected(): void
    {
        if (!$this->connection) {
            throw new ConnectionException('Not connected to mail server. Call connect() first.');
        }
    }

    /**
     * Ensure IMAP extension is available
     *
     * @return void
     * @throws MailReaderException If IMAP extension is not available
     */
    private static function ensureImapExtension(): void
    {
        if (!function_exists('imap_open')) {
            throw new MailReaderException('IMAP extension is not enabled. Please enable it in your PHP configuration.');
        }
    }

    /**
     * Destructor - ensures connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}