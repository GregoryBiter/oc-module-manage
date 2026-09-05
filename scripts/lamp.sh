#!/bin/bash

# ==============================================================================
# GB-LAMP Installer
# Установка и подключение Docker-окружения LAMP (gb-lamp) для OpenCart
# Репозиторий: https://github.com/GregoryBiter/gb-lamp
# Запуск: ocm lamp [target_dir] или curl -sSL https://raw.githubusercontent.com/GregoryBiter/gb-lamp/main/lamp.sh | bash
# ==============================================================================

set -e

# Цвета для терминала
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

REPO_TAR_URL="https://github.com/GregoryBiter/gb-lamp/archive/refs/heads/main.tar.gz"

TARGET_DIR="${1:-.}"

if [ "$TARGET_DIR" != "." ]; then
    if [ ! -d "$TARGET_DIR" ]; then
        mkdir -p "$TARGET_DIR"
    fi
    cd "$TARGET_DIR"
fi

PROJECT_DIR="$(pwd)"

echo -e "${BLUE}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║          GB-LAMP: Добавление Docker в проект             ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════════════╝${NC}"
echo -e "Целевой каталог: ${GREEN}$PROJECT_DIR${NC}"
echo ""

# Шаг 1: Получение файлов окружения (локально или из GitHub)
TMP_DIR=""
cleanup() {
    if [ -n "$TMP_DIR" ] && [ -d "$TMP_DIR" ]; then
        rm -rf "$TMP_DIR"
    fi
}
trap cleanup EXIT

# Источник файлов GB-LAMP: переменная GB_LAMP_SRC, текущая папка или GitHub
if [ -n "${GB_LAMP_SRC:-}" ] && [ -d "$GB_LAMP_SRC/.docker" ]; then
    echo -e "${GREEN}✓ Использование файлов GB-LAMP из $GB_LAMP_SRC${NC}"
    SRC_DIR="$GB_LAMP_SRC"
elif [ -d ".docker/templates" ] && [ -f ".env.example" ]; then
    echo -e "${GREEN}✓ Использование локальных файлов GB-LAMP${NC}"
    SRC_DIR="$PROJECT_DIR"
else
    echo -e "${BLUE}Загрузка компонентов GB-LAMP из GitHub...${NC}"
    TMP_DIR=$(mktemp -d)
    if command -v curl >/dev/null 2>&1; then
        curl -sSL "$REPO_TAR_URL" | tar -xz -C "$TMP_DIR"
    elif command -v wget >/dev/null 2>&1; then
        wget -qO- "$REPO_TAR_URL" | tar -xz -C "$TMP_DIR"
    else
        echo -e "${RED}Ошибка: curl или wget не найдены. Установите curl или wget.${NC}"
        exit 1
    fi
    SRC_DIR="$TMP_DIR/gb-lamp-main"
    echo -e "${GREEN}✓ Компоненты успешно загружены${NC}"
fi

echo ""

# Шаг 2: Копирование .docker/ и служебных файлов
echo -e "${BLUE}Копирование компонентов окружения...${NC}"

# Копируем .docker/
if [ "$SRC_DIR" != "$PROJECT_DIR" ]; then
    cp -r "$SRC_DIR/.docker" "$PROJECT_DIR/"
fi

# Копируем run.sh
if [ "$SRC_DIR" != "$PROJECT_DIR" ] || [ ! -f "run.sh" ]; then
    cp "$SRC_DIR/run.sh" "$PROJECT_DIR/run.sh"
    chmod +x "$PROJECT_DIR/run.sh"
fi

# Копируем .env.example
if [ ! -f ".env.example" ]; then
    cp "$SRC_DIR/.env.example" "$PROJECT_DIR/.env.example"
fi

# Копируем .vscode если его нет
if [ ! -d ".vscode" ] && [ -d "$SRC_DIR/.vscode" ]; then
    cp -r "$SRC_DIR/.vscode" "$PROJECT_DIR/.vscode"
    echo -e "${GREEN}✓ Добавлена конфигурация отладки .vscode${NC}"
fi

# Обработка Makefile
if [ ! -f "Makefile" ]; then
    cp "$SRC_DIR/Makefile" "$PROJECT_DIR/Makefile"
    echo -e "${GREEN}✓ Создан Makefile${NC}"
else
    # Проверяем, есть ли уже команды LAMP в существующем Makefile
    if ! grep -q "start:" "Makefile"; then
        echo "" >> Makefile
        echo "# === GB-LAMP Commands ===" >> Makefile
        echo ".PHONY: init start clean db-import fix-permissions" >> Makefile
        echo "init: ## Инициализировать проект с выбором версии PHP" >> Makefile
        echo -e "\t./.docker/scripts/init.sh" >> Makefile
        echo "start: ## Запустить проект" >> Makefile
        echo -e "\t./.docker/scripts/start.sh" >> Makefile
        echo "db-import: ## Импортировать SQL-файл" >> Makefile
        echo -e "\t./.docker/scripts/db_import.sh" >> Makefile
        echo "clean: ## Очистить контейнеры и образы" >> Makefile
        echo -e "\tdocker compose down --volumes --remove-orphans" >> Makefile
        echo -e "\tdocker system prune -f" >> Makefile
        echo "fix-permissions: ## Настроить права доступа к файлам и папкам проекта" >> Makefile
        echo -e "\t./.docker/scripts/fixPermissions.sh" >> Makefile
        echo -e "${GREEN}✓ Команды GB-LAMP добавлены в существующий Makefile${NC}"
    fi
fi

# Делаем скрипты исполняемыми
chmod +x .docker/scripts/*.sh run.sh 2>/dev/null || true

echo -e "${GREEN}✓ Файлы окружения успешно размещены${NC}"
echo ""

# Шаг 3: Обновление .gitignore
if [ ! -f ".gitignore" ]; then
    touch ".gitignore"
fi

if ! grep -q "GB-LAMP Environment" ".gitignore"; then
    cat << 'EOF' >> .gitignore

# === GB-LAMP Environment ===
/.docker/logs/apache2/*
/.docker/logs/php/*
docker-compose.yml
.tmpDocker/
.env
*.sql

# === OpenCart Cache & Logs ===
/system/storage/cache/*
!/system/storage/cache/index.html
/system/storage/logs/*
!/system/storage/logs/index.html
/system/storage/session/*
!/system/storage/session/index.html
/system/storage/modification/*
!/system/storage/modification/index.html
/image/cache/*
!/image/cache/index.html
EOF
    echo -e "${GREEN}✓ Секции GB-LAMP и OpenCart добавлены в .gitignore${NC}"
    echo ""
fi

# Шаг 4: Передача управления в скрипт инициализации
echo -e "${BLUE}Запуск инициализации проекта (.docker/scripts/init.sh)...${NC}"
echo ""

if [ -e /dev/tty ] && [ ! -t 0 ]; then
    exec ./.docker/scripts/init.sh < /dev/tty
else
    exec ./.docker/scripts/init.sh
fi