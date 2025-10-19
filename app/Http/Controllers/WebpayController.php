<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Transbank\Webpay\WebpayPlus; // Importa la clase principal
use Transbank\Webpay\WebpayPlus\Transaction; // Importa la clase de Transacción
use App\Models\Cobro; // Asumimos que quieres pagar un cobro

class WebpayController extends Controller
{
    public function __construct()
    {
        // Configuramos Webpay para el entorno de pruebas (integración)
        if (app()->environment('local')) {
            WebpayPlus::configureForIntegration(config('services.transbank.webpay_plus_commerce_code'), config('services.transbank.webpay_plus_api_key'));
        } else {
            // En producción, usarías esto:
            // WebpayPlus::configureForProduction(config('services.transbank.webpay_plus_commerce_code'), config('services.transbank.webpay_plus_api_key'));
        }
    }

    /**
     * Inicia una transacción de pago para un Cobro específico.
     */
    public function iniciarPago(Request $request, Cobro $cobro)
    {
        $monto = $cobro->monto_total;
        $ordenDeCompra = 'cobro_' . $cobro->id . '_' . time();
        $sessionId = session()->getId();
        $urlDeRetorno = route('pago.confirmar');

        $transaccion = (new Transaction)->create($ordenDeCompra, $sessionId, $monto, $urlDeRetorno);

        // Guardamos el token y el ID del cobro para verificarlo después
        session([
            'webpay_token' => $transaccion->getToken(),
            'cobro_id_en_proceso' => $cobro->id,
        ]);

        return view('webpay.redirect', ['transaccion' => $transaccion]);
    }

    /**
     * Confirma la transacción después de que el usuario regresa de Webpay.
     */
    public function confirmarPago(Request $request)
    {
        $token = $request->input('token_ws');

        if ($token !== session('webpay_token')) {
            abort(403, 'Token de Webpay inválido.');
        }

        $response = (new Transaction)->commit($token);

        if ($response->isApproved()) {
            // La transacción fue exitosa
            $cobroId = session('cobro_id_en_proceso');
            $cobro = Cobro::find($cobroId);

            if ($cobro) {
                $cobro->estado = 'pagado';
                $cobro->save();
            }

            // Limpiamos los datos de la sesión
            session()->forget(['webpay_token', 'cobro_id_en_proceso']);

            return view('webpay.exito', ['response' => $response]);
        }

        // La transacción falló
        session()->forget(['webpay_token', 'cobro_id_en_proceso']);
        return view('webpay.fracaso', ['response' => $response]);
    }
}