<?php

namespace Codechap\MailReader\Exceptions;

/**
 * Base exception class for MailReader
 */
class MailReaderException extends \Exception
{
    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $message = "", int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}