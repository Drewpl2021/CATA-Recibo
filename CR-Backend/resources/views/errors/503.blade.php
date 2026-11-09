{{-- En mantenimiento. Ver errors/plantilla. --}}
@include('errors.plantilla', [
    'codigo' => '503',
    'titulo' => 'En mantenimiento',
    'mensajes' => ['CATA-Recibo se está actualizando y vuelve en unos minutos.', 'Tus boletas, hojas y documentos están a salvo.'],
    'boton' => ['texto' => 'Volver a intentar', 'url' => request()->fullUrl()],
    'recargarCada' => 60,
])
