<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('trainer_profiles', 'compensation_mode')) {
                $table->string('compensation_mode', 20)->default('commission')->after('certifications');
            }

            if (! Schema::hasColumn('trainer_profiles', 'base_salary')) {
                $table->decimal('base_salary', 10, 2)->nullable()->after('compensation_mode');
            }

            if (! Schema::hasColumn('trainer_profiles', 'commission_enabled')) {
                $table->boolean('commission_enabled')->default(true)->after('base_salary');
            }

            if (! Schema::hasColumn('trainer_profiles', 'salary_enabled')) {
                $table->boolean('salary_enabled')->default(false)->after('commission_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trainer_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('trainer_profiles', 'salary_enabled')) {
                $table->dropColumn('salary_enabled');
            }
            if (Schema::hasColumn('trainer_profiles', 'commission_enabled')) {
                $table->dropColumn('commission_enabled');
            }
            if (Schema::hasColumn('trainer_profiles', 'base_salary')) {
                $table->dropColumn('base_salary');
            }
            if (Schema::hasColumn('trainer_profiles', 'compensation_mode')) {
                $table->dropColumn('compensation_mode');
            }
        });
    }
};
