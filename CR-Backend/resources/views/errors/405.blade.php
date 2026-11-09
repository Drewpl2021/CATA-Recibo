{{-- No se pudo completar la solicitud. Ver errors/plantilla. --}}
@include('errors.plantilla', [
    'codigo' => '405',
    'titulo' => 'No se pudo completar la solicitud',
    'mensajes' => ['La solicitud no es válida o ya no está disponible.', 'Vuelva a la página anterior e inténtelo de nuevo.'],
])
