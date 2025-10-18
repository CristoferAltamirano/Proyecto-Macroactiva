<?php

namespace App\Http\Controllers;

use App\Models\Cobro;
use App\Models\Pago;
use App\Services\ContabilidadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Transbank\Webpay\WebpayPlus\Transaction;

class PagoOnlineController extends Controller
{
    public function __construct()
    {
        // Configura Transbank para usar las credenciales del .env
        // El SDK moderno lo hace automáticamente, pero es una buena práctica asegurarse.
        if (config('app.env') === 'production') {
            Transaction::configureForProduction(
                config('services.transbank.webpay_plus_commerce_code'),
                config('services.transbank.webpay_plus_api_key')
            );
        } else {
            Transaction::configureForIntegration();
        }
    }

    /**
     * Inicia una nueva transacción de pago con Webpay.
     */
    public function iniciar(Request $request, Cobro $cobro)
    {
        $residente = auth()->guard('residente')->user();

        // Seguridad: Verificar que el cobro pertenece al residente
        if ($cobro->unidad_id !== $residente->id) {
            abort(403, 'Acceso no autorizado.');
        }

        // Crear un registro de pago PENDIENTE
        $pago = Pago::create([
            'cobro_id' => $cobro->id,
            'unidad_id' => $residente->id,
            'monto' => $cobro->monto_total,
            'fecha_pago' => now(),
            'metodo_pago' => 'webpay_pendiente',
        ]);

        $buyOrder = 'PAGO_' . $pago->id;
        $sessionId = session()->getId();
        $amount = $pago->monto;
        $returnUrl = route('portal.pago.confirmar');

        try {
            $response = (new Transaction)->create($buyOrder, $sessionId, $amount, $returnUrl);

            // Guardar el token para la verificación
            $pago->webpay_token = $response->getToken();
            $pago->save();

            // Redirigir al usuario al portal de pagos de Webpay
            return redirect($response->getUrl() . '?token_ws=' . $response->getToken());

        } catch (\Exception $e) {
            Log::error("Error al iniciar pago con Webpay: " . $e->getMessage());
            $pago->delete(); // Eliminar el intento de pago fallido
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
            // El usuario canceló el pago en el portal de Webpay
            return redirect()->route('portal.dashboard')->with('error', 'El pago fue cancelado.');
        }

        try {
            $response = (new Transaction)->commit($token);
            $pago = Pago::where('webpay_token', $token)->firstOrFail();

            if ($response->isApproved()) {
                // Pago APROBADO
                $pago->update(['metodo_pago' => 'webpay_exitoso']);

                // Actualizar el estado del cobro asociado
                $pago->cobro->update(['estado' => 'pagado']);

                // Registrar en la contabilidad
                (new ContabilidadService())->registrarPago($pago);

                return redirect()->route('portal.dashboard')->with('success', '¡Tu pago ha sido procesado exitosamente!');
            } else {
                // Pago RECHAZADO
                $pago->update(['metodo_pago' => 'webpay_rechazado']);
                return redirect()->route('portal.cobro.show', $pago->cobro_id)
                                 ->with('error', 'El pago fue rechazado por el banco. Por favor, intenta con otro método.');
            }

        } catch (\Exception $e) {
            Log::error("Error al confirmar pago con Webpay: " . $e->getMessage());
            return redirect()->route('portal.dashboard')
                             ->with('error', 'Ocurrió un error al confirmar tu pago. Contacta a la administración.');
        }
    }
}