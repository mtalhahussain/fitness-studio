<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('biometric_devices')->where('brand', 'zkteco')->orderBy('id')
            ->each(function ($device) {
                $settings = json_decode($device->settings ?? '{}', true) ?: [];
                $settings['punch_mode'] = 'toggle';
                DB::table('biometric_devices')->where('id', $device->id)
                    ->update(['settings' => json_encode($settings)]);
            });
    }

    public function down(): void
    {
        // Leave attendance preferences intact; the previous values cannot be reconstructed.
    }
};
