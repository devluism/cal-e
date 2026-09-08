<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A dónde le paga el cliente. En tabla y no en config: ver la migración. */
class PaymentAccount extends Model
{
    protected $fillable = [
        'method', 'label', 'bank', 'id_number', 'phone', 'holder', 'extra', 'active', 'position',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function scopeActivas($query)
    {
        return $query->where('active', true)->orderBy('position')->orderBy('id');
    }
}
