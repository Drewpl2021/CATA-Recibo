<?php
// Un Administrador temporal para las capturas: se crea, da su token y se
// borra al terminar (ver SKILL.md). Nunca usar la cuenta de una persona real.
//
//   docker cp usuario-temporal.php cata-recibo-app-1:/tmp/
//   docker exec cata-recibo-app-1 php artisan tinker /tmp/usuario-temporal.php
//
// Para borrarlo:
//   php artisan tinker --execute="App\Models\User::where('email','like','%temporal.test')->get()->each(fn($u) => [$u->tokens()->delete(), $u->forceDelete()]);"
$rol = App\Models\Rol::where('nombre', 'admin')->first();
$u = App\Models\User::where('email', 'prueba.presentacion@temporal.test')->first() ?? new App\Models\User([
    'name'                  => 'Recursos Humanos',
    'email'                 => 'prueba.presentacion@temporal.test',
    'password'              => Illuminate\Support\Str::random(24),
    'rol_id'                => $rol->id,
    'estado_registro'       => 'activo',
    'debe_cambiar_password' => false,
]);
$u->terminos_firmados = true;
$u->terminos_version = App\Support\TerminosDeUso::VERSION;
$u->save();
echo 'TOKEN=' . $u->createToken('presentacion')->plainTextToken . PHP_EOL;
