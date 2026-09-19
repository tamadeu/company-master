# ERP Game — Documento de início para o GitHub Copilot

## 1. Visão do produto

Criar um jogo de simulação empresarial executado no navegador. O jogador administra uma empresa por meio de uma interface inspirada em um ERP real, começando com capital limitado e tomando decisões sobre compras, estoque, vendas, preços e finanças.

O jogo deve ensinar relações de causa e efeito da gestão empresarial sem transformar a experiência em trabalho burocrático. O realismo deve estar nas consequências das decisões, não na quantidade de formulários.

### Proposta central

> Um simulador empresarial em que todas as decisões acontecem dentro de um ERP funcional e seus efeitos aparecem no caixa, no estoque, nos resultados e na sobrevivência da empresa.

## 2. Objetivo deste documento

Este documento orienta o Copilot na criação do primeiro MVP. O foco inicial é validar o ciclo principal do jogo:

1. analisar os dados da empresa;
2. tomar decisões;
3. avançar um dia;
4. processar vendas, despesas, entregas e eventos;
5. apresentar os resultados;
6. repetir até a vitória ou falência.

Não implementar um ERP completo nesta primeira fase.

## 3. Stack sugerida

- Backend: PHP 8.3+ e Laravel 11 ou superior;
- Frontend: Inertia.js com Vue 3 e TypeScript;
- Estilo: Tailwind CSS;
- Banco de dados: PostgreSQL 16+;
- Filas e cache: Redis, preparado para uso futuro;
- Testes: Pest;
- Ambiente local: Docker Compose/Laravel Sail;
- Autenticação: Laravel Breeze com Inertia/Vue.

Se o repositório já possuir outra stack, preserve a estrutura existente e adapte este documento em vez de reescrever o projeto sem necessidade.

## 4. Princípios técnicos

- Usar valores monetários como inteiros em centavos. Nunca usar `float` para dinheiro.
- Isolar a simulação em serviços de domínio, sem colocar regras de negócio em controllers ou componentes Vue.
- Toda alteração financeira deve gerar uma movimentação imutável no livro-caixa.
- Toda alteração de estoque deve gerar uma movimentação imutável de estoque.
- O processamento de um dia deve ser transacional e idempotente.
- Números aleatórios devem aceitar uma semente para permitir testes reproduzíveis.
- Um jogador só pode acessar empresas pertencentes à sua conta.
- Datas do jogo são independentes da data real do servidor.
- Interfaces e textos devem começar em português do Brasil.

## 5. Escopo do MVP

### Incluído

- cadastro e autenticação;
- criação de uma nova partida;
- uma empresa varejista por partida;
- capital inicial de R$ 100.000,00;
- 5 produtos;
- 3 fornecedores;
- compra de produtos;
- prazo de entrega de fornecedores;
- controle de estoque;
- definição de preço de venda;
- demanda e vendas diárias simuladas;
- contas a pagar e a receber;
- despesas operacionais diárias/mensais;
- fluxo de caixa;
- DRE simplificada;
- dashboard com indicadores;
- avanço manual de um dia;
- eventos aleatórios;
- vitória, falência e histórico diário;
- testes das regras principais.

### Fora do MVP

- emissão fiscal real;
- integração bancária;
- folha de pagamento detalhada;
- contabilidade de partidas dobradas completa;
- produção industrial;
- múltiplos estabelecimentos;
- multiplayer;
- economia global compartilhada;
- concorrentes controlados por IA;
- tempo correndo continuamente;
- integrações externas;
- microserviços.

## 6. Experiência inicial

Ao criar uma partida, o sistema deve:

1. criar a empresa do jogador;
2. definir a data inicial do jogo como `2026-01-01`;
3. creditar R$ 100.000,00 no caixa;
4. cadastrar 5 produtos iniciais;
5. cadastrar 3 fornecedores com preço, prazo e confiabilidade diferentes;
6. criar despesas fixas recorrentes;
7. iniciar todos os produtos sem estoque ou com um pequeno estoque de demonstração;
8. exibir um tutorial curto com a primeira missão: comprar estoque e concluir o primeiro dia.

## 7. Ciclo do jogo

O jogador pode alterar preços e criar pedidos de compra antes de avançar o dia. Ao clicar em **Avançar dia**, o backend deve executar, nesta ordem:

1. validar que a partida está ativa;
2. iniciar uma transação no banco;
3. impedir o reprocessamento da mesma data;
4. receber compras cuja data de entrega chegou;
5. processar contas a receber vencidas;
6. processar contas a pagar vencidas;
7. calcular a demanda de cada produto;
8. limitar as vendas ao estoque disponível;
9. registrar vendas e custo da mercadoria vendida;
10. aplicar no máximo um evento aleatório do dia;
11. gerar um resumo diário;
12. verificar vitória ou falência;
13. avançar a data da empresa em um dia;
14. confirmar a transação.

