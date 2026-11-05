<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CheckInOutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\HotelServiceController;
use App\Http\Controllers\HotelSettingController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RazorpayController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomMaintenanceController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StaffController;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health Check
Route::get('/health', function () {
    return ApiResponse::success([
        'status' => 'healthy',
        'service' => config('app.name', 'Hotel Management System'),
    ], 'Hotel Management API is running');
});

// CSRF Cookie endpoint alias under /api (in addition to default /sanctum/csrf-cookie)
Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);

// Public Hotel Settings (Branding, Name, Times, Contact Info for UI and Login Screen)
Route::get('/settings', [HotelSettingController::class, 'index']);

// Razorpay Webhook (Raw body HMAC SHA256 signature verification - public endpoint)
Route::post('/razorpay/webhook', [RazorpayController::class, 'webhook']);

// Authentication Endpoints
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::middleware('active')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
        });
    });
});

// Role-Protected Verification Test Endpoints
Route::middleware(['auth:sanctum', 'active'])->prefix('test')->group(function () {
    Route::get('/owner-only', function () {
        return ApiResponse::success(null, 'Authorized: OWNER role verified.');
    })->middleware('role:OWNER');

    Route::get('/admin-or-owner', function () {
        return ApiResponse::success(null, 'Authorized: ADMIN or OWNER role verified.');
    })->middleware('role:OWNER,ADMIN');

    Route::get('/receptionist-only', function () {
        return ApiResponse::success(null, 'Authorized: RECEPTIONIST role verified.');
    })->middleware('role:RECEPTIONIST');
});

