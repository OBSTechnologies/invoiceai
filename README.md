# InvoiceAI - AI-Powered Invoice Parsing for Laravel

A Laravel package that uses Claude AI to automatically extract and parse invoice data from images and PDFs.

## Installation

### From Local Development

Add the repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/obstechnologies/invoiceai"
        }
    ]
}
```

Then install:

```bash
composer require obstechnologies/invoiceai
```

### From Packagist (when published)

```bash
composer require obstechnologies/invoiceai
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=invoiceai-config
```

Set your Anthropic API key in `.env`:

```env
ANTHROPIC_API_KEY=your-api-key-here
INVOICEAI_CLAUDE_MODEL=claude-sonnet-4-5-20250929
```

## Database Setup

Run migrations:

```bash
php artisan migrate
```

Or publish migrations first to customize:

```bash
php artisan vendor:publish --tag=invoiceai-migrations
php artisan migrate
```

## Usage

### Using the Facade

```php
use OBSTechnologies\InvoiceAI\Facades\InvoiceAI;

// Extract from a file path
$data = InvoiceAI::extract('/path/to/invoice.pdf');

// Extract from storage disk
$data = InvoiceAI::extract('invoices/invoice.pdf', 'public');

// Extract from base64
$data = InvoiceAI::extractFromBase64($base64Content, 'application/pdf');
```

### Using Dependency Injection

```php
use OBSTechnologies\InvoiceAI\Contracts\InvoiceExtractorInterface;

class InvoiceController
{
    public function __construct(
        protected InvoiceExtractorInterface $extractor
    ) {}

    public function process(Request $request)
    {
        $data = $this->extractor->extract($request->file('invoice')->path());
        // ...
    }
}
```

### Creating Invoice Records

```php
use OBSTechnologies\InvoiceAI\Models\Invoice;
use OBSTechnologies\InvoiceAI\Facades\InvoiceAI;

// Extract and create in one step
$extractedData = InvoiceAI::extract($filePath, 'public');
$invoice = Invoice::createFromExtractedData($extractedData, [
    'file_path' => $filePath,
    'original_filename' => 'invoice.pdf',
    'user_id' => auth()->id(),
]);
```

## API Endpoints

When routes are enabled (default), the following endpoints are available:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/invoiceai/invoices` | List all invoices |
| POST | `/api/invoiceai/invoices` | Upload and process invoice |
| GET | `/api/invoiceai/invoices/{id}` | Get single invoice |
| DELETE | `/api/invoiceai/invoices/{id}` | Delete invoice |
| POST | `/api/invoiceai/extract` | Extract data without saving |

### Upload Invoice Example

```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -F "file=@invoice.pdf" \
  https://your-app.com/api/invoiceai/invoices
```

## Multi-Tenancy

The package supports multi-tenancy out of the box. Configure in `config/invoiceai.php`:

```php
'multi_tenancy' => [
    'enabled' => true,
    'column' => 'company_id',
    'resolver' => null, // or a callable/class
],
```

### Custom Tenant Resolver

```php
// In a service provider
$this->app->bind('invoiceai.tenant_id', function () {
    return auth()->user()?->company_id;
});

// Or use a resolver class
'resolver' => \App\Services\TenantResolver::class,
```

## Extracted Data Structure

```json
{
  "issuer": {
    "name": "Company Name",
    "vat_number": "EL123456789",
    "address": "123 Main St"
  },
  "customer": {
    "name": "Customer Name",
    "vat_number": "EL987654321",
    "address": "456 Other St"
  },
  "invoice_number": "INV-001",
  "invoice_date": "2024-01-15",
  "currency": "EUR",
  "line_items": [
    {
      "description": "Product A",
      "quantity": 2,
      "unit_price": 100.00,
      "vat_rate": 24,
      "line_total": 200.00
    }
  ],
  "discounts": [],
  "other_charges": [],
  "totals": {
    "subtotal": 200.00,
    "vat_total": 48.00,
    "grand_total": 248.00
  }
}
```

## Supported File Types

- JPEG/JPG
- PNG
- GIF
- WebP
- PDF

## License

MIT License
