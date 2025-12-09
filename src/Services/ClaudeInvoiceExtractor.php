<?php

namespace OBSTechnologies\InvoiceAI\Services;

use Anthropic\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OBSTechnologies\InvoiceAI\Contracts\InvoiceExtractorInterface;
use OBSTechnologies\InvoiceAI\Exceptions\ExtractionException;
use OBSTechnologies\InvoiceAI\Exceptions\InvalidFileException;

class ClaudeInvoiceExtractor implements InvoiceExtractorInterface
{
    protected Client $client;
    protected string $model;

    protected array $supportedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
    ];

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $apiKey = $apiKey ?? config('invoiceai.drivers.claude.api_key');

        if (empty($apiKey)) {
            throw new \InvalidArgumentException('Anthropic API key is not configured.');
        }

        $this->client = new Client($apiKey);
        $this->model = $model ?? config('invoiceai.drivers.claude.model', 'claude-sonnet-4-5-20250929');
    }

    /**
     * @inheritDoc
     */
    public function extract(string $filePath, ?string $disk = null): array
    {
        if ($disk !== null) {
            $fullPath = Storage::disk($disk)->path($filePath);
        } else {
            $fullPath = $filePath;
        }

        if (!file_exists($fullPath)) {
            throw new InvalidFileException("File not found: {$fullPath}");
        }

        $mimeType = $this->getMimeType($fullPath);

        if (!in_array($mimeType, $this->supportedMimeTypes)) {
            throw new InvalidFileException("Unsupported file type: {$mimeType}");
        }

        $base64Content = base64_encode(file_get_contents($fullPath));

        return $this->extractFromBase64($base64Content, $mimeType);
    }

    /**
     * @inheritDoc
     */
    public function extractFromBase64(string $base64Content, string $mimeType): array
    {
        if (!in_array($mimeType, $this->supportedMimeTypes)) {
            throw new InvalidFileException("Unsupported file type: {$mimeType}");
        }

        $prompt = $this->buildPrompt();

        try {
            $messages = $this->buildMessages($base64Content, $mimeType, $prompt);

            $response = $this->client->messages->create(
                maxTokens: 4096,
                messages: $messages,
                model: $this->model
            );

            $rawResponse = $response->content[0]->text ?? '';

            return $this->parseResponse($rawResponse);
        } catch (ExtractionException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('InvoiceAI: Claude API Error', [
                'message' => $e->getMessage(),
            ]);

            throw new ExtractionException(
                'Failed to extract invoice data: ' . $e->getMessage(),
                null,
                0,
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getSupportedMimeTypes(): array
    {
        return $this->supportedMimeTypes;
    }

    /**
     * Build messages array for Claude API.
     */
    protected function buildMessages(string $base64Content, string $mimeType, string $prompt): array
    {
        $contentType = $mimeType === 'application/pdf' ? 'document' : 'image';

        return [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => $contentType,
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $mimeType,
                            'data' => $base64Content,
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => $prompt,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build the prompt for invoice extraction.
     */
    protected function buildPrompt(): string
    {
        return <<<PROMPT
Analyze this invoice image and extract the data into the following JSON structure. Be precise with numbers and dates. If a field is not visible or cannot be determined, use null.

Return ONLY valid JSON in this exact format (no markdown, no explanation):

{
  "issuer": {
    "name": "string",
    "vat_number": "string|null",
    "address": "string|null"
  },
  "customer": {
    "name": "string|null",
    "vat_number": "string|null",
    "address": "string|null"
  },
  "invoice_number": "string|null",
  "invoice_date": "YYYY-MM-DD|null",
  "currency": "string|null",
  "line_items": [
    {
      "description": "string",
      "quantity": number,
      "unit_price": number,
      "vat_rate": number|null,
      "line_total": number
    }
  ],
  "discounts": [
    {
      "description": "string",
      "amount": number
    }
  ],
  "other_charges": [
    {
      "description": "string",
      "amount": number
    }
  ],
  "totals": {
    "subtotal": number,
    "vat_total": number,
    "grand_total": number
  }
}

Important:
- All numeric values should be numbers (not strings)
- Dates must be in YYYY-MM-DD format
- VAT rates should be percentages (e.g., 20 for 20%)
- Include shipping, handling, or service fees in other_charges
- Discounts should be positive numbers (the amount to subtract)
- If no discounts exist, return an empty array []
- If no other charges exist, return an empty array []
- Currency should be a 3-letter code like "USD", "EUR", "GBP"
PROMPT;
    }

    /**
     * Parse the Claude response into structured data.
     */
    protected function parseResponse(string $rawResponse): array
    {
        $jsonString = $rawResponse;

        // Remove potential markdown code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $rawResponse, $matches)) {
            $jsonString = $matches[1];
        }

        $jsonString = trim($jsonString);

        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('InvoiceAI: Failed to parse Claude response', [
                'error' => json_last_error_msg(),
                'raw_response' => $rawResponse,
            ]);

            throw new ExtractionException(
                'Failed to parse invoice data: ' . json_last_error_msg(),
                $rawResponse
            );
        }

        // Validate required fields
        if (!isset($data['issuer']['name'])) {
            throw new ExtractionException(
                'Invoice must have an issuer name',
                $rawResponse
            );
        }

        // Add raw response for debugging
        $data['raw_response'] = $rawResponse;

        return $data;
    }

    /**
     * Get the MIME type of a file.
     */
    protected function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => mime_content_type($path) ?: 'application/octet-stream',
        };
    }
}
