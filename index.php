<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['tasks']) || !is_array($_SESSION['tasks'])) {
    $_SESSION['tasks'] = [
        ['id' => 1, 'title' => 'Belajar PHP', 'status' => 'belum'],
        ['id' => 2, 'title' => 'Kerjakan tugas UX', 'status' => 'selesai'],
    ];
    $_SESSION['next_task_id'] = 3;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirectToHome(): void
{
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['next_task_id'])) {
    $taskIds = array_column($_SESSION['tasks'], 'id');
    $_SESSION['next_task_id'] = $taskIds === [] ? 1 : max($taskIds) + 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (
        !is_string($submittedToken)
        || !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        http_response_code(400);
        exit('Permintaan tidak valid. Muat ulang halaman lalu coba lagi.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = $_POST['title'] ?? '';
        if (!is_string($title)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Judul tugas tidak valid.'];
        } else {
            $title = trim($title);
            if ($title === '') {
                $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Judul tugas tidak boleh kosong.'];
            } elseif (strlen($title) > 200) {
                $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Judul tugas maksimal 200 karakter.'];
            } else {
                $_SESSION['tasks'][] = [
                    'id' => $_SESSION['next_task_id'],
                    'title' => $title,
                    'status' => 'belum',
                ];
                $_SESSION['next_task_id']++;
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Tugas berhasil ditambahkan.'];
            }
        }
    } elseif (in_array($action, ['toggle', 'edit', 'delete'], true)) {
        $taskId = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
        $taskIndex = false;
        if ($taskId !== false && $taskId !== null) {
            foreach ($_SESSION['tasks'] as $index => $task) {
                if ($task['id'] === $taskId) {
                    $taskIndex = $index;
                    break;
                }
            }
        }

        if ($taskIndex === false) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Tugas tidak ditemukan.'];
        } elseif ($action === 'toggle') {
            $_SESSION['tasks'][$taskIndex]['status'] =
                $_SESSION['tasks'][$taskIndex]['status'] === 'selesai' ? 'belum' : 'selesai';
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Status tugas berhasil diperbarui.'];
        } elseif ($action === 'edit') {
            $title = $_POST['title'] ?? '';
            if (!is_string($title)) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Judul tugas tidak valid.'];
            } else {
                $title = trim($title);
                if ($title === '') {
                    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Judul tugas tidak boleh kosong.'];
                } elseif (strlen($title) > 200) {
                    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Judul tugas maksimal 200 karakter.'];
                } else {
                    $_SESSION['tasks'][$taskIndex]['title'] = $title;
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Tugas berhasil diedit.'];
                }
            }
        } else {
            array_splice($_SESSION['tasks'], $taskIndex, 1);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Tugas berhasil dihapus.'];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Aksi tidak dikenal.'];
    }

    redirectToHome();
}

$tasks = $_SESSION['tasks'];
$editTaskId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$remainingTasks = count(array_filter(
    $tasks,
    static function (array $task): bool {
        return $task['status'] !== 'selesai';
    }
));
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Aplikasi sederhana untuk mencatat dan mengelola tugas harian.">
    <title>To-Do List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bs-primary: #d63384;
            --bs-primary-rgb: 214, 51, 132;
        }

        body {
            min-height: 100vh;
            background: #fff0f6;
        }

        .todo-card {
            max-width: 760px;
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(173, 35, 100, 0.12) !important;
        }

        .task-title {
            overflow-wrap: anywhere;
        }

        .btn-primary {
            --bs-btn-bg: #d63384;
            --bs-btn-border-color: #d63384;
            --bs-btn-hover-bg: #b52b70;
            --bs-btn-hover-border-color: #b52b70;
            --bs-btn-active-bg: #a82766;
            --bs-btn-active-border-color: #a82766;
            --bs-btn-disabled-bg: #d63384;
            --bs-btn-disabled-border-color: #d63384;
            --bs-btn-disabled-color: #fff;
        }

        .btn-outline-primary {
            --bs-btn-color: #c12d76;
            --bs-btn-border-color: #d63384;
            --bs-btn-hover-bg: #d63384;
            --bs-btn-hover-border-color: #d63384;
            --bs-btn-active-bg: #b52b70;
            --bs-btn-active-border-color: #b52b70;
        }

        .form-check-input:checked {
            background-color: #d63384;
            border-color: #d63384;
        }
    </style>
