<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    use Auditavel;

    protected $table = 'tipos_documento';

    protected $fillable = ['nome', 'obrigatorio', 'exige_validade', 'alerta_dias', 'colaborador_envia'];

    protected $attributes = ['obrigatorio' => false, 'exige_validade' => false, 'alerta_dias' => 30, 'colaborador_envia' => true];

    protected $casts = ['obrigatorio' => 'boolean', 'exige_validade' => 'boolean', 'colaborador_envia' => 'boolean', 'alerta_dias' => 'integer'];

    public function documentos() { return $this->hasMany(Documento::class); }
}
