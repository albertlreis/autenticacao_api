<?php

namespace App\Saas;

final class BackgroundModules
{
    public static function command(string $name): array
    {
        if (str_starts_with($name, 'conta-azul:')) return ['vendas'];
        if (str_starts_with($name, 'financeiro:')) return ['financeiro'];
        if ($name === 'comunicacao:agendar-cobrancas') return ['comunicacao', 'financeiro'];
        if (str_starts_with($name, 'comunicacao:')) return ['comunicacao'];
        if (str_starts_with($name, 'holidays:')) return ['agenda'];
        return [];
    }

    public static function job(string $class): ?array
    {
        if (str_starts_with($class, 'App\\Jobs\\ContaAzul\\')) return ['vendas'];
        if ($class === 'App\\Jobs\\GenerateDocumentExport') return ['vendas'];
        return null;
    }
}
