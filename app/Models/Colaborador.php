<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Colaborador extends Model
{
    use Auditavel;

    public const TIPOS_CONTRATO = ['clt' => 'CLT', 'estagio' => 'Estágio', 'temporario' => 'Temporário', 'aprendiz' => 'Aprendiz', 'pj' => 'PJ'];

    protected $table = 'colaboradores';

    protected $attributes = ['ativo' => true, 'tipo_contrato' => 'clt'];

    protected $fillable = [
        'empresa_id', 'setor_id', 'cargo_id', 'gestor_id', 'user_id', 'matricula', 'nome', 'cpf', 'pis',
        'data_nascimento', 'email', 'telefone', 'data_admissao', 'data_demissao', 'tipo_contrato', 'salario', 'ativo',
    ];

    protected $casts = [
        'data_nascimento' => 'date',
        'data_admissao' => 'date',
        'data_demissao' => 'date',
        'ativo' => 'boolean',
        'salario' => 'decimal:2',
    ];

    public function empresa() { return $this->belongsTo(Empresa::class); }
    public function setor() { return $this->belongsTo(Setor::class); }
    public function cargo() { return $this->belongsTo(Cargo::class); }
    public function gestor() { return $this->belongsTo(Colaborador::class, 'gestor_id'); }
    public function equipe() { return $this->hasMany(Colaborador::class, 'gestor_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function jornadas() { return $this->hasMany(ColaboradorJornada::class)->orderByDesc('inicio'); }
    public function marcacoes() { return $this->hasMany(Marcacao::class); }
    public function lancamentosBanco() { return $this->hasMany(BancoHorasLancamento::class); }
    public function solicitacoes() { return $this->hasMany(Solicitacao::class); }
    public function feriasPeriodos() { return $this->hasMany(FeriasPeriodo::class)->orderBy('inicio'); }
    public function feriasProgramacoes() { return $this->hasMany(FeriasProgramacao::class)->orderBy('inicio'); }
    public function documentos() { return $this->hasMany(Documento::class); }

    public function jornadaEm(CarbonInterface $data): ?ColaboradorJornada
    {
        $d = $data->toDateString();
        return $this->jornadas->first(fn ($cj) => $cj->inicio->toDateString() <= $d && (! $cj->fim || $cj->fim->toDateString() >= $d));
    }

    public function jornadaAtual(): ?ColaboradorJornada
    {
        return $this->jornadaEm(now());
    }

    public function saldoBancoMinutos(): int
    {
        return (int) $this->lancamentosBanco()->sum('minutos');
    }
}
