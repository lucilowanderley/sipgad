# SIPGAD

Sistema Integrado de Ponto eletrônico e Gestão Administrativa. Fase 1: núcleo de cadastros + controle de ponto. Fase 2: portal do colaborador, aprovações, férias, dossiê digital e mural de comunicados.

## Instalação no Laragon (Windows)

1. Abra o terminal do Laragon (botão **Terminal**) e entre na pasta:
   ```
   cd C:\laragon\www\sipgad
   composer install
   copy .env.example .env
   php artisan key:generate
   ```
2. Crie o banco `sipgad` no MySQL (HeidiSQL do Laragon, ou `mysql -u root -e "CREATE DATABASE sipgad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"`).
3. Crie as tabelas e os dados iniciais:
   ```
   php artisan migrate --seed
   ```
   O seeder pergunta se deve criar dados de demonstração (empresa, 3 colaboradores e batidas do último mês). Responda `no` em produção.
4. Acesse **http://sipgad.test** (o Laragon cria o domínio automaticamente; se não, clique em *Menu > Apache > Reload*).

## Atualizar uma instalação da Fase 1 para a Fase 2

No terminal do Laragon, dentro de `C:\laragon\www\sipgad`, depois de copiar os arquivos novos por cima:
```
php artisan migrate
php artisan db:seed --class=Fase2Seeder
php artisan view:clear
```
O `migrate` só cria as tabelas novas (nada é apagado). O `Fase2Seeder` cadastra os tipos de documento padrão e, se encontrar os usuários de demonstração, cria exemplos (comunicados, solicitações e férias).

Os arquivos enviados (atestados e documentos do dossiê) ficam em `storage/app/private`. Inclua essa pasta no backup.

## Acessos iniciais

| Perfil | E-mail | Senha |
| --- | --- | --- |
| Administrador | admin@sipgad.test | admin123 |
| Departamento Pessoal (demo) | dp@sipgad.test | senha123 |
| Gestor (demo) | gestor@sipgad.test | senha123 |
| Colaborador 12x36 noturno (demo) | colaborador@sipgad.test | senha123 |

Troque a senha do administrador antes de usar com dados reais.

## O que a Fase 1 entrega

- **Perfis**: Administrador, RH, DP, Gestor (vê a equipe) e Colaborador (vê só o próprio ponto).
- **Cadastros**: empresas, setores, cargos (CBO), feriados (nacionais já carregados), jornadas e colaboradores com login.
- **Jornadas**: semanal (horário por dia, com intervalo e turno que vira a noite) e revezamento (12x36 etc.), com histórico por colaborador.
- **Registro de ponto web** com hora do servidor, NSR sequencial por empresa, hash encadeado (detecta adulteração no banco) e comprovante.
- **Espelho de ponto** mensal: horas previstas, trabalhadas, extras, débitos, adicional noturno (real e reduzido), faltas e marcações ímpares. Versão para impressão com campos de assinatura.
- **Ajustes do DP**: incluir marcação esquecida ou desconsiderar uma indevida, sempre com motivo. Nada é apagado.
- **Banco de horas**: saldo lançado no fechamento do mês, mais compensações, pagamentos e ajustes; extrato por colaborador.
- **Fechamento de competência**: bloqueia o mês para ajustes; o administrador pode reabrir.
- **Exportação** do resumo do mês em CSV (abre no Excel) para a contabilidade.
- **Auditoria** de toda alteração de cadastro e de ponto, com verificação de integridade das marcações.

## O que a Fase 2 entrega

- **Portal do colaborador**: o painel mostra batidas, saldo do mês e do banco de horas, solicitações pendentes, próximas férias, férias a vencer, documentos vencidos/faltando e os últimos comunicados.
- **Solicitações**: ajuste de batida (horário esquecido), abono (dia inteiro, vários dias ou só algumas horas) e atestado médico (com anexo obrigatório e CID opcional). O RH/DP também pode lançar em nome de um colaborador e já aprovar.
- **Aprovação pelo gestor**: cada pedido vai para o gestor direto (campo Gestor do colaborador); sem gestor, vai para o RH/DP, que também pode decidir qualquer pedido. Ninguém aprova o próprio pedido. Rejeição exige justificativa. Contador de pendências no menu.
  - Ajuste aprovado vira marcação de origem "ajuste" (com o nº da solicitação no motivo).
  - Abono/atestado aprovado entra no espelho na coluna **Abonado**: o dia não gera falta nem débito.
  - Não é possível aprovar nada em competência fechada.
