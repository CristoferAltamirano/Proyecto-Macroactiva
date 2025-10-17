<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Unidad;
use App\Models\Gasto;
use App\Mail\NuevoCobroDisponible;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class GeneracionCobroTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_queued_when_cobro_is_generated()
    {
        // Preparamos el entorno
        Mail::fake();
        $admin = User::factory()->create();
        $unidad = Unidad::factory()->create(['email' => 'residente@test.com']);
        Gasto::factory()->create([
            'periodo_gasto' => Carbon::now()->startOfMonth(),
            'tipo' => 'ordinario',
            'monto' => 100000
        ]);

        // Actuamos como el admin y generamos el cobro
        $this->actingAs($admin);
        $this->post(route('generacion.generar'), [
            'periodo' => Carbon::now()->format('Y-m')
        ]);

        // Verificamos que se encoló un email para la unidad correcta
        Mail::assertQueued(NuevoCobroDisponible::class, function ($mail) use ($unidad) {
            return $mail->hasTo($unidad->email);
        });
    }
}