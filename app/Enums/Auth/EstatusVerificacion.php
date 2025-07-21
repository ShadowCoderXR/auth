<?php

namespace App\Enums\Auth;

class EstatusVerificacion
{
    const COMPLETADO = 'completado';
    const PENDIENTE = 'pendiente';
    const INVALIDO = 'invalido';
    const EXPIRADO = 'expirado';

    public static function getEstatusVerificacion(): array
    {
        return [
            self::COMPLETADO,
            self::PENDIENTE,
            self::INVALIDO,
            self::EXPIRADO,
        ];
    }
}
