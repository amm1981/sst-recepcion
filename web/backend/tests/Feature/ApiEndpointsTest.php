<?php

namespace Tests\Feature;

use App\Models\DeliveryRelation;
use App\Models\Management;
use App\Models\MedicalDocument;
use App\Models\MedicalDocumentStatusHistory;
use App\Models\MedicalDocumentType;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sector;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_user_field_instead_of_email(): void
    {
        $user = $this->adminUser();

        $this->postJson('/api/auth/login', [
            'user' => $user->user,
            'password' => 'Password123',
            'device_name' => 'Feature test',
        ])->assertOk()
            ->assertJsonPath('user.user', $user->user)
            ->assertJsonStructure(['token']);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('user');
    }

    public function test_profile_password_requires_current_password(): void
    {
        $user = $this->adminUser();
        Sanctum::actingAs($user);

        $this->putJson('/api/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'bad-password',
            'password' => 'NewPassword123!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->putJson('/api/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'Password123',
            'password' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_notifications_can_be_marked_all_as_read(): void
    {
        $user = $this->adminUser();
        Notification::create(['user_id' => $user->id, 'title' => 'Uno']);
        Notification::create(['user_id' => $user->id, 'title' => 'Dos']);
        Sanctum::actingAs($user);

        $this->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJson(['message' => 'Notificaciones marcadas como leidas.']);

        $this->assertSame(0, $user->notifications()->whereNull('read_at')->count());
    }

    public function test_document_counts_endpoint_returns_status_totals(): void
    {
        $user = $this->adminUser();
        $fixtures = $this->documentFixtures($user);
        Sanctum::actingAs($user);

        foreach ([
            MedicalDocument::STATUS_PENDING,
            MedicalDocument::STATUS_RECEIVED,
            MedicalDocument::STATUS_REGISTERED,
            MedicalDocument::STATUS_REJECTED,
        ] as $status) {
            MedicalDocument::create($fixtures + ['status' => $status]);
        }

        $this->getJson('/api/medical-documents/counts')
            ->assertOk()
            ->assertJson([
                'pending' => 1,
                'received' => 1,
                'registered' => 1,
                'rejected' => 1,
            ]);
    }

    public function test_rrhh_user_can_create_medical_document(): void
    {
        Storage::fake('local');

        $role = Role::create([
            'name' => 'Recursos Humanos',
            'code' => 'rrhh',
            'is_active' => true,
        ]);
        $permission = Permission::where('code', 'documents.create')->firstOrFail();
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create(['role_id' => $role->id]);
        $fixtures = $this->documentFixtures($user);
        $worker = Worker::findOrFail($fixtures['worker_id']);
        Sanctum::actingAs($user);

        $this->post('/api/medical-documents', [
            'medical_document_type_id' => $fixtures['medical_document_type_id'],
            'worker_dni' => $worker->dni,
            'delivery_relation_id' => $fixtures['delivery_relation_id'],
            'deliverer_name' => 'Carlos Ramirez',
            'contact_number' => '999111222',
            'medical_document_file' => UploadedFile::fake()->create('descanso-medico.pdf', 128, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('status', MedicalDocument::STATUS_PENDING);

        $this->assertDatabaseHas('medical_documents', [
            'worker_id' => $worker->id,
            'created_by' => $user->id,
            'status' => MedicalDocument::STATUS_PENDING,
        ]);

        $file = MedicalDocument::firstOrFail()->files()->firstOrFail();
        $this->assertStringStartsWith('sst/medical-documents/', $file->path);
        Storage::disk(config('filesystems.default'))->assertExists($file->path);
    }

    public function test_report_summary_applies_status_and_type_filters(): void
    {
        $user = $this->adminUser();
        $registeredFixtures = $this->documentFixtures($user);
        $pendingFixtures = $this->documentFixtures($user);

        MedicalDocument::create($registeredFixtures + ['status' => MedicalDocument::STATUS_REGISTERED]);
        MedicalDocument::create($pendingFixtures + ['status' => MedicalDocument::STATUS_PENDING]);
        Sanctum::actingAs($user);

        $this->getJson('/api/reports/summary?status=' . MedicalDocument::STATUS_REGISTERED . '&type_id=' . $registeredFixtures['medical_document_type_id'])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'by_status')
            ->assertJsonPath('by_status.0.status', MedicalDocument::STATUS_REGISTERED)
            ->assertJsonPath('by_status.0.total', 1)
            ->assertJsonPath('by_type.0.registrados', 1)
            ->assertJsonPath('by_type.0.pendientes', 0);
    }

    public function test_report_workers_history_applies_search_and_returns_status_history(): void
    {
        $user = $this->adminUser();
        $fixtures = $this->documentFixtures($user);
        $document = MedicalDocument::create($fixtures + ['status' => MedicalDocument::STATUS_REGISTERED]);
        $worker = Worker::findOrFail($fixtures['worker_id']);

        MedicalDocumentStatusHistory::create([
            'medical_document_id' => $document->id,
            'from_status' => null,
            'to_status' => MedicalDocument::STATUS_PENDING,
            'observation' => 'Documento creado.',
            'changed_by' => $user->id,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        MedicalDocumentStatusHistory::create([
            'medical_document_id' => $document->id,
            'from_status' => MedicalDocument::STATUS_RECEIVED,
            'to_status' => MedicalDocument::STATUS_REGISTERED,
            'observation' => 'Validado por SST.',
            'changed_by' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/reports/workers-history?q=' . $worker->dni . '&status=' . MedicalDocument::STATUS_REGISTERED)
            ->assertOk()
            ->assertJsonPath('data.0.dni', $worker->dni)
            ->assertJsonPath('data.0.documents_count', 1)
            ->assertJsonPath('data.0.documents.0.id', $document->id)
            ->assertJsonFragment(['to_status' => MedicalDocument::STATUS_REGISTERED]);
    }

    public function test_report_filters_return_validation_error_for_invalid_dates(): void
    {
        $user = $this->adminUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/reports/registrars?from=2')
            ->assertStatus(422)
            ->assertJsonValidationErrors('from');
    }

    public function test_rejecting_registered_document_updates_observation_and_sends_report(): void
    {
        Mail::fake();
        $user = $this->adminUser();
        $document = MedicalDocument::create($this->documentFixtures($user) + [
            'status' => MedicalDocument::STATUS_REGISTERED,
        ]);
        SystemSetting::create([
            'key' => \App\Services\RejectedDocumentsMailSettings::RECIPIENTS_KEY,
            'value' => ['sst@example.com'],
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/medical-documents/{$document->id}/status", [
            'status' => MedicalDocument::STATUS_REJECTED,
            'observation' => 'Documento ilegible.',
        ])->assertOk()
            ->assertJsonPath('status', MedicalDocument::STATUS_REJECTED);

        $this->assertDatabaseHas('medical_documents', [
            'id' => $document->id,
            'status' => MedicalDocument::STATUS_REJECTED,
            'observation' => 'Documento ilegible.',
        ]);
        $this->assertDatabaseHas('medical_document_status_history', [
            'medical_document_id' => $document->id,
            'to_status' => MedicalDocument::STATUS_REJECTED,
            'observation' => 'Documento ilegible.',
        ]);
        Mail::assertSent(\App\Mail\RejectedDocumentsReport::class, fn ($mail) => $mail->hasTo('sst@example.com'));
    }

    public function test_report_pdf_export_returns_a_pdf_file(): void
    {
        $user = $this->adminUser();
        MedicalDocument::create($this->documentFixtures($user) + ['status' => MedicalDocument::STATUS_PENDING]);
        Sanctum::actingAs($user);

        $response = $this->get('/api/reports/export/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
    }

    public function test_admin_can_configure_and_send_rejected_report_test_email(): void
    {
        Mail::fake();
        $user = $this->adminUser();
        Sanctum::actingAs($user);

        $this->putJson('/api/admin/mail-settings', [
            'rejected_report_recipients' => ['rrhh@example.com', 'sst@example.com'],
        ])->assertOk()
            ->assertJsonPath('rejected_report_recipients.0', 'rrhh@example.com')
            ->assertJsonPath('rejected_report_recipients.1', 'sst@example.com');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'rejected_documents_report_recipients',
        ]);

        $this->postJson('/api/admin/mail-settings/test')
            ->assertOk()
            ->assertJsonPath('documents_count', 2);

        Mail::assertSent(\App\Mail\RejectedDocumentsReport::class, function ($mail) {
            return $mail->hasTo('rrhh@example.com')
                && $mail->hasTo('sst@example.com')
                && $mail->isTest === true;
        });
    }

    private function adminUser(): User
    {
        $role = Role::create([
            'name' => 'Administrador',
            'code' => 'ADMIN',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'password' => Hash::make('Password123'),
        ]);
    }

    private function documentFixtures(User $user): array
    {
        $management = Management::updateOrCreate(['code' => 'OPER'], ['name' => 'Operaciones', 'is_active' => true]);
        $sector = Sector::updateOrCreate(['code' => 'PLANTA'], ['name' => 'Planta', 'is_active' => true]);
        $worker = Worker::create([
            'dni' => fake()->unique()->numerify('########'),
            'first_name' => 'Carlos',
            'last_name' => 'Ramirez',
            'management_id' => $management->id,
            'sector_id' => $sector->id,
            'is_active' => true,
        ]);
        $type = MedicalDocumentType::create(['name' => 'Descanso Medico', 'code' => 'DOC' . str()->random(8), 'is_active' => true]);
        $relation = DeliveryRelation::create(['name' => 'Trabajador', 'code' => 'REL' . str()->random(8), 'is_active' => true]);

        return [
            'medical_document_type_id' => $type->id,
            'worker_id' => $worker->id,
            'delivery_relation_id' => $relation->id,
            'deliverer_name' => 'Carlos Ramirez',
            'contact_number' => '999111222',
            'created_by' => $user->id,
        ];
    }
}
