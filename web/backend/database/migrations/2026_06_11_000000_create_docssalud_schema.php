<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();

            // Reemplaza $table->morphs('tokenable') para evitar índice demasiado largo
            $table->string('tokenable_type', 191);
            $table->unsignedBigInteger('tokenable_id');
            $table->index(
                ['tokenable_type', 'tokenable_id'],
                'personal_access_tokens_tokenable_index'
            );

            $table->string('name', 191);
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 191)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 191)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('phone', 191)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('password');
        });

        Schema::create('managements', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 191)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 191)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('dni', 20)->unique();
            $table->string('first_name', 191);
            $table->string('last_name', 191);
            $table->string('email', 191)->nullable();
            $table->string('phone', 191)->nullable();
            $table->string('position', 191)->nullable();
            $table->foreignId('management_id')->nullable()->constrained('managements')->nullOnDelete();
            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('medical_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 191)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('delivery_relations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 191)->unique();
            $table->boolean('requires_detail')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('medical_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_document_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('worker_id')->constrained()->restrictOnDelete();
            $table->foreignId('delivery_relation_id')->constrained()->restrictOnDelete();
            $table->string('delivery_relation_detail', 191)->nullable();
            $table->string('deliverer_name', 191);
            $table->string('deliverer_document', 191)->nullable();
            $table->string('contact_number', 191);
            $table->text('observation')->nullable();
            $table->string('status', 20)->default('PENDIENTE')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable();
            $table->string('offline_uuid', 191)->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('medical_document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_document_id')->constrained()->cascadeOnDelete();
            $table->string('file_type', 30);
            $table->string('original_name', 191);
            $table->string('path', 191);
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('medical_document_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_document_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('observation')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_uuid', 191)->unique();
            $table->string('name', 191)->nullable();
            $table->string('platform', 191)->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mobile_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 20);
            $table->string('entity', 80);
            $table->string('status', 30)->default('OK');
            $table->json('payload')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 191);
            $table->text('body')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('entity', 120);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('mobile_devices');
        Schema::dropIfExists('medical_document_status_history');
        Schema::dropIfExists('medical_document_files');
        Schema::dropIfExists('medical_documents');
        Schema::dropIfExists('delivery_relations');
        Schema::dropIfExists('medical_document_types');
        Schema::dropIfExists('workers');
        Schema::dropIfExists('sectors');
        Schema::dropIfExists('managements');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['phone', 'is_active']);
        });

        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('personal_access_tokens');
    }
};