Depois, a interface deve mostrar um modal ou página com o resumo: faturamento, unidades vendidas, margem bruta, despesas, variação do caixa, ruptura de estoque, entregas e eventos.

## 8. Regras iniciais da simulação

### 8.1 Demanda

Cada produto possui `base_daily_demand` e `reference_price_cents`. Uma fórmula inicial simples:

```text
price_factor = clamp(reference_price / sale_price, 0.35, 1.80)
random_factor = random(0.85, 1.15)
event_factor = multiplicador do evento ativo, ou 1.0
demand = round(base_daily_demand * price_factor * random_factor * event_factor)
units_sold = min(demand, available_stock)
```

A fórmula é provisória e deve ficar encapsulada em `DemandCalculator`, permitindo evolução posterior.

### 8.2 Receita e custo

```text
gross_revenue = units_sold * sale_price
cogs = soma do custo das unidades retiradas do estoque
gross_profit = gross_revenue - cogs
```

No MVP, usar custo médio ponderado ou camadas de estoque FIFO. Escolher apenas um método e documentá-lo. Sugestão: custo médio ponderado por ser mais simples para o painel.

### 8.3 Compras

- Um pedido começa como `ordered`.
- Seu valor pode ser pago à vista no pedido ou gerar conta a pagar, conforme o prazo do fornecedor.
- A entrega ocorre em `ordered_at_game_date + lead_time_days`.
- Ao receber, registrar entrada de estoque e mudar o pedido para `received`.
- O fornecedor pode atrasar conforme sua taxa de confiabilidade, mas essa regra pode entrar após o fluxo básico estar estável.

### 8.4 Caixa e insolvência

- O saldo deve ser derivado das movimentações financeiras ou atualizado de forma transacional com trilha de auditoria.
- Não permitir pagamento quando não existir saldo, salvo se futuramente houver limite de crédito.
- A empresa perde imediatamente se ficar sem capacidade de pagar uma obrigação vencida.
- Para o MVP, a falência pode ocorrer quando há conta vencida sem saldo suficiente.

### 8.5 Vitória

Condição inicial de vitória:

- completar 180 dias;
- não estar falida;
- possuir patrimônio líquido simplificado de pelo menos R$ 200.000,00.

O patrimônio líquido simplificado será:

```text
caixa + contas a receber + valor do estoque a custo - contas a pagar
```

Esses valores devem ser configuráveis em `config/game.php`.

## 9. Dados iniciais sugeridos

### Produtos

| Produto | Preço de referência | Demanda base/dia | Custo inicial sugerido |
|---|---:|---:|---:|
| Café Premium 500g | R$ 34,90 | 12 | R$ 19,00 |
| Chá Especial 100g | R$ 24,90 | 8 | R$ 12,00 |
| Chocolate 70% | R$ 18,90 | 15 | R$ 9,50 |
| Geleia Artesanal | R$ 27,90 | 7 | R$ 14,00 |
| Biscoito Amanteigado | R$ 16,90 | 18 | R$ 8,00 |

### Fornecedores

| Fornecedor | Perfil | Prazo | Pagamento | Confiabilidade |
|---|---|---:|---:|---:|
| Distribuidora Alfa | equilibrado | 3 dias | 7 dias | 95% |
| Atacado Econômico | barato e lento | 7 dias | à vista | 85% |
| Entrega Expressa | caro e rápido | 1 dia | 14 dias | 98% |

Cada fornecedor deve possuir ofertas próprias por produto. Os custos podem variar aproximadamente `-8%`, `0%` e `+12%` de acordo com o perfil.

## 10. Eventos iniciais

Implementar eventos como classes ou definições de dados, com duração, peso e efeitos explícitos.

- **Influenciador recomenda o produto:** demanda de um produto +60% por 3 dias.
- **Chuva intensa:** demanda geral -15% por 2 dias.
- **Fornecedor atrasado:** uma entrega é adiada em 2 dias.
- **Produto em alta:** preço de referência e demanda de um produto +20% por 5 dias.
- **Manutenção emergencial:** despesa inesperada entre R$ 500 e R$ 2.000.

Um evento não deve alterar silenciosamente dados permanentes. Registre sua ocorrência e seus efeitos.

## 11. Modelo de dados inicial

Os nomes abaixo são sugestões. Ajustar às convenções do projeto.

### Entidades principais

