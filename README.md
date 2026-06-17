# Task Manager

Prosta aplikacja PHP do zarządzania zadaniami. Projekt demonstracyjny z CI/CD.

![CI](https://github.com/adrianbugaj5-sys/cicd/actions/workflows/ci.yml/badge.svg)

## Wymagania

- [Docker](https://www.docker.com/) + Docker Compose

Nie potrzebujesz lokalnie PHP ani Composera.

## Uruchomienie

```bash
git clone https://github.com/adrianbugaj5-sys/cicd.git
cd cicd

# Zbuduj i uruchom
docker compose up -d --build

# Otwórz w przeglądarce
open http://localhost:8080
```

## Testy

```bash
docker exec task-manager-php-1 ./vendor/bin/phpunit --colors=always
```

## Git hooks (jednorazowo po sklonowaniu)

```bash
sh scripts/install-hooks.sh
```

Od tej chwili `git push` automatycznie uruchamia testy. Jeśli padną — push jest zablokowany.

## CI/CD

Każdy push i pull request do `main` odpala na GitHubie:

| Krok | Co sprawdza |
|---|---|
| PHPUnit | testy jednostkowe (PHP 8.2 i 8.3) |
| PHPStan | statyczna analiza kodu (level 5) |
| PHP CS Fixer | code style (PSR-12) |
| composer audit | znane podatności w zależnościach |

## Struktura projektu

```
src/
├── Model/Task.php                     # encja domenowa
├── Repository/
│   ├── TaskRepositoryInterface.php    # kontrakt
│   ├── InMemoryTaskRepository.php     # implementacja na potrzeby testów
│   └── SessionTaskRepository.php      # implementacja dla web (sesja PHP)
└── Service/TaskService.php            # logika biznesowa

tests/Unit/
├── TaskTest.php                       # testy modelu
└── TaskServiceTest.php                # testy serwisu

public/index.php                       # entry point aplikacji webowej
```
