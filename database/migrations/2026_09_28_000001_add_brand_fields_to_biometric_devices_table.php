<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->string('brand', 30)->default('zkteco')->after('gym_id');
            $table->string('webhook_token', 64)->nullable()->unique()->after('api_key');
            $table->json('settings')->nullable()->after('webhook_token');
            $table->text('last_payload')->nullable()->after('last_seen_at');
            $table->timestamp('last_payload_at')->nullable()->after('last_payload');
            $table->string('serial_number')->nullable()->change();
        });

        DB::table('biometric_devices')->whereNull('webhook_token')->orderBy('id')->each(function ($row) {
            DB::table('biometric_devices')->where('id', $row->id)->update(['webhook_token' => Str::random(40)]);
        });
    }

    public function down(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropUnique(['webhook_token']);
            $table->dropColumn(['brand', 'webhook_token', 'settings', 'last_payload', 'last_payload_at']);
        });
    }
};
