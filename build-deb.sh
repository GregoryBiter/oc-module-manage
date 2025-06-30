#!/bin/bash

# Скрипт для сборки deb пакета oc-module-manage
# Автор: Gregory Biter

set -e  # Остановка при ошибке

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Функция для вывода с цветом
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Переменные
SCRIPT_DIR=$(dirname "$(readlink -f "$0")")
BUILD_DIR="$SCRIPT_DIR/build"
PACKAGE_NAME="oc-module-manage"

# Очистка предыдущей сборки
cleanup() {
    print_status "Очистка предыдущих файлов сборки..."
    rm -rf "$BUILD_DIR"
    rm -f ../*.deb
    rm -f ../*.changes
    rm -f ../*.buildinfo
    find . -name "*.deb" -delete 2>/dev/null || true
    print_success "Очистка завершена"
}

# Проверка зависимостей
check_dependencies() {
    print_status "Проверка зависимостей для сборки..."
    
    local missing_deps=()
    
    # Проверяем наличие необходимых пакетов
    if ! dpkg -l | grep -q "debhelper"; then
        missing_deps+=("debhelper")
    fi
    
    if ! dpkg -l | grep -q "build-essential"; then
        missing_deps+=("build-essential")
    fi
    
    if ! dpkg -l | grep -q "devscripts"; then
        missing_deps+=("devscripts")
    fi
    
    if [ ${#missing_deps[@]} -ne 0 ]; then
        print_warning "Отсутствуют зависимости: ${missing_deps[*]}"
        print_status "Устанавливаем недостающие пакеты..."
        sudo apt update
        sudo apt install -y "${missing_deps[@]}"
    fi
    
    print_success "Все зависимости установлены"
}

# Валидация структуры проекта
validate_structure() {
    print_status "Проверка структуры проекта..."
    
    # Проверяем основные файлы
    local required_files=(
        "oc-module.php"
        "ocm"
        "debian/control"
        "debian/rules"
        "debian/changelog"
        "debian/install"
        "debian/postinst"
        "debian/prerm"
    )
    
    for file in "${required_files[@]}"; do
        if [ ! -f "$file" ]; then
            print_error "Отсутствует обязательный файл: $file"
            exit 1
        fi
    done
    
    # Проверяем права выполнения
    if [ ! -x "debian/rules" ]; then
        print_warning "Устанавливаем права выполнения для debian/rules"
        chmod +x debian/rules
    fi
    
    if [ ! -x "debian/postinst" ]; then
        chmod +x debian/postinst
    fi
    
    if [ ! -x "debian/prerm" ]; then
        chmod +x debian/prerm
    fi
    
    if [ ! -x "ocm" ]; then
        chmod +x ocm
    fi
    
    print_success "Структура проекта корректна"
}

# Сборка пакета
build_package() {
    print_status "Начинаем сборку deb пакета..."
    
    # Сборка с помощью debuild
    if command -v debuild >/dev/null 2>&1; then
        print_status "Используем debuild для сборки..."
        debuild -us -uc -b
    else
        print_status "Используем dpkg-buildpackage для сборки..."
        dpkg-buildpackage -us -uc -b
    fi
    
    print_success "Сборка завершена"
}

# Поиск созданного пакета
find_package() {
    print_status "Поиск созданного пакета..."
    
    # Ищем .deb файл в родительской директории
    local deb_file=$(find .. -name "${PACKAGE_NAME}*.deb" -type f | head -1)
    
    if [ -n "$deb_file" ]; then
        print_success "Пакет создан: $deb_file"
        
        # Показываем информацию о пакете
        print_status "Информация о пакете:"
        dpkg-deb --info "$deb_file"
        
        print_status "Содержимое пакета:"
        dpkg-deb --contents "$deb_file"
        
        # Копируем пакет в текущую директорию для удобства
        local package_filename=$(basename "$deb_file")
        cp "$deb_file" "./$package_filename"
        print_success "Пакет скопирован в текущую директорию: $package_filename"
        
        return 0
    else
        print_error "Пакет не найден!"
        return 1
    fi
}

# Тестирование пакета
test_package() {
    local package_file="$1"
    
    if [ -z "$package_file" ]; then
        package_file=$(find . -name "${PACKAGE_NAME}*.deb" -type f | head -1)
    fi
    
    if [ -z "$package_file" ]; then
        print_error "Файл пакета не найден для тестирования"
        return 1
    fi
    
    print_status "Тестирование пакета: $package_file"
    
    # Проверяем что пакет можно установить
    print_status "Проверка зависимостей пакета..."
    if dpkg-deb --info "$package_file" | grep -q "Depends:"; then
        print_success "Зависимости корректны"
    fi
    
    # Предлагаем установить пакет
    read -p "Хотите установить пакет для тестирования? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_status "Устанавливаем пакет..."
        sudo dpkg -i "$package_file" || {
            print_warning "Возможно отсутствуют зависимости, пытаемся исправить..."
            sudo apt-get install -f
        }
        
        print_status "Тестируем команду ocm..."
        if command -v ocm >/dev/null 2>&1; then
            print_success "Команда ocm доступна"
            ocm help || true
        else
            print_error "Команда ocm недоступна"
        fi
    fi
}

# Главная функция
main() {
    print_status "=== Сборка deb пакета $PACKAGE_NAME ==="
    
    # Проверяем что мы в правильной директории
    if [ ! -f "oc-module.php" ]; then
        print_error "Запустите скрипт из корневой директории проекта"
        exit 1
    fi
    
    case "${1:-build}" in
        "clean")
            cleanup
            ;;
        "deps")
            check_dependencies
            ;;
        "build")
            cleanup
            check_dependencies
            validate_structure
            build_package
            find_package
            ;;
        "test")
            test_package "$2"
            ;;
        "full")
            cleanup
            check_dependencies
            validate_structure
            build_package
            if find_package; then
                test_package
            fi
            ;;
        "help")
            echo "Использование: $0 [команда]"
            echo ""
            echo "Команды:"
            echo "  build  - Собрать пакет (по умолчанию)"
            echo "  clean  - Очистить файлы сборки"
            echo "  deps   - Проверить/установить зависимости"
            echo "  test   - Протестировать пакет"
            echo "  full   - Полная сборка с тестированием"
            echo "  help   - Показать эту справку"
            ;;
        *)
            print_error "Неизвестная команда: $1"
            echo "Используйте '$0 help' для справки"
            exit 1
            ;;
    esac
}

# Запуск
main "$@"
