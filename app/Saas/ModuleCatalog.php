<?php

namespace App\Saas;

use InvalidArgumentException;

final class ModuleCatalog
{
    public const MODULES = [
        'estoque' => ['label' => 'Estoque', 'requires' => []],
        'financeiro' => ['label' => 'Financeiro', 'requires' => []],
        'vendas' => ['label' => 'Vendas', 'requires' => ['estoque']],
        'assistencia' => ['label' => 'Assistência', 'requires' => ['vendas']],
        'comunicacao' => ['label' => 'Comunicação', 'requires' => []],
        'agenda' => ['label' => 'Agenda', 'requires' => []],
    ];

    public static function validate(array $modules): array
    {
        foreach ($modules as $module) {
            if (!is_string($module) || !isset(self::MODULES[$module])) {
                throw new InvalidArgumentException('Módulo desconhecido.');
            }
            foreach (self::MODULES[$module]['requires'] as $dependency) {
                if (!in_array($dependency, $modules, true)) {
                    throw new InvalidArgumentException("{$module} exige {$dependency}.");
                }
            }
        }
        return array_values(array_unique($modules));
    }
}
