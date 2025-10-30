<?php

namespace App\Enums;

enum PerfilEnum: string
{
    case ADMINISTRADOR = 'Administrador';
    case VENDEDOR = 'Vendedor';
    case DESENVOLVEDOR = 'Desenvolvedor';
    case FINANCEIRO = 'Financeiro';
    case ESTOQUISTA = 'Estoquista';


    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRADOR => 'Administrador',
            self::VENDEDOR => 'Vendedor',
            self::DESENVOLVEDOR => 'Desenvolvedor',
            self::FINANCEIRO => 'Financeiro',
            self::ESTOQUISTA => 'Estoquista',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
