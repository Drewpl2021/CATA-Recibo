{{-- No tienes permiso para ver esto. Ver errors/plantilla. --}}
@include('errors.plantilla', [
    'codigo' => '403',
    'titulo' => 'No tienes permiso para ver esto',
    'mensajes' => ['Tu cuenta no tiene acceso a esta página.', 'Si crees que es un error, pídelo a Recursos Humanos.'],
])
