import os
import json
import shutil
from pathlib import Path
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler
import argparse
import time

# Пути
CURRENT_DIR = Path.cwd()
MODULE_DIR = CURRENT_DIR / "upload"
OPENCART_DIR = CURRENT_DIR.parent.parent
JSON_FILE = CURRENT_DIR / "opencart-module.json"

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
            pass
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
    parser.add_argument("command", choices=["init", "install", "dev", "remove", "help"], help="Команда для выполнения")
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
    elif args.command == "help":
        show_help()
    
    if args.script:
        run_script(args.script)
