<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\BiometricSyncController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MembershipPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'gym.tenant'])->group(function () {

    // ── Membership Plans ──────────────────────────────────────────────────
    Route::apiResource('membership-plans', MembershipPlanController::class);

    // ── Members CRUD ──────────────────────────────────────────────────────
    Route::apiResource('members', MemberController::class);
    Route::get('members/{member}/memberships', [MemberController::class, 'memberships']);
    Route::post('members/{member}/memberships', [MemberController::class, 'assignMembership']);
    Route::post('memberships/{membership}/renew', [MemberController::class, 'renewMembership']);
    Route::post('memberships/{membership}/cancel', [MemberController::class, 'cancelMembership']);

    // ── Attendance (Manual) ───────────────────────────────────────────────
    Route::prefix('attendance')->group(function () {
        Route::post('check-in',   [AttendanceController::class, 'checkIn']);
        Route::post('check-out',  [AttendanceController::class, 'checkOut']);
        Route::get('today',       [AttendanceController::class, 'today']);
        Route::get('my-status',   [AttendanceController::class, 'myStatus']);
    });

    // ── Biometric Sync (ZKTeco-compatible) ────────────────────────────────
    // Authenticate devices with: $device->createToken('zkDevice-01', ['biometric-device'])
    Route::prefix('biometric')->middleware('auth:sanctum')->group(function () {
        Route::post('sync',  [BiometricSyncController::class, 'sync']);
        Route::post('punch', [BiometricSyncController::class, 'punch']);
    });
});
