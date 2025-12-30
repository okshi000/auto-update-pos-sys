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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_method_id')->constrained()->onDelete('restrict');
            
            $table->decimal('amount', 12, 3); // LYD - 3 decimal precision
            $table->decimal('tendered', 12, 3)->nullable(); // Amount given by customer (for cash)
            $table->decimal('change', 12, 3)->nullable(); // Change returned (for cash)
            
            $table->string('reference')->nullable(); // Transaction reference for non-cash
            $table->string('status')->default('completed'); // pending, completed, refunded
            
            $table->timestamps();
            
            $table->index(['sale_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
