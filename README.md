# MarinaFlow

Sistema de gestão de marina: cadastro de embarcações e funcionários, registro de
serviços realizados e consulta ao histórico. Aplicação web construída com
[Laravel](https://laravel.com), [Inertia.js](https://inertiajs.com) e
[React](https://react.dev), em substituição à versão anterior, um aplicativo
desktop feito em Tauri/Rust.

## Stack

- **Backend:** PHP 8.5 + Laravel 13
- **Frontend:** React 19 + Inertia.js v3 + Mantine UI
- **Build:** Vite
- **Banco de dados:** SQLite (padrão local) — configurável via `.env`
- **Ambiente de desenvolvimento:** [Laravel Sail](https://laravel.com/docs/sail) (Docker)

## Pré-requisitos

- PHP 8.3+ e [Composer](https://getcomposer.org/) (só para instalar as
  dependências — a aplicação em si roda dentro do Docker)
- [Docker](https://www.docker.com/) e Docker Compose (para rodar via Sail — recomendado)
- Alternativamente, para rodar tudo sem Docker: PHP 8.3+ com as extensões
  usadas pelo Laravel (incluindo `pdo_sqlite`) e Node.js 20+

## Instalação (com Docker/Sail — recomendado)

1. Clone o repositório e entre na pasta do projeto:

   ```bash
   git clone <url-do-repositorio>
   cd marinaflow
   ```

2. Copie o arquivo de variáveis de ambiente e instale as dependências PHP:

   ```bash
   cp .env.example .env
   composer install
   ```

3. Suba os containers (a primeira vez faz o build da imagem, pode demorar um pouco):

   ```bash
   ./vendor/bin/sail up -d
   ```

4. Gere a chave da aplicação, crie o banco SQLite e rode as migrations:

   ```bash
   ./vendor/bin/sail artisan key:generate
   touch database/database.sqlite
   ./vendor/bin/sail artisan migrate --seed
   ```

5. Instale as dependências JavaScript e suba o Vite:

   ```bash
   ./vendor/bin/sail npm install
   ./vendor/bin/sail npm run dev
   ```

6. Acesse a aplicação em [http://localhost:8080](http://localhost:8080) (porta
   definida em `APP_PORT` no `.env.example`, escolhida para não colidir com
   outros projetos rodando na porta 80 padrão do Sail).

### Login padrão

O seeder cria um usuário administrador para o primeiro acesso:

- **E-mail:** `admin@marinaflow.local`
- **Senha:** `admin123`

Demais usuários são criados por convite (link enviado por e-mail). Como
`MAIL_MAILER=log` por padrão, os e-mails de convite/redefinição de senha ficam
gravados em `storage/logs/laravel.log` em vez de serem enviados de verdade.

## Instalação (sem Docker)

1. Copie o `.env` e instale as dependências:

   ```bash
   cp .env.example .env
   composer install
   npm install
   ```

2. Gere a chave da aplicação, crie o banco SQLite e rode as migrations:

   ```bash
   php artisan key:generate
   touch database/database.sqlite
   php artisan migrate --seed
   ```

3. Suba o servidor de desenvolvimento (PHP + fila + logs + Vite juntos):

   ```bash
   composer run dev
   ```

   A aplicação fica disponível na porta configurada em `APP_PORT`/`APP_URL` no `.env`.

## Rodando os testes

Com Sail:

```bash
./vendor/bin/sail artisan test
```

Sem Sail:

```bash
php artisan test
```

## Formatação de código

O projeto usa o [Laravel Pint](https://laravel.com/docs/pint). Antes de
finalizar alterações em arquivos PHP, rode:

```bash
./vendor/bin/sail pint --dirty
```

(ou `vendor/bin/pint --dirty`, sem Sail)

## Estrutura do projeto

- `app/Http/Controllers` — controllers HTTP
- `app/Services` — regras de domínio e invariantes de negócio
- `app/Models` — modelos Eloquent
- `resources/js/pages` — páginas React renderizadas via Inertia
- `database/migrations` — schema do banco de dados
- `tests/Feature` — testes de recurso (o grosso da cobertura do projeto)
