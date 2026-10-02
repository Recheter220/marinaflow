<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Git — regra do projeto

- **Commit direto na `main` é proibido.** A `main` só recebe merge de pull request.
- Todo desenvolvimento sai em branch própria: **`feature/MF-XX`**, uma por spec de
  `docs/specs/` (ex.: `feature/MF-04`). Trabalho sem história associada usa
  `fix/<slug>` ou `chore/<slug>`.
- Commits seguem **Conventional Commits**, com o identificador da spec como escopo:
  `feat(MF-04): store service photos on the private disk`. Tipos: `feat`, `fix`,
  `docs`, `refactor`, `test`, `chore`, `ci`, `perf`, `style`, `build`, `revert`.
  Assunto no imperativo, minúsculo, sem ponto final. O histórico do repositório
  está em inglês — manter.
- O corpo do commit explica o **porquê**, não o quê; o diff já diz o quê.

Detalhes e exemplos em [README.md](README.md#fluxo-de-trabalho-no-git).
