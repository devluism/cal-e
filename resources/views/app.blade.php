<!DOCTYPE html>
<html lang="es" class="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b1220">

    <title inertia>{{ config('app.name', 'Norte') }}</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/norte-iso.svg">

    {{-- El tema se aplica antes de pintar: si esperara a React, la pantalla de entrada
         parpadearía del tema equivocado al correcto, que es lo primero que vería un
         usuario nuevo. Debe usar la misma clave que hooks/use-theme.js. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('norte-theme');
                if (t !== 'light' && t !== 'dark') t = 'dark';
                document.documentElement.classList.toggle('dark', t === 'dark');
                document.documentElement.style.colorScheme = t;
            } catch (e) { /* modo privado: se queda con el oscuro del atributo */ }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @routes
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>
