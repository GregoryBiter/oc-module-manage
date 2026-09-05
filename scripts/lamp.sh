#!/bin/bash
set -e

TARGET_DIR="${1:-.}"

echo "=== OCM: Установка окружения LAMP для OpenCart (gb-lamp) ==="

if [ "$TARGET_DIR" != "." ] && [ ! -d "$TARGET_DIR" ]; then
    mkdir -p "$TARGET_DIR"
fi

git clone https://github.com/GregoryBiter/gb-lamp.git "$TARGET_DIR"

echo "=== Окружение LAMP успешно установлено в $TARGET_DIR ==="