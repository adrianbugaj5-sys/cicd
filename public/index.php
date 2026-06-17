<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Repository\SessionTaskRepository;
use App\Service\TaskService;

$service = new TaskService(new SessionTaskRepository());

// — handle POST actions, then redirect (PRG pattern) —
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = isset($_POST['id']) ? (int) $_POST['id'] : null;
    $title  = trim($_POST['title'] ?? '');



    try {
        match ($action) {
            'add'      => $service->createTask($title),
            'complete' => $service->completeTask($id),
            'delete'   => $service->deleteTask($id),
            'rename'   => $service->renameTask($id, $title),
            default    => null,
        };
    } catch (Throwable) {
        // silently ignore (empty title etc.)
    }

    header('Location: /');
    exit;
}

$pending   = $service->listPending();
$completed = $service->listCompleted();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Task Manager</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f0f2f5;
            color: #1a1a2e;
            padding: 2rem 1rem;
            min-height: 100vh;
        }

        .container { max-width: 680px; margin: 0 auto; }

        h1 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        .badge {
            background: #e0e7ff;
            color: #4338ca;
            font-size: .7rem;
            font-weight: 600;
            padding: .2rem .55rem;
            border-radius: 99px;
        }

        /* Add form */
        .add-form {
            display: flex;
            gap: .5rem;
            margin-bottom: 1.75rem;
        }

        .add-form input[type=text] {
            flex: 1;
            padding: .65rem .9rem;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: .95rem;
            outline: none;
            transition: border-color .15s;
        }

        .add-form input[type=text]:focus { border-color: #6366f1; }

        .btn {
            padding: .65rem 1.1rem;
            border: none;
            border-radius: 8px;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .15s;
        }

        .btn:hover { opacity: .85; }
        .btn-primary { background: #6366f1; color: #fff; }
        .btn-success { background: #22c55e; color: #fff; }
        .btn-danger  { background: #ef4444; color: #fff; padding: .4rem .7rem; font-size: .8rem; }
        .btn-ghost   { background: #e5e7eb; color: #374151; padding: .4rem .7rem; font-size: .8rem; }

        /* Sections */
        .section { margin-bottom: 1.5rem; }

        .section-header {
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: .6rem;
            padding-left: .25rem;
        }

        .task-list { display: flex; flex-direction: column; gap: .5rem; }

        .task {
            background: #fff;
            border-radius: 10px;
            padding: .8rem 1rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }

        .task.completed { opacity: .6; }

        .task-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .dot-pending   { background: #f59e0b; }
        .dot-completed { background: #22c55e; }

        /* Inline rename form */
        .task-title-form {
            flex: 1;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .task-title-form input[type=text] {
            flex: 1;
            border: none;
            background: transparent;
            font-size: .95rem;
            color: inherit;
            outline: none;
            border-bottom: 1.5px solid transparent;
            transition: border-color .15s;
            min-width: 0;
        }

        .task-title-form input[type=text]:focus {
            border-bottom-color: #6366f1;
        }

        .task.completed .task-title-form input[type=text] {
            text-decoration: line-through;
        }

        .task-actions { display: flex; gap: .35rem; flex-shrink: 0; }

        .empty {
            text-align: center;
            color: #9ca3af;
            font-size: .9rem;
            padding: 1.2rem;
            background: #fff;
            border-radius: 10px;
        }

        .counter {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            color: #6b7280;
            border-radius: 99px;
            min-width: 1.4rem;
            height: 1.4rem;
            font-size: .72rem;
            font-weight: 700;
            padding: 0 .35rem;
        }
    </style>
</head>
<body>
<div class="container">

    <h1>
        Task Manager
        <span class="badge">PHP <?= PHP_VERSION ?></span>
    </h1>

    <form class="add-form" method="POST">
        <input type="hidden" name="action" value="add">
        <input type="text" name="title" placeholder="Nowe zadanie…" autofocus required>
        <button class="btn btn-primary" type="submit">Dodaj</button>
    </form>

    <!-- Pending tasks -->
    <div class="section">
        <div class="section-header">
            Do zrobienia <span class="counter"><?= count($pending) ?></span>
        </div>
        <div class="task-list">
            <?php if (empty($pending)): ?>
                <div class="empty">Brak zadań — dobra robota!</div>
            <?php else: ?>
                <?php foreach ($pending as $task): ?>
                <div class="task">
                    <span class="task-dot dot-pending"></span>
                    <form class="task-title-form" method="POST">
                        <input type="hidden" name="action" value="rename">
                        <input type="hidden" name="id" value="<?= $task->getId() ?>">
                        <input type="text" name="title"
                               value="<?= htmlspecialchars($task->getTitle()) ?>"
                               onblur="this.form.requestSubmit()"
                               title="Kliknij, żeby zmienić nazwę">
                    </form>
                    <div class="task-actions">
                        <form method="POST">
                            <input type="hidden" name="action" value="complete">
                            <input type="hidden" name="id" value="<?= $task->getId() ?>">
                            <button class="btn btn-success" type="submit" title="Ukończ">✓</button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $task->getId() ?>">
                            <button class="btn btn-danger" type="submit" title="Usuń">✕</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Completed tasks -->
    <?php if (!empty($completed)): ?>
    <div class="section">
        <div class="section-header">
            Ukończone <span class="counter"><?= count($completed) ?></span>
        </div>
        <div class="task-list">
            <?php foreach ($completed as $task): ?>
            <div class="task completed">
                <span class="task-dot dot-completed"></span>
                <span style="flex:1; font-size:.95rem; text-decoration:line-through; color:#6b7280">
                    <?= htmlspecialchars($task->getTitle()) ?>
                </span>
                <div class="task-actions">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $task->getId() ?>">
                        <button class="btn btn-ghost" type="submit" title="Usuń">✕</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
