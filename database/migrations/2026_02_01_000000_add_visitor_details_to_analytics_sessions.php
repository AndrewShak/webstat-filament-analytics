<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adds IP + device detail columns. Safe to run on installs that already have the base tables. */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('analytics.connection');
    }

    public function up(): void
    {
        Schema::table('analytics_sessions', function (Blueprint $t): void {
            if (! Schema::hasColumn('analytics_sessions', 'ip')) {
                $t->string('ip', 45)->nullable()->after('visitor_id');
            }
            if (! Schema::hasColumn('analytics_sessions', 'os_version')) {
                $t->string('os_version', 16)->nullable()->after('os');
            }
            if (! Schema::hasColumn('analytics_sessions', 'device_brand')) {
                $t->string('device_brand', 32)->nullable()->after('device');
            }
            if (! Schema::hasColumn('analytics_sessions', 'device_model')) {
                $t->string('device_model', 48)->nullable()->after('device_brand');
            }
            if (! Schema::hasColumn('analytics_sessions', 'screen')) {
                $t->string('screen', 11)->nullable()->after('device_model');
            }
        });

        Schema::table('analytics_sessions', function (Blueprint $t): void {
            $t->index('ip');
            $t->index(['country', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::table('analytics_sessions', function (Blueprint $t): void {
            $t->dropIndex(['ip']);
            $t->dropIndex(['country', 'started_at']);
        });
        Schema::table('analytics_sessions', function (Blueprint $t): void {
            $t->dropColumn(['ip', 'os_version', 'device_brand', 'device_model', 'screen']);
        });
    }
};
