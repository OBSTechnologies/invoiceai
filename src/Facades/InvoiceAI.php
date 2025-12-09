<?php

namespace OBSTechnologies\InvoiceAI\Facades;

use Illuminate\Support\Facades\Facade;
use OBSTechnologies\InvoiceAI\Contracts\InvoiceExtractorInterface;

/**
 * @method static array extract(string $filePath, ?string $disk = null)
 * @method static array extractFromBase64(string $base64Content, string $mimeType)
 * @method static array getSupportedMimeTypes()
 *
 * @see \OBSTechnologies\InvoiceAI\Contracts\InvoiceExtractorInterface
 */
class InvoiceAI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return InvoiceExtractorInterface::class;
    }
}
