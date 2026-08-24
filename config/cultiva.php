<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Conexión con Cultiva
    |--------------------------------------------------------------------------
    |
    | Cultiva (Cultura y Desarrollo) es la fuente de verdad del expediente de
    | los integrantes. La tiendita no crea ni edita ese expediente: lo
    | sincroniza y le arma su usuario para comprar.
    |
    */
    'url' => env('CULTIVA_URL'),

    'api_key' => env('CULTIVA_API_KEY'),

    'timeout' => (int) env('CULTIVA_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Ámbito sincronizado
    |--------------------------------------------------------------------------
    |
    | Entran todos los integrantes activos, sin importar empresa ni
    | departamento: la tienda es para todo el personal.
    |
    | Quien deja de estar activo no se borra —conserva su historial de pedidos—
    | sino que pasa al histórico y pierde el acceso a la tienda.
    |
    */
    'solo_activos' => true,
];
