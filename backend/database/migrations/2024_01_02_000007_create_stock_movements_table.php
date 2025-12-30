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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->integer('quantity_change'); // Positive for in, negative for out
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->enum('type', ['adjustment', 'purchase', 'sale', 'transfer_in', 'transfer_out', 'return', 'supplier_return', 'damage', 'correction']);
            $table->string('reason')->nullable();
            $table->string('reference_type')->nullable(); // e.g., 'order', 'purchase_order'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id']);
            $table->index(['type', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
