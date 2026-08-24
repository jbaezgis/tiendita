<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\Cultiva\IntegranteSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('empleado');
});

/**
 * Registro tal como lo entrega la API de integrantes de Cultiva.
 */
function registroCultiva(array $sobreescribe = []): array
{
    return array_replace_recursive([
        'codigo_empleado' => 422,
        'numero' => 60219882,
        'cedula' => '402-1468567-5',
        'nombre_completo' => 'ALICIA GONZALEZ FUNDADOR',
        'empresa' => ['codigo' => 2, 'nombre' => 'MAVERICK SAS'],
        'departamento' => ['codigo' => 24, 'nombre' => 'PRODUCCION'],
        'posicion' => ['codigo' => 157, 'nombre' => 'ANALISTA DE PROCESOS'],
        'estatus' => 'A',
        'activo' => true,
    ], $sobreescribe);
}

it('crea al integrante con su usuario de la tienda', function () {
    $resumen = app(IntegranteSync::class)->sincronizar([registroCultiva()]);

    $integrante = Employee::where('code', '60219882')->first();

    expect($integrante)->not->toBeNull()
        ->and($integrante->name)->toBe('ALICIA GONZALEZ FUNDADOR')
        ->and($integrante->cedula)->toBe('40214685675')
        ->and($integrante->position)->toBe('ANALISTA DE PROCESOS')
        ->and($integrante->active)->toBeTrue()
        ->and($resumen['usuarios'])->toBe(1);

    $usuario = $integrante->user;

    expect($usuario)->not->toBeNull()
        ->and($usuario->cedula)->toBe('40214685675')
        ->and($usuario->hasRole('empleado'))->toBeTrue();
});

it('guarda la cédula solo con dígitos, como la espera el login', function () {
    app(IntegranteSync::class)->sincronizar([registroCultiva()]);

    expect(Employee::where('code', '60219882')->first()->cedula)->toBe('40214685675')
        ->and(User::where('cedula', '40214685675')->exists())->toBeTrue();
});

it('casa las empresas por nombre y crea la que falte', function () {
    Company::create(['name' => 'MAVERICK SAS', 'active' => true]);

    app(IntegranteSync::class)->sincronizar([
        registroCultiva(),
        registroCultiva([
            'numero' => 111,
            'cedula' => '001-1111111-1',
            'nombre_completo' => 'OTRO INTEGRANTE',
            'empresa' => ['codigo' => 4, 'nombre' => 'VALORA BRANDS  S.A.S'],
        ]),
    ]);

    $maverick = Company::whereRaw('UPPER(name) = ?', ['MAVERICK SAS'])->first();
    $valora = Company::whereRaw('UPPER(name) = ?', ['VALORA BRANDS S.A.S'])->first();

    expect($valora)->not->toBeNull()
        ->and(Employee::where('code', '60219882')->first()->company_id)->toBe($maverick->id)
        ->and(Employee::where('code', '111')->first()->company_id)->toBe($valora->id);
});

it('no toca la categoría, que es de la tienda', function () {
    $categoria = Category::create([
        'code' => 'A',
        'salary_from' => 0,
        'salary_to' => 50000,
        'purchase_limit' => 5000,
    ]);

    $integrante = Employee::create([
        'company_id' => Company::create(['name' => 'MAVERICK SAS', 'active' => true])->id,
        'code' => '60219882',
        'name' => 'NOMBRE VIEJO',
        'cedula' => '40214685675',
        'position' => 'ALGO',
        'department' => 'ALGO',
        'category_id' => $categoria->id,
        'active' => true,
    ]);

    app(IntegranteSync::class)->sincronizar([registroCultiva()]);

    $integrante->refresh();

    expect($integrante->name)->toBe('ALICIA GONZALEZ FUNDADOR')
        ->and($integrante->category_id)->toBe($categoria->id);
});

it('inactiva a quien deja de estar activo, sin borrarlo', function () {
    $saliente = Employee::create([
        'company_id' => Company::create(['name' => 'AJFA SAS', 'active' => true])->id,
        'code' => '999',
        'name' => 'YA NO ESTA',
        'cedula' => '00099999999',
        'position' => 'X',
        'department' => 'X',
        'active' => true,
    ]);

    $resumen = app(IntegranteSync::class)->sincronizar([registroCultiva()]);

    $saliente->refresh();

    expect($saliente->exists)->toBeTrue()
        ->and($saliente->active)->toBeFalse()
        ->and($resumen['inactivados'])->toBe(1);
});

it('reactiva al que vuelve, sobre su mismo expediente y usuario', function () {
    $integrante = Employee::create([
        'company_id' => Company::create(['name' => 'MAVERICK SAS', 'active' => true])->id,
        'code' => '60219882',
        'name' => 'ALICIA GONZALEZ FUNDADOR',
        'cedula' => '40214685675',
        'position' => 'X',
        'department' => 'X',
        'active' => false,
    ]);
    $integrante->syncUser();
    $usuarioId = $integrante->user()->first()->id;

    $resumen = app(IntegranteSync::class)->sincronizar([registroCultiva()]);

    $integrante->refresh();

    expect($integrante->active)->toBeTrue()
        ->and($resumen['reactivados'])->toBe(1)
        ->and($integrante->user()->first()->id)->toBe($usuarioId)
        ->and(Employee::where('code', '60219882')->count())->toBe(1);
});

it('omite las cédulas repetidas en vez de reventar la corrida', function () {
    $resumen = app(IntegranteSync::class)->sincronizar([
        registroCultiva(['numero' => 1, 'cedula' => '00000000000', 'nombre_completo' => 'PRIMERO']),
        registroCultiva(['numero' => 2, 'cedula' => '00000000000', 'nombre_completo' => 'SEGUNDO']),
        registroCultiva(),
    ]);

    expect(Employee::where('code', '1')->exists())->toBeTrue()
        ->and(Employee::where('code', '2')->exists())->toBeFalse()
        ->and($resumen['conflictos'])->toHaveCount(1)
        ->and($resumen['creados'])->toBe(2);
});