- `users`
- `games`: usuário, nome, status, semente, data atual, datas de início/fim;
- `companies`: partida, nome, saldo em caixa e configurações;
- `products`: empresa, SKU, nome, preço de venda, preço de referência e demanda base;
- `suppliers`: empresa, nome, prazo, prazo de pagamento e confiabilidade;
- `supplier_products`: fornecedor, produto, custo e quantidade mínima;
- `purchase_orders`: empresa, fornecedor, status, datas, total;
- `purchase_order_items`: pedido, produto, quantidade e custo unitário;
- `inventory_balances`: empresa, produto, quantidade e custo médio;
- `inventory_movements`: produto, tipo, quantidade, custo, data do jogo e referência polimórfica;
- `sales`: empresa, data do jogo, receita, custo e status;
- `sale_items`: venda, produto, quantidade, preço e custo unitário;
- `financial_entries`: empresa, tipo entrada/saída, categoria, valor, vencimento, pagamento e referência;
- `game_events`: partida, tipo, payload, início, fim e status;
- `daily_snapshots`: partida, data, indicadores e resumo JSON;
- `day_processes`: partida, data, status, seed utilizada e timestamps.

### Restrições importantes

- `day_processes`: índice único por `game_id + game_date`;
- `inventory_balances`: índice único por `company_id + product_id`;
- quantidades nunca podem ficar negativas;
- valores monetários devem ser `BIGINT`;
- datas do jogo devem usar `date`, não timestamps;
- snapshots e movimentos concluídos não devem ser editados.

## 12. Organização do backend

Estrutura sugerida:

```text
app/
  Domain/
    Game/
      Actions/CreateGame.php
      Actions/AdvanceDay.php
      Services/DayProcessor.php
      Services/DemandCalculator.php
      Services/VictoryCondition.php
      Services/BankruptcyCondition.php
      Events/
    Inventory/
      Actions/ReceivePurchaseOrder.php
      Services/InventoryService.php
    Finance/
      Services/LedgerService.php
      Services/IncomeStatementService.php
    Sales/
      Services/SalesSimulator.php
```

Controllers devem apenas validar a requisição, autorizar o acesso, chamar uma action e retornar a resposta.

## 13. Rotas/telas do MVP

### Rotas principais

- `GET /dashboard`: lista ou cria partidas;
- `POST /games`: cria nova partida;
- `GET /games/{game}`: dashboard da empresa;
- `POST /games/{game}/advance-day`: processa o próximo dia;
- `GET /games/{game}/products`: produtos e preços;
- `PATCH /games/{game}/products/{product}`: altera preço de venda;
- `GET /games/{game}/purchases`: pedidos e fornecedores;
- `POST /games/{game}/purchase-orders`: cria pedido;
- `GET /games/{game}/inventory`: posição e movimentações de estoque;
- `GET /games/{game}/finance`: caixa, contas e DRE;
- `GET /games/{game}/reports`: histórico diário.

Usar route model binding com políticas de autorização.

### Dashboard

Exibir no topo:

- data atual do jogo;
- caixa disponível;
- faturamento acumulado;
- lucro líquido acumulado;
- valor do estoque;
- contas a pagar nos próximos 7 dias;
- botão **Avançar dia**.

Também exibir:

- gráfico de caixa nos últimos 30 dias;
- vendas por produto;
- alertas de estoque baixo;
- entregas previstas;
- últimas movimentações;
- evento ativo, se houver.

O botão de avanço deve pedir confirmação, ficar bloqueado durante o processamento e não permitir duplo clique.

## 14. API/resposta do avanço diário

Exemplo conceitual:

```json
{
  "processed_date": "2026-01-01",
  "next_date": "2026-01-02",
  "sales_revenue_cents": 189700,
  "units_sold": 83,
  "cogs_cents": 91800,
  "expenses_cents": 25000,
  "cash_change_cents": 80200,
  "stockout_product_ids": [3],
  "received_purchase_order_ids": [7],
  "event": null,
  "game_status": "active"
}
```

Não usar os números retornados pelo navegador como fonte de verdade; toda simulação ocorre no servidor.

## 15. Segurança e consistência

- Autorizar todas as ações por usuário e partida.
- Validar preço, quantidade e IDs no servidor.
- Usar transação e bloqueio pessimista ao avançar o dia.
- Criar chave de idempotência lógica por partida/data.
- Evitar mass assignment não controlado.
- Não confiar em saldo ou totais enviados pelo frontend.
- Registrar erros de processamento e reverter toda a transação.
- Não expor stack traces em produção.

## 16. Testes obrigatórios

### Unitários

