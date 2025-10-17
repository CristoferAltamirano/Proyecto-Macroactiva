<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Unidad;
use App\Models\Cobro;

class PortalResidenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_residente_can_view_dashboard()
    {
        // Creamos una unidad que actuará como residente
        $residente = Unidad::factory()->create();

        // Creamos algunos cobros para esta unidad
        Cobro::factory()->count(3)->create([
            'unidad_id' => $residente->id,
            'estado' => 'pendiente',
            'monto_total' => 10000
        ]);

        // Autenticamos a la unidad como el residente
        $this->actingAs($residente, 'residente');

        // Accedemos a la ruta del dashboard
        $response = $this->get(route('portal.dashboard'));

        // Verificamos que la respuesta es exitosa
        $response->assertStatus(200);

        // Verificamos que se está usando la vista correcta
        $response->assertViewIs('portal.dashboard');

        // Verificamos que la vista tiene las variables que necesita
        $response->assertViewHasAll([
            'unidad',
            'cobros',
            'saldo_total_pendiente'
        ]);

        // Verificamos que el cálculo del saldo pendiente es correcto
        $response->assertViewHas('saldo_total_pendiente', 30000);
    }

    public function test_guest_is_redirected_from_dashboard()
    {
        // Accedemos a la ruta del dashboard sin estar autenticados
        $response = $this->get(route('portal.dashboard'));

        // Verificamos que somos redirigidos a la página de login del portal
        $response->assertRedirect(route('portal.login'));
    }
}