it('aborta sin tocar nada si Cultiva no devuelve integrantes', function () {
    $integrante = Employee::create([
        'company_id' => Company::create(['name' => 'MAVERICK SAS', 'active' => true])->id,
        'code' => '1', 'name' => 'ALGUIEN', 'cedula' => '11111111111',
        'position' => 'X', 'department' => 'X', 'active' => true,
    ]);

    expect(fn () => app(IntegranteSync::class)->sincronizar([]))->toThrow(RuntimeException::class);

    expect($integrante->fresh()->active)->toBeTrue();
});

it('el comando recorre la paginación completa', function () {
    config()->set('cultiva.url', 'https://cultiva.example');
    config()->set('cultiva.api_key', 'llave');

    Http::fake([
        'cultiva.example/api/empleados*' => Http::sequence()
            ->push(['data' => [registroCultiva()], 'meta' => ['ultima_pagina' => 2]])
            ->push(['data' => [registroCultiva(['numero' => 222, 'cedula' => '002-2222222-2', 'nombre_completo' => 'SEGUNDA PAGINA'])], 'meta' => ['ultima_pagina' => 2]]),
    ]);

    $this->artisan('cultiva:sync-integrantes')->assertSuccessful();

    expect(Employee::count())->toBe(2)
        ->and(User::whereNotNull('employee_id')->count())->toBe(2);
});

it('falla cuando Cultiva responde con error', function () {
    config()->set('cultiva.url', 'https://cultiva.example');
    config()->set('cultiva.api_key', 'llave');

    Http::fake(['cultiva.example/api/empleados*' => Http::response(['message' => 'no'], 401)]);

    $this->artisan('cultiva:sync-integrantes')->assertFailed();
});

it('adopta el usuario suelto que ya tenía esa cédula en vez de fallar', function () {
    // Cuentas creadas antes de que existiera el registro del integrante:
    // users.cedula es única, así que crear otro usuario reventaría.
    $suelto = User::create([
        'name' => 'ALICIA G.',
        'email' => 'alicia@empresa.com',
        'cedula' => '40214685675',
        'password' => bcrypt('x'),
    ]);

    $resumen = app(IntegranteSync::class)->sincronizar([registroCultiva()]);

    $integrante = Employee::where('code', '60219882')->first();
    $suelto->refresh();

    expect(User::where('cedula', '40214685675')->count())->toBe(1)
        ->and($suelto->employee_id)->toBe($integrante->id)
        ->and($suelto->name)->toBe('ALICIA GONZALEZ FUNDADOR')
        ->and($suelto->hasRole('empleado'))->toBeTrue()
        ->and($resumen['usuarios'])->toBe(1);
});

it('no le roba a otro integrante su usuario', function () {
    $otro = Employee::create([
        'company_id' => Company::create(['name' => 'MAVERICK SAS', 'active' => true])->id,
        'code' => '777',
        'name' => 'OTRO INTEGRANTE',
        'cedula' => '40214685675',
        'position' => 'X',
        'department' => 'X',
        'active' => true,
    ]);
    $otro->syncUser();

    $resumen = app(IntegranteSync::class)->sincronizar([
        registroCultiva(),
        // Un registro sano acompaña al conflictivo: sin ninguno vigente la
        // corrida abortaría por la salvaguarda, y no es eso lo que se prueba.
        registroCultiva(['numero' => 888, 'cedula' => '008-8888888-8', 'nombre_completo' => 'INTEGRANTE SANO']),
    ]);

    // La cédula ya pertenece a otro integrante: se reporta y no se toca nada.
    expect($resumen['conflictos'])->toHaveCount(1)
        ->and($otro->user()->first()->employee_id)->toBe($otro->id)
        ->and(Employee::where('code', '60219882')->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Pantallas
|--------------------------------------------------------------------------
*/

function administrador(): User
{
    Role::findOrCreate('admin');

    $admin = User::create([
        'name' => 'ADMINISTRADOR',
        'email' => 'admin@empresa.com',
        'cedula' => '00000000009',
        'password' => bcrypt('x'),
    ]);

    return $admin->assignRole('admin');
}

function integrante(string $code, string $nombre, string $cedula, bool $activo): Employee
{
    return Employee::create([
        'company_id' => Company::firstOrCreate(['name' => 'MAVERICK SAS'], ['active' => true])->id,
        'code' => $code,
        'name' => $nombre,
        'cedula' => $cedula,
        'position' => 'ANALISTA',
        'department' => 'PRODUCCION',
        'active' => $activo,
    ]);
}

it('la pantalla de histórico lista solo a los inactivos', function () {
    integrante('1', 'VIGENTE PEREZ', '11111111111', true);
    integrante('2', 'SALIDO GOMEZ', '22222222222', false);

    $this->actingAs(administrador())
        ->get(route('employees.inactivos'))
        ->assertOk()
        ->assertSee('SALIDO GOMEZ')
        ->assertDontSee('VIGENTE PEREZ');
});

it('el listado principal deja fuera a los inactivos', function () {
    integrante('1', 'VIGENTE PEREZ', '11111111111', true);
    integrante('2', 'SALIDO GOMEZ', '22222222222', false);

    $this->actingAs(administrador())
        ->get(route('employees.index'))
        ->assertOk()
        ->assertSee('VIGENTE PEREZ')
        ->assertDontSee('SALIDO GOMEZ');
});
