<?php

namespace Codechap\MailReader\Exceptions;

/**
 * Exception thrown when connection to mail server fails
 */
class ConnectionException extends MailReaderException
{
    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $message = "Connection to mail server failed", int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}