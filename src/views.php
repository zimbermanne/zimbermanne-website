<?php
declare(strict_types=1);

function page(string $title, string $body, bool $admin = false): void {
    $count = array_sum($_SESSION['basket'] ?? []);
    $back = urlencode($_SERVER['REQUEST_URI'] ?? '/');
    $other = lang() === 'en' ? 'sw' : 'en';
    $otherLabel = lang() === 'en' ? 'Kiswahili' : 'English';
    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html>
<html lang="<?= lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | <?= e(COMPANY) ?></title>
<meta name="description" content="<?= e(COMPANY) ?>: plumbing, hardware supply and building contracts in Moshi, Kilimanjaro, Tanzania.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@500;700;800&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/style.css">
</head>
<body>
<header>
  <div class="wrap bar">
    <a class="brand" href="/"><?= e(COMPANY) ?></a>
    <nav aria-label="Main">
    <?php if ($admin): ?>
      <a href="/admin">Dashboard</a>
      <a href="/admin/requests">Requests</a>
      <a href="/admin/products">Products</a>
      <a href="/admin/export">Export CSV</a>
      <form method="post" action="/admin/logout" class="inline"><?= csrf_field() ?><button class="link">Log out</button></form>
    <?php else: ?>
      <a href="/products"><?= t('products') ?></a>
      <a href="/contracts"><?= t('contracts') ?></a>
      <a href="/basket"><?= t('basket') ?><?= $count ? ' (' . (int)$count . ')' : '' ?></a>
      <a href="/lang?l=<?= $other ?>&amp;back=<?= $back ?>" lang="<?= $other ?>"><?= $otherLabel ?></a>
    <?php endif; ?>
    </nav>
  </div>
</header>
<main><?= $body ?></main>
<footer>
  <div class="wrap foot">
    <div>
      <strong><?= e(COMPANY) ?></strong><br>
      Moshi, Kilimanjaro, Tanzania<br>TIN <?= e(TIN) ?>
    </div>
    <div>
      <a href="https://wa.me/<?= WA ?>">WhatsApp <?= e(PHONE) ?></a><br>
      <a href="tel:<?= e(str_replace(' ', '', PHONE)) ?>"><?= e(PHONE) ?></a><br>
      <a href="mailto:<?= e(EMAIL) ?>"><?= e(EMAIL) ?></a>
    </div>
  </div>
  <div class="wrap copy">&copy; <?= date('Y') ?> <?= e(COMPANY) ?>. <a href="/admin">Staff</a></div>
</footer>
</body>
</html>
<?php
}

function ob_page(string $title, callable $fn, bool $admin = false): void {
    ob_start(); $fn(); page($title, (string)ob_get_clean(), $admin);
}

function not_found(): void { http_response_code(404); page('Not found', '<section><div class="wrap"><h1>Page not found</h1><p><a href="/">Back to home</a></p></div></section>'); }

function set_lang(): void {
    $l = $_GET['l'] ?? 'en';
    $_SESSION['lang'] = $l === 'sw' ? 'sw' : 'en';
    $back = (string)($_GET['back'] ?? '/');
    redirect(preg_match('#^/(?!/)#', $back) ? $back : '/');
}

function product_card(array $p): string {
    $id = (int)$p['id'];
    $price = $p['price_tzs'] !== null ? money($p['price_tzs']) . ' / ' . e($p['unit']) : t('price_req');
    $action = $p['in_stock']
        ? '<form method="post" action="/basket/add" class="add">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '">'
          . '<label class="sr" for="q' . $id . '">' . t('qty') . '</label><input id="q' . $id . '" type="number" name="qty" value="1" min="1" max="9999">'
          . '<button class="btn small">' . t('add') . '</button></form>'
        : '<p class="oos">' . t('out') . '</p>';
    return '<article class="card"><p class="cat">' . e($p['category']) . '</p><h3>' . e($p['name']) . '</h3><p>' . e($p['description']) . '</p>'
        . '<p class="price">' . $price . '</p>' . $action . '</article>';
}

function view_home(): void {
    $feat = db()->query('SELECT * FROM products WHERE in_stock ORDER BY id DESC LIMIT 6')->fetchAll();
    ob_page('Plumbing, hardware and building contracts', function () use ($feat) { ?>
<div class="hero"><div class="wrap">
  <h1><?= t('hero_h') ?></h1>
  <p><?= t('hero_p') ?></p>
  <div class="actions">
    <a class="btn" href="/products"><?= t('cta_quote') ?></a>
    <a class="btn ghost" href="/contracts"><?= t('cta_contract') ?></a>
  </div>
</div></div>
<section><div class="wrap three">
  <div><h2><?= t('svc_pl') ?></h2><p><?= t('svc_pl_p') ?></p></div>
  <div><h2><?= t('svc_hw') ?></h2><p><?= t('svc_hw_p') ?></p></div>
  <div><h2><?= t('svc_bc') ?></h2><p><?= t('svc_bc_p') ?></p></div>
</div></section>
<section class="band"><div class="wrap">
  <h2><?= t('featured') ?></h2>
  <div class="grid"><?php foreach ($feat as $p) echo product_card($p); ?></div>
  <p class="more"><a class="btn" href="/products"><?= t('products') ?></a></p>
</div></section>
<?php });
}

