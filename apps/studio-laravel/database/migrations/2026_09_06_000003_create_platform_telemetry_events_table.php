<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_telemetry_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestampTz('occurred_at')->index();
            $table->string('severity', 16)->index();
            $table->string('source', 32)->index();
            $table->string('event_type', 64)->index();
            $table->string('project_id', 128)->nullable()->index();
            $table->string('request_id', 128)->nullable()->index();
            $table->string('route', 512)->nullable();
            $table->string('method', 16)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('error_class', 255)->nullable();
            $table->text('message');
            $table->text('stack_trace')->nullable();
            $table->text('context')->nullable();
            $table->string('release', 128)->nullable()->index();
            $table->string('user_agent', 1024)->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_telemetry_events');
    }
};
