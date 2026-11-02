<?php

namespace App\Models\Concerns;

use App\Models\Auditoria;

/**
 * Registra em `auditorias` toda criação, alteração e exclusão do model.
 */
trait Auditavel
{
    public static function bootAuditavel(): void
    {
        static::created(fn ($m) => Auditoria::registrar('criou', $m, null, $m->getAttributes()));
        static::updated(function ($m) {
            $alterados = $m->getChanges();
            unset($alterados['updated_at']);
            if ($alterados) {
                Auditoria::registrar('alterou', $m, array_intersect_key($m->getOriginal(), $alterados), $alterados);
            }
        });
        static::deleted(fn ($m) => Auditoria::registrar('excluiu', $m, $m->getOriginal(), null));
    }
}