</head>
<body>
<main class="container py-5">
    <div class="card todo-card shadow-sm mx-auto">
        <div class="card-body p-4 p-md-5">
            <header class="mb-4">
                <p class="text-primary fw-semibold text-uppercase small mb-2">Produktivitas harian</p>
                <h1 class="h2 fw-bold mb-2">To-Do List</h1>
                <p class="text-secondary mb-0">
                    Ada <?= $remainingTasks ?> tugas yang belum selesai.
                </p>
            </header>

            <?php if (is_array($flash)): ?>
                <div class="alert alert-<?= escape((string) $flash['type']) ?> alert-dismissible fade show" role="status">
                    <?= escape((string) $flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endif; ?>

            <form method="post" class="mb-4">
                <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="add">
                <label for="title" class="form-label fw-semibold">Tambah tugas</label>
                <div class="d-flex gap-2">
                    <input
                        type="text"
                        class="form-control flex-grow-1"
                        id="title"
                        name="title"
                        maxlength="200"
                        required
                    >
                    <button class="btn btn-primary px-4 flex-shrink-0" type="submit">Tambah</button>
                </div>
            </form>

            <section aria-labelledby="tasks-heading">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 id="tasks-heading" class="h5 fw-bold mb-0">Daftar tugas</h2>
                    <span class="badge text-bg-light"><?= count($tasks) ?> tugas</span>
                </div>

                <?php if ($tasks === []): ?>
                    <div class="text-center border rounded-3 bg-light py-5 px-3">
                        <p class="fw-semibold mb-1">Belum ada tugas</p>
                        <p class="text-secondary mb-0">Tambahkan tugas pertamamu melalui form di atas.</p>
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush border rounded-3 overflow-hidden">
                        <?php foreach ($tasks as $task): ?>
                            <?php $isDone = $task['status'] === 'selesai'; ?>
                            <li class="list-group-item d-flex align-items-center gap-3 py-3">
                                <form method="post" class="flex-shrink-0">
                                    <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                                    <div class="form-check mb-0">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="task-<?= (int) $task['id'] ?>"
                                            aria-label="<?= $isDone ? 'Tugas selesai' : 'Tandai selesai' ?>: <?= escape($task['title']) ?>"
                                            <?= $isDone ? 'checked' : '' ?>
                                            onchange="this.form.submit()"
                                        >
                                    </div>
                                </form>
                                <?php if ($isDone): ?>
                                    <span class="task-title flex-grow-1"><?= escape($task['title']) ?></span>
                                    <button type="button" class="btn btn-sm btn-primary flex-shrink-0" disabled>
                                        Selesai
                                    </button>
                                <?php elseif ($editTaskId === (int) $task['id']): ?>
                                    <form method="post" class="d-flex flex-grow-1 gap-2">
                                        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            name="title"
                                            value="<?= escape($task['title']) ?>"
                                            maxlength="200"
                                            aria-label="Edit judul tugas"
                                            required
                                            autofocus
                                        >
                                        <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                                        <a href="index.php" class="btn btn-sm btn-outline-secondary">Batal</a>
                                    </form>
                                <?php else: ?>
                                    <span class="task-title flex-grow-1"><?= escape($task['title']) ?></span>
                                <?php endif; ?>
                                <?php if (!$isDone): ?>
                                    <a
                                        href="index.php?edit=<?= (int) $task['id'] ?>"
                                        class="btn btn-sm btn-outline-primary"
                                        aria-label="Edit: <?= escape($task['title']) ?>"
                                    >
                                        Edit
                                    </a>
                                    <form method="post" class="flex-shrink-0">
                                        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            aria-label="Hapus: <?= escape($task['title']) ?>"
                                            onclick="return confirm('Yakin ingin menghapus tugas ini?')"
                                        >
                                            Hapus
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>