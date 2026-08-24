<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Un integrante dado de baja conserva su cuenta y su historial de pedidos,
 * pero no debe poder seguir comprando en la tienda.
 */
function integranteConUsuario(bool $activo): Employee
{
    Role::findOrCreate('empleado');

    $integrante = Employee::create([
        'company_id' => Company::create(['name' => 'MAVERICK SAS', 'active' => true])->id,
        'code' => '60219882',
        'name' => 'ALICIA GONZALEZ',
        'cedula' => '40214685675',
        'position' => 'ANALISTA',
        'department' => 'PRODUCCION',
        'active' => $activo,
    ]);

    $integrante->syncUser();

    return $integrante->refresh();
}

it('deja entrar al integrante activo', function () {
    $integrante = integranteConUsuario(activo: true);
    $integrante->user()->first()->update(['password' => bcrypt('clave-de-prueba')]);

    Volt::test('auth.login')
        ->set('login_field', '40214685675')
        ->set('password', 'clave-de-prueba')
        ->call('login')
        ->assertHasNoErrors();

    expect(Auth::check())->toBeTrue();
});

it('no deja entrar al integrante inactivo', function () {
    $integrante = integranteConUsuario(activo: false);
    $integrante->user()->first()->update(['password' => bcrypt('clave-de-prueba')]);

    Volt::test('auth.login')
        ->set('login_field', '40214685675')
        ->set('password', 'clave-de-prueba')
        ->call('login')
        ->assertHasErrors('login_field');

    expect(Auth::check())->toBeFalse();
});

it('corta la sesión abierta de quien acaban de inactivar', function () {
    $integrante = integranteConUsuario(activo: true);
    $usuario = $integrante->user()->first();

    $this->actingAs($usuario)->get(route('public.orders.history'))->assertOk();

    // La sincronización con Cultiva lo da de baja mientras navega.
    $integrante->forceFill(['active' => false])->save();

    $this->actingAs($usuario)
        ->get(route('public.orders.history'))
        ->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
});

it('no bloquea a las cuentas administrativas, que no cuelgan de un integrante', function () {
    Role::findOrCreate('admin');

    $admin = User::create([
        'name' => 'ADMINISTRADOR',
        'email' => 'admin@empresa.com',
        'cedula' => '00000000001',
        'password' => bcrypt('clave-de-prueba'),
    ]);
    $admin->assignRole('admin');

    expect($admin->isBlockedByInactiveEmployee())->toBeFalse();
});
