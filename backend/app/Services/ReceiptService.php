<?php

namespace App\Services;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptService
{
    public function __construct(
        protected SaleService $saleService
    ) {}

    /**
     * Generate receipt HTML.
     */
    public function generateReceiptHtml(Sale $sale): string
    {
        $data = $this->saleService->getReceiptData($sale);
        
        return view('receipts.pos', ['receipt' => $data])->render();
    }

    /**
     * Generate receipt PDF.
     */
    public function generateReceiptPdf(Sale $sale): \Barryvdh\DomPDF\PDF
    {
        $data = $this->saleService->getReceiptData($sale);
        
        $pdf = Pdf::loadView('receipts.pos', ['receipt' => $data]);
        
        // Set paper size for thermal receipt (80mm width)
        $pdf->setPaper([0, 0, 226.77, 600], 'portrait'); // 80mm = 226.77 points
        
        return $pdf;
    }

    /**
     * Get print-ready receipt data.
     */
    public function getPrintData(Sale $sale): array
    {
        $receiptData = $this->saleService->getReceiptData($sale);
        
        return array_merge($receiptData, [
            'print_settings' => [
                'paper_width' => '80mm',
                'font_size' => '12px',
                'line_height' => '1.4',
            ],
        ]);
    }
}
