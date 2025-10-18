<?php

namespace Tests\Feature;

use App\Models\Cobro;
use App\Models\Unidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Transbank\Webpay\WebpayPlus\Transaction;

class PagoOnlineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
        $residente = Unidad::factory()->create();
        $cobro = Cobro::factory()->create(['unidad_id' => $residente->id, 'estado' => 'pendiente']);

        $this->actingAs($residente, 'residente');

        $response = $this->post(route('portal.pago.iniciar', $cobro));

        $response->assertRedirect('https://mock.webpay.cl/payment?token_ws=mock_token_123');
        $this->assertDatabaseHas('pagos', [
            'cobro_id' => $cobro->id,
            'webpay_token' => 'mock_token_123',
        ]);
    }

    public function test_webpay_confirms_approved_payment_correctly()
    {
        $cobro = Cobro::factory()->create(['estado' => 'pendiente']);
        $pago = $cobro->pagos()->create([
            'unidad_id' => $cobro->unidad_id,
            'monto' => $cobro->monto_total,
            'fecha_pago' => now(),
            'metodo_pago' => 'webpay_pendiente',
            'webpay_token' => 'mock_token_123',
        ]);

        $response = $this->get(route('portal.pago.confirmar', ['token_ws' => 'mock_token_123']));

        $response->assertRedirect(route('portal.dashboard'));
        $response->assertSessionHas('success', '¡Tu pago ha sido procesado exitosamente!');
        $this->assertDatabaseHas('cobros', ['id' => $cobro->id, 'estado' => 'pagado']);
        $this->assertDatabaseHas('pagos', ['id' => $pago->id, 'metodo_pago' => 'webpay_exitoso']);
    }

    public function test_webpay_handles_rejected_payment_correctly()
    {
        $cobro = Cobro::factory()->create(['estado' => 'pendiente']);
        $pago = $cobro->pagos()->create([
            'unidad_id' => $cobro->unidad_id,
            'monto' => $cobro->monto_total,
            'fecha_pago' => now(),
            'metodo_pago' => 'webpay_pendiente',
            'webpay_token' => 'mock_token_rechazado',
        ]);

        $response = $this->get(route('portal.pago.confirmar', ['token_ws' => 'mock_token_rechazado']));

        $response->assertRedirect(route('portal.cobro.show', $cobro->id));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('cobros', ['id' => $cobro->id, 'estado' => 'pendiente']);
        $this->assertDatabaseHas('pagos', ['id' => $pago->id, 'metodo_pago' => 'webpay_rechazado']);
    }
}