{{--
    La página que se ve cuando algo no se pudo (mantenimiento, no existe, sin
    permiso…), con el diseño de las páginas de error del colegio (las del
    inventario SIBIP) y la marca de CATA-Recibo. Todo va dentro del archivo
    —estilos y logo—: se tiene que ver aunque el servidor no pueda entregar
    nada más. La copia fija para cuando PHP no responde está en
    docker/web/mantenimiento.html, y la de la app en la ruta /mantenimiento.

    Recibe: $codigo, $titulo, $mensajes (lista), y opcionalmente
    $boton ['texto', 'url'] y $recargarCada (segundos).
--}}
@php
    $inicio ??= rtrim((string) config('app.frontend_url'), '/') ?: '/';
    $logo = 'data:image/png;base64,' . base64_encode((string) @file_get_contents(public_path('pdf/marca-agua.png')));
    $boton ??= ['texto' => 'Ir al inicio', 'url' => $inicio];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    @if (!empty($recargarCada))
        <meta http-equiv="refresh" content="{{ (int) $recargarCada }}">
    @endif
    <title>{{ $titulo }} · CATA-Recibo</title>
    <style>
        :root { --marino-900: #0f2a55; --marino-800: #1b4282; --marino-200: #b9cbe8; --marino-50: #eef3fb; --naranja: #ec7a3a; --texto: #334155; --suave: #64748b; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px 16px;
               background: var(--marino-50); color: var(--texto); font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif; }
        .marca { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; color: var(--marino-900); text-decoration: none; }
        .marca img { height: 40px; width: auto; }
        .marca span { border-left: 1px solid var(--marino-200); padding-left: 12px; font-size: 12px; color: var(--suave); line-height: 1.3; }
        .marca strong { display: block; font-size: 17px; color: var(--marino-900); letter-spacing: .3px; }
        .tarjeta { width: 100%; max-width: 480px; background: #fff; border: 1px solid var(--marino-200); border-radius: 24px; padding: 32px 24px; text-align: center;
                   box-shadow: 0 10px 30px rgba(15, 42, 85, .08); }
        .codigo { display: inline-flex; align-items: center; justify-content: center; min-width: 88px; height: 44px; padding: 0 16px; border-radius: 999px;
                  background: var(--marino-50); color: var(--marino-800); font-weight: 800; font-size: 18px; letter-spacing: 1px; }
        h1 { margin: 20px 0 8px; font-size: 22px; color: var(--marino-900); }
        p { margin: 0 0 8px; font-size: 15px; line-height: 1.6; }
        .acciones { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-top: 24px; }
        .boton { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 20px; border-radius: 12px; font-weight: 600;
                 font-size: 14px; text-decoration: none; border: 1px solid var(--marino-200); color: var(--marino-800); background: #fff; }
        .boton.primario { background: var(--marino-800); border-color: var(--marino-800); color: #fff; }
        .boton:focus-visible { outline: 3px solid var(--marino-200); outline-offset: 2px; }
        .aviso { margin-top: 16px; font-size: 13px; color: var(--suave); }
        footer { margin-top: 24px; font-size: 12px; color: var(--suave); text-align: center; }
        @media (max-width: 480px) {
            .marca { flex-direction: column; gap: 8px; text-align: center; }
            .marca span { border-left: none; padding-left: 0; }
            .tarjeta { padding: 28px 18px; border-radius: 20px; }
            h1 { font-size: 20px; }
        }
    </style>
</head>
<body>
    <a class="marca" href="{{ $inicio }}">
        <img src="{{ $logo }}" alt="CATA">
        <span><strong>CATA-Recibo</strong>Colegio Adventista Túpac Amaru</span>
    </a>

    <main class="tarjeta">
        <span class="codigo">{{ $codigo }}</span>
        <h1>{{ $titulo }}</h1>
        @foreach ($mensajes as $mensaje)
            <p>{{ $mensaje }}</p>
        @endforeach

        <div class="acciones">
            <a class="boton primario" href="{{ $boton['url'] }}">{{ $boton['texto'] }}</a>
        </div>
        @if (!empty($recargarCada))
            <p class="aviso">Esta página se vuelve a intentar sola cada {{ (int) $recargarCada }} segundos.</p>
        @endif
    </main>

    <footer>Boletas, CTS y documentos del personal · Juliaca</footer>
</body>
</html>
