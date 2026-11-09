{{-- La página caducó. Ver errors/plantilla. --}}
@include('errors.plantilla', [
    'codigo' => '419',
    'titulo' => 'La página caducó',
    'mensajes' => ['Pasó mucho tiempo desde que se abrió.', 'Vuelve a la página anterior e inténtalo de nuevo.'],
])
