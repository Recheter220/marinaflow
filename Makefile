.PHONY: setup up down restart logs shell migrate seed npm build

# Detecta Windows (GNU make define OS=Windows_NT em Windows nativo)
ifeq ($(OS),Windows_NT)
CP = powershell -Command "if (-not (Test-Path -Path '.env')) { Copy-Item -Path '.env.example' -Destination '.env' }"
SLEEP = powershell -Command "Start-Sleep -Seconds 5"
WWWUSER ?= 1000
WWWGROUP ?= 1000
else
CP = test -f .env || cp .env.example .env
SLEEP = sleep 5
WWWUSER ?= $(shell id -u)
WWWGROUP ?= $(shell id -g)
endif

setup:
	@echo "==> Criando .env..."
	@$(CP)
	@echo "==> Construindo imagem do Sail..."
	@WWWUSER=$(WWWUSER) WWWGROUP=$(WWWGROUP) docker compose build
	@echo "==> Subindo containers..."
	@WWWUSER=$(WWWUSER) WWWGROUP=$(WWWGROUP) docker compose up -d
	@$(SLEEP)
	@echo "==> Instalando dependências PHP..."
	@docker compose exec laravel.test composer install
	@echo "==> Criando banco SQLite..."
	@docker compose exec laravel.test sh -c "touch database/database.sqlite"
	@echo "==> Gerando APP_KEY..."
	@docker compose exec laravel.test php artisan key:generate
	@echo "==> Executando migrations e seed..."
	@docker compose exec laravel.test php artisan migrate --seed
	@echo "==> Instalando dependências JavaScript..."
	@docker compose exec laravel.test npm install
	@echo ""
	@echo "=========================================="
	@echo " MarinaFlow configurado com sucesso!"
	@echo " Aplicação: http://localhost:8080"
	@echo " Mailpit:   http://localhost:8025"
	@echo " Login:     admin@marinaflow.local"
	@echo " Senha:     admin123"
	@echo "=========================================="

up:
	@docker compose up -d
	@echo "MarinaFlow: http://localhost:8080"
	@echo "==> Instalando dependências JavaScript (se necessário)..."
	@docker compose exec laravel.test npm install
	@echo "==> Iniciando Vite (dev) em background dentro do container..."
	@docker compose exec laravel.test sh -c "nohup npm run dev >/dev/null 2>&1 &" || true

down:
	@docker compose down

restart:
	@docker compose down
	@docker compose up -d

logs:
	@docker compose logs -f

shell:
	@docker compose exec laravel.test sh

migrate:
	@docker compose exec laravel.test php artisan migrate

seed:
	@docker compose exec laravel.test php artisan migrate --seed

npm:
	@docker compose exec laravel.test npm install

build:
	@docker compose build