/*
|--------------------------------------------------------------------------
| Batch 2: Hotel Operations & Dashboard API Endpoints
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Room Categories / Types
    Route::get('/room-types', [RoomTypeController::class, 'index']);
    Route::get('/room-types/{roomType}', [RoomTypeController::class, 'show']);
    Route::middleware('role:OWNER,ADMIN')->group(function () {
        Route::post('/room-types', [RoomTypeController::class, 'store']);
        Route::put('/room-types/{roomType}', [RoomTypeController::class, 'update']);
        Route::delete('/room-types/{roomType}', [RoomTypeController::class, 'destroy']);
    });

    // Rooms & Inventory
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::get('/rooms/available', [RoomController::class, 'available']);
    Route::get('/rooms/{room}', [RoomController::class, 'show']);
    Route::middleware('role:OWNER,ADMIN')->group(function () {
        Route::post('/rooms', [RoomController::class, 'store']);
        Route::put('/rooms/{room}', [RoomController::class, 'update']);
        Route::patch('/rooms/{room}/toggle-active', [RoomController::class, 'toggleActive']);
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);
    });
    Route::patch('/rooms/{room}/status', [RoomController::class, 'updateStatus'])
        ->middleware('role:OWNER,ADMIN,RECEPTIONIST,HOUSEKEEPING');

    // Maintenance
    Route::get('/room-maintenance', [RoomMaintenanceController::class, 'index']);
    Route::middleware('role:OWNER,ADMIN,RECEPTIONIST,HOUSEKEEPING')->group(function () {
        Route::post('/room-maintenance', [RoomMaintenanceController::class, 'store']);
        Route::post('/room-maintenance/{roomMaintenance}/resolve', [RoomMaintenanceController::class, 'resolve']);
    });

    // Guests
    Route::middleware('role:OWNER,ADMIN,RECEPTIONIST')->group(function () {
        Route::get('/guests', [GuestController::class, 'index']);
        Route::post('/guests', [GuestController::class, 'store']);
        Route::get('/guests/{guest}', [GuestController::class, 'show']);
        Route::put('/guests/{guest}', [GuestController::class, 'update']);
    });

    // Bookings
    Route::middleware('role:OWNER,ADMIN,RECEPTIONIST')->group(function () {
        Route::get('/bookings/calculate-rates', [BookingController::class, 'calculateRates']);
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    });

    // Check-In / Check-Out
    Route::middleware('role:OWNER,ADMIN,RECEPTIONIST')->group(function () {
        Route::get('/check-in-out/arrivals', [CheckInOutController::class, 'todayArrivals']);
        Route::get('/check-in-out/departures', [CheckInOutController::class, 'todayDepartures']);
        Route::post('/check-in-out/{booking}/check-in', [CheckInOutController::class, 'checkIn']);
        Route::post('/check-in-out/{booking}/check-out', [CheckInOutController::class, 'checkOut']);
    });

    // Room Cleaning Handover
    Route::post('/check-in-out/rooms/{room}/cleaned', [CheckInOutController::class, 'markRoomCleaned'])
        ->middleware('role:OWNER,ADMIN,RECEPTIONIST,HOUSEKEEPING');

    /*
    |--------------------------------------------------------------------------
    | Batch 3: Services, Billing, Payments, Razorpay, Invoices, Expenses
    |--------------------------------------------------------------------------
    */

    // Hotel Services Catalog
    Route::get('/services', [HotelServiceController::class, 'index']);
    Route::middleware('role:OWNER,ADMIN')->group(function () {
        Route::post('/services', [HotelServiceController::class, 'store']);
        Route::put('/services/{service}', [HotelServiceController::class, 'update']);
        Route::patch('/services/{service}/toggle-active', [HotelServiceController::class, 'toggleActive']);
    });

    // Booking Services (Attach/Remove services to reservation)
    Route::middleware('role:OWNER,ADMIN,RECEPTIONIST')->group(function () {
        Route::post('/bookings/{booking}/services', [HotelServiceController::class, 'addToBooking']);
        Route::delete('/booking-services/{bookingService}', [HotelServiceController::class, 'removeFromBooking']);
    });

    // Payments & Billing Summaries
    Route::middleware('role:OWNER,ADMIN,RECEPTIONIST,ACCOUNTANT')->group(function () {
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/bookings/{booking}/payments', [PaymentController::class, 'store']);
        Route::get('/bookings/{booking}/billing', [PaymentController::class, 'billingSummary']);

        // Razorpay Online Payment Checkout
        Route::post('/bookings/{booking}/razorpay/create-order', [RazorpayController::class, 'createOrder']);
        Route::post('/razorpay/verify-payment', [RazorpayController::class, 'verifyPayment']);
    });

    // Refunds
    Route::middleware('role:OWNER,ADMIN,ACCOUNTANT')->group(function () {
        Route::get('/refunds', [RefundController::class, 'index']);
        Route::post('/payments/{payment}/refunds', [RefundController::class, 'store']);
    });

    // Invoices
    Route::middleware('role:OWNER,ADMIN,RECEPTIONIST,ACCOUNTANT')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::post('/bookings/{booking}/invoice', [InvoiceController::class, 'generateForBooking']);
        Route::get('/invoices/{invoice}/printable', [InvoiceController::class, 'printable']);
    });
    Route::middleware('role:OWNER,ADMIN,ACCOUNTANT')->group(function () {
        Route::post('/invoices/{invoice}/adjustments', [InvoiceController::class, 'addAdjustment']);
    });

    // Expenses & Categories
    Route::middleware('role:OWNER,ADMIN,ACCOUNTANT')->group(function () {
        Route::get('/expense-categories', [ExpenseController::class, 'categories']);
        Route::get('/expenses', [ExpenseController::class, 'index']);
        Route::post('/expenses', [ExpenseController::class, 'store']);
    });
    Route::middleware('role:OWNER,ADMIN')->group(function () {
        Route::post('/expense-categories', [ExpenseController::class, 'storeCategory']);
    });

    // Cash Register & Daily Reconciliation
    Route::middleware('role:OWNER,ADMIN,ACCOUNTANT')->group(function () {
        Route::get('/cash-register/summary', [CashRegisterController::class, 'dailySummary']);
        Route::post('/cash-register/open', [CashRegisterController::class, 'open']);
        Route::post('/cash-register/reconcile', [CashRegisterController::class, 'reconcile']);
    });

    /*
    |--------------------------------------------------------------------------
    | Batch 4: Reports, Settings, Staff Management, Notifications, Search
    |--------------------------------------------------------------------------
    */

    // Global Search across Bookings, Guests, and Rooms
    Route::get('/search', [SearchController::class, 'search']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    // Hotel Settings (Restricted to Owner/Admin for modifications)
    Route::put('/settings', [HotelSettingController::class, 'update'])->middleware('role:OWNER,ADMIN');

    // Roles (for staff assignment)
    Route::get('/roles', [RoleController::class, 'index'])->middleware('role:OWNER,ADMIN');

    // Staff Account Management
    Route::middleware('role:OWNER,ADMIN')->group(function () {
        Route::get('/staff', [StaffController::class, 'index']);
        Route::post('/staff', [StaffController::class, 'store']);
        Route::get('/staff/audit-logs', [StaffController::class, 'auditLogs']);
        Route::get('/staff/{user}', [StaffController::class, 'show']);
        Route::put('/staff/{user}', [StaffController::class, 'update']);
        Route::patch('/staff/{user}/toggle-status', [StaffController::class, 'toggleStatus']);
    });

    // Reports Module
    Route::prefix('reports')->group(function () {
        // Operational Reports (front desk, accountants, managers, owners)
        Route::middleware('role:OWNER,ADMIN,RECEPTIONIST,ACCOUNTANT')->group(function () {
            Route::get('/occupancy', [ReportController::class, 'occupancy']);
            Route::get('/bookings', [ReportController::class, 'bookings']);
            Route::get('/check-in-out', [ReportController::class, 'checkInOut']);
            Route::get('/services', [ReportController::class, 'services']);
        });

        // Financial & Management Reports (accountant, admin, owner)
        Route::middleware('role:OWNER,ADMIN,ACCOUNTANT')->group(function () {
            Route::get('/summary', [ReportController::class, 'summary']);
            Route::get('/payments', [ReportController::class, 'payments']);
            Route::get('/expenses', [ReportController::class, 'expenses']);
            Route::get('/cash-register', [ReportController::class, 'cashRegister']);
            Route::get('/export', [ReportController::class, 'exportCsv']);
        });
    });
});
