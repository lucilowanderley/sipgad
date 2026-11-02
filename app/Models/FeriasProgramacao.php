<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;

/** Período de gozo de férias programado (solicitado pelo colaborador ou lançado pelo RH/DP). */
class FeriasProgramacao extends Model
{
    use Auditavel;

    public const STATUS = ['pendente' => 'Aguardando aprovação', 'aprovada' => 'Aprovada', 'rejeitada' => 'Rejeitada', 'cancelada' => 'Cancelada'];

    protected $table = 'ferias_programacoes';

    protected $fillable = ['periodo_id', 'colaborador_id', 'inicio', 'fim', 'dias', 'dias_abono', 'adiantamento_13', 'status',
        'observacao', 'resposta', 'decidido_por', 'decidido_em', 'criado_por'];

    protected $attributes = ['status' => 'pendente', 'dias_abono' => 0, 'adiantamento_13' => false];

    protected $casts = ['inicio' => 'date', 'fim' => 'date', 'decidido_em' => 'datetime', 'adiantamento_13' => 'boolean',
        'dias' => 'integer', 'dias_abono' => 'integer'];

    public function periodo() { return $this->belongsTo(FeriasPeriodo::class, 'periodo_id'); }
    public function colaborador() { return $this->belongsTo(Colaborador::class); }
    public function decisor() { return $this->belongsTo(User::class, 'decidido_por'); }

    public function scopeAtivas($q) { return $q->whereIn('status', ['pendente', 'aprovada']); }

    /** Situação exibida: aprovada vira "Em gozo" ou "Gozada" conforme a data. */
    public function getSituacaoAttribute(): string
    {
        if ($this->status === 'aprovada') {
            if ($this->fim->lt(today())) return 'Gozada';
            if ($this->inicio->lte(today())) return 'Em gozo';
        }
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function getSeloAttribute(): string
    {
        return match ($this->status) { 'aprovada' => 'ok', 'pendente' => 'alerta', 'rejeitada' => 'erro', default => '' };
    }
}
