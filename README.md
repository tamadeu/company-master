# ERP Game

Jogo de simulação empresarial no navegador, apresentado como um ERP. As **Fases 0 a 5 do MVP** descritas em [DOCUMENTACAO_COPILOT_ERP_GAME.md](DOCUMENTACAO_COPILOT_ERP_GAME.md) estão implementadas.

## Stack

- PHP 8.5 no Laravel Sail e Laravel 13;
- Inertia.js 2, Vue 3 e TypeScript;
- Tailwind CSS;
- PostgreSQL 16;
- Redis 7 para cache e filas;
- Pest para testes;
- Laravel Breeze para autenticação.

## Requisitos

- Docker com Docker Compose;
- Git;
- Node.js 22 ou superior somente para executar o frontend fora do Sail.

Não é necessário instalar PHP, Composer ou PostgreSQL na máquina local.

## Instalação

Instale as dependências PHP usando a imagem oficial do Composer:

```bash
cp .env.example .env
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:2 composer install
```

Inicie a aplicação com um único comando:

```bash
./vendor/bin/sail up -d
```

Na primeira execução, prepare a aplicação e os assets:

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
```

A aplicação estará disponível em <http://localhost:8000>.

O seeder local cria uma partida completa para inspeção:

- email: `demo@erpgame.local`;
- senha: `password`;
- empresa: `Mercado Aurora`.

## Desenvolvimento

```bash
./vendor/bin/sail npm run dev
```

Comandos de qualidade:

```bash
./vendor/bin/sail test
./vendor/bin/sail pint --test
./vendor/bin/sail npm run lint
./vendor/bin/sail npm run typecheck
./vendor/bin/sail npm run build
```

## Arquitetura

A criação de partidas fica em `app/Domain/Game/Actions/CreateGame.php`. O avanço transacional fica em `app/Domain/Game/Services/DayProcessor.php`; eventos em `app/Domain/Game/Services/EventEngine.php`; mutações de caixa passam por `app/Domain/Finance/Services/LedgerService.php`. Controllers apenas validam, autorizam, delegam operações e retornam respostas.

Valores monetários e fatores de demanda são calculados com inteiros, nunca como `float`. Estoque e liquidações de caixa geram movimentos imutáveis. A DRE usa competência; o fluxo de caixa usa a data de liquidação no calendário do jogo. Vendas são à vista no MVP.

## Banco e serviços

O ambiente local usa PostgreSQL como banco principal. Redis já está preparado para cache e filas. Os testes isolados podem substituir cache, fila e sessão por drivers em memória, mas os testes de CI executam contra PostgreSQL 16.

## Escopo atual

Incluído: partidas, compras, estoque, vendas, financeiro, relatórios, eventos temporários, tutorial, avanço idempotente, vitória por prazo/patrimônio e falência por obrigação sem cobertura.

## Equipe e RH

O módulo de RH permite contratar e desligar funcionários, organizar cargo e departamento e integrar salários recorrentes ao contas a pagar, fluxo de caixa e DRE. Valores salariais são inteiros em centavos e seguem a data do jogo.

A contratação usa um wizard: primeiro o jogador escolhe departamento e cargo; depois recebe três sugestões determinísticas da população. Nome e salário são derivados no servidor. A matriz salarial varia por departamento e progride entre Assistente, Analista, Coordenador, Gerente e Diretor.

O escopo é intencionalmente gerencial: folha detalhada, encargos, benefícios, férias e obrigações legais permanecem fora do MVP.

## Regras de vendas e aleatoriedade

- a demanda natural varia deterministicamente entre 85% e 115% conforme semente, data e SKU;
- preço abaixo da referência aumenta a demanda e preço acima reduz, limitado aos fatores de 35% e 180%;
- eventos ativos podem multiplicar demanda e preço de referência sem alterar permanentemente o produto;
- vendas são limitadas por demanda, estoque disponível e capacidade comercial diária;
- o gestor possui capacidade base de 5 unidades por dia;
- funcionários ativos do departamento Comercial adicionam capacidade conforme o cargo: Assistente 8, Analista 14, Coordenador 20 e Gerente 26 unidades base;
- a produtividade individual varia deterministicamente entre 85% e 115% por dia;
- prioridade de produtos e vendedores varia por seed, evitando favorecimento fixo;
- cada unidade vendida é atribuída ao gestor ou a um funcionário comercial para auditoria e indicadores.

Preços de venda são dados cadastrais do produto e ficam em **Estoque e produtos**. A página **Vendas** é somente operacional: apresenta dias processados, itens, clientes atendidos, responsáveis comerciais e desempenho por produto.

## População e clientes

Cada partida recebe 100 NPCs fictícios e determinísticos. A população armazena somente código, nome, nascimento, gênero, cidade, estado e email interno do jogo. CPF, RG, senha, filiação, endereço e telefones não são persistidos.

NPCs começam como prospects e são promovidos automaticamente para clientes na primeira compra. Toda quantidade e receita de `sale_items` é decomposta em compras vinculadas a clientes. O módulo Clientes apresenta conversão da população, compras, ticket médio, valor vitalício e recorrência.

Cada cliente possui uma página dedicada com recência, frequência, ranking por valor, segmento, produtos preferidos, evolução diária e histórico completo de compras.

Fora do MVP permanecem integrações reais, emissão fiscal, folha detalhada, múltiplos estabelecimentos, multiplayer, concorrentes por IA e os demais itens explicitamente excluídos na especificação.