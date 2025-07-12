<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\CylinderController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ExternalApiController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\EmergencyController;
use App\Http\Controllers\ProgressController;
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

// Authentication Routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Public routes
Route::get('/settings/public', [SettingsController::class, 'getPublicSettings']);
Route::get('/currencies/active', [CurrencyController::class, 'getActiveCurrencies']);

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    
    // Auth user routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'updatePassword']);

    // User Management Routes
    Route::apiResource('users', UserController::class);
    Route::put('/users/{user}/status', [UserController::class, 'updateStatus']);
    Route::put('/users/{user}/role', [UserController::class, 'updateRole']);
    Route::get('/users/{user}/permissions', [UserController::class, 'getPermissions']);
    Route::put('/users/{user}/permissions', [UserController::class, 'updatePermissions']);

    // Customer Management Routes
    Route::apiResource('customers', CustomerController::class);
    Route::get('/customers/{customer}/connections', [CustomerController::class, 'getConnections']);
    Route::get('/customers/{customer}/orders', [CustomerController::class, 'getOrders']);
    Route::get('/customers/{customer}/payments', [CustomerController::class, 'getPayments']);
    Route::get('/customers/{customer}/complaints', [CustomerController::class, 'getComplaints']);
    Route::put('/customers/{customer}/status', [CustomerController::class, 'updateStatus']);

    // Connection Management Routes
    Route::apiResource('connections', ConnectionController::class);
    Route::put('/connections/{connection}/status', [ConnectionController::class, 'updateStatus']);
    Route::post('/connections/{connection}/verify', [ConnectionController::class, 'verifyConnection']);
    Route::get('/connections/{connection}/usage', [ConnectionController::class, 'getUsageHistory']);

    // Cylinder Management Routes
    Route::apiResource('cylinders', CylinderController::class);
    Route::put('/cylinders/{cylinder}/status', [CylinderController::class, 'updateStatus']);
    Route::post('/cylinders/{cylinder}/assign', [CylinderController::class, 'assignToCustomer']);
    Route::post('/cylinders/{cylinder}/return', [CylinderController::class, 'returnCylinder']);
    Route::get('/cylinders/{cylinder}/history', [CylinderController::class, 'getHistory']);

    // Order Management Routes
    Route::apiResource('orders', OrderController::class);
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{order}/confirm', [OrderController::class, 'confirmOrder']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancelOrder']);
    Route::get('/orders/{order}/tracking', [OrderController::class, 'getTracking']);

    // Delivery Management Routes
    Route::apiResource('deliveries', DeliveryController::class);
    Route::put('/deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus']);
    Route::post('/deliveries/{delivery}/start', [DeliveryController::class, 'startDelivery']);
    Route::post('/deliveries/{delivery}/complete', [DeliveryController::class, 'completeDelivery']);
    Route::put('/deliveries/{delivery}/location', [DeliveryController::class, 'updateLocation']);

    // Vehicle Management Routes
    Route::apiResource('vehicles', VehicleController::class);
    Route::put('/vehicles/{vehicle}/status', [VehicleController::class, 'updateStatus']);
    Route::get('/vehicles/{vehicle}/maintenance', [VehicleController::class, 'getMaintenanceHistory']);
    Route::post('/vehicles/{vehicle}/maintenance', [VehicleController::class, 'addMaintenance']);

    // Driver Management Routes
    Route::apiResource('drivers', DriverController::class);
    Route::put('/drivers/{driver}/status', [DriverController::class, 'updateStatus']);
    Route::get('/drivers/{driver}/deliveries', [DriverController::class, 'getDeliveries']);
    Route::get('/drivers/{driver}/performance', [DriverController::class, 'getPerformance']);

    // Payment Management Routes
    Route::apiResource('payments', PaymentController::class);
    Route::post('/payments/{payment}/verify', [PaymentController::class, 'verifyPayment']);
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refundPayment']);
    Route::get('/payments/methods', [PaymentController::class, 'getPaymentMethods']);

    // Complaint Management Routes
    Route::apiResource('complaints', ComplaintController::class);
    Route::put('/complaints/{complaint}/status', [ComplaintController::class, 'updateStatus']);
    Route::post('/complaints/{complaint}/resolve', [ComplaintController::class, 'resolveComplaint']);
    Route::post('/complaints/{complaint}/escalate', [ComplaintController::class, 'escalateComplaint']);

    // ========================
    // ADVANCED FEATURES ROUTES
    // ========================

    // Advanced Settings Management Routes
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index']);
        Route::get('/category/{category}', [SettingsController::class, 'getByCategory']);
        Route::put('/{key}', [SettingsController::class, 'update']);
        Route::put('/', [SettingsController::class, 'bulkUpdate'])->name('settings.bulk-update');
        Route::post('/reset', [SettingsController::class, 'resetToDefaults']);
        Route::get('/export', [SettingsController::class, 'exportConfig']);
        Route::post('/import', [SettingsController::class, 'importConfig']);
        Route::get('/system-info', [SettingsController::class, 'getSystemInfo']);
        Route::post('/test-system', [SettingsController::class, 'testSystem']);
        Route::post('/clear-cache', [SettingsController::class, 'clearCache']);
        Route::post('/restart-services', [SettingsController::class, 'restartServices']);
    });

    // External API Management Routes
    Route::prefix('external-apis')->group(function () {
        Route::get('/', [ExternalApiController::class, 'index']);
        Route::post('/', [ExternalApiController::class, 'store']);
        Route::get('/{api}', [ExternalApiController::class, 'show']);
        Route::put('/{api}', [ExternalApiController::class, 'update']);
        Route::delete('/{api}', [ExternalApiController::class, 'destroy']);
        Route::post('/{api}/test', [ExternalApiController::class, 'testConnection']);
        Route::post('/{api}/health-check', [ExternalApiController::class, 'performHealthCheck']);
        Route::get('/{api}/statistics', [ExternalApiController::class, 'getStatistics']);
        Route::put('/{api}/status', [ExternalApiController::class, 'updateStatus']);
        Route::get('/providers/config', [ExternalApiController::class, 'getProviderConfigs']);
        Route::post('/initialize-defaults', [ExternalApiController::class, 'initializeDefaults']);
    });

    // Progress Tracking Routes
    Route::prefix('progress')->group(function () {
        Route::get('/', [ProgressController::class, 'index']);
        Route::post('/', [ProgressController::class, 'create']);
        Route::get('/{trackerId}', [ProgressController::class, 'show']);
        Route::put('/{trackerId}', [ProgressController::class, 'update']);
        Route::delete('/{trackerId}', [ProgressController::class, 'delete']);
        Route::post('/batch', [ProgressController::class, 'createBatch']);
        Route::put('/batch/{batchId}', [ProgressController::class, 'updateBatch']);
        Route::get('/statistics', [ProgressController::class, 'statistics']);
        Route::post('/cleanup', [ProgressController::class, 'cleanup']);
        Route::get('/stream', [ProgressController::class, 'stream']); // Server-Sent Events
    });

    // Multi-Currency Management Routes
    Route::prefix('currencies')->group(function () {
        Route::get('/', [CurrencyController::class, 'index']);
        Route::post('/', [CurrencyController::class, 'store']);
        Route::get('/{currency}', [CurrencyController::class, 'show']);
        Route::put('/{currency}', [CurrencyController::class, 'update']);
        Route::delete('/{currency}', [CurrencyController::class, 'destroy']);
        Route::put('/{currency}/status', [CurrencyController::class, 'updateStatus']);
        Route::post('/{currency}/set-base', [CurrencyController::class, 'setBaseCurrency']);
        Route::post('/update-rates', [CurrencyController::class, 'updateExchangeRates']);
        Route::get('/rates/history', [CurrencyController::class, 'getRateHistory']);
        Route::post('/convert', [CurrencyController::class, 'convertAmount']);
    });

    // Advanced Invoice Management Routes
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::post('/', [InvoiceController::class, 'store']);
        Route::get('/{invoice}', [InvoiceController::class, 'show']);
        Route::put('/{invoice}', [InvoiceController::class, 'update']);
        Route::delete('/{invoice}', [InvoiceController::class, 'destroy']);
        Route::put('/{invoice}/status', [InvoiceController::class, 'updateStatus']);
        Route::post('/{invoice}/send', [InvoiceController::class, 'sendInvoice']);
        Route::post('/{invoice}/payment', [InvoiceController::class, 'recordPayment']);
        Route::get('/{invoice}/pdf', [InvoiceController::class, 'generatePdf']);
        Route::get('/{invoice}/thermal', [InvoiceController::class, 'generateThermalPrint']);
        Route::post('/{invoice}/duplicate', [InvoiceController::class, 'duplicateInvoice']);
        Route::get('/templates', [InvoiceController::class, 'getTemplates']);
        Route::post('/templates', [InvoiceController::class, 'createTemplate']);
        Route::get('/numbering/next', [InvoiceController::class, 'getNextInvoiceNumber']);
        Route::post('/bulk-generate', [InvoiceController::class, 'bulkGenerate']);
        Route::get('/analytics', [InvoiceController::class, 'getAnalytics']);
    });

    // Emergency Management Routes
    Route::prefix('emergencies')->group(function () {
        Route::get('/', [EmergencyController::class, 'index']);
        Route::post('/', [EmergencyController::class, 'store']);
        Route::get('/{emergency}', [EmergencyController::class, 'show']);
        Route::put('/{emergency}', [EmergencyController::class, 'update']);
        Route::delete('/{emergency}', [EmergencyController::class, 'destroy']);
        Route::put('/{emergency}/status', [EmergencyController::class, 'updateStatus']);
        Route::post('/{emergency}/acknowledge', [EmergencyController::class, 'acknowledge']);
        Route::post('/{emergency}/escalate', [EmergencyController::class, 'escalate']);
        Route::post('/{emergency}/resolve', [EmergencyController::class, 'resolve']);
        Route::post('/{emergency}/close', [EmergencyController::class, 'close']);
        Route::get('/{emergency}/timeline', [EmergencyController::class, 'getTimeline']);
        Route::post('/{emergency}/evidence', [EmergencyController::class, 'addEvidence']);
        Route::get('/types', [EmergencyController::class, 'getEmergencyTypes']);
        Route::get('/statistics', [EmergencyController::class, 'getStatistics']);
        Route::post('/drill', [EmergencyController::class, 'createDrill']);
        Route::get('/response-teams', [EmergencyController::class, 'getResponseTeams']);
        Route::post('/notify-authorities', [EmergencyController::class, 'notifyAuthorities']);
    });

    // ========================
    // ANALYTICS & REPORTING ROUTES
    // ========================

    // Advanced Analytics Routes
    Route::prefix('analytics')->group(function () {
        Route::get('/dashboard', [AnalyticsController::class, 'getDashboardData']);
        Route::get('/sales', [AnalyticsController::class, 'getSalesAnalytics']);
        Route::get('/customers', [AnalyticsController::class, 'getCustomerAnalytics']);
        Route::get('/inventory', [AnalyticsController::class, 'getInventoryAnalytics']);
        Route::get('/delivery', [AnalyticsController::class, 'getDeliveryAnalytics']);
        Route::get('/financial', [AnalyticsController::class, 'getFinancialAnalytics']);
        Route::get('/forecasting', [AnalyticsController::class, 'getForecasting']);
        Route::get('/anomalies', [AnalyticsController::class, 'getAnomalies']);
        Route::post('/generate', [AnalyticsController::class, 'generateAnalytics']);
        Route::get('/insights', [AnalyticsController::class, 'getBusinessInsights']);
    });

    // IoT Device Management Routes
    Route::prefix('iot-devices')->group(function () {
        Route::get('/', [IoTDeviceController::class, 'index']);
        Route::post('/', [IoTDeviceController::class, 'store']);
        Route::get('/{device}', [IoTDeviceController::class, 'show']);
        Route::put('/{device}', [IoTDeviceController::class, 'update']);
        Route::delete('/{device}', [IoTDeviceController::class, 'destroy']);
        Route::put('/{device}/status', [IoTDeviceController::class, 'updateStatus']);
        Route::post('/{device}/data', [IoTDeviceController::class, 'receiveSensorData']);
        Route::get('/{device}/readings', [IoTDeviceController::class, 'getReadings']);
        Route::get('/{device}/alerts', [IoTDeviceController::class, 'getAlerts']);
        Route::get('/{device}/health', [IoTDeviceController::class, 'getHealthScore']);
        Route::get('/{device}/maintenance', [IoTDeviceController::class, 'getMaintenanceInsights']);
        Route::post('/{device}/maintenance', [IoTDeviceController::class, 'scheduleMaintenance']);
        Route::get('/types', [IoTDeviceController::class, 'getDeviceTypes']);
        Route::get('/statistics', [IoTDeviceController::class, 'getStatistics']);
    });

    // ChatBot & AI Routes
    Route::prefix('chatbot')->group(function () {
        Route::post('/message', [ChatBotController::class, 'processMessage']);
        Route::get('/conversations', [ChatBotController::class, 'getConversations']);
        Route::get('/conversations/{conversation}', [ChatBotController::class, 'getConversation']);
        Route::put('/conversations/{conversation}/escalate', [ChatBotController::class, 'escalateToHuman']);
        Route::post('/conversations/{conversation}/feedback', [ChatBotController::class, 'submitFeedback']);
        Route::get('/analytics', [ChatBotController::class, 'getAnalytics']);
        Route::get('/intents', [ChatBotController::class, 'getIntents']);
        Route::post('/train', [ChatBotController::class, 'trainModel']);
    });

    // Template Management Routes
    Route::prefix('templates')->group(function () {
        Route::get('/', [TemplateController::class, 'index']);
        Route::post('/', [TemplateController::class, 'store']);
        Route::get('/{template}', [TemplateController::class, 'show']);
        Route::put('/{template}', [TemplateController::class, 'update']);
        Route::delete('/{template}', [TemplateController::class, 'destroy']);
        Route::post('/{template}/preview', [TemplateController::class, 'preview']);
        Route::post('/{template}/duplicate', [TemplateController::class, 'duplicate']);
        Route::get('/types/{type}', [TemplateController::class, 'getByType']);
        Route::get('/variables/{type}', [TemplateController::class, 'getAvailableVariables']);
    });

    // Workflow & Automation Routes
    Route::prefix('workflows')->group(function () {
        Route::get('/', [WorkflowController::class, 'index']);
        Route::post('/', [WorkflowController::class, 'store']);
        Route::get('/{workflow}', [WorkflowController::class, 'show']);
        Route::put('/{workflow}', [WorkflowController::class, 'update']);
        Route::delete('/{workflow}', [WorkflowController::class, 'destroy']);
        Route::put('/{workflow}/status', [WorkflowController::class, 'updateStatus']);
        Route::post('/{workflow}/execute', [WorkflowController::class, 'execute']);
        Route::get('/{workflow}/executions', [WorkflowController::class, 'getExecutions']);
        Route::get('/{workflow}/performance', [WorkflowController::class, 'getPerformance']);
        Route::post('/test', [WorkflowController::class, 'testWorkflow']);
    });

    // Automation Rules Routes
    Route::prefix('automations')->group(function () {
        Route::get('/', [AutomationController::class, 'index']);
        Route::post('/', [AutomationController::class, 'store']);
        Route::get('/{automation}', [AutomationController::class, 'show']);
        Route::put('/{automation}', [AutomationController::class, 'update']);
        Route::delete('/{automation}', [AutomationController::class, 'destroy']);
        Route::put('/{automation}/status', [AutomationController::class, 'updateStatus']);
        Route::post('/{automation}/test', [AutomationController::class, 'testAutomation']);
        Route::get('/{automation}/history', [AutomationController::class, 'getExecutionHistory']);
        Route::get('/events/available', [AutomationController::class, 'getAvailableEvents']);
        Route::get('/actions/available', [AutomationController::class, 'getAvailableActions']);
    });

    // ========================
    // MOBILE APP ROUTES
    // ========================

    // Mobile Customer App Routes
    Route::prefix('mobile/customer')->group(function () {
        Route::get('/dashboard', [MobileCustomerController::class, 'getDashboard']);
        Route::get('/orders', [MobileCustomerController::class, 'getOrders']);
        Route::post('/orders', [MobileCustomerController::class, 'createOrder']);
        Route::get('/orders/{order}/track', [MobileCustomerController::class, 'trackOrder']);
        Route::get('/connections', [MobileCustomerController::class, 'getConnections']);
        Route::get('/payments', [MobileCustomerController::class, 'getPayments']);
        Route::post('/complaints', [MobileCustomerController::class, 'createComplaint']);
        Route::get('/notifications', [MobileCustomerController::class, 'getNotifications']);
        Route::put('/profile', [MobileCustomerController::class, 'updateProfile']);
    });

    // Mobile Driver App Routes
    Route::prefix('mobile/driver')->group(function () {
        Route::get('/dashboard', [MobileDriverController::class, 'getDashboard']);
        Route::get('/deliveries', [MobileDriverController::class, 'getDeliveries']);
        Route::get('/deliveries/today', [MobileDriverController::class, 'getTodayDeliveries']);
        Route::put('/deliveries/{delivery}/start', [MobileDriverController::class, 'startDelivery']);
        Route::put('/deliveries/{delivery}/complete', [MobileDriverController::class, 'completeDelivery']);
        Route::put('/deliveries/{delivery}/location', [MobileDriverController::class, 'updateLocation']);
        Route::get('/route/optimize', [MobileDriverController::class, 'optimizeRoute']);
        Route::get('/earnings', [MobileDriverController::class, 'getEarnings']);
        Route::post('/attendance', [MobileDriverController::class, 'markAttendance']);
    });

    // ========================
    // REPORTING ROUTES
    // ========================

    // Advanced Reporting Routes
    Route::prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index']);
        Route::post('/generate', [ReportController::class, 'generate']);
        Route::get('/templates', [ReportController::class, 'getTemplates']);
        Route::post('/schedule', [ReportController::class, 'scheduleReport']);
        Route::get('/scheduled', [ReportController::class, 'getScheduledReports']);
        Route::get('/{report}/download', [ReportController::class, 'download']);
        Route::post('/custom', [ReportController::class, 'createCustomReport']);
        Route::get('/dashboard', [ReportController::class, 'getDashboardReports']);
    });

    // ========================
    // NOTIFICATION ROUTES
    // ========================

    // Notification Management Routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/', [NotificationController::class, 'store']);
        Route::get('/{notification}', [NotificationController::class, 'show']);
        Route::put('/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{notification}', [NotificationController::class, 'destroy']);
        Route::get('/settings', [NotificationController::class, 'getSettings']);
        Route::put('/settings', [NotificationController::class, 'updateSettings']);
        Route::post('/test', [NotificationController::class, 'testNotification']);
        Route::get('/templates', [NotificationController::class, 'getTemplates']);
    });

    // ========================
    // SYSTEM ADMINISTRATION ROUTES
    // ========================

    // System Administration Routes (Super Admin only)
    Route::middleware(['role:super_admin'])->prefix('admin')->group(function () {
        Route::get('/system/status', [AdminController::class, 'getSystemStatus']);
        Route::get('/system/logs', [AdminController::class, 'getSystemLogs']);
        Route::post('/system/backup', [AdminController::class, 'createBackup']);
        Route::get('/system/backups', [AdminController::class, 'getBackups']);
        Route::post('/system/restore', [AdminController::class, 'restoreBackup']);
        Route::get('/database/optimize', [AdminController::class, 'optimizeDatabase']);
        Route::get('/performance/metrics', [AdminController::class, 'getPerformanceMetrics']);
        Route::post('/maintenance/mode', [AdminController::class, 'enableMaintenanceMode']);
        Route::delete('/maintenance/mode', [AdminController::class, 'disableMaintenanceMode']);
    });
});

// ========================
// WEBHOOK ROUTES
// ========================

// Webhook Routes (Public - no auth required)
Route::prefix('webhooks')->group(function () {
    Route::post('/payment/razorpay', [WebhookController::class, 'razorpayWebhook']);
    Route::post('/payment/paytm', [WebhookController::class, 'paytmWebhook']);
    Route::post('/sms/twilio', [WebhookController::class, 'twilioWebhook']);
    Route::post('/email/sendgrid', [WebhookController::class, 'sendgridWebhook']);
    Route::post('/iot/device-data', [WebhookController::class, 'iotDeviceData']);
    Route::post('/external/{provider}', [WebhookController::class, 'externalProviderWebhook']);
});

// ========================
// FALLBACK ROUTE
// ========================

// Catch-all route for API versioning
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found',
        'available_versions' => ['v1'],
        'documentation' => url('/api/documentation'),
    ], 404);
});