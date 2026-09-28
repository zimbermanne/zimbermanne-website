<?php
declare(strict_types=1);

function admin_ok(): bool { return !empty($_SESSION['admin']); }
function require_admin(): void { if (!admin_ok()) redirect('/admin'); }

function admin_login(): void {
    $pw = getenv('ADMIN_PASSWORD') ?: '';
    $user = getenv('ADMIN_USER') ?: 'admin';
    if (($_SESSION['locked_until'] ?? 0) > time()) redirect('/admin?err=locked');
    if ($pw !== '' && hash_equals($user, post('user', 80)) && hash_equals($pw, (string)($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['fails'] = 0;
        redirect('/admin');
    }
    usleep(600000);
    $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
    if ($_SESSION['fails'] >= 5) { $_SESSION['locked_until'] = time() + 300; $_SESSION['fails'] = 0; }
    redirect('/admin?err=bad');
}

function admin_logout(): void { $_SESSION = []; session_destroy(); redirect('/admin'); }

function admin_home(): void {
    if (!admin_ok()) {
        $err = $_GET['err'] ?? '';
        $enabled = (getenv('ADMIN_PASSWORD') ?: '') !== '';
        ob_page('Staff login', function () use ($err, $enabled) { ?>
<section><div class="wrap narrow">
  <h1>Staff login</h1>
  <?php if (!$enabled): ?><p class="error">Admin is disabled. Set the ADMIN_PASSWORD variable on the server.</p><?php endif; ?>
  <?php if ($err === 'bad'): ?><p class="error" role="alert">Wrong username or password.</p><?php endif; ?>
  <?php if ($err === 'locked'): ?><p class="error" role="alert">Too many attempts. Try again in a few minutes.</p><?php endif; ?>
  <form method="post" action="/admin/login" class="stack"><?= csrf_field() ?>
    <label>Username<input name="user" autocomplete="username" required></label>
    <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
    <button class="btn">Log in</button>
  </form>
</div></section>
<?php });
        return;
    }
    $counts = array_fill_keys(STATUSES, 0);
    foreach (db()->query('SELECT status, COUNT(*) c FROM requests GROUP BY status')->fetchAll() as $r) $counts[$r['status']] = (int)$r['c'];
    $latest = db()->query('SELECT * FROM requests ORDER BY id DESC LIMIT 10')->fetchAll();
    $prod = (int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
    ob_page('Dashboard', function () use ($counts, $latest, $prod) { ?>
<section><div class="wrap">
  <h1>Dashboard</h1>
  <div class="stats">
    <?php foreach ($counts as $s => $n): ?><a class="stat" href="/admin/requests?status=<?= e($s) ?>"><strong><?= $n ?></strong><span><?= e(ucfirst($s)) ?></span></a><?php endforeach; ?>
    <a class="stat" href="/admin/products"><strong><?= $prod ?></strong><span>Products</span></a>
  </div>
  <h2>Latest requests</h2>
  <?= requests_table($latest) ?>
</div></section>
<?php }, true);
}

function requests_table(array $rows): string {
    if (!$rows) return '<p>No requests yet.</p>';
    $h = '<div class="scroll"><table><thead><tr><th>Ref</th><th>Type</th><th>Name</th><th>Phone</th><th>Date</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $h .= '<tr><td><a href="/admin/request?id=' . (int)$r['id'] . '">' . e($r['ref']) . '</a></td><td>' . e($r['kind']) . '</td><td>' . e($r['name'])
            . '</td><td>' . e($r['phone']) . '</td><td>' . e(substr($r['created_at'], 0, 16)) . '</td><td><span class="badge ' . e($r['status']) . '">' . e($r['status']) . '</span></td></tr>';
    }
    return $h . '</tbody></table></div>';
}

function admin_requests(): void {
    require_admin();
    $s = (string)($_GET['status'] ?? '');
    if (in_array($s, STATUSES, true)) {
        $st = db()->prepare('SELECT * FROM requests WHERE status = ? ORDER BY id DESC LIMIT 200');
        $st->execute([$s]);
        $rows = $st->fetchAll();
    } else {
        $rows = db()->query('SELECT * FROM requests ORDER BY id DESC LIMIT 200')->fetchAll();
    }
    ob_page('Requests', function () use ($rows, $s) { ?>
<section><div class="wrap">
  <h1>Requests</h1>
  <p class="filters-links"><a href="/admin/requests">All</a><?php foreach (STATUSES as $x): ?> <a href="/admin/requests?status=<?= $x ?>"<?= $x === $s ? ' class="on"' : '' ?>><?= ucfirst($x) ?></a><?php endforeach; ?></p>
  <?= requests_table($rows) ?>
</div></section>
<?php }, true);
}

function load_request(): array {
    $st = db()->prepare('SELECT * FROM requests WHERE id = ?');
    $st->execute([(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
    $r = $st->fetch();
    if (!$r) { not_found(); exit; }
    $r['items'] = json_decode($r['items'], true) ?: [];
    $r['details'] = json_decode($r['details'], true) ?: [];
    return $r;
}

function admin_request(): void {
    require_admin();
    $r = load_request();
    ob_page('Request ' . $r['ref'], function () use ($r) { ?>
<section><div class="wrap">
  <h1><?= e($r['ref']) ?> <span class="badge <?= e($r['status']) ?>"><?= e($r['status']) ?></span></h1>
  <p><strong><?= e($r['name']) ?></strong> &middot; <a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a>
     <?= $r['email'] ? ' &middot; <a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a>' : '' ?><br>
     <?= e($r['location']) ?> &middot; <?= e(substr($r['created_at'], 0, 16)) ?></p>
  <?php foreach ($r['details'] as $k => $v): if ($v !== ''): ?><p><strong><?= e(ucfirst(str_replace('_', ' ', $k))) ?>:</strong> <?= e($v) ?></p><?php endif; endforeach; ?>
  <?php if ($r['message'] !== ''): ?><p class="msg"><?= nl2br(e($r['message'])) ?></p><?php endif; ?>
  <form method="post" action="/admin/request/save" class="stack wide"><?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <?php if ($r['items']): ?>
    <div class="scroll"><table>
      <thead><tr><th>Item</th><th>Qty</th><th>Unit price (TZS)</th></tr></thead><tbody>
      <?php foreach ($r['items'] as $i => $it): ?>
        <tr><td><?= e($it['name']) ?> <small>/ <?= e($it['unit']) ?></small></td><td><?= (int)$it['qty'] ?></td>
        <td><input type="number" min="0" name="price[<?= $i ?>]" value="<?= $it['price'] !== null ? (int)$it['price'] : '' ?>"></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <label>Status<select name="status"><?php foreach (STATUSES as $s): ?><option<?= $s === $r['status'] ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></label>
    <div class="actions">
      <button class="btn">Save</button>
      <?php if ($r['items']): ?><a class="btn ghost dark" href="/admin/quote?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener">Printable quotation</a><?php endif; ?>
      <a class="btn ghost dark" href="https://wa.me/<?= e(wa_phone($r['phone'])) ?>" target="_blank" rel="noopener">Reply on WhatsApp</a>
    </div>
  </form>
</div></section>
<?php }, true);
}

function admin_request_save(): void {
    require_admin();
    $r = load_request();
    $items = $r['items'];
    foreach ($items as $i => &$it) {
        $v = $_POST['price'][$i] ?? '';
        $it['price'] = ($v === '' || !is_numeric($v)) ? null : max(0, (int)$v);
    }
    unset($it);
    $status = in_array($_POST['status'] ?? '', STATUSES, true) ? $_POST['status'] : $r['status'];
    $st = db()->prepare('UPDATE requests SET status = ?, items = ? WHERE id = ?');
    $st->execute([$status, json_encode($items, JSON_UNESCAPED_UNICODE), $r['id']]);
    redirect('/admin/request?id=' . (int)$r['id']);
}

function admin_quote(): void {
    require_admin();
    $r = load_request();
    header('Content-Type: text/html; charset=utf-8');
    $total = 0; $rows = '';
    foreach ($r['items'] as $it) {
        $line = $it['price'] !== null ? (int)$it['price'] * (int)$it['qty'] : null;
        $total += (int)$line;
        $rows .= '<tr><td>' . e($it['name']) . '</td><td>' . (int)$it['qty'] . ' ' . e($it['unit']) . '</td><td>'
            . ($it['price'] !== null ? number_format((int)$it['price']) : 'TBC') . '</td><td>' . ($line !== null ? number_format($line) : 'TBC') . '</td></tr>';
    }
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Quotation <?= e($r['ref']) ?></title>
<style>
body{font-family:Arial,sans-serif;max-width:800px;margin:2rem auto;padding:0 1rem;color:#111}
h1{margin:0}table{width:100%;border-collapse:collapse;margin:1.5rem 0}
th,td{border-bottom:1px solid #999;padding:.5rem;text-align:left}td:nth-child(n+3),th:nth-child(n+3){text-align:right}
.top{display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap}button{padding:.6rem 1rem;margin-bottom:1rem}
@media print{button{display:none}}
</style></head><body>
<button onclick="window.print()">Print or save as PDF</button>
<div class="top"><div><h1><?= e(COMPANY) ?></h1>Moshi, Kilimanjaro, Tanzania<br>TIN <?= e(TIN) ?><br><?= e(PHONE) ?> &middot; <?= e(EMAIL) ?></div>
<div><strong>QUOTATION</strong><br>Ref: <?= e($r['ref']) ?><br>Date: <?= date('d M Y') ?><br>Valid for 14 days</div></div>
<p>To: <strong><?= e($r['name']) ?></strong><br><?= e($r['phone']) ?><br><?= e($r['location']) ?></p>
<table><thead><tr><th>Item</th><th>Quantity</th><th>Unit price (TZS)</th><th>Total (TZS)</th></tr></thead><tbody><?= $rows ?></tbody>
<tfoot><tr><th colspan="3">Total (priced items)</th><th><?= number_format($total) ?></th></tr></tfoot></table>
<p>Items marked TBC will be priced on confirmation. Prices exclude delivery unless stated.</p>
</body></html>
<?php
}

/* ---------- products ---------- */
function admin_products(): void {
    require_admin();
    $rows = db()->query('SELECT * FROM products ORDER BY category, name')->fetchAll();
    ob_page('Products', function () use ($rows) { ?>
<section><div class="wrap">
  <h1>Products</h1>
  <p><a class="btn" href="/admin/product">Add product</a></p>
  <div class="scroll"><table>
    <thead><tr><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $p): ?>
      <tr><td><?= e($p['name']) ?></td><td><?= e($p['category']) ?></td>
      <td><?= $p['price_tzs'] !== null ? money($p['price_tzs']) . ' / ' . e($p['unit']) : 'On request' ?></td>
      <td><?= $p['in_stock'] ? 'In stock' : 'Out' ?></td>
      <td><a href="/admin/product?id=<?= (int)$p['id'] ?>">Edit</a></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div></section>
<?php }, true);
}

function admin_product_form(): void {
    require_admin();
    $id = (int)($_GET['id'] ?? 0);
    $p = ['id' => 0, 'name' => '', 'category' => '', 'description' => '', 'unit' => 'piece', 'price_tzs' => null, 'in_stock' => true];
    if ($id) {
        $st = db()->prepare('SELECT * FROM products WHERE id = ?');
        $st->execute([$id]);
        $p = $st->fetch() ?: $p;
    }
    $cats = db()->query('SELECT DISTINCT category FROM products ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    ob_page($id ? 'Edit product' : 'Add product', function () use ($p, $cats) { ?>
<section><div class="wrap narrow">
  <h1><?= $p['id'] ? 'Edit product' : 'Add product' ?></h1>
  <form method="post" action="/admin/product/save" class="stack"><?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <label>Name<input name="name" value="<?= e($p['name']) ?>" required></label>
    <label>Category<input name="category" list="cats" value="<?= e($p['category']) ?>" required></label>
    <datalist id="cats"><?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
    <label>Description<textarea name="description"><?= e($p['description']) ?></textarea></label>
    <label>Unit (piece, bag, length, sheet)<input name="unit" value="<?= e($p['unit']) ?>" required></label>
    <label>Price in TZS (leave empty for price on request)<input name="price" type="number" min="0" value="<?= $p['price_tzs'] !== null ? (int)$p['price_tzs'] : '' ?>"></label>
    <label class="check"><input type="checkbox" name="in_stock" value="1"<?= $p['in_stock'] ? ' checked' : '' ?>> In stock</label>
    <div class="actions"><button class="btn">Save product</button></div>
  </form>
  <?php if ($p['id']): ?>
  <form method="post" action="/admin/product/delete" onsubmit="return confirm('Delete this product?')"><?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn ghost dark">Delete product</button>
  </form>
  <?php endif; ?>
</div></section>
<?php }, true);
}

function admin_product_save(): void {
    require_admin();
    $id = (int)($_POST['id'] ?? 0);
    $price = post('price', 12);
    $vals = [post('name', 160), post('category', 80), post('description', 1000), post('unit', 30) ?: 'piece',
             $price === '' ? null : max(0, (int)$price), isset($_POST['in_stock']) ? 'true' : 'false'];
    if ($vals[0] === '' || $vals[1] === '') redirect('/admin/products');
    if ($id) {
        $st = db()->prepare('UPDATE products SET name=?, category=?, description=?, unit=?, price_tzs=?, in_stock=?::boolean WHERE id=?');
        $st->execute([...$vals, $id]);
    } else {
        $st = db()->prepare('INSERT INTO products (name, category, description, unit, price_tzs, in_stock) VALUES (?,?,?,?,?,?::boolean)');
        $st->execute($vals);
    }
    redirect('/admin/products');
}

function admin_product_delete(): void {
    require_admin();
    $st = db()->prepare('DELETE FROM products WHERE id = ?');
    $st->execute([(int)($_POST['id'] ?? 0)]);
    redirect('/admin/products');
}

function csv_safe($v): string { $v = (string)$v; return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v; }

function admin_export(): void {
    require_admin();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="requests-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w');
    fputcsv($o, ['Ref', 'Type', 'Status', 'Name', 'Phone', 'Email', 'Location', 'Message', 'Details', 'Items', 'Created'], ',', '"', '\\');
    foreach (db()->query('SELECT * FROM requests ORDER BY id DESC')->fetchAll() as $r) {
        fputcsv($o, array_map('csv_safe', [$r['ref'], $r['kind'], $r['status'], $r['name'], $r['phone'], $r['email'], $r['location'],
            $r['message'], $r['details'], $r['items'], $r['created_at']]), ',', '"', '\\');
    }
}
