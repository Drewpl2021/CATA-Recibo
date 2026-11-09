{{-- Demasiados intentos. Ver errors/plantilla. --}}
@include('errors.plantilla', [
    'codigo' => '429',
    'titulo' => 'Demasiados intentos',
    'mensajes' => ['Se hicieron muchos pedidos seguidos.', 'Espera un momento y vuelve a intentarlo.'],
])
