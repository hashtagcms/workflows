<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_logs')) {
            Schema::create('workflow_logs', function (Blueprint $table) {
                $table->id();
                $table->string('workflow_alias')->index();
                $table->unsignedBigInteger('site_id')->default(1);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('session_id')->nullable();
                $table->json('payload')->nullable();
                $table->json('response_directives')->nullable();
                $table->boolean('is_success')->default(true);
                $table->string('error_message')->nullable();
                $table->integer('execution_time_ms')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_logs');
    }
};
