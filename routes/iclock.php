<?php

use App\Http\Controllers\Iclock\IclockController;
use Illuminate\Support\Facades\Route;

// ZKTeco ADMS push. Loaded at the root with no middleware group (no session, no CSRF):
// firmware hardcodes these paths and identifies itself only by the SN query param.
Route::prefix('iclock')->name('iclock.')->group(function () {
    Route::get('cdata',      [IclockController::class, 'handshake'])->name('handshake');
    Route::post('cdata',     [IclockController::class, 'upload'])->name('upload');
    Route::get('getrequest', [IclockController::class, 'getrequest'])->name('getrequest');
    Route::post('devicecmd', [IclockController::class, 'devicecmd'])->name('devicecmd');
});
