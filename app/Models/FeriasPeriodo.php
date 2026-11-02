<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;

/** Período aquisitivo de férias. */
class FeriasPeriodo extends Model
{
    use Auditavel;

    protected $table = 'ferias_periodos';

    protected $fillable = ['colaborador_id', 'inicio', 'fim', 'limite_concessivo', 'faltas_injustificadas', 'dias_direito', 'perdido', 'observacao'];

    protected $attributes = ['dias_direito' => 30, 'faltas_injustificadas' => 0, 'perdido' => false];

    protected $casts = ['inicio' => 'date', 'fim' => 'date', 'limite_concessivo' => 'date', 'perdido' => 'boolean',
        'dias_direito' => 'integer', 'faltas_injustificadas' => 'integer'];

    public function colaborador() { return $this->belongsTo(Colaborador::class); }
    public function programacoes() { return $this->hasMany(FeriasProgramacao::class, 'periodo_id')->orderBy('inicio'); }

    /** Programações que consomem dias (pendentes reservam o saldo até a decisão). */
    public function programacoesAtivas()
    {
        return $this->programacoes->whereIn('status', ['pendente', 'aprovada']);
    }

    public function diasUsados(?int $ignorarId = null): int
    {
        return (int) $this->programacoesAtivas()->where('id', '!=', $ignorarId)->sum(fn ($p) => $p->dias + $p->dias_abono);
    }

    public function saldo(?int $ignorarId = null): int
    {
        return $this->perdido ? 0 : max($this->dias_direito - $this->diasUsados($ignorarId), 0);
    }

    public function completo(): bool
    {
        return $this->fim->lt(today());
    }

    public function getRotuloAttribute(): string
    {
        return $this->inicio->format('d/m/Y').' a '.$this->fim->format('d/m/Y');
    }
}
