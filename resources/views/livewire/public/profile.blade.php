<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Flux\Flux;

/**
 * Perfil del integrante.
 *
 * Solo permite cambiar la contraseña: nombre, cédula, cargo, departamento y
 * categoría son datos de nómina y únicamente Recursos Humanos puede
 * modificarlos desde el módulo de Integrantes. Aquí se muestran como solo
 * lectura.
 */
new #[Layout('components.layouts.public')] class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ], [
                'current_password.required' => 'Debes ingresar tu contraseña actual',
                'current_password.current_password' => 'La contraseña actual no es correcta',
                'password.required' => 'Debes ingresar la nueva contraseña',
                'password.confirmed' => 'La confirmación no coincide con la nueva contraseña',
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(
            heading: 'Contraseña actualizada',
            text: 'Tu contraseña fue cambiada correctamente.',
            variant: 'success',
            position: 'top-right',
        );
    }

    public function with(): array
    {
        return [
            'user' => auth()->user(),
            'employee' => auth()->user()->employee,
        ];
    }
}; ?>

<div>
    <x-public-header>
        <flux:button variant="primary" color="blue" href="{{ route('public.orders') }}" icon="shopping-cart" wire:navigate>
            Ir a la tienda
        </flux:button>
    </x-public-header>

    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6">
            <flux:heading size="xl" class="text-gray-900">Mi Perfil</flux:heading>
            <flux:subheading class="text-gray-600">Consulta tus datos y actualiza tu contraseña</flux:subheading>
        </div>

        {{-- Datos de nómina: solo lectura --}}
        <flux:card class="mb-6">
            <div class="mb-4 flex items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-neutral-200 font-semibold text-black">
                    {{ $user->initials() }}
                </span>
                <div class="min-w-0">
                    <flux:heading size="lg" class="truncate">{{ $user->name }}</flux:heading>
                    <flux:text size="sm" class="truncate text-zinc-500">{{ $user->email }}</flux:text>
                </div>
            </div>

            @if($employee)
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-zinc-500">Código</dt>
                        <dd class="text-sm font-medium text-zinc-900">{{ $employee->code }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500">Cédula</dt>
                        <dd class="text-sm font-medium text-zinc-900">{{ $employee->cedula }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500">Cargo</dt>
                        <dd class="text-sm font-medium text-zinc-900">{{ $employee->position }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500">Departamento</dt>
                        <dd class="text-sm font-medium text-zinc-900">{{ $employee->department }}</dd>
                    </div>
                    @if($user->category)
                        <div>
                            <dt class="text-xs text-zinc-500">Límite de compra</dt>
                            <dd class="text-sm font-medium text-zinc-900">
                                RD$ {{ number_format($user->category->purchase_limit, 2) }}
                            </dd>
                        </div>
                    @endif
                </dl>
            @endif

            <flux:callout icon="information-circle" class="mt-5">
                <flux:callout.text>
                    Estos datos los administra Recursos Humanos. Si alguno es incorrecto, comunícate con ellos.
                </flux:callout.text>
            </flux:callout>
        </flux:card>

        {{-- Cambio de contraseña --}}
        <flux:card>
            <flux:heading size="lg">Cambiar contraseña</flux:heading>
            <flux:subheading>Usa una contraseña larga y que no utilices en otros sitios</flux:subheading>

            <form wire:submit="updatePassword" class="mt-6 space-y-5">
                <flux:input
                    wire:model="current_password"
                    label="Contraseña actual"
                    type="password"
                    required
                    viewable
                    autocomplete="current-password"
                />
                <flux:input
                    wire:model="password"
                    label="Nueva contraseña"
                    type="password"
                    required
                    viewable
                    autocomplete="new-password"
                />
                <flux:input
                    wire:model="password_confirmation"
                    label="Confirmar nueva contraseña"
                    type="password"
                    required
                    viewable
                    autocomplete="new-password"
                />

                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit">
                        Actualizar contraseña
                    </flux:button>
                </div>
            </form>
        </flux:card>
    </div>
</div>
