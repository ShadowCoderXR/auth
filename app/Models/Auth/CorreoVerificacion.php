<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $id)
 * @method static create(array $array)
 */
class CorreoVerificacion extends Model
{
    use HasFactory;

    protected $table = 'correo_verificacion';
    public $timestamps = false;

    protected $fillable = [
        'codigo_correo',
        'correo',
        'estatus_verificacion',
        'fecha_creacion',
        'fecha_verificacion',
        'conteo'
    ];

    protected $casts = [
        'fecha_creacion' => 'datetime',
        'fecha_verificacion' => 'datetime',
    ];
}
