<?php

namespace OBSTechnologies\InvoiceAI\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OBSTechnologies\InvoiceAI\Contracts\InvoiceExtractorInterface;
use OBSTechnologies\InvoiceAI\Exceptions\ExtractionException;
use OBSTechnologies\InvoiceAI\Exceptions\InvalidFileException;
use OBSTechnologies\InvoiceAI\Models\Invoice;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceExtractorInterface $extractor
    ) {}

    /**
     * List all invoices.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        $invoices = Invoice::with(['lineItems', 'discounts', 'otherCharges'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    /**
     * Show a single invoice.
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['lineItems', 'discounts', 'otherCharges']);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    /**
     * Upload and process a new invoice.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . config('invoiceai.max_file_size', 10240),
                'mimes:jpg,jpeg,png,pdf',
            ],
        ]);

        $file = $request->file('file');
        $disk = config('invoiceai.storage.disk', 'local');
        $path = config('invoiceai.storage.path', 'invoices');

        try {
            // Store the file
            $filePath = $file->store($path, $disk);
            $originalFilename = $file->getClientOriginalName();

            // Extract invoice data
            $extractedData = $this->extractor->extract($filePath, $disk);

            // Create invoice in a transaction
            $invoice = DB::transaction(function () use ($extractedData, $filePath, $originalFilename, $request) {
                return Invoice::createFromExtractedData($extractedData, [
                    'file_path' => $filePath,
                    'original_filename' => $originalFilename,
                    'user_id' => $request->user()?->id,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Invoice processed successfully',
                'data' => $invoice,
            ], 201);

        } catch (InvalidFileException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (ExtractionException $e) {
            // Clean up file if extraction failed
            if (isset($filePath)) {
                Storage::disk($disk)->delete($filePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to extract invoice data: ' . $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            // Clean up file on any error
            if (isset($filePath)) {
                Storage::disk($disk)->delete($filePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the invoice',
            ], 500);
        }
    }

    /**
     * Delete an invoice.
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        // Delete the associated file
        if ($invoice->file_path) {
            $disk = config('invoiceai.storage.disk', 'local');
            Storage::disk($disk)->delete($invoice->file_path);
        }

        $invoice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Invoice deleted successfully',
        ]);
    }

    /**
     * Extract data from a file without saving.
     */
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . config('invoiceai.max_file_size', 10240),
                'mimes:jpg,jpeg,png,pdf',
            ],
        ]);

        $file = $request->file('file');

        try {
            $extractedData = $this->extractor->extract(
                $file->getRealPath(),
                null
            );

            // Remove raw response from the output
            unset($extractedData['raw_response']);

            return response()->json([
                'success' => true,
                'data' => $extractedData,
            ]);

        } catch (ExtractionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
