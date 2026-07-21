{{--
    Header de la tienda pública, en dos barras:
      1. Logo + menú del integrante (perfil, mis pedidos, cerrar sesión).
      2. Acciones de navegación que recibe el slot (carrito, historial, tienda).
    El slot va en la segunda barra; si viene vacío, esa barra no se dibuja.
--}}
@props(['user' => null])

@php($user = $user ?? auth()->user())

<header class="sticky top-0 z-40 border-b border-zinc-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-3">
            <a href="{{ route('public.orders') }}" wire:navigate class="flex shrink-0 items-center gap-3">
                <img
                    src="{{ asset('images/logo-tiendita.png') }}"
                    alt="Tiendita ValoraBrands"
                    class="h-8 w-auto sm:h-10"
                />
                <span class="hidden border-l border-zinc-200 pl-3 text-sm text-zinc-500 md:block">
                    Tienda de productos de ValoraBrands
                </span>
            </a>

            <flux:dropdown position="bottom" align="end">
                <flux:profile :initials="$user->initials()" icon-trailing="chevron-down" />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                        {{ $user->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ $user->name }}</span>
                                    <span class="truncate text-xs">{{ $user->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('public.orders.history')" icon="clipboard-document-list" wire:navigate>
                            Mis Pedidos
                        </flux:menu.item>
                        <flux:menu.item :href="route('public.profile')" icon="cog" wire:navigate>
                            Mi Perfil
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Cerrar sesión
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    @if(trim($slot) !== '')
        <div class="border-t border-zinc-100 bg-zinc-50">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-14 items-center justify-end gap-2">
                    {{ $slot }}
                </div>
            </div>
        </div>
    @endif
</header>
