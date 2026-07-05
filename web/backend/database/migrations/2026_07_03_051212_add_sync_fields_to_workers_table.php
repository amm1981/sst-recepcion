<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->string('source', 30)->nullable()->default(null)->after('is_active');
            $table->date('hire_date')->nullable()->after('source');
            $table->date('termination_date')->nullable()->after('hire_date');
            $table->timestamp('external_updated_at')->nullable()->after('termination_date');
            $table->json('external_payload')->nullable()->after('external_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn(['source', 'hire_date', 'termination_date', 'external_updated_at', 'external_payload']);
        });
    }
};
