# OpenCart Module Manager (OCM)

OpenCart Module Management Tool - инструмент командной строки для управления модулями OpenCart.

## Описание

OCM - это PHP-инструмент, который упрощает разработку и управление модулями OpenCart. Он предоставляет команды для создания, установки, сборки и разработки модулей с использованием системы шаблонов.

## Возможности

- 🚀 Создание новых модулей OpenCart из шаблонов
- 📦 Установка и удаление модулей
- 🔧 Режим разработки с отслеживанием изменений файлов
- 🏗️ Сборка модулей для распространения
- 📋 Система шаблонов для генерации модулей

## Установка

Для установки OCM выполните следующую команду в терминале:

```bash
curl -sL https://raw.githubusercontent.com/GregoryBiter/oc-module-manage/master/install.sh | bash
```

Или, если вы уже клонировали репозиторий:

```bash
chmod +x install.sh
./install.sh
```

## Требования

- **PHP** >= 7.4
- **Composer** - для управления PHP-зависимостями
- **php-cli** - интерфейс командной строки PHP
- **php-json**, **php-zip**, **php-mbstring** - необходимые расширения PHP

Зависимости автоматически устанавливаются при установке пакета.

## Использование

После установки команда `ocm` доступна из любой директории:

```bash
# Показать справку
ocm help

# Инициализировать новый модуль
ocm init

# Создать модуль из шаблона
ocm create

# Установить модуль в OpenCart
ocm install

# Запустить режим разработки
ocm dev

# Собрать модуль для распространения
ocm build

# Удалить модуль из OpenCart
ocm remove

# Вернуть файлы из OpenCart в проект
ocm return
```

## Примеры

### Создание нового модуля

```bash
# Переходим в директорию проектов
cd ~/projects

# Инициализируем новый модуль
ocm init
# Введите название модуля: my_awesome_module

# Создаем файлы модуля из шаблона
ocm create

# Запускаем режим разработки
ocm dev
```

### Установка модуля в OpenCart

```bash
# Находясь в директории модуля
ocm install

# Сборка модуля с архивом
ocm build -a
```

## Структура проекта

```
my_module/
├── opencart-module.json    # Конфигурация модуля
├── upload/                 # Файлы модуля для OpenCart
│   └── admin/
│       ├── controller/
│       ├── language/
│       ├── model/
│       └── view/
└── .path-opencart         # Путь к установке OpenCart (опционально)
```

## Конфигурация

### Файл opencart-module.json

```json
{
    "module_name": "my_module",
    "display_name": "My Awesome Module",
    "version": "1.0.0",
    "author": "Your Name",
    "description": "Description of my module"
}
```

### Указание пути к OpenCart

Создайте файл `.path-opencart` в корне проекта:

```bash
echo "/path/to/your/opencart" > .path-opencart
```

## Шаблоны

OCM поддерживает несколько шаблонов модулей:

- **my_module** - базовый шаблон с полной функциональностью
- **ocm_gbt_extension_module** - упрощенный шаблон

## Режим разработки

Команда `ocm dev` запускает отслеживание изменений файлов и автоматически синхронизирует их с установкой OpenCart.

## Сборка и развертывание

### Локальная сборка

```bash
# Сборка без архива
ocm build

# Сборка с созданием ZIP архива
ocm build -a
```

### Обновление OCM

Для обновления просто запустите команду установки еще раз.

## Устранение неполадок

### Проблемы с PHP

```bash
# Проверка версии PHP
php --version

# Установка необходимых расширений
sudo apt install php-cli php-json
```

### Проблемы с правами доступа

```bash
# Убедитесь что OCM имеет права на запись в директорию OpenCart
sudo chown -R $USER:$USER /path/to/opencart
```

## Поддержка

- **GitHub**: [https://github.com/GregoryBiter/oc-module-manage](https://github.com/GregoryBiter/oc-module-manage)
- **Issues**: [https://github.com/GregoryBiter/oc-module-manage/issues](https://github.com/GregoryBiter/oc-module-manage/issues)

## Лицензия

MIT License. См. файл [LICENSE](LICENSE) для подробностей.

## Автор

Gregory Biter - [your.email@example.com](mailto:your.email@example.com)

---

**OCM** - делает разработку модулей OpenCart быстрой и приятной! 🚀
