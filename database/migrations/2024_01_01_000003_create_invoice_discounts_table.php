<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('invoiceai.tables.invoice_discounts', 'invoice_discounts');
        $invoicesTable = config('invoiceai.tables.invoices', 'invoices');

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
        Schema::dropIfExists(config('invoiceai.tables.invoice_discounts', 'invoice_discounts'));
    }
};
