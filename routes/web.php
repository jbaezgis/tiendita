<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', 'home')->name('home');

Route::middleware(['auth', 'role.redirect'])->group(function () {
    Volt::route('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    
    // Rutas administrativas - solo para Super Admin y Admin
    Route::middleware(['ensure.admin.access'])->group(function () {
        // Products routes
        Volt::route('products', 'products.index')->name('products.index');
        
        // Product Categories routes
        Volt::route('product-categories', 'products.product-categories')->name('product-categories.index');
        
        // Categories routes
        Volt::route('categories', 'categories.index')->name('categories.index');
        
        // Employees routes
        Volt::route('employees', 'employees.index')->name('employees.index');
        
        // Users management routes
        Volt::route('users', 'users.index')->name('users.index');
        Volt::route('users/{id}/assign-roles', 'users.assign-roles')->name('users.assign-roles');
        Route::get('users/download-template', [\App\Http\Controllers\UserController::class, 'downloadCategoryTemplate'])->name('users.download-template');
        
        // Internal Orders routes
        Volt::route('orders', 'orders.index')->name('orders.index');
        Volt::route('orders/create', 'orders.create')->name('orders.create');
        Volt::route('orders/{id}/view', 'orders.view')->name('orders.view');
        
        // Store Configuration routes
        Volt::route('store-config', 'store-config.index')->name('store-config.index');
    });
    
    // Public Orders routes (for employees and supervisors) - con middleware de redirección y verificación
    Route::middleware(['role.redirect', 'ensure.employee'])->group(function () {
        // Ruta de historial - siempre accesible
        Volt::route('public/orders/history', 'public.orders-history')->name('public.orders.history');
        
        // Ruta de creación de pedidos - protegida por estado de tienda
        Route::middleware(['ensure.store.open'])->group(function () {
            Volt::route('public/orders', 'public.orders')->name('public.orders');
        });
    });
});

require __DIR__.'/auth.php';