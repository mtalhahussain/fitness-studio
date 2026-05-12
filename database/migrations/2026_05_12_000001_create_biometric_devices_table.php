<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->string('serial_number')->unique();   // ZKTeco SN from machine
            $table->string('name');                      // e.g. "Main Entrance", "Back Door"
            $table->string('model')->nullable();         // F18, K40, MA300, etc.
            $table->string('location')->nullable();      // optional label
            $table->string('api_key')->unique();         // secret key machine sends with each push
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};