function view_products(): void {
    $q = trim((string)($_GET['q'] ?? ''));
    $cat = trim((string)($_GET['cat'] ?? ''));
    $sql = 'SELECT * FROM products WHERE 1=1';
    $args = [];
    if ($q !== '') { $sql .= ' AND (name ILIKE ? OR description ILIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
    if ($cat !== '') { $sql .= ' AND category = ?'; $args[] = $cat; }
    $st = db()->prepare($sql . ' ORDER BY category, name');
    $st->execute($args);
    $rows = $st->fetchAll();
    $cats = db()->query('SELECT DISTINCT category FROM products ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    ob_page(t('products'), function () use ($rows, $cats, $q, $cat) { ?>
<section><div class="wrap">
  <h1><?= t('products') ?></h1>
  <form method="get" action="/products" class="filters">
    <label class="sr" for="q"><?= t('search') ?></label>
    <input id="q" name="q" value="<?= e($q) ?>" placeholder="<?= t('search') ?>">
    <select name="cat" aria-label="Category">
      <option value=""><?= t('all') ?></option>
      <?php foreach ($cats as $c): ?><option<?= $c === $cat ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
    </select>
    <button class="btn"><?= t('go') ?></button>
  </form>
  <?php if (!$rows): ?><p><?= t('none') ?></p><?php endif; ?>
  <div class="grid"><?php foreach ($rows as $p) echo product_card($p); ?></div>
</div></section>
<?php });
}

/* ---------- quote basket ---------- */
function basket_add(): void {
    $id = (int)($_POST['id'] ?? 0);
    $qty = max(1, min(9999, (int)($_POST['qty'] ?? 1)));
    $st = db()->prepare('SELECT 1 FROM products WHERE id = ? AND in_stock');
    $st->execute([$id]);
    if ($st->fetchColumn()) $_SESSION['basket'][$id] = min(9999, ($_SESSION['basket'][$id] ?? 0) + $qty);
    redirect('/basket');
}

function basket_update(): void {
    foreach ((array)($_POST['qty'] ?? []) as $id => $q) {
        $q = (int)$q;
        if ($q <= 0) unset($_SESSION['basket'][(int)$id]);
        else $_SESSION['basket'][(int)$id] = min(9999, $q);
    }
    redirect('/basket');
}

function basket_rows(): array {
    $b = $_SESSION['basket'] ?? [];
    if (!$b) return [];
    $ids = array_map('intval', array_keys($b));
    $st = db()->prepare('SELECT * FROM products WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $st->execute($ids);
    $rows = [];
    foreach ($st->fetchAll() as $p) { $p['qty'] = (int)$b[$p['id']]; $rows[] = $p; }
    return $rows;
}

function view_basket(): void {
    $rows = basket_rows();
    $err = !empty($_GET['err']);
    ob_page(t('basket'), function () use ($rows, $err) { ?>
<section><div class="wrap">
  <h1><?= t('basket_h') ?></h1>
  <?php if (!$rows): ?>
    <p><?= t('empty') ?></p><p><a class="btn" href="/products"><?= t('products') ?></a></p>
  <?php else: $sum = 0; ?>
  <form method="post" action="/basket/update"><?= csrf_field() ?>
    <div class="scroll"><table>
      <thead><tr><th>Product</th><th><?= t('qty') ?></th><th>Unit price</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $p): $line = $p['price_tzs'] !== null ? $p['price_tzs'] * $p['qty'] : null; $sum += (int)$line; ?>
        <tr>
          <td><?= e($p['name']) ?><br><small><?= e($p['unit']) ?></small></td>
          <td><input type="number" name="qty[<?= (int)$p['id'] ?>]" value="<?= $p['qty'] ?>" min="0" max="9999" aria-label="<?= t('qty') ?>"> <small>0 = <?= t('remove') ?></small></td>
          <td><?= $p['price_tzs'] !== null ? money($p['price_tzs']) : t('price_req') ?></td>
          <td><?= $line !== null ? money($line) : '-' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><th colspan="3"><?= t('subtotal') ?></th><th><?= money($sum) ?></th></tr></tfoot>
    </table></div>
    <p><button class="btn ghost dark"><?= t('update') ?></button></p>
  </form>
  <p class="note"><?= t('note_prices') ?></p>
  <h2><?= t('your_details') ?></h2>
  <?php if ($err): ?><p class="error" role="alert"><?= t('err_req') ?></p><?php endif; ?>
  <form method="post" action="/basket/submit" class="stack"><?= csrf_field() ?>
    <div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <label><?= t('name') ?><input name="name" required autocomplete="name"></label>
    <label><?= t('phone') ?><input name="phone" type="tel" required autocomplete="tel"></label>
    <label><?= t('email') ?><input name="email" type="email" autocomplete="email"></label>
    <label><?= t('location') ?><input name="location"></label>
    <label><?= t('message') ?><textarea name="message"></textarea></label>
    <button class="btn"><?= t('send') ?></button>
  </form>
  <?php endif; ?>
</div></section>
<?php });
}

