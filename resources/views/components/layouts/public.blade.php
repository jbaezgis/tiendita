<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="font-sans antialiased bg-gray-50">
        <flux:toast />
        
        {{ $slot }}

        @fluxScripts
    </body>
</html>