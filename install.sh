#!/bin/bash

# OCM (OpenCart Module Manager) Installer
# https://github.com/GregoryBiter/oc-module-manage

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Config
REPO_URL="https://github.com/GregoryBiter/oc-module-manage"
INSTALL_DIR="$HOME/.local/share/ocm"
BIN_DIR="$HOME/.local/bin"
EXECUTABLE="$BIN_DIR/ocm"
TMP_DIR="/tmp/ocm-install"

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Check dependencies
check_system_deps() {
    log_info "Проверка системных зависимостей..."
    
    if ! command -v git &> /dev/null && ! command -v curl &> /dev/null; then
        log_error "Требуется curl или git для скачивания."
        exit 1
    fi
    
    if ! command -v tar &> /dev/null; then
        log_error "Требуется tar для распаковки."
        exit 1
    fi
}

install_ocm() {
    log_info "Подготовка к установке..."
    mkdir -p "$INSTALL_DIR"
    mkdir -p "$BIN_DIR"
    rm -rf "$TMP_DIR"
    mkdir -p "$TMP_DIR"
    
    log_info "Скачивание OCM из GitHub..."
    if command -v curl &> /dev/null; then
        curl -L "$REPO_URL/archive/refs/heads/master.tar.gz" -o "$TMP_DIR/ocm.tar.gz"
    else
        wget "$REPO_URL/archive/refs/heads/master.tar.gz" -O "$TMP_DIR/ocm.tar.gz"
    fi
    
    log_info "Распаковка архива..."
    tar -xzf "$TMP_DIR/ocm.tar.gz" -C "$TMP_DIR" --strip-components=1
    
    log_info "Копирование файлов в $INSTALL_DIR..."
    # Удаляем старые файлы перед копированием
    rm -rf "$INSTALL_DIR"/*
    cp -r "$TMP_DIR"/* "$INSTALL_DIR/"
    
    log_info "Настройка прав доступа..."
    chmod +x "$INSTALL_DIR/ocm"
    chmod +x "$INSTALL_DIR/oc-module.php"
    
    log_info "Создание символьной ссылки в $BIN_DIR..."
    ln -sf "$INSTALL_DIR/ocm" "$EXECUTABLE"
    
    # Очистка
    rm -rf "$TMP_DIR"
}

check_path() {
    if [[ ":$PATH:" != *":$BIN_DIR:"* ]]; then
        log_warning "$BIN_DIR не найден в вашем PATH."
        log_info "Пожалуйста, добавьте его в ~/.bashrc или ~/.zshrc:"
        echo -e "\n    echo 'export PATH=\"\$HOME/.local/bin:\$PATH\"' >> ~/.bashrc\n"
    fi
}

main() {
    echo -e "${BLUE}"
    echo "=================================================="
    echo "  OCM (OpenCart Module Manager) - Установка"
    echo "=================================================="
    echo -e "${NC}"
    
    check_system_deps
    install_ocm
    
    echo -e "${GREEN}"
    echo "=================================================="
    echo "  Установка успешно завершена!"
    echo "=================================================="
    echo -e "${NC}"
    
    log_success "Команда 'ocm' теперь доступна из терминала."
    
    # Проверка PHP зависимостей через сам ocm
    log_info "Проверка PHP зависимостей..."
    "$EXECUTABLE" help > /dev/null || log_warning "Возможно, требуются дополнительные PHP расширения."
    
    check_path
}

main "$@"
