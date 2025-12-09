<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('invoiceai.table_prefix', 'invoiceai_');
        $tableName = $prefix . config('invoiceai.tables.invoice_other_charges', 'invoice_other_charges');
        $invoicesTable = $prefix . config('invoiceai.tables.invoices', 'invoices');

        Schema::create($tableName, function (Blueprint $table) use ($invoicesTable) {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained($invoicesTable)
                ->cascadeOnDelete();

            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('invoiceai.table_prefix', 'invoiceai_');
        Schema::dropIfExists($prefix . config('invoiceai.tables.invoice_other_charges', 'invoice_other_charges'));
    }
};
