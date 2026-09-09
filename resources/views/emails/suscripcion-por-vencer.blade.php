<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0; padding:0; background:#f4f4f5; font-family:Arial, Helvetica, sans-serif; color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; padding:32px; max-width:480px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 4px; font-size:13px; letter-spacing:.05em; text-transform:uppercase; color:#71717a;">Norte</p>

                            @if ($hito === 'vencida')
                                <h1 style="margin:0 0 16px; font-size:20px;">Tu suscripción venció</h1>
                                <p style="margin:0 0 16px; font-size:15px; line-height:1.5;">
                                    Hola, {{ $negocio->name }}. Tu suscripción a Norte venció y tu catálogo
                                    dejó de calcular precios con la tasa del día — sigue intacto, pero esa
                                    parte se pausó hasta que renueves.
                                </p>
                            @else
                                <h1 style="margin:0 0 16px; font-size:20px;">
                                    Te {{ $dias === 1 ? 'queda' : 'quedan' }} {{ $dias }} día{{ $dias === 1 ? '' : 's' }}
                                </h1>
                                <p style="margin:0 0 16px; font-size:15px; line-height:1.5;">
                                    Hola, {{ $negocio->name }}. Tu suscripción a Norte está por vencer. Renueva
                                    antes para que tus precios sigan calculándose solos con la tasa de cada día.
                                </p>
                            @endif

                            <p style="margin:0 0 24px;">
                                <a href="{{ route('suscripcion') }}" style="display:inline-block; background:#0a1e45; color:#ffffff; text-decoration:none; padding:10px 20px; border-radius:8px; font-size:14px;">
                                    Ir a mi suscripción
                                </a>
                            </p>

                            <p style="margin:0; font-size:12px; color:#a1a1aa;">
                                Este es un aviso automático. Si ya pagaste, puede que todavía no lo hayamos
                                confirmado — no hace falta que hagas nada más.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
