<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Web\AttendanceWebController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\MemberWebController;
use App\Http\Controllers\Web\POSWebController;
use App\Http\Controllers\Web\ReportWebController;
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

    // Members
    Route::get('/members',                          [MemberWebController::class, 'index'])->name('members.index');
    Route::post('/members',                         [MemberWebController::class, 'store'])->name('members.store');
    Route::put('/members/{member}',                 [MemberWebController::class, 'update'])->name('members.update');
    Route::delete('/members/{member}',              [MemberWebController::class, 'destroy'])->name('members.destroy');
    Route::post('/members/{member}/membership',     [MemberWebController::class, 'assignMembership'])->name('members.membership');

    // Attendance
    Route::get('/attendance',                       [AttendanceWebController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/check-in',             [AttendanceWebController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('/attendance/check-out',            [AttendanceWebController::class, 'checkOut'])->name('attendance.check-out');

    // Trainers
    Route::get('/trainers',                         [TrainerWebController::class, 'index'])->name('trainers.index');
    Route::post('/trainers',                        [TrainerWebController::class, 'store'])->name('trainers.store');
    Route::put('/trainers/{trainer}',               [TrainerWebController::class, 'update'])->name('trainers.update');
    Route::delete('/trainers/{trainer}',            [TrainerWebController::class, 'destroy'])->name('trainers.destroy');
    Route::post('/trainers/{trainer}/assign',       [TrainerWebController::class, 'assignMember'])->name('trainers.assign');
    Route::get('/trainers/{trainer}/schedule',      [TrainerWebController::class, 'schedule'])->name('trainers.schedule');
    Route::post('/trainers/{trainer}/sessions',     [TrainerWebController::class, 'createSession'])->name('trainers.sessions');

    // Reports
    Route::get('/reports',                        [ReportWebController::class, 'index'])->name('reports.index');
    Route::get('/reports/data/revenue',           [ReportWebController::class, 'revenueData'])->name('reports.revenue');
    Route::get('/reports/data/members',           [ReportWebController::class, 'membersData'])->name('reports.members');
    Route::get('/reports/data/attendance',        [ReportWebController::class, 'attendanceData'])->name('reports.attendance');

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
});
