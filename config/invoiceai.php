<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Invoice Extractor Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default invoice extraction driver that will be
    | used to parse invoices. Currently supported: "claude"
    |
    */
    'default_driver' => env('INVOICEAI_DRIVER', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | Extraction Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the extraction drivers for your application.
    | Each driver can have its own configuration options.
    |
    */
    'drivers' => [
        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('INVOICEAI_CLAUDE_MODEL', 'claude-sonnet-4-5-20250929'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure where uploaded invoice files should be stored.
    |
    */
    'storage' => [
        'disk' => env('INVOICEAI_STORAGE_DISK', 'local'),
        'path' => env('INVOICEAI_STORAGE_PATH', 'invoices'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported File Types
    |--------------------------------------------------------------------------
    |
    | List of allowed MIME types for invoice file uploads.
    |
    */
    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | Maximum file size in kilobytes for uploaded invoices.
    |
    */
    'max_file_size' => env('INVOICEAI_MAX_FILE_SIZE', 10240), // 10MB

    /*
    |--------------------------------------------------------------------------
    | Table Prefix
    |--------------------------------------------------------------------------
    |
    | Add a prefix to all package tables to avoid conflicts with existing
    | tables in your application. Set to empty string for no prefix.
    |
    */
    'table_prefix' => env('INVOICEAI_TABLE_PREFIX', 'invoiceai_'),

    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the database table names used by the package.
    | The table_prefix will be prepended to these names.
    |
    */
    'tables' => [
        'invoices' => 'invoices',
        'invoice_line_items' => 'invoice_line_items',
        'invoice_discounts' => 'invoice_discounts',
        'invoice_other_charges' => 'invoice_other_charges',
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | Enable multi-tenancy support and configure the tenant column.
    |
    */
    'multi_tenancy' => [
        'enabled' => env('INVOICEAI_MULTI_TENANCY', true),
        'column' => 'company_id',
        'resolver' => null, // Callable or class to resolve current tenant ID
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the package routes.
    |
    */
    'routes' => [
        'enabled' => env('INVOICEAI_ROUTES_ENABLED', true),
        'prefix' => 'api/invoiceai',
        'middleware' => ['api', 'auth:sanctum'],
    ],
];
