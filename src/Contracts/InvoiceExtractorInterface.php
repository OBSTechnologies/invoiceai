<?php

namespace OBSTechnologies\InvoiceAI\Contracts;

interface InvoiceExtractorInterface
{
    /**
     * Extract invoice data from a file.
     *
     * @param string $filePath Path to the file (can be storage path or absolute path)
     * @param string|null $disk Storage disk name (null for absolute path)
     * @return array Extracted invoice data
     * @throws \OBSTechnologies\InvoiceAI\Exceptions\ExtractionException
     */
    public function extract(string $filePath, ?string $disk = null): array;

    /**
     * Extract invoice data from base64 encoded content.
     *
     * @param string $base64Content Base64 encoded file content
     * @param string $mimeType MIME type of the file
     * @return array Extracted invoice data
     * @throws \OBSTechnologies\InvoiceAI\Exceptions\ExtractionException
     */
    public function extractFromBase64(string $base64Content, string $mimeType): array;

    /**
     * Get supported MIME types.
     *
     * @return array<string>
     */
    public function getSupportedMimeTypes(): array;
}
