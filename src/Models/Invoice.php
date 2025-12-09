<?php

namespace OBSTechnologies\InvoiceAI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OBSTechnologies\InvoiceAI\Traits\HasTenant;

class Invoice extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'user_id',
        'invoice_number',
        'invoice_date',
        'issuer_name',
        'issuer_vat',
        'issuer_address',
        'customer_name',
        'customer_vat',
        'customer_address',
        'currency',
        'subtotal',
        'vat_total',
        'grand_total',
        'file_path',
        'original_filename',
        'raw_response',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'subtotal' => 'decimal:2',
        'vat_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(invoiceai_table('invoices'));

        // Add tenant column to fillable if multi-tenancy is enabled
        if (config('invoiceai.multi_tenancy.enabled', true)) {
            $tenantColumn = config('invoiceai.multi_tenancy.column', 'company_id');
            if (!in_array($tenantColumn, $this->fillable)) {
                $this->fillable[] = $tenantColumn;
            }
        }
    }

    /**
     * Get the line items for the invoice.
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    /**
     * Get the discounts for the invoice.
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(InvoiceDiscount::class);
    }

    /**
     * Get the other charges for the invoice.
     */
    public function otherCharges(): HasMany
    {
        return $this->hasMany(InvoiceOtherCharge::class);
    }

    /**
     * Calculate the sum of line item totals.
     */
    public function getCalculatedSubtotalAttribute(): float
    {
        return $this->lineItems->sum('line_total');
    }

    /**
     * Calculate total discounts.
     */
    public function getTotalDiscountsAttribute(): float
    {
        return $this->discounts->sum('amount');
    }

    /**
     * Calculate total other charges.
     */
    public function getTotalOtherChargesAttribute(): float
    {
        return $this->otherCharges->sum('amount');
    }

    /**
     * Check if calculated totals match stored totals.
     */
    public function getTotalsMatchAttribute(): bool
    {
        $calculated = $this->calculated_subtotal - $this->total_discounts + $this->total_other_charges + $this->vat_total;
        return abs($calculated - $this->grand_total) < 0.01;
    }

    /**
     * Create invoice from extracted data.
     */
    public static function createFromExtractedData(array $data, array $additionalAttributes = []): self
    {
        $invoice = new static(array_merge([
            'invoice_number' => $data['invoice_number'] ?? null,
            'invoice_date' => $data['invoice_date'] ?? null,
            'issuer_name' => $data['issuer']['name'] ?? null,
            'issuer_vat' => $data['issuer']['vat_number'] ?? null,
            'issuer_address' => $data['issuer']['address'] ?? null,
            'customer_name' => $data['customer']['name'] ?? null,
            'customer_vat' => $data['customer']['vat_number'] ?? null,
            'customer_address' => $data['customer']['address'] ?? null,
            'currency' => $data['currency'] ?? 'EUR',
            'subtotal' => $data['totals']['subtotal'] ?? 0,
            'vat_total' => $data['totals']['vat_total'] ?? 0,
            'grand_total' => $data['totals']['grand_total'] ?? 0,
            'raw_response' => $data['raw_response'] ?? null,
        ], $additionalAttributes));

        $invoice->save();

        // Create line items
        if (!empty($data['line_items'])) {
            foreach ($data['line_items'] as $item) {
                $invoice->lineItems()->create([
                    'description' => $item['description'] ?? '',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'vat_rate' => $item['vat_rate'] ?? null,
                    'line_total' => $item['line_total'] ?? 0,
                ]);
            }
        }

        // Create discounts
        if (!empty($data['discounts'])) {
            foreach ($data['discounts'] as $discount) {
                $invoice->discounts()->create([
                    'description' => $discount['description'] ?? '',
                    'amount' => $discount['amount'] ?? 0,
                ]);
            }
        }

        // Create other charges
        if (!empty($data['other_charges'])) {
            foreach ($data['other_charges'] as $charge) {
                $invoice->otherCharges()->create([
                    'description' => $charge['description'] ?? '',
                    'amount' => $charge['amount'] ?? 0,
                ]);
            }
        }

        return $invoice->fresh(['lineItems', 'discounts', 'otherCharges']);
    }
}
