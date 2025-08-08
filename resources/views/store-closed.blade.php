<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Tienda Cerrada</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    {{-- header --}}
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-center py-2">
        <div class="text-2xl font-bold">Tiendita AJFA</div>
        <div class="">Tienda de productos de Grupo AJFA</div>
    </div>
    <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-4 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="text-center">
                <flux:icon.lock-closed class="mx-auto h-12 w-12 text-red-500" />
                <h2 class="mt-6 text-3xl font-bold tracking-tight text-gray-900">
                    Tienda Cerrada
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ $message ?? 'La tienda está cerrada actualmente.' }}
                </p>
            </div>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <div class="space-y-6">
                    <div class="text-center">
                        <flux:icon.clock class="mx-auto h-16 w-16 text-yellow-500 mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">
                            Horario de la Tienda
                        </h3>
                        <p class="text-sm text-gray-600 mb-6">
                            La tienda está temporalmente cerrada. Los empleados no pueden realizar nuevos pedidos en este momento.
                        </p>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <flux:icon.information-circle class="h-5 w-5 text-blue-400" />
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">Información Importante</h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        <li>Los pedidos existentes permanecen visibles en el historial</li>
                                        <li>Los pedidos aprobados se procesan normalmente</li>
                                        <li>La tienda se abrirá nuevamente según la configuración</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col space-y-3">
                        <flux:button 
                            href="{{ route('public.orders.history') }}" 
                            variant="primary" 
                            icon="clipboard-document-list"
                            class="w-full justify-center"
                        >
                            Ver Historial de Pedidos
                        </flux:button>
                       @role('Super Admin|admin')
                        <flux:button 
                            href="{{ route('dashboard') }}" 
                            variant="ghost" 
                            icon="home"
                            class="w-full justify-center"
                        >
                            Ir al Dashboard
                        </flux:button>
                        @endrole
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 