<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_documents', function (Blueprint $table) {
            $table->string('worker_position_snapshot', 191)->nullable()->after('worker_id');
            $table->foreignId('worker_management_id_snapshot')->nullable()->after('worker_position_snapshot')->constrained('managements')->nullOnDelete();
            $table->string('worker_management_name_snapshot', 191)->nullable()->after('worker_management_id_snapshot');
            $table->foreignId('worker_sector_id_snapshot')->nullable()->after('worker_management_name_snapshot')->constrained('sectors')->nullOnDelete();
            $table->string('worker_sector_name_snapshot', 191)->nullable()->after('worker_sector_id_snapshot');
        });

        DB::table('medical_documents')
            ->leftJoin('workers', 'workers.id', '=', 'medical_documents.worker_id')
            ->leftJoin('managements', 'managements.id', '=', 'workers.management_id')
            ->leftJoin('sectors', 'sectors.id', '=', 'workers.sector_id')
            ->select([
                'medical_documents.id as document_id',
                'workers.position as worker_position',
                'workers.management_id as worker_management_id',
                'managements.name as worker_management_name',
                'workers.sector_id as worker_sector_id',
                'sectors.name as worker_sector_name',
            ])
            ->orderBy('medical_documents.id')
            ->get()
            ->each(function ($row) {
                DB::table('medical_documents')
                    ->where('id', $row->document_id)
                    ->update([
                        'worker_position_snapshot' => $row->worker_position,
                        'worker_management_id_snapshot' => $row->worker_management_id,
                        'worker_management_name_snapshot' => $row->worker_management_name,
                        'worker_sector_id_snapshot' => $row->worker_sector_id,
                        'worker_sector_name_snapshot' => $row->worker_sector_name,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('medical_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('worker_management_id_snapshot');
            $table->dropConstrainedForeignId('worker_sector_id_snapshot');
            $table->dropColumn([
                'worker_position_snapshot',
                'worker_management_name_snapshot',
                'worker_sector_name_snapshot',
            ]);
        });
    }
};
