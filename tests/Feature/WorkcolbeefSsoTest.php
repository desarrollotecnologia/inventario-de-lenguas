<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkcolbeefSsoTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ab';

    public function test_sso_is_disabled_by_default(): void
    {
        $this->get(route('auth.workcolbeef', ['sso_code' => self::CODE]))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_active_local_user_is_linked_without_changing_its_role(): void
    {
        $this->enableSso();
        $user = User::factory()->create([
            'username' => 'tecnologia@colbeef.com',
            'email' => 'tecnologia@colbeef.com',
            'role' => 'super_admin',
            'must_change_password' => true,
        ]);
        Http::fake(['*' => Http::response([
            'active' => true,
            'sub' => '42',
            'username' => 'tecnologia@colbeef.com',
            'name' => 'Tecnología',
            'email' => 'tecnologia@colbeef.com',
        ])]);

        $this->get(route('auth.workcolbeef', ['sso_code' => self::CODE]))
            ->assertRedirect(route('menu'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'workcolbeef_user_id' => '42',
            'role' => 'super_admin',
            'must_change_password' => false,
        ]);
    }

    public function test_ambiguous_local_identity_is_rejected(): void
    {
        $this->enableSso();
        User::factory()->create([
            'username' => 'primero',
            'email' => 'tecnologia@colbeef.com',
        ]);
        User::factory()->create([
            'username' => 'tecnologia@colbeef.com',
            'email' => 'otro@colbeef.com',
        ]);
        Http::fake(['*' => Http::response([
            'active' => true,
            'sub' => '42',
            'username' => 'tecnologia@colbeef.com',
            'name' => 'Tecnología',
            'email' => 'tecnologia@colbeef.com',
        ])]);

        $this->get(route('auth.workcolbeef', ['sso_code' => self::CODE]))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['workcolbeef_user_id' => '42']);
    }

    private function enableSso(): void
    {
        Config::set('services.workcolbeef', [
            'enabled' => true,
            'base_url' => 'http://workcolbeef.test',
            'exchange_path' => '/api/sso/exchange',
            'client_id' => 'lenguas',
            'client_secret' => 'test-secret',
            'audience' => 'lenguas',
            'timeout' => 5,
        ]);
    }
}
