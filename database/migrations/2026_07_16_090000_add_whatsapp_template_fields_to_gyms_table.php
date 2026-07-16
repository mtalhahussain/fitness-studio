<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            if (! Schema::hasColumn('gyms', 'whatsapp_template_name')) {
                $table->string('whatsapp_template_name')->nullable()->after('whatsapp_message_template');
            }
            if (! Schema::hasColumn('gyms', 'whatsapp_template_language')) {
                $table->string('whatsapp_template_language')->nullable()->default('en_US')->after('whatsapp_template_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            if (Schema::hasColumn('gyms', 'whatsapp_template_language')) {
                $table->dropColumn('whatsapp_template_language');
            }
            if (Schema::hasColumn('gyms', 'whatsapp_template_name')) {
                $table->dropColumn('whatsapp_template_name');
            }
        });
    }
};
