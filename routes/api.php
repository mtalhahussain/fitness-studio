<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\BiometricPushController;
use App\Http\Controllers\Api\BiometricSyncController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MembershipPlanController;
use App\Http\Controllers\Api\POSController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TrainerController;
use Illuminate\Support\Facades\Route;

// ZKTeco machine push — no Laravel auth, device authenticates via api_key
Route::prefix('biometric')->group(function () {
    Route::post('push',  [BiometricPushController::class, 'receive'])->name('biometric.push');
    Route::get('push',   [BiometricPushController::class, 'ping'])->name('biometric.ping');
    Route::get('iclock/cdata', [BiometricPushController::class, 'ping'])->name('biometric.iclock');
});

Route::middleware(['auth:sanctum', 'resolve.gym'])->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('dashboard', [DashboardController::class, 'index']);

    // ── Membership Plans ──────────────────────────────────────────────────
    Route::apiResource('membership-plans', MembershipPlanController::class)->names('api.membership-plans');

    // ── Members CRUD ──────────────────────────────────────────────────────
    Route::apiResource('members', MemberController::class)->names('api.members');
    Route::get('members/{member}/memberships', [MemberController::class, 'memberships']);
    Route::post('members/{member}/memberships', [MemberController::class, 'assignMembership']);
    Route::post('memberships/{membership}/renew', [MemberController::class, 'renewMembership']);
    Route::post('memberships/{membership}/cancel', [MemberController::class, 'cancelMembership']);

    // ── Trainers ──────────────────────────────────────────────────────────
    Route::apiResource('trainers', TrainerController::class)->names('api.trainers');

    // Member assignment
    Route::post('trainers/{trainer}/assign-member',           [TrainerController::class, 'assignMember']);
    Route::delete('trainers/{trainer}/members/{member}',      [TrainerController::class, 'unassignMember']);
    Route::get('trainers/{trainer}/members',                  [TrainerController::class, 'assignedMembers']);

    // Sessions
    Route::post('trainers/{trainer}/sessions',                [TrainerController::class, 'createSession']);
    Route::get('trainers/{trainer}/schedule',                 [TrainerController::class, 'schedule']);
    Route::patch('sessions/{session}',                        [TrainerController::class, 'updateSession']);
    Route::get('sessions/upcoming',                           [TrainerController::class, 'upcomingSessions']);

    // ── Attendance (Manual) ───────────────────────────────────────────────
    Route::prefix('attendance')->group(function () {
        Route::post('check-in',  [AttendanceController::class, 'checkIn']);
        Route::post('check-out', [AttendanceController::class, 'checkOut']);
        Route::get('today',      [AttendanceController::class, 'today']);
        Route::get('my-status',  [AttendanceController::class, 'myStatus']);
    });

    // ── Biometric Sync (ZKTeco-compatible) ────────────────────────────────
    Route::prefix('biometric')->middleware('auth:sanctum')->group(function () {
        Route::post('sync',  [BiometricSyncController::class, 'sync']);
        Route::post('punch', [BiometricSyncController::class, 'punch']);
    });

    // ── Reports ───────────────────────────────────────────────────────────────
    Route::prefix('reports')->group(function () {
        Route::get('revenue',    [ReportController::class, 'revenue']);
        Route::get('members',    [ReportController::class, 'memberGrowth']);
        Route::get('attendance', [ReportController::class, 'attendanceTrends']);
    });

    // ── POS ───────────────────────────────────────────────────────────────────
    Route::prefix('pos')->group(function () {
        Route::get('products',                     [POSController::class, 'products']);
        Route::post('products',                    [POSController::class, 'storeProduct']);
        Route::put('products/{product}',           [POSController::class, 'updateProduct']);
        Route::delete('products/{product}',        [POSController::class, 'destroyProduct']);

        Route::get('invoices',                     [POSController::class, 'invoices']);
        Route::post('invoices',                    [POSController::class, 'storeInvoice']);
        Route::get('invoices/{invoice}',           [POSController::class, 'showInvoice']);
        Route::post('invoices/{invoice}/pay',      [POSController::class, 'markPaid']);
        Route::post('invoices/{invoice}/payments', [POSController::class, 'addPayment']);
        Route::post('invoices/{invoice}/unpay',    [POSController::class, 'markUnpaid']);
        Route::post('invoices/{invoice}/cancel',   [POSController::class, 'cancelInvoice']);
        Route::delete('invoices/{invoice}',        [POSController::class, 'destroyInvoice']);

        Route::get('revenue',                      [POSController::class, 'revenue']);
    });
});
