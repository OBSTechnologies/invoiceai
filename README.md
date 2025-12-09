# InvoiceAI - AI-Powered Invoice Parsing for Laravel

A Laravel package that uses Claude AI to automatically extract and parse invoice data from images and PDFs.

## Requirements

- PHP 8.2 or higher
- Laravel 10.x, 11.x, or 12.x
- Anthropic API key (for Claude AI)
- PHP Extensions: `fileinfo`, `json`

## Installation

### From GitHub

Add the repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/OBSTechnologies/invoiceai"
        }
    ]
}
```

Then install:

```bash
composer require obstechnologies/invoiceai
```

### Install Specific Version

```bash
# Latest stable
composer require obstechnologies/invoiceai

# Specific version
composer require obstechnologies/invoiceai:^1.0

# Development version
composer require obstechnologies/invoiceai:dev-main
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

### All Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `ANTHROPIC_API_KEY` | Your Anthropic API key | (required) |
| `INVOICEAI_CLAUDE_MODEL` | Claude model to use | `claude-sonnet-4-5-20250929` |
| `INVOICEAI_DRIVER` | Extraction driver | `claude` |
| `INVOICEAI_TABLE_PREFIX` | Database table prefix | `invoiceai_` |
| `INVOICEAI_STORAGE_DISK` | Storage disk for files | `local` |
| `INVOICEAI_STORAGE_PATH` | Storage path for files | `invoices` |
| `INVOICEAI_MAX_FILE_SIZE` | Max file size in KB | `10240` (10MB) |
| `INVOICEAI_MULTI_TENANCY` | Enable multi-tenancy | `true` |
| `INVOICEAI_ROUTES_ENABLED` | Enable API routes | `true` |

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

### Table Prefix

By default, all tables are prefixed with `invoiceai_` to avoid conflicts with existing tables:

- `invoiceai_invoices`
- `invoiceai_invoice_line_items`
- `invoiceai_invoice_discounts`
- `invoiceai_invoice_other_charges`

You can customize or remove the prefix in `.env`:

```env
# Custom prefix
INVOICEAI_TABLE_PREFIX=myapp_

# No prefix (use with caution)
INVOICEAI_TABLE_PREFIX=
```

## Usage

### Basic Extraction

```php
use OBSTechnologies\InvoiceAI\Facades\InvoiceAI;

// Extract from an absolute file path
$data = InvoiceAI::extract('/path/to/invoice.pdf');

// Extract from a storage disk
$data = InvoiceAI::extract('invoices/invoice.pdf', 'public');

// Extract from base64 content
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

// Access related data
$invoice->lineItems;    // Collection of line items
$invoice->discounts;    // Collection of discounts
$invoice->otherCharges; // Collection of other charges
```

### Processing Uploaded Files

```php
use OBSTechnologies\InvoiceAI\Facades\InvoiceAI;
use OBSTechnologies\InvoiceAI\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

public function store(Request $request)
{
    $request->validate([
        'invoice' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
    ]);

    $file = $request->file('invoice');
    $path = $file->store('invoices', 'public');

    try {
        $data = InvoiceAI::extract($path, 'public');

        $invoice = DB::transaction(function () use ($data, $path, $file) {
            return Invoice::createFromExtractedData($data, [
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'user_id' => auth()->id(),
            ]);
        });

        return response()->json(['success' => true, 'invoice' => $invoice]);

    } catch (\Exception $e) {
        Storage::disk('public')->delete($path);
        return response()->json(['error' => $e->getMessage()], 422);
    }
}
```

### Working with Invoice Models

```php
use OBSTechnologies\InvoiceAI\Models\Invoice;

// Query invoices
$invoices = Invoice::with(['lineItems', 'discounts', 'otherCharges'])
    ->where('currency', 'EUR')
    ->orderBy('invoice_date', 'desc')
    ->paginate(15);

// Calculated attributes
$invoice = Invoice::find(1);
$invoice->calculated_subtotal;  // Sum of line item totals
$invoice->total_discounts;      // Sum of all discounts
$invoice->total_other_charges;  // Sum of other charges
$invoice->totals_match;         // Boolean: do calculations match stored totals?

// Delete with file cleanup
Storage::disk('public')->delete($invoice->file_path);
$invoice->delete();
```

### Error Handling

```php
use OBSTechnologies\InvoiceAI\Facades\InvoiceAI;
use OBSTechnologies\InvoiceAI\Exceptions\ExtractionException;
use OBSTechnologies\InvoiceAI\Exceptions\InvalidFileException;

try {
    $data = InvoiceAI::extract($filePath, 'public');
} catch (InvalidFileException $e) {
    // File not found or unsupported type
    Log::error('Invalid file: ' . $e->getMessage());
} catch (ExtractionException $e) {
    // AI extraction failed
    Log::error('Extraction failed: ' . $e->getMessage());

    // Get raw AI response for debugging
    $rawResponse = $e->getRawResponse();
}
```

## API Endpoints

