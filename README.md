# ERP Game

Jogo de simulação empresarial no navegador, apresentado como um ERP. Este repositório contém apenas a **Fase 0 - Fundação** descrita em [DOCUMENTACAO_COPILOT_ERP_GAME.md](DOCUMENTACAO_COPILOT_ERP_GAME.md).

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
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
```

A aplicação estará disponível em <http://localhost:8000>.

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

A Fase 0 fornece autenticação e infraestrutura, sem implementar o domínio do jogo. Nas próximas fases, regras de simulação serão organizadas em `app/Domain`, enquanto controllers apenas validarão, autorizarão e delegarão operações. Componentes Vue não serão fonte de verdade para cálculos.

Valores monetários serão persistidos e calculados como inteiros em centavos (`BIGINT` no PostgreSQL), nunca como `float`. Datas do jogo serão independentes do relógio do servidor.

## Banco e serviços

O ambiente local usa PostgreSQL como banco principal. Redis já está preparado para cache e filas. Os testes isolados podem substituir cache, fila e sessão por drivers em memória, mas os testes de CI executam contra PostgreSQL 16.

## Escopo atual

Incluído nesta fase: framework, frontend Inertia/Vue/TypeScript, Tailwind, autenticação, PostgreSQL, Redis, testes, lint e CI.

Não incluído: partidas, empresas, produtos, compras, estoque, finanças, processamento diário ou eventos. Esses itens pertencem às fases seguintes da especificação.