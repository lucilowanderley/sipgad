<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['antes' => 'array', 'depois' => 'array'];

    private const OCULTOS = ['password', 'remember_token'];

    public static function registrar(string $acao, Model $model, ?array $antes, ?array $depois): void
    {
        $limpar = fn (?array $d) => $d === null ? null : array_diff_key($d, array_flip(self::OCULTOS));

        static::create([
            'user_id' => auth()->id(),
            'acao' => $acao,
            'entidade' => class_basename($model),
            'entidade_id' => $model->getKey(),
            'antes' => $limpar($antes),
            'depois' => $limpar($depois),
            'ip' => request()?->ip(),
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
