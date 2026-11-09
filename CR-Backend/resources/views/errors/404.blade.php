{{-- No encontramos esta página. Ver errors/plantilla. --}}
@include('errors.plantilla', [
    'codigo' => '404',
    'titulo' => 'No encontramos esta página',
    'mensajes' => ['La dirección no existe o ya no está disponible.', 'Revisa el enlace o vuelve al inicio.'],
])
