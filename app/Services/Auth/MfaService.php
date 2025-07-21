<?php

namespace App\Services\Auth;

use App\Enums\Auth\EstatusVerificacion;
use App\Mail\VerificaCorreo;
use App\Models\Auth\CorreoVerificacion;
use App\Models\Auth\CuentasSociales;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Exception;
use Laravel\Socialite\Facades\Socialite;

class MfaService
{
    /**
     * Genera un nuevo código para un usuario, lo guarda y lo envía por correo.
     * También invalida cualquier código pendiente anterior.
     *
     * @param Usuario $usuario
     * @param Request $request
     * @return void
     */
    public function generarYEnviarCodigo(Usuario $usuario, Request $request): void
    {
        CorreoVerificacion::where('correo', $usuario->email)
            ->where('estatus_verificacion', EstatusVerificacion::PENDIENTE)
            ->update(['estatus_verificacion' => EstatusVerificacion::INVALIDO]);

        $codigo = random_int(100000, 999999);

        CorreoVerificacion::create([
            'codigo_correo' => $codigo,
            'correo' => $usuario->email,
            'estatus_verificacion' => EstatusVerificacion::PENDIENTE,
            'fecha_creacion' => Carbon::now()->format('Ymd H:i:s')
        ]);

        Mail::to($usuario->email)->send(new VerificaCorreo($codigo));
    }

    /**
     * Verifica el código proporcionado por el usuario contra las reglas de negocio.
     *
     * @param Usuario $usuario
     * @param string $codigoIntroducido
     * @return void
     * @throws Exception
     */
    public function verificarCodigo(Usuario $usuario, string $codigoIntroducido): void
    {
        $codigoVerificacion = CorreoVerificacion::where('correo', $usuario->email)
            ->latest('fecha_creacion')
            ->first();

        if (!$codigoVerificacion) {
            throw new Exception('No se ha generado ningún código. Por favor, intenta iniciar sesión de nuevo.');
        }

        if ($codigoVerificacion->estatus_verificacion === EstatusVerificacion::INVALIDO) {
            throw new Exception('Código inválido. Por favor, solicita un nuevo código.');
        }
        if ($codigoVerificacion->estatus_verificacion === EstatusVerificacion::EXPIRADO) {
            throw new Exception('Código expirado. Por favor, solicita un nuevo código.');
        }
        if ($codigoVerificacion->estatus_verificacion === EstatusVerificacion::COMPLETADO) {
            throw new Exception('Este código ya fue utilizado. Por favor, inicia sesión de nuevo.');
        }

        if ($codigoVerificacion->codigo_correo !== $codigoIntroducido) {
            $codigoVerificacion->increment('conteo');
            $intentos_restantes = 3 - $codigoVerificacion->conteo;
            $mensaje = "Código incorrecto. Te quedan {$intentos_restantes} intento(s).";

            if ($codigoVerificacion->conteo >= 3) {
                $codigoVerificacion->estatus_verificacion = EstatusVerificacion::INVALIDO;
                $mensaje = 'Código incorrecto. Has agotado tus intentos y el código ha sido invalidado.';
            }
            $codigoVerificacion->save();
            throw new Exception($mensaje);
        }

        if (Carbon::parse($codigoVerificacion->fecha_creacion)->addMinutes(5)->isPast()) {
            $codigoVerificacion->estatus_verificacion = EstatusVerificacion::EXPIRADO;
            $codigoVerificacion->save();
            throw new Exception('El código es correcto, pero ha expirado. Por favor, solicita uno nuevo.');
        }

        $codigoVerificacion->estatus_verificacion = EstatusVerificacion::COMPLETADO;
        $codigoVerificacion->fecha_verificacion = now();
        $codigoVerificacion->save();
    }

    /**
     * Redirecciona al usuario al proveedor de autenticación (Google).
     *
     * @return RedirectResponse
     */
    public function redireccionarAProveedor(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtiene el usuario autenticado desde Google.
     *
     * @return Usuario
     * @throws Exception
     */
    public function obtenerUsuarioDesdeGoogle(): Usuario
    {
        $googleUser = Socialite::driver('google')->user();

        $cuentaSocial = CuentasSociales::where('proveedor', 'google')
            ->where('proveedor_id', $googleUser->getId())
            ->first();

        if ($cuentaSocial) {
            return $cuentaSocial->usuario;
        }

        $usuario = Usuario::where('email', $googleUser->getEmail())->first();

        if (!$usuario) {
            throw new Exception('Tu cuenta de Google no está registrada en nuestro sistema.');
        }

        CuentasSociales::create([
            'usuario_id' => $usuario->id,
            'proveedor' => 'google',
            'proveedor_id' => $googleUser->getId(),
        ]);

        return $usuario;
    }
}