function new_ref(string $prefix): string { return $prefix . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2))); }

function valid_contact(): bool {
    return post('name', 120) !== '' && strlen(preg_replace('/\D/', '', post('phone', 30))) >= 7;
}

function spam(): bool {
    if (post('website') !== '') return true;
    if (time() - ($_SESSION['last_submit'] ?? 0) < 15) return true;
    return false;
}

function save_request(string $kind, array $details, array $items): string {
    $ref = new_ref($kind === 'quote' ? 'ZQ' : 'ZC');
    $st = db()->prepare('INSERT INTO requests (ref, kind, name, phone, email, location, message, details, items) VALUES (?,?,?,?,?,?,?,?,?)');
    $st->execute([$ref, $kind, post('name', 120), post('phone', 30), post('email', 120), post('location', 200), post('message', 2000),
        json_encode($details, JSON_UNESCAPED_UNICODE), json_encode($items, JSON_UNESCAPED_UNICODE)]);
    $_SESSION['last_submit'] = time();
    return $ref;
}

function basket_submit(): void {
    if (spam()) redirect('/basket');
    $rows = basket_rows();
    if (!$rows) redirect('/basket');
    if (!valid_contact()) redirect('/basket?err=1');
    $items = array_map(fn($p) => ['name' => $p['name'], 'unit' => $p['unit'], 'qty' => $p['qty'], 'price' => $p['price_tzs']], $rows);
    $ref = save_request('quote', [], $items);
    unset($_SESSION['basket']);
    redirect('/thanks?ref=' . urlencode($ref));
}

/* ---------- building contracts ---------- */
const PROJECT_TYPES = ['New house', 'Commercial building', 'Extension', 'Renovation', 'Plumbing installation', 'Other'];
const BUDGETS = ['Under 10 million', '10 to 50 million', '50 to 200 million', 'Over 200 million', 'Not sure yet'];
const TIMELINES = ['Within 1 month', '1 to 3 months', '3 to 6 months', 'Flexible'];

function select_field(string $label, string $name, array $opts): string {
    $o = '';
    foreach ($opts as $v) $o .= '<option>' . e($v) . '</option>';
    return '<label>' . $label . '<select name="' . $name . '">' . $o . '</select></label>';
}

function view_contracts(): void {
    $err = !empty($_GET['err']);
    ob_page(t('contracts'), function () use ($err) { ?>
<section><div class="wrap narrow">
  <h1><?= t('contract_h') ?></h1>
  <p><?= t('contract_p') ?></p>
  <?php if ($err): ?><p class="error" role="alert"><?= t('err_req') ?></p><?php endif; ?>
  <form method="post" action="/contracts" class="stack"><?= csrf_field() ?>
    <div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <label><?= t('name') ?><input name="name" required autocomplete="name"></label>
    <label><?= t('phone') ?><input name="phone" type="tel" required autocomplete="tel"></label>
    <label><?= t('email') ?><input name="email" type="email" autocomplete="email"></label>
    <?= select_field(t('project'), 'project_type', PROJECT_TYPES) ?>
    <label><?= t('location') ?><input name="location"></label>
    <?= select_field(t('budget'), 'budget', BUDGETS) ?>
    <?= select_field(t('timeline'), 'timeline', TIMELINES) ?>
    <label><?= t('message') ?><textarea name="message"></textarea></label>
    <button class="btn"><?= t('send') ?></button>
  </form>
</div></section>
<?php });
}

function contracts_submit(): void {
    if (spam()) redirect('/contracts');
    if (!valid_contact()) redirect('/contracts?err=1');
    $pick = fn(string $k, array $opts) => in_array(post($k), $opts, true) ? post($k) : '';
    $ref = save_request('contract', [
        'project_type' => $pick('project_type', PROJECT_TYPES),
        'budget' => $pick('budget', BUDGETS),
        'timeline' => $pick('timeline', TIMELINES),
    ], []);
    redirect('/thanks?ref=' . urlencode($ref));
}

function view_thanks(): void {
    $ref = preg_replace('/[^A-Z0-9\-]/', '', strtoupper((string)($_GET['ref'] ?? '')));
    $msg = urlencode('Hello Zimbermanne, my request reference is ' . $ref);
    ob_page(t('thanks_h'), function () use ($ref, $msg) { ?>
<section><div class="wrap narrow">
  <h1><?= t('thanks_h') ?></h1>
  <p><?= t('thanks_p') ?> <strong><?= e($ref) ?></strong>.</p>
  <p><a class="btn" href="https://wa.me/<?= WA ?>?text=<?= $msg ?>"><?= t('wa_us') ?></a></p>
</div></section>
<?php });
}
