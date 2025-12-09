<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('invoiceai.tables.invoice_line_items', 'invoice_line_items');
        $invoicesTable = config('invoiceai.tables.invoices', 'invoices');

        Schema::create($tableName, function (Blueprint $table) use ($invoicesTable) {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained($invoicesTable)
                ->cascadeOnDelete();

            $table->text('description');
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->decimal('line_total', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('invoiceai.tables.invoice_line_items', 'invoice_line_items'));
    }
};
