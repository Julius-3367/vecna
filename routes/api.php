<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MpesaController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::prefix('v1')->group(function () {
    // Authentication
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Public routes
    Route::get('/plans', [\App\Http\Controllers\Api\SubscriptionController::class, 'plans']);
    Route::get('/templates', [\App\Http\Controllers\Api\TemplateController::class, 'index']);
    Route::get('/templates/{slug}', [\App\Http\Controllers\Api\TemplateController::class, 'show']);

    // M-Pesa Callbacks (no auth)
    Route::post('/mpesa/stk-callback', [MpesaController::class, 'stkCallback']);
    Route::post('/mpesa/b2c-callback', [MpesaController::class, 'b2cCallback']);

    // WhatsApp Webhooks (no auth)
    Route::get('/whatsapp/webhook', [\App\Http\Controllers\Api\WhatsAppController::class, 'verifyWebhook']);
    Route::post('/whatsapp/webhook', [\App\Http\Controllers\Api\WhatsAppController::class, 'handleWebhook']);    // M-Pesa Callbacks (public webhooks)
    Route::post('/mpesa/callback', [MpesaController::class, 'callback']);
    Route::post('/mpesa/timeout', [MpesaController::class, 'timeout']);
    Route::post('/mpesa/validation', [MpesaController::class, 'validation']);
    Route::post('/mpesa/confirmation', [MpesaController::class, 'confirmation']);
});

