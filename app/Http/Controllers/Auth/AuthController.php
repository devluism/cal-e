<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Entrada y alta de cuentas.
 *
 * El registro **crea el negocio y el usuario en la misma transacción**: en Norte no existe
 * un usuario sin negocio. Dejar que se registre alguien y pedirle después "ahora crea tu
 * negocio" es una pantalla más antes de que el producto le haya dado nada, y el público de
 * Norte abandona en esa pantalla. Con un solo formulario ya tiene cuenta, negocio y puede
 * cargar su primer producto.
 */
class AuthController extends Controller
{
    public function mostrarLogin()
    {
        return inertia('Auth/Login', ['status' => session('status')]);
    }

    public function entrar(Request $request)
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Escribe tu correo.',
            'email.email' => 'Ese correo no parece válido.',
            'password.required' => 'Escribe tu contraseña.',
        ]);

        if (! Auth::attempt($datos, $request->boolean('remember'))) {
            // Un solo mensaje para correo y clave: decir cuál de los dos falló le confirma
            // a quien prueba correos ajenos que la cuenta existe.
            return back()->withErrors(['email' => 'El correo o la contraseña no coinciden.']);
        }

        if (! $request->user()->active) {
            Auth::logout();

            return back()->withErrors(['email' => 'Esta cuenta está desactivada.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('panel'));
    }

    public function mostrarRegistro()
    {
        return inertia('Auth/Registro');
    }

    public function registrar(Request $request)
    {
        $datos = $request->validate([
            'negocio' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ], [
            'negocio.required' => 'Ponle nombre a tu negocio.',
            'name.required' => 'Escribe tu nombre.',
            'email.required' => 'Escribe tu correo.',
            'email.unique' => 'Ya hay una cuenta con ese correo.',
            'password.required' => 'Escribe una contraseña.',
        ]);

        $usuario = DB::transaction(function () use ($datos) {
            $negocio = Tenant::create([
                'name' => $datos['negocio'],
                'slug' => $this->slugLibre($datos['negocio']),
                // El BCV por defecto: es lo que espera la mayoría, y quien use otra tasa
                // la cambia en ajustes. Arrancar preguntando sería una decisión más antes
                // del primer producto.
                'rate_source' => Tenant::TASA_BCV,
            ]);

            return User::create([
                'tenant_id' => $negocio->id,
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => $datos['password'],
                'role' => 'owner',
                'active' => true,
            ]);
        });

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->route('panel');
    }

    public function salir(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Entrada con Google.
     *
     * Todavía no está conectada: `laravel/socialite` no soporta Laravel 13 al día de hoy.
     * El botón se deja en la interfaz porque el público objetivo no siempre recuerda
     * contraseñas y va a ser la vía principal — pero avisa en vez de reventar, que es lo
     * mismo que hace IGA cuando falta el router o la clave de IA.
     */
    public function oauth(string $proveedor)
    {
        return redirect()->route('login')->withErrors([
            'email' => 'Entrar con '.ucfirst($proveedor).' todavía no está disponible. Usa tu correo por ahora.',
        ]);
    }

    /** Un slug que no choque: el del negocio va a ser su URL pública de catálogo. */
    private function slugLibre(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'negocio';
        $slug = $base;
        $i = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
