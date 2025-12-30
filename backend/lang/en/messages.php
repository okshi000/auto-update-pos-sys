<?php

return [
    /*
    |--------------------------------------------------------------------------
    | General Messages
    |--------------------------------------------------------------------------
    */
    'success' => 'Operation completed successfully',
    'error' => 'An error occurred',
    'not_found' => 'Resource not found',
    'unauthorized' => 'Unauthorized access',
    'forbidden' => 'Access denied',
    'validation_failed' => 'Validation failed',
    'server_error' => 'Internal server error',
    'api_running' => 'API is running',

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'login_success' => 'Login successful',
        'logout_success' => 'Logged out successfully',
        'invalid_credentials' => 'Invalid credentials or account is inactive',
        'token_refreshed' => 'Token refreshed successfully',
        'token_invalid' => 'Invalid or expired token',
        'account_inactive' => 'Your account is inactive',
    ],

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */
    'users' => [
        'created' => 'User created successfully',
        'updated' => 'User updated successfully',
        'deleted' => 'User deleted successfully',
        'not_found' => 'User not found',
        'status_toggled' => 'User status updated',
        'roles_synced' => 'User roles synchronized',
        'cannot_delete_self' => 'You cannot delete your own account',
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles & Permissions
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'created' => 'Role created successfully',
        'updated' => 'Role updated successfully',
        'deleted' => 'Role deleted successfully',
        'not_found' => 'Role not found',
        'cannot_delete_system' => 'Cannot delete system role',
        'cannot_modify_system_name' => 'Cannot modify system role name',
    ],

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */
    'products' => [
        'created' => 'Product created successfully',
        'updated' => 'Product updated successfully',
        'deleted' => 'Product deleted successfully',
        'not_found' => 'Product not found',
        'duplicated' => 'Product duplicated successfully',
        'image_uploaded' => 'Product image uploaded successfully',
        'barcode_not_found' => 'No product found with this barcode',
        'sku_exists' => 'A product with this SKU already exists',
    ],

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'created' => 'Category created successfully',
        'updated' => 'Category updated successfully',
        'deleted' => 'Category deleted successfully',
        'not_found' => 'Category not found',
        'cannot_parent_self' => 'Category cannot be its own parent',
    ],

    /*
    |--------------------------------------------------------------------------
    | Warehouses
    |--------------------------------------------------------------------------
    */
    'warehouses' => [
        'created' => 'Warehouse created successfully',
        'updated' => 'Warehouse updated successfully',
        'deleted' => 'Warehouse deleted successfully',
        'not_found' => 'Warehouse not found',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stock
    |--------------------------------------------------------------------------
    */
    'stock' => [
        'adjusted' => 'Stock adjusted successfully',
        'transferred' => 'Stock transferred successfully',
        'set' => 'Stock level set successfully',
        'insufficient' => 'Insufficient stock available',
        'same_warehouse' => 'Cannot transfer to the same warehouse',
        'reserved' => 'Stock reserved successfully',
        'reservation_released' => 'Stock reservation released',
        'cannot_reserve' => 'Cannot reserve more than available stock',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sales
    |--------------------------------------------------------------------------
    */
    'sales' => [
        'created' => 'Sale completed successfully',
        'refunded' => 'Sale refunded successfully',
        'not_found' => 'Sale not found',
        'already_refunded' => 'This sale has already been refunded',
        'duplicate_transaction' => 'Duplicate transaction detected',
        'insufficient_payment' => 'Payment amount is insufficient',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Methods
    |--------------------------------------------------------------------------
    */
    'payment_methods' => [
        'created' => 'Payment method created successfully',
        'updated' => 'Payment method updated successfully',
        'deleted' => 'Payment method deleted successfully',
        'not_found' => 'Payment method not found',
        'code_exists' => 'A payment method with this code already exists',
    ],

    /*
    |--------------------------------------------------------------------------
    | Suppliers
    |--------------------------------------------------------------------------
    */
    'suppliers' => [
        'created' => 'Supplier created successfully',
        'updated' => 'Supplier updated successfully',
        'deleted' => 'Supplier deleted successfully',
        'not_found' => 'Supplier not found',
        'code_exists' => 'A supplier with this code already exists',
        'has_orders' => 'Cannot delete supplier with existing orders',
    ],

    /*
    |--------------------------------------------------------------------------
    | Purchases
    |--------------------------------------------------------------------------
    */
    'purchase_orders' => [
        'created' => 'Purchase created successfully',
        'updated' => 'Purchase updated successfully',
        'deleted' => 'Purchase deleted successfully',
        'sent' => 'Purchase sent to supplier',
        'received' => 'Goods received successfully',
        'cancelled' => 'Purchase cancelled',
        'not_found' => 'Purchase not found',
        'already_sent' => 'Purchase has already been sent',
        'cannot_cancel' => 'Cannot cancel a received purchase',
        'exceed_quantity' => 'Cannot receive more than ordered quantity',
    ],

    /*
    |--------------------------------------------------------------------------
    | Purchase Invoices
    |--------------------------------------------------------------------------
    */
    'purchase_invoices' => [
        'created' => 'Purchase invoice created and stock updated successfully',
        'deleted' => 'Purchase invoice deleted',
        'not_found' => 'Purchase invoice not found',
        'payment_recorded' => 'Payment recorded successfully',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supplier Returns
    |--------------------------------------------------------------------------
    */
    'supplier_returns' => [
        'created' => 'Supplier return created successfully',
        'approved' => 'Supplier return approved',
        'shipped' => 'Supplier return shipped',
        'completed' => 'Supplier return completed',
        'not_found' => 'Supplier return not found',
        'invalid_status' => 'Invalid status transition',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */
    'reports' => [
        'generated' => 'Report generated successfully',
        'date_range_exceeded' => 'Date range cannot exceed :days days',
        'no_data' => 'No data available for the selected criteria',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation
    |--------------------------------------------------------------------------
    */
    'reconciliation' => [
        'accepted' => 'Conflict accepted successfully',
        'adjusted' => 'Conflict adjusted and stock restored',
        'voided' => 'Sale voided and stock restored',
        'not_conflict' => 'This sale does not have a stock conflict',
        'not_found' => 'Conflicted sale not found',
    ],

    /*
    |--------------------------------------------------------------------------
    | Offline Sync
    |--------------------------------------------------------------------------
    */
    'offline_sync' => [
        'success' => 'Offline sales synced successfully',
        'partial' => 'Some sales synced with conflicts',
        'duplicate' => 'Duplicate sale detected',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logs
    |--------------------------------------------------------------------------
    */
    'audit_logs_retrieved' => 'Audit logs retrieved successfully',
    'audit_log_retrieved' => 'Audit log retrieved successfully',

    /*
    |--------------------------------------------------------------------------
    | Printer / Barcode Printing
    |--------------------------------------------------------------------------
    */
    'printer' => [
        'print_success' => 'Barcode sticker printed successfully',
        'print_failed' => 'Failed to print barcode sticker: :error',
        'no_barcode' => 'Product does not have a barcode assigned',
        'connection_failed' => 'Cannot connect to printer at :path',
        'network_connection_failed' => 'Cannot connect to printer at :ip::port - :error',
        'socket_error' => 'Failed to create network socket',
        'send_failed' => 'Failed to send data to printer',
        'test_success' => 'Printer connection test successful',
        'test_failed' => 'Printer connection test failed',
        'unexpected_error' => 'An unexpected error occurred while printing',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'required' => 'The :attribute field is required',
        'email' => 'The :attribute must be a valid email address',
        'min' => 'The :attribute must be at least :min characters',
        'max' => 'The :attribute may not be greater than :max characters',
        'unique' => 'The :attribute has already been taken',
        'exists' => 'The selected :attribute is invalid',
        'numeric' => 'The :attribute must be a number',
        'date' => 'The :attribute must be a valid date',
        'after_or_equal' => 'The :attribute must be after or equal to :date',
        'array' => 'The :attribute must be an array',
        'in' => 'The selected :attribute is invalid',
    ],
];
