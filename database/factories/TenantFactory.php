<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $nombre = fake()->company();

        return [
            'name' => $nombre,
            // El slug es único y va a ser la URL pública del catálogo: se le pega un
            // sufijo para que dos negocios con nombre parecido no choquen en las pruebas.
            'slug' => Str::slug($nombre).'-'.Str::lower(Str::random(5)),
            'rate_source' => Tenant::TASA_BCV,
            'rounding' => 0,
        ];
    }
}
