<?php

namespace Tests\Feature;

use App\Models\Cobro;
use App\Models\Unidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Transbank\Webpay\WebpayPlus\Transaction;
use App\Models\CatCobroEstado;

class PagoOnlineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CatCobroEstado::factory()->create(['id_cobro_estado' => 1, 'codigo' => 'pendiente']);
        CatCobroEstado::factory()->create(['id_cobro_estado' => 2, 'codigo' => 'pagado']);

        // Mock de la transacción de Webpay para no hacer llamadas reales
        $this->mock(Transaction::class, function ($mock) {
            $mock->shouldReceive('create')->andReturn((object)[
                'token' => 'mock_token_123',
                'url' => 'https://mock.webpay.cl/payment',
            ]);
            $mock->shouldReceive('commit')->with('mock_token_123')->andReturn((object)[
                'isApproved' => true,
                'response_code' => 0,
            ]);
            $mock->shouldReceive('commit')->with('mock_token_rechazado')->andReturn((object)[
                'isApproved' => false,
                'response_code' => -1,
            ]);
        });
    }

    public function test_residente_can_initiate_webpay_payment()
    {
        $unidad = Unidad::factory()->create();
        $residente = \App\Models\User::factory()->create([
            'id_unidad' => $unidad->id_unidad,
            'tipo_usuario' => 'residente',
        ]);
        $cobro = Cobro::factory()->create(['id_unidad' => $unidad->id_unidad, 'id_cobro_estado' => 1]);

        $this->actingAs($residente, 'residente');

        $response = $this->post(route('portal.pago.iniciar', $cobro));

        $response->assertRedirect('https://mock.webpay.cl/payment');
    }

    public function test_webpay_confirms_approved_payment_correctly()
    {
        $cobro = Cobro::factory()->create(['id_cobro_estado' => 1]);
        $pago = \App\Models\Pago::factory()->forCobro($cobro)->create(['webpay_token' => 'mock_token_123']);

        $response = $this->get(route('portal.pago.confirmar', ['token_ws' => 'mock_token_123']));

        $response->assertRedirect(route('portal.dashboard'));
        $response->assertSessionHas('success', '¡Tu pago ha sido procesado exitosamente!');
        $this->assertDatabaseHas('cobros', ['id_cobro' => $cobro->id_cobro, 'id_cobro_estado' => 2]);
        $this->assertDatabaseHas('pagos', ['id_pago' => $pago->id_pago, 'id_metodo_pago' => 1]);
    }

    public function test_webpay_handles_rejected_payment_correctly()
    {
        $cobro = Cobro::factory()->create(['id_cobro_estado' => 1]);
        $pago = \App\Models\Pago::factory()->forCobro($cobro)->create(['webpay_token' => 'mock_token_rechazado']);

        $response = $this->get(route('portal.pago.confirmar', ['token_ws' => 'mock_token_rechazado']));

        $response->assertRedirect(route('portal.cobro.show', $cobro->id_cobro));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('cobros', ['id_cobro' => $cobro->id_cobro, 'id_cobro_estado' => 1]);
        $this->assertDatabaseHas('pagos', ['id_pago' => $pago->id_pago, 'id_metodo_pago' => 1]);
    }
}