# OpenCart Module Manager (OCM)

**OCM** — это инструмент командной строки (CLI) для быстрой и удобной разработки модулей OpenCart, созданный по подобию **Laravel Artisan**, но полностью автономный от самого движка OpenCart.

---

## Возможности

- 🚀 **Artisan DX**: понятные неймспейсы команд (`make:module`, `module:dev`, `module:build`, `ocmod:refresh`, `cache:clear`) и короткие привычные алиасы (`dev`, `build`, `install`, `create`, `info`).
- ⚡ **Мгновенная установка через Composer**: глобальный пакет для Linux, macOS и Windows.
- 📦 **Надежная сборка `*.ocmod.zip`**: автоматическая упаковка только нужных файлов (`upload/`, `install.xml`, `install.php`) без мусора и репозиторных файлов.
- 🔄 **Режим Watch (`ocm dev`)**: мгновенная синхронизация изменений в установку OpenCart в реальном времени.
- 🛠️ **Инструменты OpenCart**: автономный сброс модификаторов (`ocm ocmod:refresh`) и очистка системного кэша (`ocm cache:clear`).
- 📋 **Каскадные шаблоны**: поддержка встроенных, глобальных пользовательских (`~/.config/ocm/templates`) и проектных (`./.ocm/templates`) шаблонов.
- 🔗 **Привязка к OpenCart**: простая команда `ocm link /path/to/opencart` без ручного редактирования файлов.

---

## Установка

### 1. Глобальная установка через Composer (Рекомендуется)

```bash
composer global require gregorybiter/oc-module-manage
```

> [!TIP]
> Убедитесь, что каталог глобальных бинарников Composer добавлен в переменную `PATH`.
> Для Linux/macOS добавьте в `~/.bashrc` или `~/.zshrc`:
> ```bash
> export PATH="$HOME/.config/composer/vendor/bin:$HOME/.composer/vendor/bin:$PATH"
> ```

Обновление утилиты до последней версии:
```bash
composer global update gregorybiter/oc-module-manage
```

---

### 2. Локальная установка в проект модуля

Вы можете установить OCM как dev-зависимость прямо в репозиторий вашего модуля:

```bash
composer require --dev gregorybiter/oc-module-manage
```

И запускать через:
```bash
./vendor/bin/ocm list
```

---

### 3. Установка через Shell-скрипт (Legacy)

```bash
curl -sL https://raw.githubusercontent.com/GregoryBiter/oc-module-manage/master/install.sh | bash
```

---

## Системные требования

- **PHP** >= 7.4 (поддерживаются PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4)
- **Composer**
- Расширения PHP: `ext-json`, `ext-zip`, `ext-fileinfo` (для строгой валидации XML рекомендуется `ext-dom`)

---

## Использование и Команды

Выполните `ocm list` для просмотра всех доступных команд или `ocm <команда> --help` для подробной справки по опциям.

```
OCM (OpenCart Module Manager) 2.0.0

Доступные команды:
  link            Привязать текущий модуль к директории OpenCart
  status          [info] Показать статус текущего модуля и привязки к OpenCart
  init            Инициализация метаданных модуля и списка файлов (.ocm/files.json)
  migrate         Миграция конфигурации и списков файлов в новый формат .ocm/

 cache
  cache:clear     [cc] Очистить системный кэш OpenCart (system/storage/cache)

 make
  make:module     [create] Создать новый модуль OpenCart из шаблона

 module
  module:build    [build] Сборка готового к распространению архива (*.ocmod.zip)
  module:dev      [dev|watch] Режим наблюдения за изменениями файлов и авто-синхронизация
  module:install  [install] Копирование файлов модуля в установку OpenCart
  module:pull     [return] Возврат файлов из OpenCart в папку модуля (upload/)
  module:remove   [remove] Удаление файлов модуля и модификаций из OpenCart

 ocmod
  ocmod:refresh   Обновить OCMOD модификаторы в связанном OpenCart

 template
  template:list   Список доступных шаблонов модулей OCM
```

---

## Быстрый старт: сценарии работы

### 1. Создание нового модуля

```bash
# Интерактивное создание:
ocm make:module

# Или с указанием параметров в одну команду:
ocm make:module my_super_filter --template=my_module --title="Super Filter" --ver="1.0.0"

# Переходим в созданный модуль:
cd my_super_filter
```

### 2. Привязка к установке OpenCart

```bash
ocm link /var/www/my-opencart.loc
```

### 3. Режим активной разработки

```bash
ocm dev
```
Команда выполнит первичную установку файлов и будет отслеживать изменения в `upload/` и `install.xml`, мгновенно отправляя их в OpenCart.

### 4. Сборка архива для клиентов / маркетплейса

```bash
ocm build
```
Создаст чистый, готовый к загрузке в админку OpenCart архив: `my_super_filter.ocmod.zip` (содержит только `upload/` и `install.xml`).

### 5. Полезные команды для OpenCart

```bash
# Очистить кэш модификаций и перекомпилировать OCMOD:
ocm ocmod:refresh

# Очистить системный кэш OpenCart:
ocm cache:clear

# Посмотреть текущий статус модуля и привязки:
ocm status

# Забрать файлы из OpenCart обратно в модуль (если правили код прямо в магазине):
ocm return
```

---

## Структура проекта модуля

```
my_module/
├── opencart-module.json    # Метаданные модуля (название, код, версия, автор)
├── install.xml             # OCMOD-модификатор (опционально)
├── upload/                 # Файлы модуля для OpenCart
│   ├── admin/
│   │   ├── controller/
│   │   ├── language/
│   │   ├── model/
│   │   └── view/
│   └── catalog/
└── .ocm/                   # Служебная директория OCM (создается автоматически)
    ├── files.json          # Список отслеживаемых файлов
    └── target              # Путь к связанному OpenCart
```

---

## Шаблоны (Templates)

OCM поддерживает трехуровневую систему шаблонов:
1. **Локальные для проекта**: `./.ocm/templates/`
2. **Пользовательские**: `~/.config/ocm/templates/`
3. **Встроенные**: поставляются вместе с пакетом OCM

Посмотреть список шаблонов:
```bash
ocm template:list
```

---

## Тестирование

```bash
composer test
```

---

## Лицензия

MIT License. См. файл [LICENSE](LICENSE).
