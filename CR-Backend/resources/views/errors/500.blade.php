{{-- No pudimos completar la operación. Ver errors/plantilla. --}}
@include('errors.plantilla', [
    'codigo' => '500',
    'titulo' => 'No pudimos completar la operación',
    'mensajes' => ['Algo falló de nuestro lado. Vuelve a intentarlo en unos minutos.', 'Si sigue igual, avisa a soporte.'],
])
