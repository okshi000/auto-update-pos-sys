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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            
            // Quantities
            $table->integer('quantity_ordered');
            $table->integer('quantity_received')->default(0);
            $table->integer('quantity_returned')->default(0); // For supplier returns
            
            // Pricing (LYD 3 decimal precision)
            $table->decimal('unit_cost', 12, 3); // Cost price from supplier
            $table->decimal('tax_rate', 5, 2)->default(0); // Tax percentage
            $table->decimal('tax_amount', 12, 3)->default(0);
            $table->decimal('discount_amount', 12, 3)->default(0);
            $table->decimal('line_total', 12, 3)->default(0);
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
