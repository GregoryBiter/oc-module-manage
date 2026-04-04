<?php

// Пути
if (!defined('SCRIPT_DIR')) define('SCRIPT_DIR', dirname(dirname(__FILE__)));

// Устаревшие функциональные команды (для совместимости на время рефакторинга)
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/commands/dev.php';
require_once __DIR__ . '/commands/init.php';
require_once __DIR__ . '/commands/install.php';
require_once __DIR__ . '/commands/remove.php';
require_once __DIR__ . '/commands/create.php';
require_once __DIR__ . '/commands/build.php';
require_once __DIR__ . '/commands/help.php';
require_once __DIR__ . '/commands/return.php';

// Новый объектно-ориентированный движок команд
require_once __DIR__ . '/Base/Input.php';
require_once __DIR__ . '/Base/Output.php';
require_once __DIR__ . '/Base/Command.php';
require_once __DIR__ . '/Base/Application.php';

// Классы команд
require_once __DIR__ . '/Commands/InitCommand.php';
require_once __DIR__ . '/Commands/InstallCommand.php';
require_once __DIR__ . '/Commands/DevCommand.php';
require_once __DIR__ . '/Commands/RemoveCommand.php';
require_once __DIR__ . '/Commands/ReturnCommand.php';
require_once __DIR__ . '/Commands/CreateCommand.php';
require_once __DIR__ . '/Commands/BuildCommand.php';
require_once __DIR__ . '/Commands/MigrateCommand.php';
require_once __DIR__ . '/Commands/HelpCommand.php';
