<?php

namespace Tests\Feature;

use App\Models\Condominio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_configuracion_page()
    {
        $superAdmin = User::factory()->create(['tipo_usuario' => 'super-admin']);
        $condominio = Condominio::factory()->create();
        $this->actingAs($superAdmin);

        $response = $this->get(route('condominios.edit', $condominio));

        $response->assertStatus(200);
        $response->assertViewIs('condominios.edit');
    }

    public function test_non_super_admin_cannot_access_configuracion_page()
    {
        $user = User::factory()->create(['tipo_usuario' => 'admin']);
        $condominio = Condominio::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('condominios.edit', $condominio));

        $response->assertStatus(403);
    }

    public function test_super_admin_can_update_configuracion()
    {
        $superAdmin = User::factory()->create(['tipo_usuario' => 'super-admin']);
        $condominio = Condominio::factory()->create();
        $this->actingAs($superAdmin);

        $response = $this->put(route('condominios.update', $condominio), [
            'nombre' => 'New Name',
            'direccion' => 'New Address',
        ]);

        $response->assertRedirect(route('condominios.index'));
        $this->assertDatabaseHas('condominios', [
            'id' => $condominio->id,
            'nombre' => 'New Name',
            'direccion' => 'New Address',
        ]);
    }
}