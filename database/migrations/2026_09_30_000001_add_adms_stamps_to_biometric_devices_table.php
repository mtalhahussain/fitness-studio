<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Last upload stamps a ZKTeco machine reported, echoed back in the ADMS handshake
        // so the machine only sends records newer than what we already have.
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->string('attlog_stamp', 50)->nullable()->after('last_payload_at');
            $table->string('operlog_stamp', 50)->nullable()->after('attlog_stamp');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropColumn(['attlog_stamp', 'operlog_stamp']);
        });
    }
};
