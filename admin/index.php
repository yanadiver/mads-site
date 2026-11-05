<?php
// Админка новостей. При первом входе попросит придумать пароль.
session_start();
$root = dirname(__DIR__);
$json = $root . '/news/news.json';
$photos = $root . '/news/photos';
$passFile = __DIR__ . '/.pass.php';
if (!is_dir($photos)) @mkdir($photos, 0755, true);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function load($f){ $d = @json_decode(@file_get_contents($f), true); return is_array($d) ? $d : []; }
function save($f, $d){ file_put_contents($f, json_encode(array_values($d), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX); }
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
$msg = '';
$hash = is_file($passFile) ? (include $passFile) : null;
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post && ($_POST['csrf'] ?? '') !== $csrf) { http_response_code(400); exit('Обновите страницу'); }

if (!$hash && $post && isset($_POST['newpass'])) {
  if (strlen($_POST['newpass']) < 8) $msg = 'Пароль — минимум 8 символов';
  elseif ($_POST['newpass'] !== ($_POST['newpass2'] ?? '')) $msg = 'Пароли не совпадают';
  else { $hash = password_hash($_POST['newpass'], PASSWORD_DEFAULT);
    file_put_contents($passFile, "<?php return " . var_export($hash, true) . ";");
    $_SESSION['ok'] = 1; header('Location: ./'); exit; }
}
if ($hash && $post && isset($_POST['pass'])) {
  if (password_verify($_POST['pass'], $hash)) { session_regenerate_id(true); $_SESSION['ok'] = 1; header('Location: ./'); exit; }
  sleep(2); $msg = 'Неверный пароль';
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: ./'); exit; }
$ok = !empty($_SESSION['ok']) && $hash;

if ($ok && $post && ($_POST['act'] ?? '') === 'add') {
  $list = load($json);
  $names = [];
  if (!empty($_FILES['photos']['name'][0])) {
    foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
      if (!is_uploaded_file($tmp)) continue;
      $info = @getimagesize($tmp);
      $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$info['mime'] ?? ''] ?? null;
      if (!$ext) continue;
      $n = date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
      if (move_uploaded_file($tmp, "$photos/$n")) $names[] = $n;
    }
  }
  $list[] = ['id' => time(), 'date' => trim($_POST['date']), 'place' => trim($_POST['place']),
    'title' => trim($_POST['title']), 'summary' => trim($_POST['summary']), 'text' => trim($_POST['text']), 'photos' => $names];
  save($json, $list); $msg = 'Новость опубликована';
}
if ($ok && $post && ($_POST['act'] ?? '') === 'del') {
  $list = load($json);
  foreach ($list as $k => $n) if ((string)$n['id'] === $_POST['id']) {
    foreach ($n['photos'] as $p) @unlink("$photos/" . basename($p));
    unset($list[$k]);
  }
  save($json, $list); $msg = 'Новость удалена';
}
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>Новости — админка</title>
<style>body{font-family:system-ui,Arial,sans-serif;background:#f4f5f8;color:#1b2340;margin:0;padding:24px 16px}
.box{max-width:720px;margin:0 auto;background:#fff;border-radius:14px;padding:24px;box-shadow:0 4px 20px rgba(0,0,0,.06);margin-bottom:20px}
h1{text-align:center;font-size:22px;margin:0 0 18px}label{display:block;font-weight:600;margin:14px 0 6px;font-size:14px}
input,textarea{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #ccd2e0;border-radius:8px;font:inherit}
textarea{min-height:200px}button{background:#1d3a7a;color:#fff;border:0;border-radius:8px;padding:12px 22px;font:inherit;font-weight:600;cursor:pointer;margin-top:16px}
.msg{background:#eaf5ea;padding:10px 14px;border-radius:8px;margin-bottom:12px}.row{display:flex;justify-content:space-between;align-items:center;border-top:1px solid #eee;padding:10px 0;gap:10px}
.del{background:#b23a3a;margin:0;padding:8px 14px}.hint{color:#6b7390;font-size:13px}a{color:#1d3a7a}</style></head><body>
<?php if ($msg): ?><div class="box msg"><?= h($msg) ?></div><?php endif; ?>
<?php if (!$hash): ?>
<div class="box"><h1>Придумайте пароль для админки</h1><form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>">
<label>Пароль (от 8 символов)</label><input type="password" name="newpass" required><label>Ещё раз</label><input type="password" name="newpass2" required>
<button>Сохранить</button></form></div>
<?php elseif (!$ok): ?>
<div class="box"><h1>Вход</h1><form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><label>Пароль</label><input type="password" name="pass" required autofocus><button>Войти</button></form></div>
<?php else: ?>
<div class="box"><h1>Добавить новость</h1>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="add">
<label>Дата</label><input name="date" placeholder="05.11.2026" required>
<label>Место</label><input name="place" placeholder="Москва">
<label>Заголовок</label><input name="title" required>
<label>Коротко (2–3 строки для карточки)</label><input name="summary">
<label>Полный текст</label><textarea name="text" placeholder="Каждый абзац — с новой строки"></textarea>
<label>Фото (можно несколько; первое — главное)</label><input type="file" name="photos[]" accept="image/*" multiple>
<p class="hint">Фото лучше до 3 МБ каждое.</p>
<button>Опубликовать</button></form></div>
<div class="box"><h1>Опубликованные</h1>
<?php $list = array_reverse(load($json)); if (!$list) echo '<p class="hint">Пока нет новостей, добавленных через админку.</p>';
foreach ($list as $n): ?><div class="row"><div><b><?= h($n['date']) ?></b> — <?= h($n['title']) ?></div>
<form method="post" onsubmit="return confirm('Удалить новость?')"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="del"><input type="hidden" name="id" value="<?= h($n['id']) ?>"><button class="del">Удалить</button></form></div>
<?php endforeach; ?>
<p style="margin-top:18px"><a href="../" target="_blank">Открыть сайт</a> · <a href="?logout=1">Выйти</a></p></div>
<?php endif; ?>
</body></html>
