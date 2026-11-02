<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use Auditavel;

    protected $fillable = ['razao_social', 'nome_fantasia', 'cnpj', 'endereco', 'cidade', 'uf', 'banco_horas_ativo', 'tolerancia_diaria_minutos'];

    protected $casts = ['banco_horas_ativo' => 'boolean'];

    protected $attributes = ['banco_horas_ativo' => true, 'tolerancia_diaria_minutos' => 10, 'ultimo_nsr' => 0];

    public function setores() { return $this->hasMany(Setor::class); }
    public function cargos() { return $this->hasMany(Cargo::class); }
    public function colaboradores() { return $this->hasMany(Colaborador::class); }
    public function jornadas() { return $this->hasMany(Jornada::class); }

    public function getNomeAttribute(): string
    {
        return $this->nome_fantasia ?: $this->razao_social;
    }
}
