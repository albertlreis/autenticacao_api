<?php

// Explicit domain ownership. Unmapped actions fail closed in SaaS mode.
return [
    'App\\Http\\Controllers\\Api\\AuthController' => ['base'],
    'App\\Http\\Controllers\\Api\\UsuarioController' => ['base'],
    'App\\Http\\Controllers\\Api\\PerfilController' => ['base'],
    'App\\Http\\Controllers\\Api\\PermissaoController' => ['base'],
    'App\\Http\\Controllers\\MonitoramentoController' => ['base'],
    'App\\Saas\\LocalDeveloperSwitchController' => ['base'],
];
