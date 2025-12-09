<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('invoiceai.table_prefix', 'invoiceai_');
        $tableName = $prefix . config('invoiceai.tables.invoices', 'invoices');
        $tenantColumn = config('invoiceai.multi_tenancy.column', 'company_id');
        $multiTenancy = config('invoiceai.multi_tenancy.enabled', true);

        Schema::create($tableName, function (Blueprint $table) use ($tenantColumn, $multiTenancy) {
            $table->id();

            if ($multiTenancy) {
                $table->unsignedBigInteger($tenantColumn)->nullable()->index();
            }

            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Invoice identification
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();

            // Issuer information
            $table->string('issuer_name');
            $table->string('issuer_vat')->nullable();
            $table->text('issuer_address')->nullable();

            // Customer information
            $table->string('customer_name')->nullable();
            $table->string('customer_vat')->nullable();
            $table->text('customer_address')->nullable();

            // Financial
            $table->string('currency', 3)->default('EUR');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);

            // File storage
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();

            // AI response
            $table->longText('raw_response')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('invoiceai.table_prefix', 'invoiceai_');
        Schema::dropIfExists($prefix . config('invoiceai.tables.invoices', 'invoices'));
    }
};
