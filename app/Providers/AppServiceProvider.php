<?php

namespace App\Providers;

use Carbon\Carbon;
use App\Models\Comunicado;
use App\Models\FeriasProgramacao;
use App\Models\Solicitacao;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));
        Paginator::defaultView('pagination::default');
        Paginator::defaultSimpleView('pagination::simple-default');

        // Contadores do menu lateral: pendências de aprovação e comunicados não lidos
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            if (! $user) {
                return;
            }
            $pendentes = 0;
            if ($user->aprova()) {
                $ids = $user->aprovaveis()->select('id');
                $pendentes = Solicitacao::pendentes()->whereIn('colaborador_id', $ids)->count()
                    + FeriasProgramacao::where('status', 'pendente')->whereIn('colaborador_id', $user->aprovaveis()->select('id'))->count();
            }
            $view->with('menuPendentes', $pendentes)
                ->with('menuNaoLidos', Comunicado::visiveisPara($user)->naoLidosPor($user)->count());
        });
    }
}
