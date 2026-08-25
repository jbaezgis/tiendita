<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StoreConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('empleado');

    // Por defecto StoreConfig se crea cerrada y addToCart abortaría.
    StoreConfig::create(['is_open' => true, 'current_season' => 'Test', 'max_order_amount' => 100000]);

    $this->category = Category::create([
        'code' => 'CAT-A',
        'salary_from' => 0,
        'salary_to' => 100000,
        'purchase_limit' => 8500,
    ]);

    Company::create(['name' => 'Valora']);

    $this->employee = Employee::create([
        'company_id' => 1,
        'code' => 'E-1',
        'name' => 'Yoel Baez',
        'cedula' => '00112345678',
        'position' => 'Analista',
        'department' => 'Tecnología',
        'category_id' => $this->category->id,
        'active' => true,
    ]);
    $this->employee->syncUser();

    $this->user = $this->employee->fresh()->user;
    $this->user->update(['category_id' => $this->category->id]);

    $productCategory = ProductCategory::create(['name' => 'Cuidado personal', 'is_active' => true]);

    $this->productA = Product::create([
        'code' => 'P-1',
        'description' => 'Acondicionador OGX Argan Oil Morocco XS 13oz.',
        'price' => 291.90,
        'product_category_id' => $productCategory->id,
        'is_active' => true,
    ]);

    $this->productB = Product::create([
        'code' => 'P-2',
        'description' => 'Acondicionador OGX Biotin & Collagen 13oz.',
        'price' => 271.05,
        'product_category_id' => $productCategory->id,
        'is_active' => true,
    ]);
});

it('calcula el total en el mismo request en que se agrega el primer producto', function () {
    // Escenario de la captura: 1 producto en el carrito debía mostrar 291.90,
    // no 0.00. El total se leía del caché de la propiedad computada.
    Volt::actingAs($this->user)
        ->test('public.orders')
        ->call('addToCart', $this->productA)
        ->assertSet('cart.'.$this->productA->id.'.quantity', 1)
        ->assertSee('Total: RD$ 291.90');
});

it('acumula el total al agregar una segunda unidad del mismo producto', function () {
    Volt::actingAs($this->user)
        ->test('public.orders')
        ->call('addToCart', $this->productA)
        ->call('addToCart', $this->productA)
        ->assertSet('cart.'.$this->productA->id.'.quantity', 2)
        ->assertSee('Total: RD$ 583.80');
});

it('calcula el total con productos distintos', function () {
    Volt::actingAs($this->user)
        ->test('public.orders')
        ->call('addToCart', $this->productA)
        ->call('addToCart', $this->productB)
        ->assertSee('Total: RD$ 562.95');
});

it('actualiza el total al cambiar la cantidad con los botones + y -', function () {
    $component = Volt::actingAs($this->user)
        ->test('public.orders')
        ->call('addToCart', $this->productA)
        ->call('updateCartQuantity', $this->productA->id, 3)
        ->assertSee('Total: RD$ 875.70')
        ->call('updateCartQuantity', $this->productA->id, 1)
        ->assertSee('Total: RD$ 291.90');

    expect($component->get('cart')[$this->productA->id]['subtotal'])->toEqual(291.90);
});

it('actualiza el total al eliminar un producto del carrito', function () {
    Volt::actingAs($this->user)
        ->test('public.orders')
        ->call('addToCart', $this->productA)
        ->call('addToCart', $this->productB)
        ->call('removeFromCart', $this->productA->id)
        ->assertSee('Total: RD$ 271.05')
        ->assertDontSee('Total: RD$ 562.95');
});

it('oculta la barra de total al vaciar el carrito', function () {
    // Con el carrito vacío la barra fija no se renderiza (@if(!empty($cart))).
    Volt::actingAs($this->user)
        ->test('public.orders')
        ->call('addToCart', $this->productA)
        ->assertSee('Total: RD$ 291.90')
        ->call('clearCart')
        ->assertSet('cart', [])
        ->assertDontSee('Total: RD$');
});

it('muestra el header con el logo de la tiendita y sin marca AJFA', function () {
    Volt::actingAs($this->user)
        ->test('public.orders')
        ->assertSee('images/logo-tiendita.png')
        ->assertSee('ValoraBrands')
        ->assertDontSee('AJFA');
});

