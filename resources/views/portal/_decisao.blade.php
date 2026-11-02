<form method="post" action="{{ $acao }}" class="decisao" onsubmit="if(this.decisao.value==='rejeitar'&&!this.resposta.value.trim()){alert('Escreva o motivo da rejeição.');return false;}">
    @csrf <input type="hidden" name="decisao" value="aprovar">
    <input name="resposta" placeholder="Comentário (obrigatório para rejeitar)" maxlength="1000">
    <button class="btn peq" onclick="this.form.decisao.value='aprovar'">Aprovar</button>
    <button class="btn perigo peq" onclick="this.form.decisao.value='rejeitar'">Rejeitar</button>
</form>
