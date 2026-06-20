<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->boolean('whatsapp_enabled')->default(false)->after('subscription_ends_at');
            $table->string('whatsapp_token', 500)->nullable()->after('whatsapp_enabled');
            $table->string('whatsapp_phone_number_id', 100)->nullable()->after('whatsapp_token');
        });
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_enabled', 'whatsapp_token', 'whatsapp_phone_number_id']);
        });
    }
};
