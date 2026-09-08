<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlNegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un pago reportado por el cliente. El monto queda congelado en las dos monedas.
 */
class Payment extends Model
{
    use PerteneceAlNegocio;

    public const REPORTADO = 'reportado';

    public const CONFIRMADO = 'confirmado';

    public const RECHAZADO = 'rechazado';

    protected $fillable = [
        'tenant_id', 'plan_id', 'payment_account_id', 'method_snapshot',
        'amount_usd', 'amount_bs', 'exchange_rate',
        'reference', 'paid_on', 'status', 'notes', 'confirmed_by', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'amount_bs' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'paid_on' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function scopePendientes($query)
    {
        return $query->where('status', self::REPORTADO);
    }
}
