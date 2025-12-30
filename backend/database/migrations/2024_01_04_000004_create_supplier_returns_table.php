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
        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique(); // RET-YYYYMMDD-XXXX
            
            $table->foreignId('supplier_id')->constrained()->onDelete('restrict');
            $table->foreignId('purchase_order_id')->nullable()->constrained()->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained()->onDelete('restrict');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            
            // Status: pending, approved, shipped, completed, cancelled
            $table->string('status')->default('pending');
            
            // Totals (LYD 3 decimal precision)
            $table->decimal('total', 12, 3)->default(0);
            
            $table->string('reason'); // damaged, defective, wrong_item, excess, other
            $table->text('notes')->nullable();
            
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'created_at']);
            $table->index('supplier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_returns');
    }
};