// Protected routes (require authentication)
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // User Profile
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Products
    Route::apiResource('products', ProductController::class);
    Route::post('/products/{product}/stock', [ProductController::class, 'updateStock']);
    Route::get('/products/{product}/stock-history', [ProductController::class, 'stockHistory']);
    Route::post('/products/import', [ProductController::class, 'import']);
    Route::get('/products/export', [ProductController::class, 'export']);
    Route::post('/products/{product}/duplicate', [ProductController::class, 'duplicate']);
    Route::get('/products/{product}/profit-analysis', [ProductController::class, 'profitAnalysis']);

    // TODO: Implement CategoryController
    // Categories
    // Route::apiResource('categories', \App\Http\Controllers\Api\CategoryController::class);

    // Sales
    Route::apiResource('sales', SaleController::class);
    Route::post('/sales/{sale}/payments', [SaleController::class, 'addPayment']);
    Route::post('/sales/{sale}/complete', [SaleController::class, 'complete']);
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel']);
    Route::get('/sales/{sale}/receipt', [SaleController::class, 'receipt']);
    Route::post('/sales/{sale}/send-receipt', [SaleController::class, 'sendReceipt']);
    Route::post('/sales/pos', [SaleController::class, 'createPOS']);

    // Sale Returns
    Route::post('/sales/{sale}/returns', [SaleController::class, 'createReturn']);
    Route::get('/sale-returns', [SaleController::class, 'returns']);

    // TODO: Implement QuotationController
    // Quotations
    // Route::apiResource('quotations', \App\Http\Controllers\Api\QuotationController::class);
    // Route::post('/quotations/{quotation}/convert', [\App\Http\Controllers\Api\QuotationController::class, 'convertToSale']);
    // Route::post('/quotations/{quotation}/send', [\App\Http\Controllers\Api\QuotationController::class, 'sendEmail']);

    // Customers
    Route::apiResource('customers', CustomerController::class);
    Route::get('/customers/{customer}/sales', [CustomerController::class, 'sales']);
    Route::get('/customers/{customer}/statements', [CustomerController::class, 'statement']);
    Route::post('/customers/{customer}/credit-limit', [CustomerController::class, 'updateCreditLimit']);
    Route::post('/customers/import', [CustomerController::class, 'import']);

    // TODO: Implement SupplierController
    // Suppliers
    // Route::apiResource('suppliers', SupplierController::class);
    // Route::get('/suppliers/{supplier}/purchases', [SupplierController::class, 'purchases']);
    // Route::get('/suppliers/{supplier}/statements', [SupplierController::class, 'statement']);
    // Route::post('/suppliers/{supplier}/ratings', [SupplierController::class, 'rate']);

    // TODO: Implement PurchaseOrderController
    // Purchase Orders
    // Route::apiResource('purchase-orders', PurchaseOrderController::class);
    // Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
    // Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
    // Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);

    // TODO: Implement GrnController
    // Goods Received Notes
    // Route::apiResource('grns', \App\Http\Controllers\Api\GrnController::class);
    // Route::post('/grns/{grn}/approve', [\App\Http\Controllers\Api\GrnController::class, 'approve']);

    // TODO: Implement InvoiceController
    // Invoices
    // Route::apiResource('invoices', InvoiceController::class);
    // Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send']);
    // Route::post('/invoices/{invoice}/record-payment', [InvoiceController::class, 'recordPayment']);
    // Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);

    // Inventory Management
    Route::get('/inventory/stock-levels', [InventoryController::class, 'stockLevels']);
    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock']);
    Route::get('/inventory/stock-value', [InventoryController::class, 'stockValue']);
    Route::post('/inventory/stock-transfer', [InventoryController::class, 'transfer']);
    Route::post('/inventory/stock-adjustment', [InventoryController::class, 'adjustment']);
    Route::get('/inventory/movements', [InventoryController::class, 'movements']);
    Route::get('/inventory/locations', [InventoryController::class, 'locations']);

    // Stock Alerts
    Route::get('/stock-alerts', [InventoryController::class, 'alerts']);
    Route::put('/stock-alerts/{alert}/acknowledge', [InventoryController::class, 'acknowledgeAlert']);

    // TODO: Implement ExpenseController
    // Expenses
    // Route::apiResource('expenses', ExpenseController::class);
    // Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve']);
    // Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject']);

    // TODO: Implement AccountingController
    // Accounting
    // Route::prefix('accounting')->group(function () {
    //     Route::get('/accounts', [\App\Http\Controllers\Api\AccountingController::class, 'chartOfAccounts']);
    //     Route::post('/journal-entries', [\App\Http\Controllers\Api\AccountingController::class, 'createJournalEntry']);
    //     Route::get('/trial-balance', [\App\Http\Controllers\Api\AccountingController::class, 'trialBalance']);
    //     Route::get('/balance-sheet', [\App\Http\Controllers\Api\AccountingController::class, 'balanceSheet']);
    //     Route::get('/income-statement', [\App\Http\Controllers\Api\AccountingController::class, 'incomeStatement']);
    //     Route::get('/cash-flow', [\App\Http\Controllers\Api\AccountingController::class, 'cashFlow']);
    // });

    // M-Pesa Integration
    Route::prefix('mpesa')->group(function () {
        Route::post('/stk-push', [MpesaController::class, 'stkPush']);
        Route::post('/b2c', [MpesaController::class, 'b2c']);
        Route::get('/transactions', [MpesaController::class, 'transactions']);
        Route::post('/reconcile', [MpesaController::class, 'reconcile']);
    });

    // Subscription Management
    Route::prefix('subscriptions')->group(function () {
        Route::get('/current', [\App\Http\Controllers\Api\SubscriptionController::class, 'current']);
        Route::post('/subscribe', [\App\Http\Controllers\Api\SubscriptionController::class, 'subscribe']);
        Route::post('/cancel', [\App\Http\Controllers\Api\SubscriptionController::class, 'cancel']);
        Route::post('/resume', [\App\Http\Controllers\Api\SubscriptionController::class, 'resume']);
        Route::post('/change-plan', [\App\Http\Controllers\Api\SubscriptionController::class, 'changePlan']);
        Route::get('/invoices', [\App\Http\Controllers\Api\SubscriptionController::class, 'invoices']);
        Route::get('/invoices/{id}', [\App\Http\Controllers\Api\SubscriptionController::class, 'invoice']);
        Route::post('/invoices/{id}/pay', [\App\Http\Controllers\Api\SubscriptionController::class, 'payInvoice']);
    });

    // WhatsApp Integration
    Route::prefix('whatsapp')->group(function () {
        Route::post('/send', [\App\Http\Controllers\Api\WhatsAppController::class, 'sendMessage']);
        Route::post('/order-confirmation', [\App\Http\Controllers\Api\WhatsAppController::class, 'sendOrderConfirmation']);
        Route::post('/payment-reminder', [\App\Http\Controllers\Api\WhatsAppController::class, 'sendPaymentReminder']);
        Route::post('/daily-snapshot', [\App\Http\Controllers\Api\WhatsAppController::class, 'sendDailySnapshot']);
        Route::get('/balance', [MpesaController::class, 'accountBalance']);
    });

    // Industry Templates
    Route::prefix('templates')->group(function () {
        Route::post('/apply', [\App\Http\Controllers\Api\TemplateController::class, 'apply']);
        Route::get('/{slug}/checklist', [\App\Http\Controllers\Api\TemplateController::class, 'checklist']);
    });

    // TODO: Implement KraController
    // KRA Tax Compliance
    // Route::prefix('kra')->group(function () {
    //     Route::get('/vat-return', [\App\Http\Controllers\Api\KraController::class, 'vatReturn']);
    //     Route::post('/vat-return/submit', [\App\Http\Controllers\Api\KraController::class, 'submitVatReturn']);
    //     Route::get('/tax-records', [\App\Http\Controllers\Api\KraController::class, 'taxRecords']);
    // });

    // TODO: Implement EmployeeController
    // Employees
    // Route::apiResource('employees', EmployeeController::class);
    // Route::post('/employees/{employee}/activate', [EmployeeController::class, 'activate']);
    // Route::post('/employees/{employee}/deactivate', [EmployeeController::class, 'deactivate']);

    // TODO: Implement AttendanceController
    // Attendance
    // Route::post('/attendance/check-in', [\App\Http\Controllers\Api\AttendanceController::class, 'checkIn']);
    // Route::post('/attendance/check-out', [\App\Http\Controllers\Api\AttendanceController::class, 'checkOut']);
    // Route::get('/attendance', [\App\Http\Controllers\Api\AttendanceController::class, 'index']);
    // Route::get('/attendance/summary', [\App\Http\Controllers\Api\AttendanceController::class, 'summary']);

    // TODO: Implement LeaveController
    // Leave Management
    // Route::apiResource('leave-applications', \App\Http\Controllers\Api\LeaveController::class);
    // Route::post('/leave-applications/{leave}/approve', [\App\Http\Controllers\Api\LeaveController::class, 'approve']);
    // Route::post('/leave-applications/{leave}/reject', [\App\Http\Controllers\Api\LeaveController::class, 'reject']);
    // Route::get('/leave-balances', [\App\Http\Controllers\Api\LeaveController::class, 'balances']);

    // TODO: Implement PayrollController
    // Payroll
    // Route::get('/payroll/periods', [PayrollController::class, 'periods']);
    // Route::post('/payroll/run', [PayrollController::class, 'run']);
    // Route::get('/payroll/{period}/payslips', [PayrollController::class, 'payslips']);
    // Route::get('/payroll/payslips/{payslip}', [PayrollController::class, 'showPayslip']);
    // Route::post('/payroll/payslips/{payslip}/send', [PayrollController::class, 'sendPayslip']);

    // TODO: Implement LeadController
    // CRM - Leads
    // Route::apiResource('leads', LeadController::class);
    // Route::post('/leads/{lead}/convert', [LeadController::class, 'convertToCustomer']);
    // Route::post('/leads/{lead}/activities', [LeadController::class, 'addActivity']);
    // Route::put('/leads/{lead}/stage', [LeadController::class, 'updateStage']);

    // TODO: Implement OpportunityController
    // CRM - Opportunities
    // Route::apiResource('opportunities', \App\Http\Controllers\Api\OpportunityController::class);
    // Route::post('/opportunities/{opportunity}/win', [\App\Http\Controllers\Api\OpportunityController::class, 'markWon']);
    // Route::post('/opportunities/{opportunity}/lose', [\App\Http\Controllers\Api\OpportunityController::class, 'markLost']);

    // TODO: Implement TaskController
    // TODO: Implement ProjectController
    // Tasks & Projects
    // Route::apiResource('tasks', TaskController::class);
    // Route::post('/tasks/{task}/complete', [TaskController::class, 'complete']);
    // Route::apiResource('projects', \App\Http\Controllers\Api\ProjectController::class);
    // Route::get('/projects/{project}/tasks', [\App\Http\Controllers\Api\ProjectController::class, 'tasks']);

    // Reports
    Route::prefix('reports')->group(function () {
        // Sales Reports
        Route::get('/sales-summary', [ReportController::class, 'salesSummary']);
        Route::get('/sales-by-product', [ReportController::class, 'salesByProduct']);
        Route::get('/sales-by-customer', [ReportController::class, 'salesByCustomer']);
        Route::get('/sales-by-location', [ReportController::class, 'salesByLocation']);
        Route::get('/daily-sales', [ReportController::class, 'dailySales']);

        // Inventory Reports
        Route::get('/inventory-valuation', [ReportController::class, 'inventoryValuation']);
        Route::get('/stock-movement', [ReportController::class, 'stockMovement']);
        Route::get('/slow-moving-items', [ReportController::class, 'slowMovingItems']);
        Route::get('/fast-moving-items', [ReportController::class, 'fastMovingItems']);

        // Financial Reports
        Route::get('/profit-loss', [ReportController::class, 'profitLoss']);
        Route::get('/cash-flow-statement', [ReportController::class, 'cashFlowStatement']);
        Route::get('/aging-report', [ReportController::class, 'agingReport']);
        Route::get('/expense-analysis', [ReportController::class, 'expenseAnalysis']);

        // Tax Reports
        Route::get('/vat-report', [ReportController::class, 'vatReport']);
        Route::get('/withholding-tax', [ReportController::class, 'withholdingTax']);

        // HR Reports
        Route::get('/attendance-report', [ReportController::class, 'attendanceReport']);
        Route::get('/payroll-summary', [ReportController::class, 'payrollSummary']);
        Route::get('/leave-report', [ReportController::class, 'leaveReport']);

        // Dashboard
        Route::get('/dashboard', [ReportController::class, 'dashboard']);
        Route::get('/kpi', [ReportController::class, 'kpi']);
    });

    // TODO: Implement SettingController
    // Settings
    // Route::prefix('settings')->group(function () {
    //     Route::get('/', [\App\Http\Controllers\Api\SettingController::class, 'index']);
    //     Route::put('/', [\App\Http\Controllers\Api\SettingController::class, 'update']);
    //     Route::get('/locations', [\App\Http\Controllers\Api\SettingController::class, 'locations']);
    //     Route::get('/departments', [\App\Http\Controllers\Api\SettingController::class, 'departments']);
    //     Route::get('/payment-methods', [\App\Http\Controllers\Api\SettingController::class, 'paymentMethods']);
    // });

    // TODO: Implement NotificationController
    // Notifications
    // Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    // Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    // Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
});
