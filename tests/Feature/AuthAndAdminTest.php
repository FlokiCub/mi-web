<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Ingreso al Sistema');
    }

    public function test_admin_can_login_with_username_and_password(): void
    {
        $admin = User::create([
            'name' => 'Director Test',
            'username' => 'director_test',
            'email' => 'director@scgi.cu',
            'password' => Hash::make('1234'),
            'role' => UserRole::DIRECTOR,
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'login' => 'director_test',
            'password' => '1234',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_guest_cannot_access_admin_panel(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/login');
    }

    public function test_non_director_user_cannot_access_admin_panel(): void
    {
        $user = User::create([
            'name' => 'Chofer User',
            'username' => 'chofer1',
            'email' => 'chofer@scgi.cu',
            'password' => Hash::make('password123'),
            'role' => UserRole::LOGISTICA_CHOFER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(403);
    }

    public function test_director_can_view_users_list_and_create_user(): void
    {
        $admin = User::create([
            'name' => 'Director Master',
            'username' => 'director_master',
            'email' => 'master@scgi.cu',
            'password' => Hash::make('password123'),
            'role' => UserRole::DIRECTOR,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get('/admin/users')->assertStatus(200)->assertSee('Listado de Usuarios');

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Nuevo Inspector',
            'username' => 'inspector1',
            'email' => 'inspector@scgi.cu',
            'password' => 'secret123',
            'role' => UserRole::ADUANA->value,
            'is_active' => '1',
        ]);

        $response->assertRedirect('/admin/users');
        $this->assertDatabaseHas('users', [
            'email' => 'inspector@scgi.cu',
            'role' => UserRole::ADUANA->value,
        ]);
    }
}
