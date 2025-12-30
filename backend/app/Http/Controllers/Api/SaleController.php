<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePosSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class SaleController extends Controller
{
    public function __construct(
        protected SaleService $saleService,
        protected ReceiptService $receiptService
    ) {}

    /**
     * List sales with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $sales = $this->saleService->getSales(
            filters: $request->only([
                'status', 
                'user_id', 
                'warehouse_id', 
                'start_date', 
                'end_date',
                'has_conflicts',
                'unsynced',
                'search',
            ]),
            perPage: $request->input('per_page', 15)
        );

        return $this->success([
            'data' => SaleResource::collection($sales),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    /**
     * Create a new POS sale.
     */
    public function createPosSale(CreatePosSaleRequest $request): JsonResponse
    {
        try {
            $sale = $this->saleService->createPosSale($request->validated());

            return $this->created(
                new SaleResource($sale),
                'Sale completed successfully'
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Get sale details.
     */
    public function show(int $id): JsonResponse
    {
        $sale = $this->saleService->getSale($id);

        return $this->success(new SaleResource($sale));
    }

    /**
     * Process full refund.
     */
    public function refund(Request $request, Sale $sale): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $sale = $this->saleService->processFullRefund(
                $sale,
                $request->input('reason')
            );

            return $this->success(
                new SaleResource($sale),
                'Sale refunded successfully'
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Get receipt data for a sale.
     */
    public function receipt(Sale $sale): JsonResponse
    {
        $html = $this->receiptService->generateReceiptHtml($sale);

        return $this->success([
            'html' => $html,
            'invoice_number' => $sale->invoice_number,
        ]);
    }

    /**
     * Download receipt as PDF.
     */
    public function receiptPdf(Sale $sale): Response
    {
        $pdf = $this->receiptService->generateReceiptPdf($sale);

        return $pdf->download("receipt-{$sale->invoice_number}.pdf");
    }

    /**
     * Get sale by invoice number.
     */
    public function findByInvoice(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_number' => 'required|string',
        ]);

        $sale = Sale::where('invoice_number', $request->input('invoice_number'))
            ->with(['items', 'payments.paymentMethod', 'user', 'warehouse'])
            ->first();

        if (!$sale) {
            return $this->error('Sale not found', 404);
        }

        return $this->success(new SaleResource($sale));
    }

    /**
     * Get daily sales summary.
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());
        
        $sales = Sale::whereDate('created_at', $date)
            ->completed()
            ->get();

        return $this->success([
            'date' => $date,
            'total_sales' => $sales->count(),
            'total_revenue' => (float) $sales->sum('total'),
            'total_tax' => (float) $sales->sum('tax_total'),
            'total_discounts' => (float) $sales->sum('discount_amount'),
            'average_sale' => $sales->count() > 0 
                ? round($sales->sum('total') / $sales->count(), 3) 
                : 0,
            'currency' => 'LYD',
        ]);
    }
}
