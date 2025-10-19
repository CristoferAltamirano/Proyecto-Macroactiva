<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Configuracion;
use App\Models\Condominio;

class ConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_configuracion_page()
    {
        $superAdmin = User::factory()->create(['tipo_usuario' => 'super-admin']);
        $this->actingAs($superAdmin);
        $condominio = Condominio::factory()->create();

        $response = $this->get(route('condominios.edit', $condominio));

        $response->assertStatus(200);
        $response->assertViewIs('admin.condominios.edit');
    }

    public function test_non_super_admin_cannot_access_configuracion_page()
    {
        $user = User::factory()->create(['tipo_usuario' => 'admin']);
        $this->actingAs($user);
        $condominio = Condominio::factory()->create();

        $response = $this->get(route('condominios.edit', $condominio));

        $response->assertStatus(403);
    }

    public function test_super_admin_can_update_configuracion()
    {
        $superAdmin = User::factory()->create(['tipo_usuario' => 'super-admin']);
        $this->actingAs($superAdmin);
        $condominio = Condominio::factory()->create();

        $response = $this->put(route('condominios.update', $condominio), [
            'nombre' => 'Condominio Actualizado',
            'direccion' => 'Nueva Direccion',
        ]);

        $response->assertRedirect(route('condominios.index'));
        $this->assertDatabaseHas('condominios', [
            'id_condominio' => $condominio->id_condominio,
            'nombre' => 'Condominio Actualizado',
        ]);
    }
}