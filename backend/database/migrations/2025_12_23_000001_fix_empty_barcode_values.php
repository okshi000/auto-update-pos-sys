<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fix empty string barcodes to null for proper unique validation
     */
    public function up(): void
    {
        // Convert empty string barcodes to null
        DB::table('products')
            ->where('barcode', '')
            ->update(['barcode' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse
    }
};