- demanda diminui quando o preço aumenta;
- demanda respeita mínimo/máximo dos fatores;
- venda nunca supera o estoque;
- dinheiro é calculado sem erro de ponto flutuante;
- custo médio é atualizado corretamente;
- critérios de vitória e falência funcionam;
- mesma semente produz o mesmo resultado.

### Feature/integrados

- usuário cria uma partida com dados iniciais;
- usuário não acessa a partida de outro usuário;
- pedido gera conta e depois entrada de estoque;
- avanço diário registra vendas e movimentos;
- o mesmo dia não é processado duas vezes;
- falha no meio do processamento reverte todas as alterações;
- conta vencida sem saldo encerra a partida;
- partida encerrada não pode avançar;
- resumo diário confere com as movimentações geradas.

## 17. Critérios de aceite do MVP

O MVP estará funcional quando um usuário conseguir:

1. criar uma conta e uma partida;
2. visualizar capital, estoque e indicadores;
3. comparar ofertas de fornecedores;
4. criar um pedido de compra;
5. alterar preços;
6. avançar dias sem inconsistências ou processamento duplicado;
7. receber estoque e vender produtos automaticamente;
8. acompanhar caixa, contas e DRE simplificada;
9. perceber claramente por que ganhou ou perdeu dinheiro;
10. chegar a uma condição de vitória ou falência.

Além disso:

- todos os testes obrigatórios devem passar;
- o projeto deve iniciar com um único comando documentado;
- migrations e seeders devem funcionar do zero;
- não pode haver saldo ou estoque negativo sem regra explícita;
- o README deve explicar instalação, arquitetura e comandos.

## 18. Plano de implementação

### Fase 0 — Fundação

- inicializar Laravel, Inertia, Vue, TypeScript, Tailwind e Pest;
- configurar PostgreSQL;
- adicionar autenticação;
- criar CI para lint e testes;
- escrever README inicial e `.env.example`.

### Fase 1 — Domínio e nova partida

- migrations, models, factories e seeders;
- criação transacional de uma partida;
- dashboard inicial;
- políticas de autorização.

### Fase 2 — Compras e estoque

- catálogo de fornecedores e ofertas;
- criação de pedidos;
- contas a pagar;
- recebimento e custo médio;
- tela de estoque e histórico.

### Fase 3 — Motor diário e vendas

- `DayProcessor`;
- demanda determinística por seed;
- vendas, CMV e movimentações;
- idempotência e resumo diário;
- modal de resultado.

### Fase 4 — Financeiro e relatórios

- livro-caixa;
- contas a pagar/receber;
- DRE simplificada;
- snapshots e gráficos.

### Fase 5 — Eventos e encerramento

- catálogo de eventos;
- vitória e falência;
- tutorial e equilíbrio básico;
- revisão dos testes e UX.

## 19. Primeira tarefa para o Copilot

Comece pela **Fase 0**. Antes de editar arquivos:

1. inspecione o repositório e identifique a stack já instalada;
2. apresente um plano curto com os arquivos que serão criados ou alterados;
3. preserve código e configurações existentes;
4. implemente apenas a fundação necessária;
5. execute os testes e comandos de lint;
6. relate arquivos alterados, decisões tomadas e pendências.

Depois da Fase 0, implemente uma fase por vez. Não avance automaticamente para a próxima fase se os testes atuais estiverem falhando.

## 20. Prompt sugerido para iniciar no Copilot

```text
Leia integralmente o arquivo DOCUMENTACAO_COPILOT_ERP_GAME.md e trate-o como a especificação do produto.

Comece apenas pela Fase 0 — Fundação. Primeiro inspecione o repositório e descreva brevemente o estado atual, as decisões de stack e o plano de alteração. Preserve tudo que já estiver funcionando. Em seguida, implemente a fase, crie ou atualize o README e o .env.example, execute os testes e o lint e apresente o resultado.

Regras importantes: use centavos inteiros para dinheiro, mantenha regras de domínio fora de controllers/componentes, use PostgreSQL, Laravel + Inertia/Vue/TypeScript quando o repositório ainda não definir outra stack, e não implemente funcionalidades das fases seguintes antes de validar a fundação.
```

## 21. Decisões em aberto

Registrar como ADR ou issue antes de sofisticar o MVP:

- nome final do jogo;
- custo médio versus FIFO;
- vendas à vista ou com recebimento futuro;
- dificuldade e capital inicial configuráveis;
- prazo exato de falência por inadimplência;
- inclusão de empréstimos;
- quantidade e frequência de eventos;
- modo educacional versus modo livre;
- modelo de monetização.

Essas decisões não devem bloquear a criação da fundação.
