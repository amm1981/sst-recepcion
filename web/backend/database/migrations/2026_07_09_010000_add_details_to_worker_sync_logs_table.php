<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('worker_sync_logs') || Schema::hasColumn('worker_sync_logs', 'details')) {
            return;
        }

        Schema::table('worker_sync_logs', function (Blueprint $table) {
            $table->json('details')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('worker_sync_logs') || ! Schema::hasColumn('worker_sync_logs', 'details')) {
            return;
        }

        Schema::table('worker_sync_logs', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
