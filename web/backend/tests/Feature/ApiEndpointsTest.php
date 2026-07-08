<?php

namespace Tests\Feature;

use App\Models\DeliveryRelation;
use App\Models\Management;
use App\Models\MedicalDocument;
use App\Models\MedicalDocumentType;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
        $management = Management::create(['name' => 'Operaciones', 'code' => 'OPER', 'is_active' => true]);
        $sector = Sector::create(['name' => 'Planta', 'code' => 'PLANTA', 'is_active' => true]);
        $worker = Worker::create([
            'dni' => fake()->unique()->numerify('########'),
            'first_name' => 'Carlos',
            'last_name' => 'Ramirez',
            'management_id' => $management->id,
            'sector_id' => $sector->id,
            'is_active' => true,
        ]);
        $type = MedicalDocumentType::create(['name' => 'Descanso Medico', 'code' => fake()->unique()->lexify('DOC????'), 'is_active' => true]);
        $relation = DeliveryRelation::create(['name' => 'Trabajador', 'code' => fake()->unique()->lexify('REL????'), 'is_active' => true]);

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
