import os
import json
import shutil
from pathlib import Path
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler
import argparse
import time
import re

# Пути
SCRIPT_DIR = Path(__file__).parent
CURRENT_DIR = Path.cwd()
MODULE_DIR = CURRENT_DIR / "upload"
OPENCART_DIR = CURRENT_DIR.parent.parent
JSON_FILE = CURRENT_DIR / "opencart-module.json"
TEMPLATES_DIR = SCRIPT_DIR / "templates"

# Хранение времени последней модификации файлов
last_modified_times = {}

def load_json():
    """Загружает данные из JSON файла."""
    if JSON_FILE.exists():
        with open(JSON_FILE, "r") as file:
            return json.load(file)
    return {}


def save_json(data):
    """Сохраняет данные в JSON файл."""
    with open(JSON_FILE, "w") as file:
        json.dump(data, file, indent=4)


def to_camel_case(snake_str):
    """Преобразование snake_case в CamelCase."""
    components = snake_str.split('_')
    return ''.join(x.title() for x in components)


def to_camel_case_lower(snake_str):
    """Преобразование snake_case в camelCase."""
    components = snake_str.split('_')
    return components[0].lower() + ''.join(x.title() for x in components[1:])


def init():
    """Инициализация списка файлов и запись в JSON."""
    if JSON_FILE.exists():
        data = load_json()
        print("Файл opencart-module.json уже существует. Обновляем список файлов.")
    else:
        module_name = input("Введите имя модуля: ")
        creator_name = input("Введите имя создателя: ")
        creator_email = input("Введите email создателя: ")
        data = {
            "module_name": module_name,
            "creator_name": creator_name,
            "creator_email": creator_email,
            "files": []
        }

    files = [str(path.relative_to(MODULE_DIR)) for path in MODULE_DIR.rglob("*") if path.is_file()]
    data["files"] = sorted(files)
    save_json(data)
    print("Файлы инициализированы:\n" + "\n".join(files))


def install():
    """Копирование файлов модуля в папку OpenCart и обновление JSON."""
    data = load_json()
    files = data.get("files", [])
    new_files = []

    for file in MODULE_DIR.rglob("*"):
        relative_path = file.relative_to(MODULE_DIR)
        dest_path = OPENCART_DIR / relative_path

        if file.is_file():
            dest_path.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(file, dest_path)
            print(f"Копирование {relative_path} -> {dest_path.relative_to(OPENCART_DIR)}")
            if str(relative_path) not in files:
                new_files.append(str(relative_path))

    if new_files:
        data["files"] = files + new_files
        save_json(data)
        print("Добавлены новые файлы:", new_files)
    print("Установка завершена.")


def remove():
    """Удаление файлов из OpenCart на основе JSON."""
    data = load_json()
    files = data.get("files", [])
    for relative_path in files:
        target_file = OPENCART_DIR / relative_path
        if target_file.exists():
            target_file.unlink()
            print(f"Удалено: {relative_path}")
        else:
            print(f"Пропущено (файл не найден): {relative_path}")
        # Удаление пустых папок
        parent_dir = target_file.parent
        while parent_dir != OPENCART_DIR:
            try:
                if not any(parent_dir.iterdir()):
                    parent_dir.rmdir()
                    print(f"Удалена пустая папка: {parent_dir.relative_to(OPENCART_DIR)}")
                else:
                    break
            except FileNotFoundError:
                print(f"Пропущено (папка не найдена): {parent_dir.relative_to(OPENCART_DIR)}")
                break
            parent_dir = parent_dir.parent
    print("Удаление завершено.")


