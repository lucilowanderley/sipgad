<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;

class PeriodoFechamento extends Model
{
    use Auditavel;

    protected $table = 'periodos_fechamento';
    protected $fillable = ['empresa_id', 'competencia', 'inicio', 'fim', 'fechado_em', 'fechado_por'];
    protected $casts = ['inicio' => 'date', 'fim' => 'date', 'fechado_em' => 'datetime'];

    public function empresa() { return $this->belongsTo(Empresa::class); }
    public function responsavel() { return $this->belongsTo(User::class, 'fechado_por'); }

    public static function fechado(int $empresaId, string $data): bool
    {
        return static::where('empresa_id', $empresaId)->where('inicio', '<=', $data)->where('fim', '>=', $data)->exists();
    }
}
