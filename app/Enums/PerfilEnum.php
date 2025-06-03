<?php

namespace App\Enums;

enum PerfilEnum: string
{
    case ADMINISTRADOR = 'Administrador';
    case VENDEDOR = 'Vendedor';

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRADOR => 'Administrador',
            self::VENDEDOR => 'Vendedor',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
