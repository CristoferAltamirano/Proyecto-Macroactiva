<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Unidad;
use App\Models\Cobro;
use App\Models\Gasto;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    public function test_morosidad_report_loads_correctly()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Unidad::factory()->create()->cobros()->create(Cobro::factory()->make(['estado' => 'pendiente'])->toArray());

        $response = $this->get(route('reportes.morosidad'));

        $response->assertStatus(200);
        $response->assertViewIs('reportes.morosidad');
        $response->assertViewHas('reporte');
    }

    public function test_gastos_mensuales_report_loads_correctly()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Gasto::factory()->create();

        $response = $this->get(route('reportes.gastos'));

        $response->assertStatus(200);
        $response->assertViewIs('reportes.gastos');
        $response->assertViewHasAll(['gastos', 'totalGastos', 'mes', 'ano']);
    }
}