<?php
namespace App\Saas;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** Shared across HTTP, CLI and the backup operator through the tenant volume. */
final class MaintenanceGate
{
    public static function acquire(string $root)
    {
        if (!is_dir($root) && !mkdir($root, 0770, true) && !is_dir($root)) throw new \RuntimeException('Cannot initialize maintenance gate.');
        $marker = $root.'/.operations-maintenance.json';
        if (is_file($marker)) throw new HttpException(503, 'Ambiente em manutenção temporária.', null, ['Retry-After' => '60']);
        $lock = fopen($root.'/.operations.lock', 'c');
        if (!$lock) throw new \RuntimeException('Cannot open maintenance gate.');
        if (!flock($lock, LOCK_SH | LOCK_NB) || is_file($marker)) {
            fclose($lock);
            throw new HttpException(503, 'Ambiente em manutenção temporária.', null, ['Retry-After' => '60']);
        }
        return $lock;
    }

    public static function release($lock): void
    {
        if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    }
}
