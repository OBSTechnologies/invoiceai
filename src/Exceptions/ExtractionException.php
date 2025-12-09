<?php

namespace OBSTechnologies\InvoiceAI\Exceptions;

use Exception;

class ExtractionException extends Exception
{
    protected ?string $rawResponse;

    public function __construct(
        string $message,
        ?string $rawResponse = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->rawResponse = $rawResponse;
    }

    public function getRawResponse(): ?string
    {
        return $this->rawResponse;
    }
}