def create():
    """Создание нового модуля по выбранному шаблону."""
    templates = [d for d in TEMPLATES_DIR.iterdir() if d.is_dir()]
    if not templates:
        print("Шаблоны не найдены.")
        return

    print("Доступные шаблоны:")
    for i, template in enumerate(templates, 1):
        print(f"{i}. {template.name}")

    try:
        template_number = int(input("Введите номер шаблона: ")) - 1
        if template_number < 0 or template_number >= len(templates):
            print("Неверный номер шаблона.")
            return
    except ValueError:
        print("Неверный ввод. Введите номер шаблона.")
        return

    template_dir = templates[template_number]
    module_name = input("Введите имя нового модуля (snake_case): ")
    camel_case_name = to_camel_case(module_name)
    camel_case_lower_name = to_camel_case_lower(module_name)

    new_module_dir = CURRENT_DIR / module_name

    if new_module_dir.exists():
        print(f"Модуль {module_name} уже существует.")
        return

    shutil.copytree(template_dir, new_module_dir)

    # Замена в именах файлов и папок
    for root, dirs, files in os.walk(new_module_dir):
        for dir_name in dirs:
            new_dir_name = dir_name.replace("{{#ModuleName}}", camel_case_name).replace("{{#moduleName}}", camel_case_lower_name).replace("{{#module_name}}", module_name)
            os.rename(os.path.join(root, dir_name), os.path.join(root, new_dir_name))
        for file_name in files:
            new_file_name = file_name.replace("{{#ModuleName}}", camel_case_name).replace("{{#moduleName}}", camel_case_lower_name).replace("{{#module_name}}", module_name)
            os.rename(os.path.join(root, file_name), os.path.join(root, new_file_name))

    # Замена в содержимом файлов
    for root, dirs, files in os.walk(new_module_dir):
        for file_name in files:
            file_path = os.path.join(root, file_name)
            with open(file_path, 'r') as file:
                content = file.read()
            content = content.replace("{{#ModuleName}}", camel_case_name).replace("{{#moduleName}}", camel_case_lower_name).replace("{{#module_name}}", module_name)
            with open(file_path, 'w') as file:
                file.write(content)

    print(f"Модуль {module_name} создан на основе шаблона {template_dir.name}.")


class ChangeHandler(FileSystemEventHandler):
    """Обработчик изменений файлов в режиме dev."""

    def on_modified(self, event):
        if event.is_directory:
            return
        self.sync_file(event.src_path)

    def on_created(self, event):
        if event.is_directory:
            return
        self.sync_file(event.src_path)

    def on_deleted(self, event):
        if event.is_directory:
            return
        self.remove_file(event.src_path)

    def sync_file(self, src_path):
        src_path = Path(src_path)
        relative_path = src_path.relative_to(MODULE_DIR)
        dest_path = OPENCART_DIR / relative_path

        try:
            # Проверка времени последней модификации
            current_time = src_path.stat().st_mtime
            if relative_path in last_modified_times and last_modified_times[relative_path] == current_time:
                return

            last_modified_times[relative_path] = current_time

            dest_path.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(src_path, dest_path)
            print("Синхронизировано:", dest_path)

            # Обновляем JSON
            data = load_json()
            files = data.get("files", [])
            if str(relative_path) not in files:
                files.append(str(relative_path))
                data["files"] = files
                save_json(data)
        except FileNotFoundError:
            print(f"Пропущено (файл не найден): {src_path}")

    def remove_file(self, src_path):
        src_path = Path(src_path)
        relative_path = src_path.relative_to(MODULE_DIR)
        dest_path = OPENCART_DIR / relative_path

        if dest_path.exists():
            dest_path.unlink()
            print(f"Удалено: {dest_path}")
        else:
            print(f"Пропущено (файл не найден): {dest_path}")

        # Удаление пустых папок
        parent_dir = dest_path.parent
        while parent_dir != OPENCART_DIR:
            try:
                if not any(parent_dir.iterdir()):
                    parent_dir.rmdir()
                    print(f"Удалена пустая папка: {parent_dir.relative_to(OPENCART_DIR)}")
                else:
                    break
            except FileNotFoundError:
                print(f"Пропущено (папка не найдена): {parent_dir.relative_to(OPENCART_DIR)}")
                break
            parent_dir = parent_dir.parent

        # Обновляем JSON
        data = load_json()
        files = data.get("files", [])
        if str(relative_path) in files:
            files.remove(str(relative_path))
            data["files"] = files
            save_json(data)


def dev():
    """Режим наблюдения за изменениями."""
    observer = Observer()
    event_handler = ChangeHandler()
    observer.schedule(event_handler, str(MODULE_DIR), recursive=True)

    print("Запущен режим наблюдения. Нажмите Ctrl+C для выхода.")
    observer.start()
    try:
        while True:
            time.sleep(1)  # Добавлено ожидание
    except KeyboardInterrupt:
        observer.stop()
    observer.join()


def run_script(script_path):
    """Запуск указанного скрипта."""
    script_path = Path(script_path)
    if script_path.exists() and script_path.is_file():
        exec(open(script_path).read())
    else:
        print(f"Скрипт {script_path} не найден.")


def show_help():
    """Вывод справки."""
    parser.print_help()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Скрипт управления модулями OpenCart.")
    parser.add_argument("command", choices=["init", "install", "dev", "remove", "create", "help"], help="Команда для выполнения")
    parser.add_argument("--script", help="Путь к скрипту для запуска")
    args = parser.parse_args()

    if args.command == "init":
        init()
    elif args.command == "install":
        install()
    elif args.command == "dev":
        dev()
    elif args.command == "remove":
        remove()
    elif args.command == "create":
        create()
    elif args.command == "help":
        show_help()
    
    if args.script:
        run_script(args.script)
