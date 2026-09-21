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

## Deploy no Dokploy

O método recomendado é selecionar **Dockerfile** como build type no Dokploy, com contexto `/` e arquivo `Dockerfile`. A imagem multi-stage usa PHP 8.4 com FrankenPHP, Node 22 apenas no build, dependências Composer sem pacotes de desenvolvimento e porta interna `8080`. Migrations e caches são executados automaticamente ao iniciar o container.

O `nixpacks.toml` continua disponível como alternativa, mas não deve ser usado junto com o Dockerfile no mesmo deploy.

No Dokploy, crie serviços PostgreSQL 16 e Redis na mesma rede da aplicação e configure, no mínimo:

```dotenv
APP_NAME="ERP Game"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.example
ASSET_URL=https://seu-dominio.example
APP_KEY=base64:CHAVE_GERADA
TRUSTED_PROXIES=*

DB_CONNECTION=pgsql
DB_HOST=nome-do-servico-postgres
DB_PORT=5432
DB_DATABASE=erpgame
DB_USERNAME=usuario
DB_PASSWORD=senha-forte

REDIS_CLIENT=predis
REDIS_HOST=nome-do-servico-redis
REDIS_PORT=6379
REDIS_PASSWORD=null

SESSION_DRIVER=database
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

Gere a chave com `./vendor/bin/sail artisan key:generate --show`. No Dokploy, configure a porta `8080` e use `/up` como healthcheck. Como o Dokploy clona o GitHub, alterações locais precisam ser commitadas e enviadas ao repositório antes de um novo deploy.

O proxy HTTPS do Dokploy deve encaminhar `X-Forwarded-Proto`. A aplicação confia nesse proxy dentro do container e força HTTPS em produção quando `APP_URL` começa com `https://`. Depois de alterar `APP_URL` ou `ASSET_URL`, reinicie/redeploye o container para reconstruir o cache de configuração.

## Administração

A área `/admin` funciona como super administração: gerencia todos os jogadores e suas partidas, a população global, o catálogo de produtos para novas partidas e os cargos usados por RH, vendas e logística. Depois do primeiro cadastro, conceda acesso ao administrador pelo terminal do container:

```bash
php artisan user:grant-admin email@exemplo.com
```

O acesso é protegido por autenticação, verificação de e-mail e permissão administrativa. A população é um cadastro mestre compartilhado, sem vínculo com partidas. Produtos do catálogo afetam somente partidas criadas depois da alteração; produtos já movimentados permanecem preservados no histórico da empresa.

## Onboarding da empresa

O primeiro acesso solicita apenas o nome da empresa e o local do escritório. O nome interno da partida é gerado como `Partida #ID`. Há três localizações iniciais: Bairro Comercial (aluguel de R$ 4.000 e demanda −15%), Centro Empresarial (R$ 8.000 e demanda base) e Avenida Premium (R$ 15.000 e demanda +25%).

A escolha é salva como snapshot em `company.settings`, define o lançamento recorrente de aluguel e multiplica a demanda natural de todos os produtos. Partidas existentes sem essa configuração usam demanda base de 100%.

## Caixa de entrada

A Inbox em `/inbox` concentra mensagens não lidas e lidas do usuário. O sistema envia alertas sobre eventos da partida, recebimento de mercadorias, insuficiência de caixa e encerramento por vitória ou falência. Mensagens podem incluir um link interno para a ação relacionada. O usuário pode marcar toda a caixa como lida ou selecionar múltiplas mensagens da página para exclusão em massa.

Super administradores usam **Administração > Mensagens** para enviar um aviso a um usuário específico ou a todos os jogadores. Cada destinatário recebe uma cópia independente, preservando seu próprio estado de leitura. A primeira versão possui somente a caixa de entrada; pastas, respostas e anexos permanecem fora do escopo.

## Processamento automático

Em produção, partidas ativas processam vendas e o fechamento diário em background à meia-noite do servidor, mesmo sem usuário autenticado ou com o navegador fechado. O scheduler verifica partidas vencidas a cada minuto para também recuperar uma virada perdida durante eventual indisponibilidade e envia jobs idempotentes para a fila Redis. No Docker de produção, Supervisor mantém web, queue worker e scheduler ativos no mesmo container. Em ambiente local, a automação não é agendada e o botão **Avançar dia** permanece disponível.

Cada processamento com vendas gera uma notificação estruturada para o proprietário da partida. O sino no cabeçalho abre `/notifications`, onde ficam faturamento, unidades vendidas, novos clientes, empresa, data do jogo e acesso direto aos registros de vendas. A notificação também aparece na Inbox geral.

O horário padrão pode ser ajustado nas variáveis do Dokploy:

```dotenv
GAME_AUTOMATION_ENABLED=true
GAME_AUTOMATION_DAILY_AT=00:00
GAME_AUTOMATION_BATCH_SIZE=100
```

No desenvolvimento com Sail, mantenha estes processos em terminais separados quando quiser testar a automação continuamente:

```bash
./vendor/bin/sail artisan queue:work redis --tries=3 --timeout=120
./vendor/bin/sail artisan schedule:work
```

## Arquitetura

