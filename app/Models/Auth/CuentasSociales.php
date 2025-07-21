<?php

namespace App\Models\Auth;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static where(string $string, string $string1)
 * @method static create(array $array)
 */
class CuentasSociales extends Model
{
    use HasFactory;

    protected $table = 'cuentas_sociales';

    protected $fillable = [
        'usuario_id',
        'proveedor',
        'proveedor_id',
    ];

    /**
     * Define the relationship with the Usuario model.
     *
     * @return BelongsTo
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
