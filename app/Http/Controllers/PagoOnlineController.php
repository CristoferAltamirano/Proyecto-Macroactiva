<?php

namespace App\Http\Controllers;

use App\Models\Cobro;
use App\Models\Pago;
use App\Services\ContabilidadService;
use App\Services\WebpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PagoOnlineController extends Controller
{
    protected $webpayService;
    protected $contabilidadService;

    public function __construct(WebpayService $webpayService, ContabilidadService $contabilidadService)
    {
        $this->webpayService = $webpayService;
        $this->contabilidadService = $contabilidadService;
    }

    /**
     * Inicia una nueva transacción de pago con Webpay.
     */
    public function iniciar(Request $request, Cobro $cobro)
    {
        $residente = auth()->guard('residente')->user();

        if ($cobro->unidad_id !== $residente->id) {
            abort(403, 'Acceso no autorizado.');
        }

        $pago = Pago::create([
            'cobro_id' => $cobro->id,
            'unidad_id' => $residente->id,
            'monto_pagado' => $cobro->monto_total,
            'fecha_pago' => now(),
            'metodo_pago' => 'webpay_pendiente',
        ]);

        try {
            $redirectUrl = $this->webpayService->startTransaction($cobro);

            // Extraer token de la URL mock para guardar
            if (config('webpay.mock')) {
                parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $queryParams);
                $token = $queryParams['token_ws'] ?? null;
                if ($token) {
                    $pago->webpay_token = $token;
                    $pago->save();
                }
            }

            return redirect($redirectUrl);

        } catch (\Exception $e) {
            Log::error("Error al iniciar pago con Webpay: " . $e->getMessage());
            $pago->delete();
            return redirect()->route('portal.cobro.show', $cobro->id)
                             ->with('error', 'No se pudo iniciar el proceso de pago. Por favor, intenta de nuevo.');
        }
    }

    /**
     * Confirma la transacción después de que el usuario regresa de Webpay.
     */
    public function confirmar(Request $request)
    {
        $token = $request->input('token_ws');

        if (!$token) {
            return redirect()->route('portal.dashboard')->with('error', 'El pago fue cancelado.');
        }

        try {
            $pago = Pago::where('webpay_token', $token)->firstOrFail();
            $result = $this->webpayService->confirmTransaction($token);

            if ($result['status'] === 'approved') {
                $pago->update(['metodo_pago' => 'webpay_exitoso']);
                $pago->cobro->update(['estado' => 'pagado']);
                $this->contabilidadService->registrarPago($pago, $pago->cobro->unidad->grupo->condominio_id);
                return redirect()->route('portal.dashboard')->with('success', '¡Tu pago ha sido procesado exitosamente!');
            } else {
                $pago->update(['metodo_pago' => 'webpay_rechazado']);
                return redirect()->route('portal.cobro.show', $pago->cobro_id)
                                 ->with('error', 'Tu pago fue rechazado. Intenta nuevamente.');
            }

        } catch (\Exception $e) {
            Log::error("Error al confirmar pago con Webpay: " . $e->getMessage());
            return redirect()->route('portal.dashboard')
                             ->with('error', 'Ocurrió un error al confirmar tu pago. Contacta a la administración.');
        }
    }
}