A criação de partidas fica em `app/Domain/Game/Actions/CreateGame.php`. O avanço transacional fica em `app/Domain/Game/Services/DayProcessor.php`; eventos em `app/Domain/Game/Services/EventEngine.php`; mutações de caixa passam por `app/Domain/Finance/Services/LedgerService.php`. Controllers apenas validam, autorizam, delegam operações e retornam respostas.

Valores monetários e fatores de demanda são calculados com inteiros, nunca como `float`. Estoque e liquidações de caixa geram movimentos imutáveis. A DRE usa competência; o fluxo de caixa usa a data de liquidação no calendário do jogo. Vendas são à vista no MVP.

## Banco e serviços

O ambiente local usa PostgreSQL como banco principal. Redis já está preparado para cache e filas. Os testes isolados podem substituir cache, fila e sessão por drivers em memória, mas os testes de CI executam contra PostgreSQL 16.

## DRE gerencial

A DRE usa regime de competência e apresenta receita bruta, deduções, receita líquida, CMV, lucro bruto, despesas operacionais detalhadas, EBITDA, depreciação e amortização, resultado operacional, resultado financeiro, resultado antes de tributos, tributos e resultado líquido. Também calcula margens bruta, EBITDA, operacional e líquida em pontos-base inteiros.

Compras de mercadorias não são reconhecidas diretamente como despesa: entram no estoque e são apropriadas ao CMV conforme os produtos são vendidos. Categorias ainda não movimentadas aparecem zeradas para manter a estrutura completa sem criar lançamentos fictícios.

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

## Capacidade de estoque

- a empresa começa com capacidade para 50 unidades físicas, somando todos os produtos;
- estoque atual e pedidos em trânsito ocupam capacidade;
- pedidos que ultrapassariam o limite são rejeitados transacionalmente;
- funcionários ativos de Logística ampliam a capacidade: Assistente +50, Analista +100, Coordenador +200, Gerente +350 e Diretor +500 unidades;
- o desligamento de um funcionário de Logística é bloqueado quando estoque e pedidos excederiam a capacidade restante;
- vendas liberam vagas automaticamente ao reduzir o estoque.

## Compras automáticas

O departamento **Compras** executa reposições no fechamento diário. Cada cargo possui `purchasing_capacity_units`, que limita quantas unidades o funcionário pode comprar por dia: Assistente 10, Analista 20, Coordenador 35, Gerente 50 e Diretor 75 por padrão. Esses valores são editáveis em **Administração > Cargos**.

A reposição considera estoque físico e pedidos em trânsito. Quando a cobertura de um produto chega a 2 dias de demanda, o sistema tenta comprar até atingir 7 dias, limitado pela capacidade diária do comprador, espaço logístico, disponibilidade do fornecedor e condições de caixa/pagamento. Funcionários mais seniores atuam primeiro. Cada pedido automático registra o funcionário responsável e aparece identificado na página **Compras**. Todo pedido criado, manual ou automático, gera uma notificação transacional na Inbox com fornecedor, unidades, valor, responsável e link para Compras.

Preços de venda são dados cadastrais do produto e ficam em **Estoque e produtos**. A página **Vendas** é somente operacional: apresenta dias processados, itens, clientes atendidos, responsáveis comerciais e desempenho por produto.

O fechamento interno de cada dia permanece em `sales`, enquanto cada venda ao cliente é registrada em `customer_orders` com ID no formato `VEN-########`. Um pedido agrupa todas as linhas de `customer_purchases` daquele cliente no dia e pode conter múltiplos SKUs. A página **Vendas** exibe esses pedidos em tabela paginada; cada linha abre uma página dedicada com resumo, dados do cliente e carrinho completo com produto, SKU, quantidade, preço unitário e subtotal.

## População e clientes

A aplicação mantém uma população global inicial de 100 NPCs fictícios e determinísticos, compartilhada por todas as partidas. A população armazena somente código, nome, nascimento, gênero, cidade, estado e email interno do jogo. CPF, RG, senha, filiação, endereço e telefones não são persistidos.

Para adicionar pessoas fictícias à população global, execute o comando informando quantas novas pessoas devem ser criadas:

```bash
php artisan population:generate 9900
```

O comando adiciona registros à população existente em lotes de 500, preserva códigos únicos e pode ser executado novamente com qualquer quantidade entre 1 e 1.000.000.

As bases versionadas em `resources/data/population` contêm primeiros nomes e apelidos. Para reaplicar essas bases à população já existente, preservando IDs, códigos e vínculos, execute:

```bash
php artisan population:refresh-names
```

O catálogo usa uma permutação determinística baseada na seed da população. Índices consecutivos percorrem regiões distantes das bases, evitando blocos alfabéticos, enquanto a permutação bijetiva mantém nomes completos únicos e resultados reproduzíveis. A listagem administrativa usa a ordem estável dos IDs, não ordenação por nome.

NPCs começam como prospects e são promovidos automaticamente para clientes na primeira compra. Toda quantidade e receita de `sale_items` é decomposta em compras vinculadas a clientes. O módulo Clientes apresenta conversão da população, compras, ticket médio, valor vitalício e recorrência.

Cada cliente possui uma página dedicada com recência, frequência, ranking por valor, segmento, produtos preferidos, evolução diária e histórico completo de compras.

Fora do MVP permanecem integrações reais, emissão fiscal, folha detalhada, múltiplos estabelecimentos, multiplayer, concorrentes por IA e os demais itens explicitamente excluídos na especificação.