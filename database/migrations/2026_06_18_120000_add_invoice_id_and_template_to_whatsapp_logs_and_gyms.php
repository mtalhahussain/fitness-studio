<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_message_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_message_logs', 'invoice_id')) {
                $table->foreignId('invoice_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
                $table->index(['invoice_id', 'reminder_date']);
                $table->unique(['invoice_id', 'template_name', 'reminder_date'], 'wa_invoice_daily_unique');
            }
        });

        Schema::table('gyms', function (Blueprint $table) {
            if (! Schema::hasColumn('gyms', 'whatsapp_message_template')) {
                $table->text('whatsapp_message_template')->nullable()->after('whatsapp_business_account_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            if (Schema::hasColumn('gyms', 'whatsapp_message_template')) {
                $table->dropColumn('whatsapp_message_template');
            }
        });

        Schema::table('whatsapp_message_logs', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_message_logs', 'invoice_id')) {
                $table->dropUnique('wa_invoice_daily_unique');
                $table->dropIndex(['invoice_id', 'reminder_date']);
                $table->dropConstrainedForeignId('invoice_id');
            }
        });
    }
};
