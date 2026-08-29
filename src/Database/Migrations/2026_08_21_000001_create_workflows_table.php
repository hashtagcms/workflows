<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflows')) {
            Schema::create('workflows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('site_id')->default(1)->index();
                $table->string('name');
                $table->string('alias')->unique();
                $table->text('description')->nullable();
                $table->boolean('auth_required')->default(false);
                $table->string('handler')->nullable(); // Fully qualified PHP class
                $table->json('config')->nullable(); // Pipeline steps, rules, rate limits
                $table->boolean('publish_status')->default(true);
                $table->unsignedBigInteger('insert_by')->nullable();
                $table->unsignedBigInteger('update_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
