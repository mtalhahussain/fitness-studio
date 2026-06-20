<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            if (! Schema::hasColumn('gyms', 'domain')) {
                $table->string('domain')->nullable()->after('slug')->unique();
            }

            if (! Schema::hasColumn('gyms', 'subdomain')) {
                $table->string('subdomain')->nullable()->after('domain')->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            if (Schema::hasColumn('gyms', 'subdomain')) {
                $table->dropUnique(['subdomain']);
                $table->dropColumn('subdomain');
            }

            if (Schema::hasColumn('gyms', 'domain')) {
                $table->dropUnique(['domain']);
                $table->dropColumn('domain');
            }
        });
    }
};
