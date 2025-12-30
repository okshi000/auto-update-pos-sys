<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo e($receipt['invoice_number']); ?></title>
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
            <div class="store-name"><?php echo e($receipt['store']['name'] ?? 'POS System'); ?></div>
            <div class="store-info">
                <?php if(isset($receipt['store']['address'])): ?>
                    <?php echo e($receipt['store']['address']); ?><br>
                <?php endif; ?>
                <?php if(isset($receipt['store']['phone'])): ?>
                    Tel: <?php echo e($receipt['store']['phone']); ?><br>
                <?php endif; ?>
                <?php if(isset($receipt['store']['email'])): ?>
                    <?php echo e($receipt['store']['email']); ?>

                <?php endif; ?>
            </div>
        </div>

        <?php if(isset($receipt['is_offline']) && $receipt['is_offline']): ?>
            <div class="offline-notice">
                OFFLINE SALE - SYNCED
            </div>
        <?php endif; ?>

        <!-- Sale Info -->
        <div class="info-section">
            <div class="info-row">
                <span class="info-label"><?php echo e(__('ui.sales.invoice')); ?>:</span>
                <span><?php echo e($receipt['invoice_number']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><?php echo e(__('ui.sales.date')); ?>:</span>
                <span><?php echo e($receipt['sale_date']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><?php echo e(__('ui.sales.cashier')); ?>:</span>
                <span><?php echo e($receipt['cashier'] ?? __('ui.common.system')); ?></span>
            </div>
            <?php if(isset($receipt['warehouse'])): ?>
                <div class="info-row">
                    <span class="info-label"><?php echo e(__('ui.common.location')); ?>:</span>
                    <span><?php echo e($receipt['warehouse']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Items -->
        <div class="items-section">
            <div class="items-header">
                <span><?php echo e(__('ui.common.item')); ?></span>
                <span><?php echo e(__('ui.common.qty')); ?></span>
                <span><?php echo e(__('ui.common.price')); ?></span>
                <span><?php echo e(__('ui.common.total')); ?></span>
            </div>

            <?php $__currentLoopData = $receipt['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="item-row">
                    <div class="item-name"><?php echo e($item['name']); ?></div>
                    <?php if(isset($item['sku'])): ?>
                        <div class="item-sku">SKU: <?php echo e($item['sku']); ?></div>
                    <?php endif; ?>
                    <div class="item-details">
                        <span></span>
                        <span><?php echo e($item['quantity']); ?></span>
                        <span><?php echo e(number_format($item['unit_price'], 3)); ?></span>
                        <span><?php echo e(number_format($item['line_total'], 3)); ?></span>
                    </div>
                    <?php if(isset($item['discount_amount']) && $item['discount_amount'] > 0): ?>
                        <div class="item-details">
                            <span style="font-size: 10px; color: #666;">
                                <?php echo e(__('ui.common.discount')); ?>: -<?php echo e(number_format($item['discount_amount'], 3)); ?>

                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        <!-- Totals -->
        <div class="totals-section">
            <div class="total-row">
                <span><?php echo e(__('ui.sales.subtotal')); ?>:</span>
                <span><?php echo e(number_format($receipt['subtotal'], 3)); ?> <?php echo e($receipt['currency']); ?></span>
            </div>
            <?php if(isset($receipt['discount_total']) && $receipt['discount_total'] > 0): ?>
                <div class="total-row">
                    <span><?php echo e(__('ui.common.discount')); ?>:</span>
                    <span>-<?php echo e(number_format($receipt['discount_total'], 3)); ?> <?php echo e($receipt['currency']); ?></span>
                </div>
            <?php endif; ?>
            <div class="total-row grand-total">
                <span><?php echo e(__('ui.sales.total')); ?>:</span>
                <span><?php echo e(number_format($receipt['grand_total'], 3)); ?> <?php echo e($receipt['currency']); ?></span>
            </div>
        </div>

        <!-- Payments -->
        <div class="payment-section">
            <div class="payment-title"><?php echo e(__('ui.sales.payment_details')); ?></div>
            <?php $__currentLoopData = $receipt['payments']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="payment-row">
                    <span><?php echo e($payment['method']); ?>:</span>
                    <span><?php echo e(number_format($payment['amount'], 3)); ?> <?php echo e($receipt['currency']); ?></span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php if(isset($receipt['amount_tendered'])): ?>
                <div class="payment-row">
                    <span><?php echo e(__('ui.sales.tendered')); ?>:</span>
                    <span><?php echo e(number_format($receipt['amount_tendered'], 3)); ?> <?php echo e($receipt['currency']); ?></span>
                </div>
            <?php endif; ?>
            <?php if(isset($receipt['change_due']) && $receipt['change_due'] > 0): ?>
                <div class="payment-row" style="font-weight: bold;">
                    <span><?php echo e(__('ui.sales.change')); ?>:</span>
                    <span><?php echo e(number_format($receipt['change_due'], 3)); ?> <?php echo e($receipt['currency']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="thank-you"><?php echo e(__('ui.sales.thank_you')); ?></div>
            <div class="footer-info">
                <?php echo e(__('ui.sales.keep_receipt')); ?><br>
                <?php echo e(__('ui.sales.return_policy')); ?>

            </div>
            
            <!-- Invoice Number as Barcode -->
            <div class="barcode">
                *<?php echo e($receipt['invoice_number']); ?>*
            </div>
        </div>
    </div>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\POS\backend\resources\views/receipts/pos.blade.php ENDPATH**/ ?>