it('muestra el header con el logo en el historial de pedidos', function () {
    Volt::actingAs($this->user)
        ->test('public.orders-history')
        ->assertSee('images/logo-tiendita.png')
        ->assertDontSee('AJFA');
});

it('respeta el límite de compra de la categoría', function () {
    // 30 x 291.90 = 8757.00 > 8500 de límite.
    Volt::actingAs($this->user)
        ->test('public.orders')
        ->call('addToCart', $this->productA, 29)   // 8465.10, permitido
        ->assertSee('Total: RD$ 8,465.10')
        ->call('addToCart', $this->productA, 1)    // 8756.99, excede
        ->assertSet('cart.'.$this->productA->id.'.quantity', 29)
        ->assertSee('Total: RD$ 8,465.10');
});

it('el header muestra el menú del integrante en vez del saludo de bienvenida', function () {
    Volt::actingAs($this->user)
        ->test('public.orders')
        ->assertSee('images/logo-tiendita.png')
        ->assertSee('Mi Perfil')
        ->assertSee('Mis Pedidos')
        ->assertSee('Cerrar sesión')
        ->assertDontSee('¡Bienvenido');
});

it('permite limpiar los filtros', function () {
    Volt::actingAs($this->user)
        ->test('public.orders')
        ->set('search', 'Argan')
        ->set('categoryFilter', '1')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('categoryFilter', '');
});

it('el integrante puede cambiar su contraseña desde Mi Perfil', function () {
    Volt::actingAs($this->user)
        ->test('public.profile')
        ->set('current_password', '12345678')
        ->set('password', 'nueva-clave-segura-2026')
        ->set('password_confirmation', 'nueva-clave-segura-2026')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('nueva-clave-segura-2026', $this->user->fresh()->password))->toBeTrue();
});

it('rechaza el cambio si la contraseña actual es incorrecta', function () {
    Volt::actingAs($this->user)
        ->test('public.profile')
        ->set('current_password', 'clave-equivocada')
        ->set('password', 'nueva-clave-segura-2026')
        ->set('password_confirmation', 'nueva-clave-segura-2026')
        ->call('updatePassword')
        ->assertHasErrors('current_password');

    expect(Hash::check('12345678', $this->user->fresh()->password))->toBeTrue();
});

it('Mi Perfil no expone campos para editar datos de nómina', function () {
    // Los datos de nómina son responsabilidad de RRHH: deben verse, no editarse.
    $component = Volt::actingAs($this->user)->test('public.profile');

    $component->assertSee('Analista')                 // cargo visible
        ->assertSee('00112345678')                    // cédula visible
        ->assertSee('Recursos Humanos');              // aviso

    // No hay ningún campo editable enlazado a datos de nómina.
    foreach (['name', 'cedula', 'position', 'department', 'category_id', 'email'] as $campo) {
        $component->assertDontSee('wire:model="'.$campo.'"', escape: false);
    }

    // Y las propiedades de contraseña sí existen y arrancan vacías.
    $component->assertSet('current_password', '')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');
});

it('abre el carrito al pulsar el boton Carrito', function () {
    Volt::actingAs($this->user)
        ->test('public.orders')
        ->call('addToCart', $this->productA)
        ->call('openCart')
        ->assertDispatched('modal-show');
});

it('abre el modal de confirmacion al pulsar Crear Pedido', function () {
    Volt::actingAs($this->user)
        ->test('public.orders')
        ->call('addToCart', $this->productA)
        ->call('openOrderModal')
        ->assertDispatched('modal-show');
});

it('renderiza los divs balanceados cuando el integrante no tiene limite de compra', function () {
    // El `<div>` del pie del carrito se abria dentro de @if($purchaseLimit) y se
    // cerraba fuera: sin limite sobraba un `</div>` que cerraba antes de tiempo
    // el root del componente y dejaba los <flux:modal> fuera de el, por lo que
    // "Carrito" y "Crear Pedido" no abrian nada.
    $this->user->update(['category_id' => null]);

    $html = Volt::actingAs($this->user->fresh())
        ->test('public.orders')
        ->call('addToCart', $this->productA)
        ->assertSee('Sin límite de compra')
        ->html();

    $abiertos = preg_match_all('/<div\b/i', $html);
    $cerrados = preg_match_all('/<\/div>/i', $html);

    expect($cerrados)->toBe($abiertos);
});
