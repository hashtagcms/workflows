<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds capability-negotiation telemetry to workflow_logs: which client the run
 * was negotiated for, and what negotiation changed (downgraded / dropped
 * directives). See docs/12-directive-capability-negotiation.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_logs')) {
            return;
        }

        Schema::table('workflow_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('workflow_logs', 'client_platform')) {
                $table->string('client_platform')->nullable()->after('session_id');
            }
            if (!Schema::hasColumn('workflow_logs', 'client_app_version')) {
                $table->string('client_app_version')->nullable()->after('client_platform');
            }
            if (!Schema::hasColumn('workflow_logs', 'negotiation')) {
                $table->json('negotiation')->nullable()->after('response_directives');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('workflow_logs')) {
            return;
        }

        Schema::table('workflow_logs', function (Blueprint $table) {
            foreach (['client_platform', 'client_app_version', 'negotiation'] as $column) {
                if (Schema::hasColumn('workflow_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
