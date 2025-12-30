<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $receipt['invoice_number'] }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            width: 80mm;
            max-width: 80mm;
            padding: 10px;
            background: #fff;
            color: #000;
        }

        .receipt {
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }

        .store-name {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .store-info {
            font-size: 10px;
            color: #333;
        }

        .info-section {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        .info-label {
            font-weight: bold;
        }

        .items-section {
            margin-bottom: 10px;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }

        .items-header {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
            margin-bottom: 5px;
        }

        .items-header span:first-child {
            flex: 2;
        }

        .items-header span:nth-child(2),
        .items-header span:nth-child(3),
        .items-header span:nth-child(4) {
            flex: 1;
            text-align: right;
        }

        .item-row {
            margin-bottom: 5px;
        }

        .item-name {
            font-weight: bold;
        }

        .item-sku {
            font-size: 10px;
            color: #666;
        }

        .item-details {
            display: flex;
            justify-content: space-between;
            margin-top: 2px;
        }

        .item-details span:first-child {
            flex: 2;
        }

        .item-details span:nth-child(2),
        .item-details span:nth-child(3),
        .item-details span:nth-child(4) {
            flex: 1;
            text-align: right;
        }

        .totals-section {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        .total-row.grand-total {
            font-size: 14px;
            font-weight: bold;
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }

        .payment-section {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
        }

        .payment-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        .footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #000;
        }

        .thank-you {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .footer-info {
            font-size: 10px;
            color: #666;
        }

        .barcode {
            text-align: center;
            margin-top: 10px;
            font-family: 'Libre Barcode 39', cursive;
            font-size: 36px;
        }

        .offline-notice {
            background: #ffeb3b;
            padding: 5px;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        @media print {
            body {
                width: 80mm;
                margin: 0;
                padding: 5mm;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <!-- Header with Store Info -->
        <div class="header">
            <div class="store-name">{{ $receipt['store']['name'] ?? 'POS System' }}</div>
            <div class="store-info">
                @if(isset($receipt['store']['address']))
                    {{ $receipt['store']['address'] }}<br>
                @endif
                @if(isset($receipt['store']['phone']))
                    Tel: {{ $receipt['store']['phone'] }}<br>
                @endif
                @if(isset($receipt['store']['email']))
                    {{ $receipt['store']['email'] }}
                @endif
            </div>
        </div>

        @if(isset($receipt['is_offline']) && $receipt['is_offline'])
            <div class="offline-notice">
                OFFLINE SALE - SYNCED
            </div>
        @endif

        <!-- Sale Info -->
        <div class="info-section">
            <div class="info-row">
                <span class="info-label">{{ __('ui.sales.invoice') }}:</span>
                <span>{{ $receipt['invoice_number'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">{{ __('ui.sales.date') }}:</span>
                <span>{{ $receipt['sale_date'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">{{ __('ui.sales.cashier') }}:</span>
                <span>{{ $receipt['cashier'] ?? __('ui.common.system') }}</span>
            </div>
            @if(isset($receipt['warehouse']))
                <div class="info-row">
                    <span class="info-label">{{ __('ui.common.location') }}:</span>
                    <span>{{ $receipt['warehouse'] }}</span>
                </div>
            @endif
        </div>

        <!-- Items -->
        <div class="items-section">
            <div class="items-header">
                <span>{{ __('ui.common.item') }}</span>
                <span>{{ __('ui.common.qty') }}</span>
                <span>{{ __('ui.common.price') }}</span>
                <span>{{ __('ui.common.total') }}</span>
            </div>

            @foreach($receipt['items'] as $item)
                <div class="item-row">
                    <div class="item-name">{{ $item['name'] }}</div>
                    @if(isset($item['sku']))
                        <div class="item-sku">SKU: {{ $item['sku'] }}</div>
                    @endif
                    <div class="item-details">
                        <span></span>
                        <span>{{ $item['quantity'] }}</span>
                        <span>{{ number_format($item['unit_price'], 3) }}</span>
                        <span>{{ number_format($item['line_total'], 3) }}</span>
                    </div>
                    @if(isset($item['discount_amount']) && $item['discount_amount'] > 0)
                        <div class="item-details">
                            <span style="font-size: 10px; color: #666;">
                                {{ __('ui.common.discount') }}: -{{ number_format($item['discount_amount'], 3) }}
                            </span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Totals -->
        <div class="totals-section">
            <div class="total-row">
                <span>{{ __('ui.sales.subtotal') }}:</span>
                <span>{{ number_format($receipt['subtotal'], 3) }} {{ $receipt['currency'] }}</span>
            </div>
            @if(isset($receipt['discount_total']) && $receipt['discount_total'] > 0)
                <div class="total-row">
                    <span>{{ __('ui.common.discount') }}:</span>
                    <span>-{{ number_format($receipt['discount_total'], 3) }} {{ $receipt['currency'] }}</span>
                </div>
            @endif
            <div class="total-row grand-total">
                <span>{{ __('ui.sales.total') }}:</span>
                <span>{{ number_format($receipt['grand_total'], 3) }} {{ $receipt['currency'] }}</span>
            </div>
        </div>

        <!-- Payments -->
        <div class="payment-section">
            <div class="payment-title">{{ __('ui.sales.payment_details') }}</div>
            @foreach($receipt['payments'] as $payment)
                <div class="payment-row">
                    <span>{{ $payment['method'] }}:</span>
                    <span>{{ number_format($payment['amount'], 3) }} {{ $receipt['currency'] }}</span>
                </div>
            @endforeach
            @if(isset($receipt['amount_tendered']))
                <div class="payment-row">
                    <span>{{ __('ui.sales.tendered') }}:</span>
                    <span>{{ number_format($receipt['amount_tendered'], 3) }} {{ $receipt['currency'] }}</span>
                </div>
            @endif
            @if(isset($receipt['change_due']) && $receipt['change_due'] > 0)
                <div class="payment-row" style="font-weight: bold;">
                    <span>{{ __('ui.sales.change') }}:</span>
                    <span>{{ number_format($receipt['change_due'], 3) }} {{ $receipt['currency'] }}</span>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="thank-you">{{ __('ui.sales.thank_you') }}</div>
            <div class="footer-info">
                {{ __('ui.sales.keep_receipt') }}<br>
                {{ __('ui.sales.return_policy') }}
            </div>
            
            <!-- Invoice Number as Barcode -->
            <div class="barcode">
                *{{ $receipt['invoice_number'] }}*
            </div>
        </div>
    </div>
</body>
</html>
