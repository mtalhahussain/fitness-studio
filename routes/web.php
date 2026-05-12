<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Web\AttendanceWebController;
use App\Http\Controllers\Web\BiometricDeviceWebController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\GymWebController;
use App\Http\Controllers\Web\MemberWebController;
use App\Http\Controllers\Web\PlanWebController;
use App\Http\Controllers\Web\POSWebController;
use App\Http\Controllers\Web\ReportWebController;
use App\Http\Controllers\Web\TrainerCommissionWebController;
use App\Http\Controllers\Web\TrainerWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware(['auth', 'gym.tenant'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Super Admin only — no gym context required ────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('/gyms',                        [GymWebController::class, 'index'])->name('gyms.index');
        Route::post('/gyms',                       [GymWebController::class, 'store'])->name('gyms.store');
        Route::put('/gyms/{gym}',                  [GymWebController::class, 'update'])->name('gyms.update');
        Route::delete('/gyms/{gym}',               [GymWebController::class, 'destroy'])->name('gyms.destroy');
        Route::post('/gyms/{gym}/toggle-status',   [GymWebController::class, 'toggleStatus'])->name('gyms.toggle-status');
        Route::post('/admin/switch-gym/{gym}',     [GymWebController::class, 'switchGym'])->name('admin.switch-gym');
        Route::post('/admin/clear-gym',            [GymWebController::class, 'clearGym'])->name('admin.clear-gym');
    });

    // ── Gym-scoped routes — admin must have switched into a gym context ───────
    Route::middleware('gym.context')->group(function () {

        // Plans
        Route::get('/plans',              [PlanWebController::class, 'index'])->name('plans.index');
        Route::post('/plans',             [PlanWebController::class, 'store'])->name('plans.store');
        Route::put('/plans/{plan}',       [PlanWebController::class, 'update'])->name('plans.update');
        Route::delete('/plans/{plan}',    [PlanWebController::class, 'destroy'])->name('plans.destroy');

        // Members
        Route::get('/members',                               [MemberWebController::class, 'index'])->name('members.index');
        Route::post('/members',                              [MemberWebController::class, 'store'])->name('members.store');
        Route::put('/members/{member}',                      [MemberWebController::class, 'update'])->name('members.update');
        Route::delete('/members/{member}',                   [MemberWebController::class, 'destroy'])->name('members.destroy');
        Route::post('/members/{member}/membership',          [MemberWebController::class, 'assignMembership'])->name('members.membership');
        Route::post('/members/{member}/pay-balance',         [MemberWebController::class, 'payBalance'])->name('members.pay-balance');
        Route::post('/members/{member}/training/start',      [MemberWebController::class, 'startTraining'])->name('members.training.start');
        Route::post('/members/{member}/training/pause',      [MemberWebController::class, 'pauseTraining'])->name('members.training.pause');
        Route::post('/members/{member}/training/resume',     [MemberWebController::class, 'resumeTraining'])->name('members.training.resume');
        Route::post('/members/{member}/training/end',        [MemberWebController::class, 'endTraining'])->name('members.training.end');
        Route::get('/members/{member}/training/history',     [MemberWebController::class, 'trainingHistory'])->name('members.training.history');

        // Attendance
        Route::get('/attendance',             [AttendanceWebController::class, 'index'])->name('attendance.index');
        Route::post('/attendance/check-in',   [AttendanceWebController::class, 'checkIn'])->name('attendance.check-in');
        Route::post('/attendance/check-out',  [AttendanceWebController::class, 'checkOut'])->name('attendance.check-out');

        // Trainers
        Route::get('/trainers',                         [TrainerWebController::class, 'index'])->name('trainers.index');
        Route::post('/trainers',                        [TrainerWebController::class, 'store'])->name('trainers.store');
        Route::put('/trainers/{trainer}',               [TrainerWebController::class, 'update'])->name('trainers.update');
        Route::delete('/trainers/{trainer}',            [TrainerWebController::class, 'destroy'])->name('trainers.destroy');
        Route::post('/trainers/{trainer}/assign',       [TrainerWebController::class, 'assignMember'])->name('trainers.assign');
        Route::get('/trainers/{trainer}/schedule',      [TrainerWebController::class, 'schedule'])->name('trainers.schedule');
        Route::post('/trainers/{trainer}/sessions',     [TrainerWebController::class, 'createSession'])->name('trainers.sessions');

        // Trainer commission
        Route::get('/trainers/{trainer}/commission',  [TrainerCommissionWebController::class, 'overview'])->name('trainers.commission');
        Route::get('/trainers/{trainer}/earnings',    [TrainerCommissionWebController::class, 'trainerEarnings'])->name('trainers.earnings');
        Route::post('/commission-config',             [TrainerCommissionWebController::class, 'setConfig'])->name('commission.config');

        // Reports
        Route::get('/reports/commissions',              [TrainerCommissionWebController::class, 'report'])->name('reports.commissions');
        Route::get('/reports',                          [ReportWebController::class, 'index'])->name('reports.index');
        Route::get('/reports/data/revenue',             [ReportWebController::class, 'revenueData'])->name('reports.revenue');
        Route::get('/reports/data/membership-revenue',  [ReportWebController::class, 'membershipRevenueData'])->name('reports.membership-revenue');
        Route::get('/reports/data/members',             [ReportWebController::class, 'membersData'])->name('reports.members');
        Route::get('/reports/data/attendance',          [ReportWebController::class, 'attendanceData'])->name('reports.attendance');

        // Biometric Devices
        Route::get('/biometric/devices',                          [BiometricDeviceWebController::class, 'index'])->name('biometric.devices');
        Route::post('/biometric/devices',                         [BiometricDeviceWebController::class, 'store'])->name('biometric.devices.store');
        Route::put('/biometric/devices/{device}',                 [BiometricDeviceWebController::class, 'update'])->name('biometric.devices.update');
        Route::delete('/biometric/devices/{device}',              [BiometricDeviceWebController::class, 'destroy'])->name('biometric.devices.destroy');
        Route::post('/biometric/devices/{device}/toggle',         [BiometricDeviceWebController::class, 'toggleStatus'])->name('biometric.devices.toggle');
        Route::post('/biometric/devices/{device}/regenerate-key', [BiometricDeviceWebController::class, 'regenerateKey'])->name('biometric.devices.regenerate-key');

        // POS
        Route::get('/pos',                              [POSWebController::class, 'index'])->name('pos.index');
        Route::get('/pos/products',                     [POSWebController::class, 'products'])->name('pos.products');
        Route::post('/pos/products',                    [POSWebController::class, 'createProduct'])->name('pos.products.store');
        Route::put('/pos/products/{product}',           [POSWebController::class, 'updateProduct'])->name('pos.products.update');
        Route::delete('/pos/products/{product}',        [POSWebController::class, 'deleteProduct'])->name('pos.products.destroy');
        Route::post('/pos/invoices',                    [POSWebController::class, 'createInvoice'])->name('pos.invoices.store');
        Route::get('/pos/invoices/{invoice}',           [POSWebController::class, 'showInvoice'])->name('pos.invoices.show');
        Route::post('/pos/invoices/{invoice}/pay',      [POSWebController::class, 'markPaid'])->name('pos.invoices.pay');
        Route::post('/pos/invoices/{invoice}/unpay',    [POSWebController::class, 'markUnpaid'])->name('pos.invoices.unpay');
        Route::post('/pos/invoices/{invoice}/payment',  [POSWebController::class, 'addPayment'])->name('pos.invoices.payment');
        Route::post('/pos/invoices/{invoice}/cancel',   [POSWebController::class, 'cancel'])->name('pos.invoices.cancel');

    }); // end gym.context

});
