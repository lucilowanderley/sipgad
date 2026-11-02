@php($cls = ['pendente' => 'alerta', 'aprovada' => 'ok', 'rejeitada' => 'erro', 'cancelada' => ''][$status] ?? '')
<span class="selo {{ $cls }}">{{ \App\Models\Solicitacao::STATUS[$status] ?? $status }}</span>
