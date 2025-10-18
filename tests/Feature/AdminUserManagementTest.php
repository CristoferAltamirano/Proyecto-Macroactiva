<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['tipo_usuario' => 'super-admin']);
        $this->admin = User::factory()->create(['tipo_usuario' => 'admin']);
    }

    public function test_super_admin_can_access_user_management()
    {
        $this->actingAs($this->superAdmin);
        $response = $this->get(route('users.index'));
        $response->assertStatus(200);
    }

    public function test_admin_cannot_access_user_management()
    {
        $this->actingAs($this->admin);
        $response = $this->get(route('users.index'));
        $response->assertStatus(403);
    }

    public function test_super_admin_can_create_a_new_user()
    {
        $this->actingAs($this->superAdmin);
        $userData = [
            'name' => 'Nuevo Admin',
            'email' => 'nuevo@admin.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tipo_usuario' => 'admin',
        ];
        $response = $this->post(route('users.store'), $userData);
        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['email' => 'nuevo@admin.com']);
    }

    public function test_super_admin_can_edit_a_user()
    {
        $this->actingAs($this->superAdmin);
        $userToEdit = User::factory()->create();
        $updatedData = [
            'name' => 'Nombre Actualizado',
            'email' => $userToEdit->email,
            'tipo_usuario' => 'admin',
        ];
        $response = $this->put(route('users.update', $userToEdit), $updatedData);
        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['id' => $userToEdit->id, 'name' => 'Nombre Actualizado']);
    }

    public function test_super_admin_can_delete_a_user()
    {
        $this->actingAs($this->superAdmin);
        $userToDelete = User::factory()->create();
        $response = $this->delete(route('users.destroy', $userToDelete));
        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);
    }

    public function test_user_cannot_delete_themselves()
    {
        $this->actingAs($this->superAdmin);
        $response = $this->delete(route('users.destroy', $this->superAdmin));
        $response->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id]);
    }
}