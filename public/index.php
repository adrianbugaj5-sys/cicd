<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Model\Priority;
use App\Repository\RedisTaskRepository;
use App\Repository\SessionTaskRepository;
use App\Service\TaskService;
use Predis\Client;

$redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';

try {
    $redis      = new Client(['host' => $redisHost]);
    $repository = new RedisTaskRepository($redisHost);
} catch (Throwable) {
    $redis      = null;
    $repository = new SessionTaskRepository();
}

$service = new TaskService($repository);

$taskToArray = fn ($t) => [
    'id'        => $t->getId(),
    'title'     => $t->getTitle(),
    'completed' => $t->isCompleted(),
    'priority'  => $t->getPriority()->value,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $id       = isset($_POST['id']) ? (int) $_POST['id'] : null;
    $title    = trim($_POST['title'] ?? '');
    $priority = Priority::tryFrom($_POST['priority'] ?? '') ?? Priority::Medium;
    $emoji    = mb_substr($_POST['emoji'] ?? '👤', 0, 4);

    try {
        match ($action) {
            'add'      => $service->createTask($title, $priority),
            'complete' => $service->completeTask($id),
            'delete'   => $service->deleteTask($id),
            'rename'   => $service->renameTask($id, $title),
            'priority' => $service->changeTaskPriority($id, $priority),
            default    => null,
        };
    } catch (Throwable) {}

    $seq = 0;
    if ($redis) {
        $redis->lpush('task_manager:events', [json_encode(['emoji' => $emoji, 'action' => $action, 'title' => $title])]);
        $redis->ltrim('task_manager:events', 0, 49);
        $seq = $redis->incr('task_manager:event_seq');
    }

    header('Content-Type: application/json');
    echo json_encode([
        'seq'       => (int) $seq,
        'pending'   => array_map($taskToArray, $service->listPending()),
        'completed' => array_map($taskToArray, $service->listCompleted()),
    ]);
    exit;
}

$initialSeq = (int) ($redis?->get('task_manager:event_seq') ?? 0);

