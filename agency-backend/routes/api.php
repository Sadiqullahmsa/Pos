<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\ConnectionController;
use App\Http\Controllers\API\CylinderController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\DeliveryController;
use App\Http\Controllers\API\DriverController;
use App\Http\Controllers\API\VehicleController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\SupplierController;
use App\Http\Controllers\API\ComplaintController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Authentication routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Public routes
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()]);
});

// Protected API routes
Route::middleware(['auth:sanctum'])->group(function () {
    
    // Customer management
    Route::apiResource('customers', CustomerController::class);
    Route::post('customers/{customer}/upload-documents', [CustomerController::class, 'uploadDocuments']);
    Route::patch('customers/{customer}/kyc-status', [CustomerController::class, 'updateKycStatus']);
    Route::get('customers/{customer}/orders', [CustomerController::class, 'getOrders']);
    Route::get('customers/{customer}/connections', [CustomerController::class, 'getConnections']);
    
    // Connection management
    Route::apiResource('connections', ConnectionController::class);
    Route::patch('connections/{connection}/status', [ConnectionController::class, 'updateStatus']);
    Route::post('connections/{connection}/quota-reset', [ConnectionController::class, 'resetQuota']);
    
    // Cylinder management
    Route::apiResource('cylinders', CylinderController::class);
    Route::patch('cylinders/{cylinder}/status', [CylinderController::class, 'updateStatus']);
    Route::post('cylinders/{cylinder}/maintenance', [CylinderController::class, 'addMaintenance']);
    Route::get('cylinders/tracking/{serialNumber}', [CylinderController::class, 'trackCylinder']);
    
    // Order management
    Route::apiResource('orders', OrderController::class);
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancelOrder']);
    Route::post('orders/{order}/confirm', [OrderController::class, 'confirmOrder']);
    Route::get('orders/{order}/track', [OrderController::class, 'trackOrder']);
    
    // Delivery management
    Route::apiResource('deliveries', DeliveryController::class);
    Route::patch('deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus']);
    Route::post('deliveries/{delivery}/complete', [DeliveryController::class, 'completeDelivery']);
    Route::post('deliveries/{delivery}/failed', [DeliveryController::class, 'markFailed']);
    Route::get('deliveries/{delivery}/track', [DeliveryController::class, 'trackDelivery']);
    
    // Driver management
    Route::apiResource('drivers', DriverController::class);
    Route::patch('drivers/{driver}/availability', [DriverController::class, 'updateAvailability']);
    Route::get('drivers/{driver}/deliveries', [DriverController::class, 'getDeliveries']);
    Route::get('drivers/available', [DriverController::class, 'getAvailableDrivers']);
    
    // Vehicle management
    Route::apiResource('vehicles', VehicleController::class);
    Route::patch('vehicles/{vehicle}/status', [VehicleController::class, 'updateStatus']);
    Route::post('vehicles/{vehicle}/maintenance', [VehicleController::class, 'addMaintenance']);
    Route::get('vehicles/available', [VehicleController::class, 'getAvailableVehicles']);
    
    // Payment management
    Route::apiResource('payments', PaymentController::class);
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refundPayment']);
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'getReceipt']);
    
    // Supplier management
    Route::apiResource('suppliers', SupplierController::class);
    Route::patch('suppliers/{supplier}/status', [SupplierController::class, 'updateStatus']);
    Route::get('suppliers/{supplier}/orders', [SupplierController::class, 'getOrders']);
    
    // Complaint management
    Route::apiResource('complaints', ComplaintController::class);
    Route::patch('complaints/{complaint}/status', [ComplaintController::class, 'updateStatus']);
    Route::post('complaints/{complaint}/resolve', [ComplaintController::class, 'resolveComplaint']);
    Route::post('complaints/{complaint}/escalate', [ComplaintController::class, 'escalateComplaint']);
    
    // Dashboard and Analytics
    Route::get('dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('dashboard/recent-orders', [DashboardController::class, 'getRecentOrders']);
    Route::get('dashboard/pending-deliveries', [DashboardController::class, 'getPendingDeliveries']);
    Route::get('dashboard/low-stock', [DashboardController::class, 'getLowStockItems']);
    
    // Reports
    Route::get('reports/sales', [ReportController::class, 'salesReport']);
    Route::get('reports/inventory', [ReportController::class, 'inventoryReport']);
    Route::get('reports/customers', [ReportController::class, 'customerReport']);
    Route::get('reports/deliveries', [ReportController::class, 'deliveryReport']);
    
    // Settings
    Route::get('settings', [SettingsController::class, 'index']);
    Route::post('settings', [SettingsController::class, 'update']);
});

// File upload routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('upload/image', [FileUploadController::class, 'uploadImage']);
    Route::post('upload/document', [FileUploadController::class, 'uploadDocument']);
});