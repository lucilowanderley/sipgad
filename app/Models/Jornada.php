<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Jornada extends Model
{
    use Auditavel;

    public const DIAS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

    protected $fillable = ['empresa_id', 'nome', 'tipo', 'horas_trabalho', 'horas_folga', 'entrada', 'intervalo_minutos'];

    public function empresa() { return $this->belongsTo(Empresa::class); }
    public function dias() { return $this->hasMany(JornadaDia::class)->orderBy('dia_semana'); }

    /**
     * Intervalos previstos para a data, como pares [inicio, fim] de Carbon.
     * Saídas menores que a entrada viram o dia seguinte (jornada noturna).
     * Retorna [] em dia de folga.
     */
    public function intervalosPrevistos(CarbonInterface $data, ?CarbonInterface $dataBase = null): array
    {
        if ($this->tipo === 'revezamento') {
            $ciclo = ($this->horas_trabalho + $this->horas_folga) / 24; // 12x36 => 2 dias
            $base = ($dataBase ?? $data)->copy()->startOfDay();
            $dias = (int) $base->diffInDays($data->copy()->startOfDay(), false);
            if ($ciclo <= 0 || $dias < 0 || fmod($dias, $ciclo) != 0 || ! $this->entrada) {
                return [];
            }
            $inicio = $data->copy()->setTimeFromTimeString($this->entrada);
            $fim = $inicio->copy()->addHours($this->horas_trabalho);
            if ($this->intervalo_minutos > 0) {
                $meio = $inicio->copy()->addMinutes(intdiv($this->horas_trabalho * 60 - $this->intervalo_minutos, 2));
                return [[$inicio, $meio], [$meio->copy()->addMinutes($this->intervalo_minutos), $fim]];
            }
            return [[$inicio, $fim]];
        }

        $dia = $this->dias->firstWhere('dia_semana', $data->dayOfWeek);
        if (! $dia) {
            return [];
        }
        $intervalos = [];
        $anterior = null;
        foreach ([['entrada1', 'saida1'], ['entrada2', 'saida2']] as [$e, $s]) {
            if (! $dia->$e || ! $dia->$s) {
                continue;
            }
            $ini = $data->copy()->setTimeFromTimeString($dia->$e);
            if ($anterior && $ini->lt($anterior)) {
                $ini->addDay();
            }
            $fim = $ini->copy()->setTimeFromTimeString($dia->$s);
            if ($fim->lte($ini)) {
                $fim->addDay();
            }
            $intervalos[] = [$ini, $fim];
            $anterior = $fim;
        }
        return $intervalos;
    }

    public function descricao(): string
    {
        if ($this->tipo === 'revezamento') {
            return "{$this->horas_trabalho}x{$this->horas_folga} a partir das ".substr((string) $this->entrada, 0, 5);
        }
        return $this->dias->map(fn ($d) => mb_substr(self::DIAS[$d->dia_semana], 0, 3).' '.substr($d->entrada1, 0, 5).'-'.substr($d->saida2 ?: $d->saida1, 0, 5))->implode(', ');
    }
}