$initialState = json_encode([
    'seq'       => $initialSeq,
    'pending'   => array_map($taskToArray, $service->listPending()),
    'completed' => array_map($taskToArray, $service->listCompleted()),
    'online'    => [],
]);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<title>Task Manager</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0 }

  body {
    font-family: system-ui, -apple-system, sans-serif;
    background: #f0f2f5;
    color: #1a1a2e;
    min-height: 100vh;
    padding: 2rem 1rem;
  }

  .container { max-width: 680px; margin: 0 auto }

  /* topbar */
  .topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  h1 { font-size: 1.4rem; display: flex; align-items: center; gap: .5rem }
  .badge {
    font-size: .65rem; font-weight: 700;
    background: #e0e7ff; color: #4338ca;
    padding: .2rem .5rem; border-radius: 99px;
  }
  .online { display: flex; align-items: center; gap: .35rem; font-size: 1.15rem }
  .online-label { font-size: .65rem; color: #9ca3af }
  .me { filter: drop-shadow(0 0 5px rgba(99,102,241,.7)) }

  /* add form */
  .add-form { display: flex; gap: .5rem; margin-bottom: 1.75rem }
  .add-form input {
    flex: 1; padding: .65rem .9rem;
    border: 1.5px solid #d1d5db; border-radius: 8px;
    font: inherit; font-size: .95rem; outline: none;
    transition: border-color .15s;
  }
  .add-form input:focus { border-color: #6366f1 }
  .select-priority {
    border: 1.5px solid #d1d5db; border-radius: 8px;
    padding: .65rem .6rem; font: inherit; font-size: .85rem;
    background: #fff; cursor: pointer;
  }
  .btn {
    padding: .65rem 1.1rem; border: none; border-radius: 8px;
    font: inherit; font-size: .9rem; font-weight: 700;
    cursor: pointer; transition: opacity .15s; white-space: nowrap;
  }
  .btn:hover { opacity: .82 }
  .btn-add   { background: #6366f1; color: #fff }
  .btn-ok    { background: #22c55e; color: #fff; padding: .4rem .7rem; font-size: .8rem }
  .btn-del   { background: #ef4444; color: #fff; padding: .4rem .7rem; font-size: .8rem }
  .btn-ghost { background: #e5e7eb; color: #374151; padding: .4rem .7rem; font-size: .8rem }

  /* sections */
  .section-header {
    font-size: .7rem; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; color: #6b7280;
    margin-bottom: .6rem; padding-left: .25rem;
    display: flex; align-items: center; gap: .4rem;
  }
  .count {
    background: #f3f4f6; color: #6b7280;
    border-radius: 99px; min-width: 1.4rem; height: 1.4rem;
    font-size: .7rem; font-weight: 700;
    display: inline-flex; align-items: center; justify-content: center;
    padding: 0 .35rem;
  }
  .task-list { display: flex; flex-direction: column; gap: .5rem; margin-bottom: 1.5rem }

  /* task card */
  .task {
    background: #fff; border-radius: 10px;
    padding: .75rem 1rem;
    display: flex; align-items: center; gap: .75rem;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    animation: pop .18s ease;
    transition: opacity .2s, transform .2s;
  }
  .task.out  { opacity: 0; transform: translateX(10px) }
  .task.done { opacity: .55 }
  @keyframes pop {
    from { opacity: 0; transform: translateY(-5px) }
    to   { opacity: 1; transform: none }
  }

  .dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0 }
  .dot-pending   { background: #f59e0b }
  .dot-completed { background: #22c55e }

  .task-title {
    flex: 1; border: none; background: transparent;
    font: inherit; font-size: .95rem; outline: none;
    border-bottom: 1.5px solid transparent;
    transition: border-color .15s; min-width: 0;
  }
  .task-title:focus { border-bottom-color: #6366f1 }
  .done .task-title { text-decoration: line-through; color: #9ca3af; pointer-events: none }

  .task-actions { display: flex; gap: .3rem; align-items: center; flex-shrink: 0 }

  /* priority pill */
  .pill {
    border: none; border-radius: 99px;
    font: inherit; font-size: .68rem; font-weight: 700;
    letter-spacing: .05em; text-transform: uppercase;
    padding: .2rem .45rem; cursor: pointer;
  }
  .pill-high   { background: #fee2e2; color: #b91c1c }
  .pill-medium { background: #fef9c3; color: #854d0e }
  .pill-low    { background: #dcfce7; color: #166534 }

  .empty {
    background: #fff; border-radius: 10px;
    text-align: center; color: #9ca3af;
    font-size: .9rem; padding: 1.2rem;
  }

  /* toasts */
  #toasts {
    position: fixed; bottom: 1.5rem; right: 1.5rem;
    display: flex; flex-direction: column; gap: .4rem; z-index: 99;
  }
  .toast {
    background: #1a1a2e; color: #fff;
    padding: .55rem .9rem; border-radius: 10px;
    font-size: .82rem; box-shadow: 0 4px 14px rgba(0,0,0,.22);
    animation: slideIn .18s ease;
  }
  @keyframes slideIn {
    from { opacity: 0; transform: translateX(14px) }
    to   { opacity: 1; transform: none }
  }
</style>
</head>
<body>
<div class="container">

  <div class="topbar">
    <h1>Task Manager <span class="badge">PHP <?= PHP_VERSION ?></span></h1>
    <div class="online">
      <span class="online-label">online</span>
      <span id="peers"></span>
      <span id="me" class="me"></span>
    </div>
  </div>

  <form class="add-form" id="add-form" method="post" action="/">
    <input id="new-title" type="text" placeholder="Nowe zadanie…" autofocus required>
    <select id="new-priority" class="select-priority">
      <option value="low">Niski</option>
      <option value="medium" selected>Średni</option>
      <option value="high">Wysoki</option>
    </select>
    <button class="btn btn-add" type="submit">Dodaj</button>
  </form>

  <div>
    <div class="section-header">Do zrobienia <span class="count" id="n-pending">0</span></div>
    <div class="task-list" id="list-pending"></div>
  </div>

  <div id="section-done" style="display:none">
    <div class="section-header">Ukończone <span class="count" id="n-done">0</span></div>
    <div class="task-list" id="list-done"></div>
  </div>

</div>

<div id="toasts"></div>

<script>
// ── identity ──────────────────────────────────────────────────────────────────
const EMOJIS  = ['🦊','🐼','🐨','🦁','🐯','🦝','🦙','🐸','🦄','🐻','🦅','🐬','🦋','🐙','🦀'];
const ACTIONS = { add:'dodał/a', complete:'ukończył/a', delete:'usunął/a', rename:'zmienił/a nazwę', priority:'zmienił/a priorytet' };
const LABELS  = { low:'Niski', medium:'Średni', high:'Wysoki' };

function getIdentity() {
  let e = localStorage.getItem('tm_emoji');
  let t = localStorage.getItem('tm_token');
  if (!e) { e = EMOJIS[Math.floor(Math.random() * EMOJIS.length)]; localStorage.setItem('tm_emoji', e); }
  if (!t) { t = Math.random().toString(36).slice(2); localStorage.setItem('tm_token', t); }
  return { emoji: e, token: t };
}

const { emoji, token } = getIdentity();
document.getElementById('me').textContent = emoji;

// ── utils ─────────────────────────────────────────────────────────────────────
const esc = s => String(s).replace(/[&<>"']/g, c =>
  ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c])
);

function toast(msg) {
  const el = Object.assign(document.createElement('div'), { className:'toast', textContent:msg });
  document.getElementById('toasts').append(el);
  setTimeout(() => el.remove(), 3500);
}

// ── templates ─────────────────────────────────────────────────────────────────
const priorityOpts = p =>
  Object.entries(LABELS)
    .map(([v, l]) => `<option value="${v}"${v === p ? ' selected' : ''}>${l}</option>`)
    .join('');

const pendingRow = t => `
  <div class="task" data-id="${t.id}">
    <span class="dot dot-pending"></span>
    <input class="task-title" type="text"
      value="${esc(t.title)}"
      data-action="rename" data-id="${t.id}" data-orig="${esc(t.title)}">
    <div class="task-actions">
      <select class="pill pill-${t.priority}" data-action="priority" data-id="${t.id}">
        ${priorityOpts(t.priority)}
      </select>
      <button class="btn btn-ok"  data-action="complete" data-id="${t.id}">✓</button>
      <button class="btn btn-del" data-action="delete"   data-id="${t.id}">✕</button>
    </div>
  </div>`;

const doneRow = t => `
  <div class="task done" data-id="${t.id}">
    <span class="dot dot-completed"></span>
    <input class="task-title" type="text" value="${esc(t.title)}" readonly>
    <div class="task-actions">
      <button class="btn btn-ghost" data-action="delete" data-id="${t.id}">✕</button>
    </div>
  </div>`;

// ── render ────────────────────────────────────────────────────────────────────
function render({ pending, completed, online }) {
  document.getElementById('n-pending').textContent = pending.length;
  document.getElementById('n-done').textContent    = completed.length;
  document.getElementById('section-done').style.display = completed.length ? '' : 'none';

  document.getElementById('list-pending').innerHTML = pending.length
    ? pending.map(pendingRow).join('')
    : '<div class="empty">Brak zadań — dobra robota!</div>';

  document.getElementById('list-done').innerHTML = completed.map(doneRow).join('');

  if (online) {
    document.getElementById('peers').textContent = online.filter(e => e !== emoji).join(' ');
  }
}

// ── api ───────────────────────────────────────────────────────────────────────
async function api(data) {
  const body = new FormData();
  body.append('emoji', emoji);
  Object.entries(data).forEach(([k, v]) => body.append(k, v));
  const res = await fetch('/', { method: 'POST', body });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

// ── polling (co 2s, od innych użytkowników) ───────────────────────────────────
let lastSeq = <?= $initialSeq ?>;

async function poll() {
  try {
    const data = await fetch(
      `/events.php?seq=${lastSeq}&emoji=${encodeURIComponent(emoji)}&token=${token}`
    ).then(r => r.json());

    if (data.seq > lastSeq) {
      lastSeq = data.seq;
      render(data);
      if (data.event && data.event.emoji !== emoji) {
        const verb  = ACTIONS[data.event.action] ?? data.event.action;
        const title = data.event.title ? ` „${data.event.title}"` : '';
        toast(`${data.event.emoji} ${verb}${title}`);
      }
    } else if (data.online) {
      document.getElementById('peers').textContent = data.online.filter(e => e !== emoji).join(' ');
    }
  } catch { /* sieć offline – spróbuj za chwilę */ }
}

setInterval(poll, 2000);

// ── event delegation ──────────────────────────────────────────────────────────
document.addEventListener('click', async e => {
  const btn = e.target.closest('button[data-action]');
  if (!btn) return;
  const { action, id } = btn.dataset;
  if (action !== 'complete' && action !== 'delete') return;

  btn.closest('.task')?.classList.add('out');
  await new Promise(r => setTimeout(r, 200));

  try {
    const data = await api({ action, id });
    lastSeq = data.seq ?? lastSeq;
    render(data);
  } catch { toast('Błąd połączenia, spróbuj ponownie') }
});

document.addEventListener('change', async e => {
  const sel = e.target.closest('select[data-action="priority"]');
  if (!sel) return;
  sel.className = `pill pill-${sel.value}`;
  try {
    const data = await api({ action: 'priority', id: sel.dataset.id, priority: sel.value });
    lastSeq = data.seq ?? lastSeq;
    render(data);
  } catch { toast('Błąd połączenia, spróbuj ponownie') }
});

document.addEventListener('focusout', async e => {
  const inp = e.target.closest('input[data-action="rename"]');
  if (!inp) return;
  if (inp.value.trim() && inp.value !== inp.dataset.orig) {
    try {
      const data = await api({ action: 'rename', id: inp.dataset.id, title: inp.value });
      lastSeq = data.seq ?? lastSeq;
      render(data);
    } catch { toast('Błąd połączenia, spróbuj ponownie') }
  }
});

document.getElementById('add-form').addEventListener('submit', async e => {
  e.preventDefault();
  const titleEl    = document.getElementById('new-title');
  const priorityEl = document.getElementById('new-priority');
  if (!titleEl.value.trim()) return;

  const titleVal    = titleEl.value;
  const priorityVal = priorityEl.value;
  titleEl.value = '';
  titleEl.focus();

  try {
    const data = await api({ action: 'add', title: titleVal, priority: priorityVal });
    lastSeq = data.seq ?? lastSeq;
    render(data);
  } catch { toast('Błąd połączenia, spróbuj ponownie') }
});

// ── init ──────────────────────────────────────────────────────────────────────
render(<?= $initialState ?>);
</script>
</body>
</html>
