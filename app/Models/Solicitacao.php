<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;

class Solicitacao extends Model
{
    use Auditavel;

    public const TIPOS = ['ajuste' => 'Ajuste de batida', 'abono' => 'Abono de horas/falta', 'atestado' => 'Atestado médico'];
    public const STATUS = ['pendente' => 'Pendente', 'aprovada' => 'Aprovada', 'rejeitada' => 'Rejeitada', 'cancelada' => 'Cancelada'];

    protected $table = 'solicitacoes';

    protected $fillable = [
        'colaborador_id', 'tipo', 'status', 'data', 'data_fim', 'hora', 'minutos', 'cid', 'motivo', 'anexo_path', 'anexo_nome',
        'resposta', 'decidido_por', 'decidido_em', 'marcacao_id', 'criado_por',
    ];

    protected $attributes = ['status' => 'pendente'];

    protected $casts = ['data' => 'date', 'data_fim' => 'date', 'decidido_em' => 'datetime', 'minutos' => 'integer'];

    public function colaborador() { return $this->belongsTo(Colaborador::class); }
    public function decisor() { return $this->belongsTo(User::class, 'decidido_por'); }
    public function autor() { return $this->belongsTo(User::class, 'criado_por'); }
    public function marcacao() { return $this->belongsTo(Marcacao::class); }

    public function scopePendentes($q) { return $q->where('status', 'pendente'); }

    /** Abonos e atestados aprovados que cobrem algum dia do intervalo. */
    public function scopeAbonosAprovados($q, string $inicio, string $fim)
    {
        return $q->where('status', 'aprovada')->whereIn('tipo', ['abono', 'atestado'])
            ->whereDate('data', '<=', $fim)
            ->where(fn ($w) => $w->where(fn ($x) => $x->whereNull('data_fim')->whereDate('data', '>=', $inicio))->orWhereDate('data_fim', '>=', $inicio));
    }

    public function getFimAttribute()
    {
        return $this->data_fim ?? $this->data;
    }

    public function cobre(string $data): bool
    {
        return $this->data->toDateString() <= $data && $this->fim->toDateString() >= $data;
    }

    public function getPeriodoTextoAttribute(): string
    {
        $txt = $this->data->format('d/m/Y');
        if ($this->data_fim && ! $this->data_fim->equalTo($this->data)) {
            $txt .= ' a '.$this->data_fim->format('d/m/Y');
        }
        if ($this->tipo === 'ajuste' && $this->hora) {
            $txt .= ' às '.substr($this->hora, 0, 5);
        }
        if ($this->tipo !== 'ajuste') {
            $txt .= $this->minutos ? ' ('.\App\Services\Apuracao::hhmm($this->minutos).')' : ' (dia inteiro)';
        }
        return $txt;
    }
}
