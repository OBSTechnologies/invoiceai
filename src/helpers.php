<?php

if (!function_exists('invoiceai_table')) {
    /**
     * Get the prefixed table name for InvoiceAI package.
     *
     * @param string $table The table key from config (e.g., 'invoices')
     * @return string The full table name with prefix
     */
    function invoiceai_table(string $table): string
    {
        $prefix = config('invoiceai.table_prefix', 'invoiceai_');
        $tableName = config("invoiceai.tables.{$table}", $table);

        return $prefix . $tableName;
    }
}
