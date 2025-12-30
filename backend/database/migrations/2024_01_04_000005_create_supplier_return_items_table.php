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
        Schema::create('supplier_return_items', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('supplier_return_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->onDelete('restrict');
            
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 3); // Cost at time of return
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
        Schema::dropIfExists('supplier_return_items');
    }
};
