<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Las pruebas no dependen de los assets compilados.
         *
         * Sin esto, cualquier test que renderice una página de Inertia falla con «Vite
         * manifest not found» a menos que alguien haya corrido `npm run build` antes — y
         * entonces el resultado de la suite depende de si el compañero de al lado compiló,
         * no de si el código está bien. Un test que falla por una razón ajena a lo que
         * prueba enseña a ignorar los fallos.
         */
        $this->withoutVite();
    }
}