- **Férias**:
  - Períodos aquisitivos gerados automaticamente a partir da admissão (não se aplica a estágio e PJ), com período concessivo de 12 meses.
  - Faltas injustificadas reduzem os dias de direito pela tabela do art. 130; o RH pode marcar perda do direito (art. 133).
  - Programação pelo colaborador (vai para aprovação) ou pelo RH/DP: até 3 períodos, um com 14 dias ou mais e os demais com 5 ou mais; venda de até 1/3 (abono pecuniário); adiantamento do 13º; bloqueio de início nos 2 dias antes de feriado ou domingo (art. 134 §3º).
  - Avisos para quem pede e para quem aprova: menos de 30 dias de antecedência, gozo após o concessivo (dobra), venda fora do prazo e colegas do mesmo setor de férias no mesmo período.
  - **Cronograma do setor** (visão anual com barras, sobreposições e prazos de vencimento) e **Controle de férias** com alertas: vencido, crítico (até 60 dias para o último início possível) e atenção (até 120 dias).
  - Férias aprovadas aparecem no espelho como "Férias", sem falta.
- **Dossiê digital**: tipos de documento configuráveis (obrigatório, exige validade, quantos dias antes avisar, se o colaborador pode enviar). Upload pelo RH ou pelo colaborador (este fica "a conferir"). O envio mais recente de cada tipo é o vigente e os anteriores ficam no histórico. Painel de vencimentos com vencidos, a vencer, obrigatórios faltando e pendentes de conferência. Gestores não veem dossiês (LGPD).
- **Mural de comunicados**: público por empresa e setor, categoria, fixar no topo, agendamento, data de expiração e confirmação de ciência. Contador de não lidos no menu e relatório de quem leu/deu ciência.

## Relógios de ponto (Dimep e outros REP)

Menu **Ponto → Relógios de ponto** (perfis admin e DP).
- Cadastre o relógio (ex.: Dimep SMART 671 S, REP-C) com empresa, nº de fabricação, IP e porta.
- **Importar AFD**: no relógio Dimep, grave o AFD no pendrive (F1 + 80 + E ou porta FISCAL) e envie o .txt. Lê os leiautes da Portaria 671 (tipos 3 e 7, por CPF) e da antiga Portaria 1510 (tipo 3, por PIS).
- Cada NSR do relógio é importado uma única vez; reenviar o arquivo inteiro é seguro. As marcações entram com origem `relogio`, NSR próprio do SIPGAD e hash encadeado.
- Marcações de CPF não cadastrado ou de competência fechada ficam como pendência; corrija e clique em **Reprocessar**. O arquivo original fica em `storage/app/private/afd`.
- **Coleta automática**: informe uma pasta no cadastro; `php artisan relogios:coletar` (agendado a cada 5 min com `php artisan schedule:work`) importa todo AFD novo salvo nela.
- **Testar conexão** verifica se o relógio responde no IP/porta e **Abrir página do relógio** abre o webservice embarcado do Dimep.

## Regras de cálculo

Ficam em `app/Services/Apuracao.php`:
- Tolerância diária configurável por empresa (padrão 10 min, CLT art. 58 §1º).
- Feriado ou folga: todo tempo trabalhado vira extra.
- Noturno: 22h às 5h; hora reduzida de 52min30s.
- Dias futuros e a jornada do dia em andamento não geram falta.

## Testes

```
php artisan test
```
Usam SQLite em memória (extensão `pdo_sqlite` do PHP).

## Ainda não incluso

Geração dos arquivos oficiais AFD/AEJ do próprio SIPGAD (Portaria 671/2021), coleta direta pela rede no protocolo do fabricante, assinatura eletrônica do espelho e dos avisos de férias, notificações por e-mail/WhatsApp, cálculo de valores de férias e app mobile/offline. Estão nas próximas fases do roadmap.
