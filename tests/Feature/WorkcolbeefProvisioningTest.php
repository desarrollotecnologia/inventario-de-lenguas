<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WorkcolbeefProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = '0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.workcolbeef.provisioning_secret', self::SECRET);
    }

    public function test_creates_and_idempotently_updates_a_user(): void
    {
        $payload = $this->payload();
        $created = $this->signedPost($payload);
        $created->assertCreated()->assertJson([
            'ok' => true,
            'role' => 'logistico',
            'active' => true,
            'created' => true,
        ]);

        $payload['name'] = 'Nombre actualizado';
        $payload['role'] = 'supervisor';
        $updated = $this->signedPost($payload);
        $updated->assertOk()->assertJson([
            'local_user_id' => $created->json('local_user_id'),
            'role' => 'supervisor',
            'created' => false,
        ]);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'name' => 'Nombre actualizado',
            'username' => 'central@colbeef.com',
            'role' => 'supervisor',
            'must_change_password' => false,
        ]);
    }

    public function test_preserves_an_existing_super_admin_role(): void
    {
        User::factory()->create([
            'username' => 'local',
            'email' => 'central@colbeef.com',
            'role' => 'super_admin',
        ]);

        $this->signedPost($this->payload())
            ->assertOk()
            ->assertJson(['role' => 'super_admin']);
    }

    public function test_rejects_invalid_signature_and_ambiguous_identity(): void
    {
        $this->postJson('/api/internal/workcolbeef/users/sync', $this->payload())
            ->assertUnauthorized();

        User::factory()->create(['username' => 'central@colbeef.com', 'email' => 'uno@colbeef.com']);
        User::factory()->create(['username' => 'otro', 'email' => 'central@colbeef.com']);

        $this->signedPost($this->payload())->assertConflict();
    }

    private function payload(): array
    {
        return [
            'workcolbeef_user_id' => '42',
            'username' => 'central@colbeef.com',
            'name' => 'Usuario Central',
            'email' => 'central@colbeef.com',
            'role' => 'logistico',
            'permissions' => (object) [],
            'active' => true,
        ];
    }

    private function signedPost(array $payload)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();

        return $this->call(
            'POST',
            '/api/internal/workcolbeef/users/sync',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WORKCOLBEEF_TIMESTAMP' => $timestamp,
                'HTTP_X_WORKCOLBEEF_SIGNATURE' => hash_hmac('sha256', "{$timestamp}.{$body}", self::SECRET),
            ],
            $body
        );
    }
}
