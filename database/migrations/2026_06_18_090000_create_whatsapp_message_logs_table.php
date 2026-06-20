<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 32);
            $table->string('template_name', 100);
            $table->text('message_body')->nullable();
            $table->longText('response')->nullable();
            $table->string('status', 30)->default('queued');
            $table->date('reminder_date')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['template_name', 'status']);
            $table->index(['payment_id', 'reminder_date']);
            $table->unique(['payment_id', 'template_name', 'reminder_date'], 'wa_payment_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_logs');
    }
};

