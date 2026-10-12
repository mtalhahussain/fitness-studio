<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_commission_configs', function (Blueprint $table) {
            // base = ongoing rate (no end when created); temporary = date-range override (weekly/monthly/custom)
            if (! Schema::hasColumn('trainer_commission_configs', 'kind')) {
                $table->string('kind', 20)->default('base')->after('commission_rate');
            }
            if (! Schema::hasColumn('trainer_commission_configs', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('effective_to')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('trainer_commission_configs', function (Blueprint $table) {
            if (Schema::hasColumn('trainer_commission_configs', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('trainer_commission_configs', 'kind')) {
                $table->dropColumn('kind');
            }
        });
    }
};
