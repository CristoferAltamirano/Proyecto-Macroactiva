<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Configuracion;

class ConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_configuracion_page()
    {
        $superAdmin = User::factory()->create(['role' => 'super-admin']);
        $this->actingAs($superAdmin);

        $response = $this->get(route('configuracion.edit'));

        $response->assertStatus(200);
        $response->assertViewIs('configuracion.edit');
    }

    public function test_non_super_admin_cannot_access_configuracion_page()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->get(route('configuracion.edit'));

        $response->assertStatus(403);
    }

    public function test_super_admin_can_update_configuracion()
    {
        $superAdmin = User::factory()->create(['role' => 'super-admin']);
        $this->actingAs($superAdmin);

        $response = $this->post(route('configuracion.update'), [
            'fondo_reserva_porcentaje' => '12.5',
            'interes_mora_mensual' => '2.0',
        ]);

        $response->assertRedirect(route('configuracion.edit'));
        $this->assertDatabaseHas('configuraciones', [
            'clave' => 'fondo_reserva_porcentaje',
            'valor' => '12.5',
        ]);
        $this->assertDatabaseHas('configuraciones', [
            'clave' => 'interes_mora_mensual',
            'valor' => '2.0',
        ]);
    }
}