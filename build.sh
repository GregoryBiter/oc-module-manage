#!/bin/bash

# Скрипт сборки deb пакета для OCM (OpenCart Module Manager)

set -e

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Функция вывода сообщений
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Проверка зависимостей
check_dependencies() {
    log_info "Проверка зависимостей..."
    
    local missing_deps=()
    
    if ! command -v dpkg-buildpackage >/dev/null 2>&1; then
        missing_deps+=("build-essential")
    fi
    
    if ! command -v dh >/dev/null 2>&1; then
        missing_deps+=("debhelper")
    fi
    
    if ! command -v debuild >/dev/null 2>&1; then
        missing_deps+=("devscripts")
    fi
    
    if [ ${#missing_deps[@]} -gt 0 ]; then
        log_warning "Отсутствуют зависимости: ${missing_deps[*]}"
        log_info "Устанавливаю зависимости..."
        sudo apt update
        sudo apt install -y "${missing_deps[@]}"
    fi
    
    log_success "Все зависимости установлены"
}

# Очистка предыдущих сборок
clean_build() {
    log_info "Очистка предыдущих сборок..."
    
    # Очистка debian временных файлов
    if [ -d "debian/.debhelper" ]; then
        rm -rf debian/.debhelper
    fi
    
    if [ -f "debian/debhelper-build-stamp" ]; then
        rm -f debian/debhelper-build-stamp
    fi
    
    if [ -f "debian/files" ]; then
        rm -f debian/files
    fi
    
    if [ -d "debian/oc-module-manage" ]; then
        rm -rf debian/oc-module-manage
    fi
    
    # Очистка старых пакетов
    rm -f ../oc-module-manage_*.deb
    rm -f ../oc-module-manage_*.buildinfo
    rm -f ../oc-module-manage_*.changes
    
    log_success "Очистка завершена"
}

# Проверка структуры проекта
validate_project() {
    log_info "Проверка структуры проекта..."
    
    local required_files=(
        "oc-module.php"
        "ocm"
        "debian/control"
        "debian/rules"
        "debian/changelog"
        "debian/install"
    )
    
    for file in "${required_files[@]}"; do
        if [ ! -f "$file" ]; then
            log_error "Отсутствует обязательный файл: $file"
            exit 1
        fi
    done
    
    # Проверка прав на исполнение
    if [ ! -x "debian/rules" ]; then
        chmod +x debian/rules
        log_info "Установлены права выполнения для debian/rules"
    fi
    
    if [ ! -x "ocm" ]; then
        chmod +x ocm
        log_info "Установлены права выполнения для ocm"
    fi
    
    if [ ! -x "oc-module.php" ]; then
        chmod +x oc-module.php
        log_info "Установлены права выполнения для oc-module.php"
    fi
    
    log_success "Структура проекта валидна"
}

# Сборка пакета
build_package() {
    log_info "Начинаю сборку deb пакета..."
    
    # Сборка пакета
    if dpkg-buildpackage -b --no-sign; then
        log_success "Пакет успешно собран!"
        
        # Показываем информацию о созданном пакете
        if [ -f "../oc-module-manage_"*"_all.deb" ]; then
            local deb_file=$(ls ../oc-module-manage_*_all.deb | head -1)
            local file_size=$(du -h "$deb_file" | cut -f1)
            
            log_info "Создан пакет: $(basename "$deb_file")"
            log_info "Размер: $file_size"
            
            # Показываем содержимое пакета
            log_info "Содержимое пакета:"
            dpkg -c "$deb_file" | head -20
            
            if [ $(dpkg -c "$deb_file" | wc -l) -gt 20 ]; then
                log_info "... и еще $(( $(dpkg -c "$deb_file" | wc -l) - 20 )) файлов"
            fi
        fi
    else
        log_error "Ошибка при сборке пакета"
        exit 1
    fi
}

# Тестирование пакета (опционально)
test_package() {
    if [ "$1" = "--test" ]; then
        log_info "Тестирование пакета..."
        
        local deb_file=$(ls ../oc-module-manage_*_all.deb | head -1)
        
        if [ -f "$deb_file" ]; then
            log_info "Проверка структуры пакета..."
            lintian "$deb_file" || log_warning "Lintian нашел предупреждения (не критично)"
            
            log_info "Тестовая установка пакета..."
            if sudo dpkg -i "$deb_file"; then
                log_success "Пакет успешно установлен"
                
                # Проверяем что команда работает
                if command -v ocm >/dev/null 2>&1; then
                    log_success "Команда 'ocm' доступна"
                    ocm help | head -5
                else
                    log_error "Команда 'ocm' не найдена после установки"
                fi
                
                # Удаляем пакет после тестирования
                log_info "Удаляю тестовый пакет..."
                sudo dpkg -r oc-module-manage
            else
                log_error "Ошибка при тестовой установке"
            fi
        fi
    fi
}

# Главная функция
main() {
    echo -e "${BLUE}"
    echo "=================================================="
    echo "  OCM (OpenCart Module Manager) - Сборка пакета"
    echo "=================================================="
    echo -e "${NC}"
    
    # Проверяем что мы в правильной директории
    if [ ! -f "oc-module.php" ]; then
        log_error "Запустите скрипт из корневой директории проекта"
        exit 1
    fi
    
    check_dependencies
    clean_build
    validate_project
    build_package
    test_package "$1"
    
    echo -e "${GREEN}"
    echo "=================================================="
    echo "  Сборка завершена успешно!"
    echo "=================================================="
    echo -e "${NC}"
    
    log_info "Для установки пакета выполните:"
    echo "sudo dpkg -i ../oc-module-manage_*_all.deb"
    echo ""
    log_info "Для публикации в репозиторий скопируйте .deb файл"
    echo "в директорию веб-сервера: http://cdn.gbit-studio.com/debs/ocm/"
}

# Обработка аргументов
case "${1:-}" in
    --help|-h)
        echo "Использование: $0 [--test] [--help]"
        echo ""
        echo "Опции:"
        echo "  --test    Выполнить тестовую установку пакета"
        echo "  --help    Показать эту справку"
        exit 0
        ;;
    *)
        main "$@"
        ;;
esac
