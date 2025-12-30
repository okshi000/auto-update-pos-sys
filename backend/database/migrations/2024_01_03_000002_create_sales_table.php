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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->string('client_uuid')->nullable()->index(); // For offline client identification
            $table->string('idempotency_key')->unique(); // Prevent duplicate sales
            
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained()->onDelete('restrict');
            
            $table->decimal('subtotal', 12, 3)->default(0); // 3 decimal precision for LYD
            $table->decimal('tax_total', 12, 3)->default(0);
            $table->decimal('discount_amount', 12, 3)->default(0);
            $table->string('discount_type')->nullable(); // fixed, percentage
            $table->decimal('total', 12, 3)->default(0);
            
            $table->string('status')->default('completed'); // pending, completed, refunded, partially_refunded
            $table->boolean('is_synced')->default(true); // false for offline sales pending sync
            $table->boolean('has_stock_conflict')->default(false); // true if stock went negative during offline sync
            
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
