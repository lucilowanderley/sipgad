<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;

class BancoHorasLancamento extends Model
{
    use Auditavel;

    public const TIPOS = ['fechamento' => 'Fechamento do período', 'compensacao' => 'Compensação (folga)', 'pagamento' => 'Pagamento em folha', 'ajuste' => 'Ajuste manual'];

    protected $table = 'banco_horas_lancamentos';
    protected $fillable = ['colaborador_id', 'periodo_id', 'data', 'minutos', 'tipo', 'descricao', 'criado_por'];
    protected $casts = ['data' => 'date'];

    public function colaborador() { return $this->belongsTo(Colaborador::class); }
}
