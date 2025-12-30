<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add performance indexes for reports, sales, and stock queries.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Sales table indexes for report queries
        Schema::table('sales', function (Blueprint $table) {
            // Index for daily sales report (date + status)
            $table->index(['completed_at', 'status'], 'idx_sales_completed_status');
            
            // Index for warehouse filtering
            $table->index(['warehouse_id', 'completed_at'], 'idx_sales_warehouse_completed');
            
            // Index for conflict reconciliation
            $table->index(['has_stock_conflict', 'created_at'], 'idx_sales_conflict');
            
            // Index for user sales lookup
            $table->index(['user_id', 'completed_at'], 'idx_sales_user_completed');
        });

        // Sale items indexes for product reports
        Schema::table('sale_items', function (Blueprint $table) {
            // Index for sales by product report
            $table->index(['product_id', 'sale_id'], 'idx_sale_items_product');
        });

        // Payments table indexes for cash reconciliation
        Schema::table('payments', function (Blueprint $table) {
            // Index for payment method totals
            $table->index(['payment_method_id', 'status'], 'idx_payments_method_status');
            
            // Index for sale payments lookup
            $table->index(['sale_id', 'status'], 'idx_payments_sale_status');
        });

        // Stock levels indexes
        Schema::table('stock_levels', function (Blueprint $table) {
            // Index for stock valuation and level queries
            $table->index(['product_id', 'warehouse_id', 'quantity'], 'idx_stock_product_warehouse_qty');
        });

        // Stock movements indexes
        Schema::table('stock_movements', function (Blueprint $table) {
            // Index for movement history queries
            $table->index(['product_id', 'created_at'], 'idx_movements_product_date');
            
            // Index for warehouse movements (using 'type' not 'movement_type')
            $table->index(['warehouse_id', 'type', 'created_at'], 'idx_movements_warehouse_type');
        });

        // Products table indexes
        Schema::table('products', function (Blueprint $table) {
            // Index for category reports
            $table->index(['category_id', 'is_active'], 'idx_products_category_active');
            
            // Index for low stock queries
            $table->index(['stock_tracked', 'is_active', 'min_stock_level'], 'idx_products_stock_tracked');
        });

        // Audit logs indexes for quick lookups
        Schema::table('audit_logs', function (Blueprint $table) {
            // Index for user audit trail
            $table->index(['user_id', 'created_at'], 'idx_audit_user_date');
            
            // Index for entity audit trail
            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'idx_audit_entity_date');
            
            // Index for action filtering
            $table->index(['action', 'created_at'], 'idx_audit_action_date');
        });

        // Purchase orders indexes
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Index for supplier order history
            $table->index(['supplier_id', 'status'], 'idx_po_supplier_status');
            
            // Index for date-based reporting
            $table->index(['order_date', 'status'], 'idx_po_date_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('idx_sales_completed_status');
            $table->dropIndex('idx_sales_warehouse_completed');
            $table->dropIndex('idx_sales_conflict');
            $table->dropIndex('idx_sales_user_completed');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex('idx_sale_items_product');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_method_status');
            $table->dropIndex('idx_payments_sale_status');
        });

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropIndex('idx_stock_product_warehouse_qty');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('idx_movements_product_date');
            $table->dropIndex('idx_movements_warehouse_type');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_category_active');
            $table->dropIndex('idx_products_stock_tracked');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_user_date');
            $table->dropIndex('idx_audit_entity_date');
            $table->dropIndex('idx_audit_action_date');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('idx_po_supplier_status');
            $table->dropIndex('idx_po_date_status');
        });
    }
};