When routes are enabled (default), the following endpoints are available:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/invoiceai/invoices` | List all invoices (paginated) |
| POST | `/api/invoiceai/invoices` | Upload and process invoice |
| GET | `/api/invoiceai/invoices/{id}` | Get single invoice with relations |
| DELETE | `/api/invoiceai/invoices/{id}` | Delete invoice and file |
| POST | `/api/invoiceai/extract` | Extract data without saving |

### API Examples

**List Invoices:**
```bash
curl -X GET \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  "https://your-app.com/api/invoiceai/invoices?per_page=10"
```

**Upload Invoice:**
```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -F "file=@invoice.pdf" \
  https://your-app.com/api/invoiceai/invoices
```

**Extract Only (without saving):**
```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -F "file=@invoice.pdf" \
  https://your-app.com/api/invoiceai/extract
```

**Delete Invoice:**
```bash
curl -X DELETE \
  -H "Authorization: Bearer {token}" \
  https://your-app.com/api/invoiceai/invoices/123
```

### Customize API Routes

In `config/invoiceai.php`:

```php
'routes' => [
    'enabled' => true,                          // Set false to disable
    'prefix' => 'api/v1/billing',               // Custom prefix
    'middleware' => ['api', 'auth:sanctum'],    // Custom middleware
],
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
// Option 1: In a service provider
$this->app->bind('invoiceai.tenant_id', function () {
    return auth()->user()?->company_id;
});

// Option 2: Use a resolver class
'resolver' => \App\Services\TenantResolver::class,

// Option 3: Use a closure in config
'resolver' => fn() => session('current_company_id'),
```

### Query Without Tenant Scope

```php
use OBSTechnologies\InvoiceAI\Models\Invoice;

// Get all invoices across all tenants
$allInvoices = Invoice::withoutTenant()->get();
```

## Extracted Data Structure

```json
{
  "issuer": {
    "name": "Company Name",
    "vat_number": "EL123456789",
    "address": "123 Main St, City, Country"
  },
  "customer": {
    "name": "Customer Name",
    "vat_number": "EL987654321",
    "address": "456 Other St, City, Country"
  },
  "invoice_number": "INV-2024-001",
  "invoice_date": "2024-01-15",
  "currency": "EUR",
  "line_items": [
    {
      "description": "Product A - Premium Package",
      "quantity": 2,
      "unit_price": 100.00,
      "vat_rate": 24,
      "line_total": 200.00
    }
  ],
  "discounts": [
    {
      "description": "Early payment discount",
      "amount": 20.00
    }
  ],
  "other_charges": [
    {
      "description": "Shipping",
      "amount": 15.00
    }
  ],
  "totals": {
    "subtotal": 200.00,
    "vat_total": 46.80,
    "grand_total": 241.80
  }
}
```

## Supported File Types

| Type | Extensions | MIME Types |
|------|------------|------------|
| JPEG | `.jpg`, `.jpeg` | `image/jpeg` |
| PNG | `.png` | `image/png` |
| GIF | `.gif` | `image/gif` |
| WebP | `.webp` | `image/webp` |
| PDF | `.pdf` | `application/pdf` |

## Troubleshooting

### "Anthropic API key is not configured"

Make sure you have set `ANTHROPIC_API_KEY` in your `.env` file:

```env
ANTHROPIC_API_KEY=sk-ant-xxxxxxxxxxxxx
```

Then clear your config cache:

```bash
php artisan config:clear
```

### "File not found" errors

Ensure the file path is correct:

```php
// For absolute paths
$data = InvoiceAI::extract('/full/path/to/file.pdf');

// For storage paths, specify the disk
$data = InvoiceAI::extract('relative/path/file.pdf', 'public');
```

### "Failed to parse invoice data"

This usually means Claude couldn't extract valid JSON from the invoice. Common causes:

1. **Poor image quality** - Use higher resolution images (300+ DPI)
2. **Unsupported language** - Claude works best with Latin-based languages
3. **Complex layouts** - Some heavily designed invoices may be harder to parse

You can access the raw response for debugging:

```php
try {
    $data = InvoiceAI::extract($path);
} catch (ExtractionException $e) {
    $rawResponse = $e->getRawResponse();
    Log::debug('Raw AI response:', ['response' => $rawResponse]);
}
```

### Tables already exist

If you have existing `invoices` tables, use a custom prefix:

```env
INVOICEAI_TABLE_PREFIX=ai_
```

Or set an empty prefix and publish migrations to customize table names:

```bash
php artisan vendor:publish --tag=invoiceai-migrations
# Edit the migration files to use your preferred table names
php artisan migrate
```

### Multi-tenancy not filtering correctly

Ensure your User model has the tenant column:

```php
// User model should have company_id or your tenant column
$user->company_id;
```

Or set up a custom resolver:

```php
// In AppServiceProvider
$this->app->bind('invoiceai.tenant_id', function () {
    return session('tenant_id') ?? auth()->user()?->company_id;
});
```

### API routes not working

1. Ensure routes are enabled in config:
   ```php
   'routes' => ['enabled' => true],
   ```

2. Check your middleware - default requires `auth:sanctum`:
   ```php
   'middleware' => ['api', 'auth:sanctum'],
   ```

3. Clear route cache:
   ```bash
   php artisan route:clear
   ```

### Memory issues with large PDFs

For large PDF files, increase PHP memory limit:

```php
// In php.ini
memory_limit = 256M

// Or in your code
ini_set('memory_limit', '256M');
```

## License

MIT License

## Credits

- [OBS Technologies](https://github.com/OBSTechnologies)
- [Anthropic Claude AI](https://anthropic.com)
