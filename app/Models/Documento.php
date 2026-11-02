<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Arquivo do dossiê digital. O arquivo fica em storage/app/private e só é entregue por download autorizado. */
class Documento extends Model
{
    use Auditavel, SoftDeletes;

    protected $fillable = ['colaborador_id', 'tipo_documento_id', 'descricao', 'numero', 'emissao', 'validade', 'arquivo_path',
        'arquivo_nome', 'mime', 'tamanho', 'hash', 'enviado_por', 'conferido_em', 'conferido_por'];

    protected $casts = ['emissao' => 'date', 'validade' => 'date', 'conferido_em' => 'datetime'];

    public function colaborador() { return $this->belongsTo(Colaborador::class); }
    public function tipo() { return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id'); }
    public function remetente() { return $this->belongsTo(User::class, 'enviado_por'); }

    /** vencido | vencendo | valido | sem_validade */
    public function situacao(): string
    {
        if (! $this->validade) return 'sem_validade';
        if ($this->validade->lt(today())) return 'vencido';
        $alerta = $this->tipo?->alerta_dias ?? 30;
        return $this->validade->lte(today()->addDays($alerta)) ? 'vencendo' : 'valido';
    }

    public const SITUACOES = [
        'vencido' => ['Vencido', 'erro'], 'vencendo' => ['Vence em breve', 'alerta'],
        'valido' => ['Válido', 'ok'], 'sem_validade' => ['Sem validade', ''],
    ];
}
