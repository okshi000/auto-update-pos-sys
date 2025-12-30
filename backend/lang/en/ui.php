<?php

/**
 * UI Translation Strings - English
 * These are frontend UI strings, separate from API messages.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'nav.main' => 'Main',
    'nav.dashboard' => 'Dashboard',
    'nav.pos' => 'Point of Sale',
    'nav.catalog' => 'Catalog',
    'nav.products' => 'Products',
    'nav.inventory' => 'Inventory',
    'nav.operations' => 'Operations',
    'nav.sales' => 'Sales History',
    'nav.purchases' => 'Purchases',
    'nav.reports' => 'Reports',
    'nav.administration' => 'Administration',
    'nav.users' => 'Users',
    'nav.roles' => 'Roles',
    'nav.suppliers' => 'Suppliers',
    'nav.settings' => 'Settings',
    'nav.audit' => 'Audit Log',
    'nav.sidebar' => 'Main navigation',
    'nav.close' => 'Close navigation',
    'nav.open' => 'Open navigation',
    'nav.skip_to_main' => 'Skip to main content',
    'nav.main_content' => 'Main content',

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    'auth.login_title' => 'Sign In',
    'auth.login_subtitle' => 'Enter your credentials to access your account',
    'auth.email' => 'Email',
    'auth.email_placeholder' => 'you@example.com',
    'auth.password' => 'Password',
    'auth.password_placeholder' => '••••••••',
    'auth.sign_in' => 'Sign In',
    'auth.signing_in' => 'Signing in...',
    'auth.logout' => 'Logout',

    /*
    |--------------------------------------------------------------------------
    | Common / Shared
    |--------------------------------------------------------------------------
    */
    'common.save' => 'Save',
    'common.saving' => 'Saving...',
    'common.cancel' => 'Cancel',
    'common.delete' => 'Delete',
    'common.edit' => 'Edit',
    'common.create' => 'Create',
    'common.creating' => 'Creating...',
    'common.update' => 'Update',
    'common.view' => 'View',
    'common.back' => 'Back',
    'common.next' => 'Next',
    'common.close' => 'Close',
    'common.confirm' => 'Confirm',
    'common.apply' => 'Apply',
    'common.clear' => 'Clear',
    'common.search' => 'Search',
    'common.refresh' => 'Refresh',
    'common.retry' => 'Retry',
    'common.loading' => 'Loading...',
    'common.print' => 'Print',
    'common.remove' => 'Remove',
    'common.select' => 'Select...',
    'common.select_dates' => 'Select dates...',
    'common.status' => 'Status',
    'common.type' => 'Type',
    'common.active' => 'Active',
    'common.inactive' => 'Inactive',
    'common.all' => 'All',
    'common.all_statuses' => 'All Statuses',
    'common.all_categories' => 'All Categories',
    'common.all_warehouses' => 'All Warehouses',
    'common.all_suppliers' => 'All Suppliers',
    'common.all_roles' => 'All Roles',
    'common.all_actions' => 'All Actions',
    'common.all_entities' => 'All Entities',
    'common.none' => '(None)',
    'common.total' => 'Total',
    'common.amount' => 'Amount',
    'common.time' => 'Time',
    'common.reference' => 'Reference',
    'common.discount' => 'Discount',
    'common.shipping' => 'Shipping',
    'common.items' => 'items',
    'common.item' => 'Item',
    'common.qty' => 'Qty',
    'common.price' => 'Price',
    'common.location' => 'Location',
    'common.system' => 'System',
    'common.product' => 'Product',
    'common.products' => 'products',
    'common.records' => 'records',
    'common.no_results' => 'No results found',
    'common.no_options' => 'No options',
    'common.numeric_keypad' => 'Numeric keypad',
    'common.delete_last' => 'Delete last digit',
    'common.clear_all' => 'Clear all',
    'common.decimal_point' => 'Decimal point',
    'common.quantity' => 'Quantity',
    'common.increase' => 'Increase quantity',
    'common.decrease' => 'Decrease quantity',

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency.lyd' => 'LYD',

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    'dashboard.welcome' => 'Welcome',
    'dashboard.role' => 'Role',
    'dashboard.today_sales' => "Today's Sales",
    'dashboard.today_orders' => "Today's Orders",
    'dashboard.low_stock' => 'Low Stock Items',
    'dashboard.low_stock_alert' => 'Low Stock Alert',
    'dashboard.new_customers' => 'New Customers',
    'dashboard.pending_orders' => 'Pending Orders',
    'dashboard.vs_last_week' => 'vs last week',
    'dashboard.sales_trend' => 'Sales Trend (Last 7 Days)',
    'dashboard.sales_by_category' => 'Sales by Category',
    'dashboard.payment_methods' => 'Payment Methods',
    'dashboard.top_products' => 'Top Selling Products',
    'dashboard.view_inventory' => 'View Inventory',
    'dashboard.products_low_stock' => 'products are running low on stock',

    /*
    |--------------------------------------------------------------------------
    | Point of Sale
    |--------------------------------------------------------------------------
    */
    'pos.cart' => 'Cart',
    'pos.cart_empty' => 'Cart is empty',
    'pos.empty_cart' => 'Cart is empty',
    'pos.empty_cart_hint' => 'Scan a barcode or select products from the grid',
    'pos.clear' => 'Clear',
    'pos.clear_cart' => 'Clear Cart',
    'pos.clear_cart_confirm' => 'Are you sure you want to remove all items from the cart?',
    'pos.cart_cleared' => 'Cart cleared',
    'pos.checkout' => 'Checkout',
    'pos.complete_sale' => 'Complete Sale',
    'pos.subtotal' => 'Subtotal',
    'pos.discount' => 'Discount',
    'pos.tax' => 'Tax',
    'pos.total' => 'Total',
    'pos.items' => 'items',
    'pos.products' => 'Products',
    'pos.categories' => 'Product categories',
    'pos.no_products' => 'No products found',
    'pos.try_adjusting' => 'Try adjusting your search or category filter',
    'pos.search_barcode' => 'Search or scan barcode...',
    'pos.item_added' => 'Item added to cart',
    'pos.quantity_increased' => 'Quantity increased',
    'pos.product_not_found' => 'Product not found: ',
    'pos.out' => 'Out',
    'pos.payment_method' => 'Payment Method',
    'pos.amount_tendered' => 'Amount Tendered',
    'pos.change_due' => 'Change Due',
    'pos.remaining_balance' => 'Remaining Balance',
    'pos.no_payments_added' => 'No payments added yet',
    'pos.insufficient_payment' => 'Insufficient payment amount',
    'pos.sale_complete' => 'Sale Complete',
    'pos.sale_complete_message' => 'Sale has been completed successfully.',
    'pos.sale_id' => 'Sale ID',
    'pos.thank_you' => 'Thank You!',
    'pos.new_sale' => 'New Sale',
    'pos.view_receipt' => 'View Receipt',
    'pos.print_receipt' => 'Print Receipt',
    'pos.receipt' => 'Receipt',
    'pos.invoice' => 'Invoice',
    'pos.chars_per_line' => 'chars/line',
    'pos.scan_for_digital' => 'Scan for digital receipt',
    'pos.summary' => 'Summary',
    'pos.hold' => 'Hold Sale',
    'pos.held_sales' => 'Held Sales',
    'pos.held_sale' => 'Held Sale',
    'pos.no_held_sales' => 'No held sales',
    'pos.sale_held' => 'Sale held for later',
    'pos.recall' => 'Recall Held Sale',
    'pos.sale_recalled' => 'Sale recalled',
    'pos.sale_saved_offline' => 'Sale saved offline',
    'pos.offline_mode_active' => 'Offline Mode - Local Save',
    'pos.printer_ready' => 'Printer Ready',
    'pos.printer_offline' => 'Offline Mode',
    'pos.pending_prints' => 'pending',
    'pos.print_queued' => 'Added to print queue',
    'pos.print_sent' => 'Print job sent',
    'pos.keyboard_shortcuts' => 'Keyboard Shortcuts',
    'pos.press_f1_help' => 'Press F1 anytime to show this help',
    'pos.shortcuts.search' => 'Focus search',
    'pos.shortcuts.checkout' => 'Proceed to checkout',
    'pos.shortcuts.hold' => 'Hold current sale',
    'pos.shortcuts.recall' => 'Recall held sale',
    'pos.shortcuts.close' => 'Close modal',
    'pos.shortcuts.help' => 'Show shortcuts help',

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */
    'products.add' => 'Add Product',
    'products.create' => 'Add Product',
    'products.edit' => 'Edit Product',
    'products.name' => 'Name',
    'products.sku' => 'SKU',
    'products.barcode' => 'Barcode',
    'products.category' => 'Category',
    'products.sale_price' => 'Sale Price',
    'products.cost_price' => 'Cost Price',
    'products.stock' => 'Stock',
    'products.min_stock' => 'Min Stock Level',
    'products.status' => 'Status',
    'products.type' => 'Type',
    'products.description' => 'Description',
    'products.is_active' => 'Product is active',
    'products.search_placeholder' => 'Search by name, SKU, or barcode...',
    'products.empty' => 'No products found',
    'products.empty_desc' => 'Add your first product to get started',
    'products.delete_title' => 'Delete Product',
    'products.delete_confirm' => 'Are you sure you want to delete this product? This action cannot be undone.',
    'products.created' => 'Product created successfully',
    'products.updated' => 'Product updated successfully',
    'products.deleted' => 'Product deleted successfully',

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */
    'categories.manage' => 'Manage Category',
    'categories.add' => 'Add Category',
    'categories.edit' => 'Edit Category',
    'categories.name' => 'Name',
    'categories.description' => 'Description',
    'categories.is_active' => 'Category is active',
    'categories.empty' => 'No categories yet',
    'categories.empty_desc' => 'Create your first category to organize products',
    'categories.delete_title' => 'Delete Category',
    'categories.delete_confirm' => 'Are you sure you want to delete this category?',
    'categories.created' => 'Category created',
    'categories.updated' => 'Category updated',
    'categories.deleted' => 'Category deleted',

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */
    'inventory.warehouse' => 'Warehouse',
    'inventory.warehouses' => 'Warehouses',
    'inventory.add_warehouse' => 'Add Warehouse',
    'inventory.delete_warehouse' => 'Delete Warehouse',
    'inventory.delete_warehouse_confirm' => 'Are you sure you want to delete this warehouse? This action cannot be undone.',
    'inventory.product' => 'Product',
    'inventory.quantity' => 'Quantity',
    'inventory.available' => 'Available',
    'inventory.current' => 'Current',
    'inventory.current_stock' => 'Current stock',
    'inventory.min_level' => 'Min Level',
    'inventory.status' => 'Status',
    'inventory.in_stock' => 'In Stock',
    'inventory.low_stock' => 'Low Stock',
    'inventory.low' => 'Low',
    'inventory.out_of_stock' => 'Out of Stock',
    'inventory.low_stock_only' => 'Low stock only',
    'inventory.search_placeholder' => 'Search by product name or SKU...',
    'inventory.empty' => 'No inventory found',
    'inventory.empty_desc' => 'Stock data will appear here once products are added',
    'inventory.adjust' => 'Adjust Stock',
    'inventory.adjust_stock' => 'Adjust Stock',
    'inventory.adjustment_type' => 'Type',
    'inventory.increase' => 'Increase (+)',
    'inventory.decrease' => 'Decrease (-)',
    'inventory.set' => 'Set to Value',
    'inventory.reason' => 'Reason',
    'inventory.notes' => 'Notes',
    'inventory.notes_placeholder' => 'Optional notes...',
    'inventory.select_product' => 'Select product',
    'inventory.select_warehouse' => 'Select',
    'inventory.apply' => 'Apply Adjustment',
    'inventory.new_stock' => 'New stock will be',
    'inventory.adjusted' => 'Stock adjusted successfully',
    'inventory.insufficient_stock' => 'Insufficient stock available',
    'inventory.transfer' => 'Transfer Stock',
    'inventory.transfer_stock' => 'Transfer Stock',
    'inventory.create_transfer' => 'Create Transfer',
    'inventory.transfers' => 'Stock Transfers',
    'inventory.source_warehouse' => 'From Warehouse',
    'inventory.destination_warehouse' => 'To Warehouse',
    'inventory.from' => 'From',
    'inventory.to' => 'To',
    'inventory.transfer_notes_placeholder' => 'Optional transfer notes...',
    'inventory.transfer_created' => 'Stock transfer initiated',
    'inventory.same_warehouse' => 'Source and destination must be different',
    'inventory.history' => 'Adjustment History',
    'inventory.no_history' => 'No adjustments recorded yet',
    'inventory.no_transfers' => 'No transfers recorded yet',
    'inventory.reconciliation' => 'Reconciliation',
    'inventory.reconciliation_queue' => 'Reconciliation Queue',
    'inventory.pending_conflicts' => 'pending conflicts',
    'inventory.no_conflicts' => 'No Conflicts',
    'inventory.no_conflicts_desc' => 'All stock levels are synchronized',
    'inventory.expected' => 'Expected',
    'inventory.actual' => 'Actual',
    'inventory.difference' => 'Difference',
    'inventory.resolve' => 'Resolve',
    'inventory.resolution_action' => 'Resolution Action',
    'inventory.resolution_notes' => 'Notes',
    'inventory.resolution_notes_placeholder' => 'Explain the resolution...',
    'inventory.accept_actual' => 'Accept Actual Count',
    'inventory.accept_desc' => 'Use the actual counted quantity as the new stock level',
    'inventory.adjust_to_expected' => 'Adjust to Expected',
    'inventory.adjust_desc' => 'Create an adjustment to restore the expected quantity',
    'inventory.ignore_difference' => 'Ignore Difference',
    'inventory.ignore_desc' => 'Mark as reviewed but take no action',
    'inventory.reconciliation_resolved' => 'Conflict resolved',

    /*
    |--------------------------------------------------------------------------
    | Warehouses
    |--------------------------------------------------------------------------
    */
    'warehouses.name' => 'Warehouse Name',
    'warehouses.name_placeholder' => 'e.g., Main Warehouse',
    'warehouses.code' => 'Code',
    'warehouses.code_placeholder' => 'e.g., MAIN_WH',
    'warehouses.code_hint' => 'Unique identifier for the warehouse',
    'warehouses.address' => 'Address',
    'warehouses.address_placeholder' => 'Physical location address...',
    'warehouses.status' => 'Status',
    'warehouses.active' => 'Active',
    'warehouses.active_hint' => 'Inactive warehouses are hidden from selection',
    'warehouses.create' => 'Create Warehouse',
    'warehouses.edit' => 'Edit Warehouse',
    'warehouses.created' => 'Warehouse created',
    'warehouses.updated' => 'Warehouse updated',
    'warehouses.deleted' => 'Warehouse deleted',

    /*
    |--------------------------------------------------------------------------
    | Sales
    |--------------------------------------------------------------------------
    */
    'sales.receipt' => 'Receipt #',
    'sales.date' => 'Date',
    'sales.customer' => 'Customer',
    'sales.walk_in' => 'Walk-in',
    'sales.cashier' => 'Cashier',
    'sales.items' => 'Items',
    'sales.qty' => 'Qty',
    'sales.price' => 'Price',
    'sales.subtotal' => 'Subtotal',
    'sales.discount' => 'Discount',
    'sales.tax' => 'Tax',
    'sales.total' => 'Total',
    'sales.status' => 'Status',
    'sales.completed' => 'Completed',
    'sales.pending' => 'Pending',
    'sales.refunded' => 'Refunded',
    'sales.voided' => 'Voided',
    'sales.payments' => 'Payments',
    'sales.void' => 'Void',
    'sales.void_sale' => 'Void Sale',
    'sales.void_confirm' => 'Are you sure you want to void this sale? This action cannot be undone.',
    'sales.search_placeholder' => 'Search by receipt # or customer...',
    'sales.empty' => 'No sales found',
    'sales.empty_desc' => 'Sales will appear here after transactions',
    'sales.invoice' => 'Invoice',
    'sales.payment_details' => 'Payment Details',
    'sales.tendered' => 'Tendered',
    'sales.change' => 'Change',
    'sales.thank_you' => 'Thank You!',
    'sales.keep_receipt' => 'Please keep this receipt for your records.',
    'sales.return_policy' => 'Returns accepted within 14 days with receipt.',

    /*
    |--------------------------------------------------------------------------
    | Purchases / المشتريات
    |--------------------------------------------------------------------------
    */
    'purchases.title' => 'Purchases',
    'purchases.create' => 'New Purchase Invoice',
    'purchases.invoice_number' => 'Invoice #',
    'purchases.supplier_invoice' => 'Supplier Invoice #',
    'purchases.supplier_invoice_placeholder' => 'Enter supplier invoice number...',
    'purchases.date' => 'Invoice Date',
    'purchases.due_date' => 'Due Date',
    'purchases.supplier' => 'Supplier',
    'purchases.select_supplier' => 'Select supplier',
    'purchases.warehouse' => 'Warehouse',
    'purchases.select_warehouse' => 'Select warehouse',
    'purchases.items' => 'Items',
    'purchases.add_products' => 'Add Products',
    'purchases.search_products' => 'Search by name, SKU, barcode...',
    'purchases.no_items' => 'No items added yet',
    'purchases.qty' => 'Qty',
    'purchases.unit_cost' => 'Unit Cost',
    'purchases.tax_rate' => 'Tax %',
    'purchases.discount' => 'Discount',
    'purchases.subtotal' => 'Subtotal',
    'purchases.tax_total' => 'Tax Total',
    'purchases.shipping' => 'Shipping',
    'purchases.total' => 'Total',
    'purchases.notes' => 'Notes',
    'purchases.notes_placeholder' => 'Optional notes...',
    'purchases.update_cost' => 'Update product cost price',
    'purchases.payment_status' => 'Payment Status',
    'purchases.unpaid' => 'Unpaid',
    'purchases.partial' => 'Partially Paid',
    'purchases.paid' => 'Paid',
    'purchases.paid_amount' => 'Paid Amount',
    'purchases.remaining' => 'Remaining',
    'purchases.record_payment' => 'Record Payment',
    'purchases.payment_amount' => 'Payment Amount',
    'purchases.created' => 'Purchase invoice created successfully',
    'purchases.stock_updated' => 'Stock has been updated',
    'purchases.search_placeholder' => 'Search by invoice # or supplier...',
    'purchases.empty' => 'No purchase invoices found',
    'purchases.empty_desc' => 'Create your first purchase invoice to add stock',
    'purchases.already_added' => 'Product already added',
    
    // Additional purchase keys
    'purchases.invoice' => 'Invoice',
    'purchases.invoice_details' => 'Invoice Details',
    'purchases.payment' => 'Payment',
    'purchases.payment_summary' => 'Payment Summary',
    'purchases.total_amount' => 'Total Amount',
    'purchases.partial_paid' => 'Partially Paid',
    'purchases.payment_notes' => 'Payment Notes',
    'purchases.payment_notes_placeholder' => 'Optional notes...',
    'purchases.payment_recorded' => 'Payment recorded successfully',
    'purchases.max_payment' => 'Maximum',
    'purchases.record_purchase' => 'Record Purchase',
    'purchases.page_total' => 'Page Total',
    'purchases.description' => 'Record supplier invoices to increase inventory',
    'purchases.stock_note_title' => 'Automatic Stock Updates:',
    'purchases.stock_note' => 'When you record a purchase invoice, inventory quantities are increased immediately.',
    'purchases.all_status' => 'All Status',

    /*
    |--------------------------------------------------------------------------
    | Supplier Returns
    |--------------------------------------------------------------------------
    */
    'returns.create' => 'Create Supplier Return',
    'returns.supplier' => 'Supplier',
    'returns.reason' => 'Return Reason',
    'returns.defective' => 'Defective/Damaged',
    'returns.expired' => 'Expired',
    'returns.wrong_item' => 'Wrong Item Received',
    'returns.overstock' => 'Overstock',
    'returns.other' => 'Other',
    'returns.add_products' => 'Add Products to Return',
    'returns.search_products' => 'Search products...',
    'returns.no_items' => 'Search and add products to return',
    'returns.already_added' => 'Product already added',
    'returns.qty' => 'Qty',
    'returns.unit_cost' => 'Unit Cost',
    'returns.subtotal' => 'Subtotal',
    'returns.item_reason' => 'Item reason...',
    'returns.notes' => 'Notes',
    'returns.notes_placeholder' => 'Additional notes...',
    'returns.created' => 'Return created successfully',

    /*
    |--------------------------------------------------------------------------
    | Suppliers
    |--------------------------------------------------------------------------
    */
    'suppliers.add' => 'Add Supplier',
    'suppliers.edit' => 'Edit Supplier',
    'suppliers.delete' => 'Delete Supplier',
    'suppliers.name' => 'Name',
    'suppliers.name_placeholder' => 'Supplier name',
    'suppliers.code' => 'Code',
    'suppliers.code_placeholder' => 'SUP001',
    'suppliers.email' => 'Email',
    'suppliers.phone' => 'Phone',
    'suppliers.address' => 'Address',
    'suppliers.address_placeholder' => 'Full address...',
    'suppliers.tax_number' => 'Tax Number',
    'suppliers.tax_placeholder' => 'Tax ID',
    'suppliers.is_active' => 'Active supplier',
    'suppliers.search_placeholder' => 'Search by name, code, email...',
    'suppliers.empty' => 'No suppliers found',
    'suppliers.empty_desc' => 'Add your first supplier to get started',
    'suppliers.delete_confirm' => 'Are you sure you want to delete this supplier?',
    'suppliers.created' => 'Supplier created',
    'suppliers.updated' => 'Supplier updated',
    'suppliers.deleted' => 'Supplier deleted',

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */
    'users.add' => 'Add User',
    'users.add_user' => 'Add User',
    'users.create' => 'Create User',
    'users.edit_user' => 'Edit User',
    'users.delete_user' => 'Delete User',
    'users.name' => 'Name',
    'users.name_placeholder' => 'Enter full name',
    'users.email' => 'Email',
    'users.email_placeholder' => 'name@company.com',
    'users.password' => 'Password',
    'users.password_placeholder' => 'Min 8 characters',
    'users.password_hint' => 'Leave blank to keep current password',
    'users.confirm_password' => 'Confirm Password',
    'users.confirm_placeholder' => 'Repeat password',
    'users.roles' => 'Roles',
    'users.status' => 'Status',
    'users.active' => 'Active',
    'users.inactive' => 'Inactive',
    'users.user_active' => 'User is active',
    'users.last_login' => 'Last Login',
    'users.never' => 'Never',
    'users.activate' => 'Activate',
    'users.deactivate' => 'Deactivate',
    'users.activated' => 'User activated',
    'users.deactivated' => 'User deactivated',
    'users.search_placeholder' => 'Search by name or email...',
    'users.empty' => 'No users found',
    'users.empty_desc' => 'Create a new user to get started',
    'users.delete_confirm' => 'Are you sure you want to delete this user? This action cannot be undone.',
    'users.created' => 'User created successfully',
    'users.updated' => 'User updated successfully',
    'users.deleted' => 'User deleted successfully',

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */
    'roles.add' => 'Add Role',
    'roles.add_role' => 'Add Role',
    'roles.create' => 'Create Role',
    'roles.edit_role' => 'Edit Role',
    'roles.delete_role' => 'Delete Role',
    'roles.name' => 'Name',
    'roles.name_placeholder' => 'e.g., Cashier',
    'roles.description' => 'Description',
    'roles.description_placeholder' => 'Brief description of this role',
    'roles.permissions' => 'Permissions',
    'roles.no_permissions' => 'No permissions assigned',
    'roles.users' => 'Users',
    'roles.system' => 'System',
    'roles.search_placeholder' => 'Search roles...',
    'roles.empty' => 'No roles found',
    'roles.empty_desc' => 'Create a new role to get started',
    'roles.delete_confirm' => 'Are you sure you want to delete this role? Users with this role will lose its permissions.',
    'roles.created' => 'Role created successfully',
    'roles.updated' => 'Role updated successfully',
    'roles.deleted' => 'Role deleted successfully',

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */
    'reports.sales' => 'Sales Report',
    'reports.inventory' => 'Inventory Report',
    'reports.cash_register' => 'Cash Register',
    'reports.total_sales' => 'Total Sales',
    'reports.total_orders' => 'Total Orders',
    'reports.avg_order' => 'Avg Order Value',
    'reports.items_sold' => 'Items Sold',
    'reports.revenue' => 'Revenue',
    'reports.sales_trend' => 'Sales Trend',
    'reports.sales_by_category' => 'Sales by Category',
    'reports.by_payment_method' => 'By Payment Method',
    'reports.by_user' => 'Cash by User',
    'reports.top_products' => 'Top Products',
    'reports.quantity_sold' => 'Qty Sold',
    'reports.total_products' => 'Total Products',
    'reports.total_stock_value' => 'Total Stock Value',
    'reports.low_stock' => 'Low Stock',
    'reports.out_of_stock' => 'Out of Stock',
    'reports.low_stock_items' => 'Low Stock Items',
    'reports.stock_by_category' => 'Stock by Category',
    'reports.opening_balance' => 'Opening Balance',
    'reports.closing_balance' => 'Closing Balance',
    'reports.cash_in' => 'Cash In',
    'reports.cash_out' => 'Cash Out',
    'reports.net_cash' => 'Net Cash',
    'reports.transactions' => 'Transactions',
    'reports.recent_transactions' => 'Recent Transactions',
    'reports.no_data' => 'No data available',
    'reports.products' => 'products',
    'reports.exported' => 'Report exported successfully',

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    'settings.general' => 'General Settings',
    'settings.company_name' => 'Company Name',
    'settings.company_placeholder' => 'Your Company Name',
    'settings.email' => 'Email',
    'settings.phone' => 'Phone Number',
    'settings.address' => 'Address',
    'settings.address_placeholder' => 'Business address',
    'settings.regional' => 'Regional Settings',
    'settings.language' => 'Language',
    'settings.currency' => 'Currency',
    'settings.timezone' => 'Timezone',
    'settings.date_format' => 'Date Format',
    'settings.default_tax_rate' => 'Default Tax Rate (%)',
    'settings.pos' => 'POS Settings',
    'settings.auto_print' => 'Auto-Print Receipts',
    'settings.auto_print_desc' => 'Automatically print receipt after each sale',
    'settings.require_customer' => 'Require Customer Selection',
    'settings.require_customer_desc' => 'Require a customer to be selected before checkout',
    'settings.allow_negative' => 'Allow Negative Stock',
    'settings.allow_negative_desc' => 'Allow sales even when stock is zero or below',
    'settings.low_stock_threshold' => 'Low Stock Threshold',
    'settings.receipt_header' => 'Receipt Header',
    'settings.receipt_header_placeholder' => 'Text shown at top of receipts',
    'settings.receipt_footer' => 'Receipt Footer',
    'settings.receipt_footer_placeholder' => 'Text shown at bottom of receipts',
    'settings.save' => 'Save Settings',
    'settings.saved' => 'Settings saved successfully',

    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    */
    'audit.timestamp' => 'Timestamp',
    'audit.user' => 'User',
    'audit.action' => 'Action',
    'audit.entity' => 'Entity',
    'audit.description' => 'Description',
    'audit.category' => 'Category',
    'audit.ip' => 'IP Address',
    'audit.user_agent' => 'User Agent',
    'audit.old_values' => 'Previous Values',
    'audit.new_values' => 'New Values',
    'audit.details' => 'Audit Log Details',
    'audit.create' => 'Create',
    'audit.update' => 'Update',
    'audit.delete' => 'Delete',
    'audit.login' => 'Login',
    'audit.logout' => 'Logout',
    'audit.system' => 'System',
    'audit.product' => 'Product',
    'audit.sale' => 'Sale',
    'audit.inventory' => 'Inventory',
    'audit.role' => 'Role',
    'audit.setting' => 'Setting',
    'audit.search_placeholder' => 'Search by user, description...',
    'audit.empty' => 'No audit logs found',
    'audit.empty_desc' => 'System activities will appear here',

    /*
    |--------------------------------------------------------------------------
    | Date/Time
    |--------------------------------------------------------------------------
    */
    'date.today' => 'Today',
    'date.yesterday' => 'Yesterday',
    'date.last_7_days' => 'Last 7 days',
    'date.last_30_days' => 'Last 30 days',
    'date.this_month' => 'This month',
    'date.start' => 'Start',
    'date.end' => 'End',

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'pagination.showing' => 'Showing',
    'pagination.to' => 'to',
    'pagination.of' => 'of',
    'pagination.results' => 'results',
    'pagination.previous' => 'Previous page',
    'pagination.next' => 'Next page',

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */
    'table.actions' => 'Actions',
    'table.no_data' => 'No data found',
    'table.select_row' => 'Select row',
    'table.data_region' => 'Data table',

    /*
    |--------------------------------------------------------------------------
    | User Menu
    |--------------------------------------------------------------------------
    */
    'user.menu' => 'User menu',

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    'validation.required' => 'This field is required',
    'validation.email' => 'Invalid email format',
    'validation.invalid_email' => 'Invalid email address',
    'validation.min_length' => 'Must be at least 8 characters',
    'validation.password_match' => 'Passwords do not match',
    'validation.positive' => 'Must be greater than 0',
    'validation.non_negative' => 'Cannot be negative',
    'validation.alphanumeric' => 'Only letters, numbers, and dashes allowed',

    /*
    |--------------------------------------------------------------------------
    | Errors
    |--------------------------------------------------------------------------
    */
    'error.error_occurred' => 'Something went wrong',
    'error.unexpected_error' => 'An unexpected error occurred',
    'error.try_again' => 'Try Again',
    'error.load_failed' => 'Failed to load data',
    'error.save_failed' => 'Failed to save',
    'error.delete_failed' => 'Failed to delete',
    'error.update_failed' => 'Failed to update',
    'error.action_failed' => 'Action failed',
    'error.checkout_failed' => 'Checkout failed',
    'error.export_failed' => 'Failed to export report',
    'error.void_failed' => 'Failed to void sale',
    'error.offline_save_failed' => 'Failed to save offline',

    /*
    |--------------------------------------------------------------------------
    | System Updates
    |--------------------------------------------------------------------------
    */
    'system.updates' => 'System Updates',
    'system.updates_desc' => 'Keep your system up to date with the latest features and security patches',
    'system.management' => 'System Management',
    'system.current_version' => 'Current Version',
    'system.last_checked' => 'Last checked',
    'system.check_now' => 'Check Now',
    'system.checking_updates' => 'Checking for updates...',
    'system.update_available' => 'A new version is available: {{version}}',
    'system.up_to_date' => 'Your system is up to date!',
    'system.up_to_date_title' => 'System is Up to Date',
    'system.running_latest' => 'You are running the latest version of the system.',
    'system.new_version' => 'New Version Available',
    'system.version_info' => 'Version {{version}} is ready to install',
    'system.download_size' => 'Download size',
    'system.breaking_changes' => 'Breaking Changes',
    'system.view_changelog' => 'View Changelog',
    'system.install_update' => 'Install Update',
    'system.confirm_update' => 'Confirm Update',
    'system.update_version' => 'Updating to version {{version}}',
    'system.what_happens' => 'What will happen:',
    'system.step_backup' => 'A backup of your database will be created',
    'system.step_download' => 'The update will be downloaded and verified',
    'system.step_install' => 'New files will be installed',
    'system.step_migrate' => 'Database will be migrated',
    'system.step_restart' => 'The application will restart automatically',
    'system.update_warning' => 'Please do not close the browser or turn off your computer during the update process.',
    'system.start_update' => 'Start Update',
    'system.do_not_close' => 'Please do not close this window',
    'system.update_complete' => 'Update Complete!',
    'system.refresh_page' => 'The page will refresh automatically to apply changes.',
    'system.update_failed_title' => 'Update Failed',
    'system.try_again' => 'Try Again',
    'system.rollback_info' => 'The system has been rolled back to the previous version.',
    'system.update_info' => 'Updates are downloaded from a secure server and include the latest features, bug fixes, and security patches. A backup of your database is automatically created before each update.',
    'system.migration_required' => 'Database migration required',
    'system.migration_info' => 'This update includes database changes. Your data will be automatically migrated to the new structure.',
    'system.just_now' => 'Just now',
    'system.minutes_ago' => '{{count}} minutes ago',
    'system.hours_ago' => '{{count}} hours ago',
    'system.update_success' => 'System updated successfully!',
    'system.check_failed' => 'Failed to check for updates',
    'system.update' => 'System Update',
    'system.update_desc' => 'Update the system to the latest version',
    'system.update_now' => 'Update Now',
    'system.update_confirm' => 'Are you sure you want to update the system? The application will restart automatically.',
    'system.restarting' => 'System is restarting... Please wait 30-60 seconds.',
    'system.update_failed' => 'Update failed',
    'system.clear_cache' => 'Clear Cache',
    'system.clear_cache_desc' => 'Clear application cache and compiled views',
    'system.clear' => 'Clear',
    'system.clear_cache_failed' => 'Failed to clear cache',
    'system.backup' => 'Database Backup',
    'system.backup_desc' => 'Create a backup of the database',
    'system.backup_now' => 'Backup',
    'system.backup_failed' => 'Backup failed',
];
