<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique(); // PI-YYYYMMDD-XXXX
            $table->string('supplier_invoice_number')->nullable(); // Reference from supplier
            
            $table->foreignId('supplier_id')->constrained()->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained()->onDelete('restrict');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            
            // Totals (LYD 3 decimal precision)
            $table->decimal('subtotal', 12, 3)->default(0);
            $table->decimal('tax_total', 12, 3)->default(0);
            $table->decimal('discount_amount', 12, 3)->default(0);
            $table->decimal('shipping_cost', 12, 3)->default(0);
            $table->decimal('total', 12, 3)->default(0);
            
            // Dates
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            
            // Payment tracking
            $table->decimal('paid_amount', 12, 3)->default(0);
            $table->string('payment_status')->default('unpaid'); // unpaid, partial, paid
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['invoice_date', 'created_at']);
            $table->index(['supplier_id', 'payment_status']);
        });

        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 3); // Cost per unit
            $table->decimal('tax_rate', 5, 2)->default(0); // Percentage
            $table->decimal('tax_amount', 12, 3)->default(0);
            $table->decimal('discount_amount', 12, 3)->default(0);
            $table->decimal('line_total', 12, 3); // (quantity * unit_cost) - discount + tax
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['purchase_invoice_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
        Schema::dropIfExists('purchase_invoices');
    }
};
