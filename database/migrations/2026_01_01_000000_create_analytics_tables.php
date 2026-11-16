<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('analytics.connection');
    }

    public function up(): void
    {
        Schema::create('analytics_sessions', function (Blueprint $t): void {
            $t->id();
            $t->string('sid', 32)->unique();
            $t->string('visitor_id', 36);
            $t->timestamp('started_at');
            $t->timestamp('last_seen_at');
            $t->unsignedInteger('pageviews')->default(0);
            $t->unsignedInteger('events')->default(0);
            $t->unsignedInteger('duration')->default(0);
            $t->boolean('is_bot')->default(false);
            $t->string('bot_name', 64)->nullable();
            $t->unsignedTinyInteger('bot_score')->default(0);
            $t->boolean('is_new')->default(true);
            $t->char('country', 2)->nullable();
            $t->string('city', 96)->nullable();
            $t->string('browser', 32)->nullable();
            $t->string('browser_version', 16)->nullable();
            $t->string('os', 32)->nullable();
            $t->string('device', 16)->nullable();
            $t->string('lang', 8)->nullable();
            $t->string('source_type', 16)->default('direct');
            $t->string('referrer_host', 190)->nullable();
            $t->string('search_engine', 32)->nullable();
            $t->string('landing_path', 255)->nullable();
            $t->string('landing_title', 255)->nullable();
            $t->string('age_group', 16)->nullable();
            $t->string('gender', 16)->nullable();
            $t->jsonb('meta')->nullable();

            $t->index(['is_bot', 'started_at']);
            $t->index(['visitor_id', 'started_at']);
            $t->index(['started_at', 'source_type']);
        });

        Schema::create('analytics_events', function (Blueprint $t): void {
            $t->id();
            $t->string('sid', 32);
            $t->timestamp('created_at');
            $t->string('type', 24);
            $t->boolean('is_bot')->default(false);
            $t->string('path', 255);
            $t->string('title', 255)->nullable();
            $t->string('target', 255)->nullable(); // selector | href | form key | goal name
            $t->string('label', 255)->nullable();  // link text | field name
            $t->unsignedTinyInteger('x')->nullable(); // 0..100 % of document width
            $t->unsignedTinyInteger('y')->nullable(); // 0..100 % of document height
            $t->unsignedInteger('ph')->nullable();    // document height (px)
            $t->integer('value')->nullable();         // scroll depth % | goal value
            $t->unsignedInteger('aux')->nullable();   // viewed-bands bitmask
            $t->unsignedInteger('dur')->nullable();   // seconds on page
            $t->jsonb('data')->nullable();

            $t->index('created_at');
            $t->index(['type', 'created_at']);
            $t->index(['path', 'type', 'created_at']);
            $t->index(['sid', 'type']);
        });

        Schema::create('analytics_goals', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 120);
            $t->string('type', 16); // path | event | click | form
            $t->string('match', 255);
            $t->unsignedTinyInteger('funnel_step')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_goals');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('analytics_sessions');
    }
};
