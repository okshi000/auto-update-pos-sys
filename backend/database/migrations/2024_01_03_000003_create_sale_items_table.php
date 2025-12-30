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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            
            $table->string('product_name'); // Snapshot at time of sale
            $table->string('product_sku');
            
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 3); // Price at time of sale (LYD - 3 decimals)
            $table->decimal('cost_price', 12, 3)->default(0); // Cost at time of sale
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 3)->default(0);
            $table->decimal('discount_amount', 12, 3)->default(0);
            $table->decimal('line_total', 12, 3); // (unit_price * quantity) - discount + tax
            
            $table->timestamps();
            
            $table->index(['sale_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
