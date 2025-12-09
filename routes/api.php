<?php

use Illuminate\Support\Facades\Route;
use OBSTechnologies\InvoiceAI\Http\Controllers\Api\InvoiceController;

if (config('invoiceai.routes.enabled', true)) {
    Route::group([
        'prefix' => config('invoiceai.routes.prefix', 'api/invoiceai'),
        'middleware' => config('invoiceai.routes.middleware', ['api', 'auth:sanctum']),
    ], function () {
        Route::get('invoices', [InvoiceController::class, 'index']);
        Route::post('invoices', [InvoiceController::class, 'store']);
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy']);

        // Extract without saving
        Route::post('extract', [InvoiceController::class, 'extract']);
    });
}
