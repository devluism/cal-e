<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * La tasa del día. Compartida por toda la plataforma — ver la migración.
 */
class ExchangeRate extends Model
{
    use HasFactory;

    public const BCV = 'bcv';

    public const MANUAL = 'manual';

    protected $fillable = ['price', 'source', 'provider'];

    protected function casts(): array
    {
        return ['price' => 'decimal:4'];
    }

    /** La más reciente, o null si nunca se ha cargado ninguna. */
    public static function vigente(): ?self
    {
        return static::latest('created_at')->latest('id')->first();
    }
}
