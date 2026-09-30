<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Server → machine command queue for ZKTeco ADMS. The machine polls GET /iclock/getrequest,
        // receives "C:<cmd_id>:<command>" and reports back on POST /iclock/devicecmd.
        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->string('device_serial_number', 100)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // member/trainer the command is about
            $table->text('command');
            $table->string('status', 10)->default('pending'); // pending | sent | done | failed
            $table->unsignedBigInteger('cmd_id')->nullable()->unique();
            $table->text('result')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['device_serial_number', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_commands');
    }
};
