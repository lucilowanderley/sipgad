<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class Marcacao extends Model
{
    protected $table = 'marcacoes';

    protected $fillable = [
        'empresa_id', 'colaborador_id', 'nsr', 'data_hora', 'origem', 'ip', 'user_agent', 'hash', 'motivo',
        'criado_por', 'desconsiderada_em', 'motivo_desconsideracao', 'desconsiderada_por',
    ];

    protected $casts = ['data_hora' => 'datetime', 'desconsiderada_em' => 'datetime'];

    protected static function booted(): void
    {
        // Marcação é imutável: só pode ser desconsiderada, nunca alterada ou apagada.
        static::updating(function (Marcacao $m) {
            $permitidos = ['desconsiderada_em', 'motivo_desconsideracao', 'desconsiderada_por', 'updated_at'];
            if (array_diff(array_keys($m->getDirty()), $permitidos)) {
                throw new LogicException('Marcações de ponto não podem ser alteradas.');
            }
        });
        static::deleting(fn () => throw new LogicException('Marcações de ponto não podem ser excluídas.'));
        static::created(fn ($m) => Auditoria::registrar('registrou', $m, null, $m->only(['colaborador_id', 'nsr', 'data_hora', 'origem', 'motivo'])));
        static::updated(fn ($m) => Auditoria::registrar('desconsiderou', $m, null, $m->only(['motivo_desconsideracao'])));
    }

    public function scopeValidas($q)
    {
        return $q->whereNull('desconsiderada_em');
    }

    public function colaborador() { return $this->belongsTo(Colaborador::class); }
    public function empresa() { return $this->belongsTo(Empresa::class); }
    public function autor() { return $this->belongsTo(User::class, 'criado_por'); }
}
