<?php
declare(strict_types=1);

const COMPANY = 'Zimbermanne Company Limited';
const TIN     = '202-013-449';
const WA      = '255620563248';
const PHONE   = '+255 620 563 248';
const EMAIL   = 'zimbermannelit@gmail.com';
const STATUSES = ['new', 'contacted', 'quoted', 'won', 'lost'];

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
]);
session_start();

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_field(): string { return '<input type="hidden" name="_t" value="' . csrf() . '">'; }
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_t'] ?? ''))) {
        http_response_code(419);
        exit('Session expired. Please go back, refresh the page and try again.');
    }
}
function post(string $k, int $max = 500): string { return mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max); }
function money($n): string { return 'TZS ' . number_format((int)$n); }
function wa_phone(string $p): string {
    $d = preg_replace('/\D/', '', $p);
    return str_starts_with($d, '0') ? '255' . substr($d, 1) : $d;
}

/* ---------- database ---------- */
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $url = getenv('DATABASE_URL');
    if (!$url) { http_response_code(500); exit('DATABASE_URL is not set.'); }
    $u = parse_url($url);
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $u['host'], $u['port'] ?? 5432, ltrim($u['path'], '/'));
    $pdo = new PDO($dsn, urldecode($u['user'] ?? ''), urldecode($u['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $p): void {
    if ($p->query("SELECT to_regclass('public.requests')")->fetchColumn() !== null) return;
    $p->exec("CREATE TABLE IF NOT EXISTS products (
        id SERIAL PRIMARY KEY, name TEXT NOT NULL, category TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '', unit TEXT NOT NULL DEFAULT 'piece',
        price_tzs INTEGER, in_stock BOOLEAN NOT NULL DEFAULT TRUE,
        created_at TIMESTAMP NOT NULL DEFAULT NOW())");
    $p->exec("CREATE TABLE IF NOT EXISTS requests (
        id SERIAL PRIMARY KEY, ref TEXT UNIQUE NOT NULL, kind TEXT NOT NULL,
        name TEXT NOT NULL, phone TEXT NOT NULL, email TEXT NOT NULL DEFAULT '',
        location TEXT NOT NULL DEFAULT '', message TEXT NOT NULL DEFAULT '',
        details TEXT NOT NULL DEFAULT '{}', items TEXT NOT NULL DEFAULT '[]',
        status TEXT NOT NULL DEFAULT 'new', created_at TIMESTAMP NOT NULL DEFAULT NOW())");
    if ((int)$p->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
        $seed = [
            ['PVC pressure pipe 1 inch', 'Plumbing', 'Cold water supply pipe, 6 m length', 'length'],
            ['PVC pressure pipe 2 inch', 'Plumbing', 'Main line and drainage pipe, 6 m length', 'length'],
            ['Brass gate valve 1 inch', 'Plumbing', 'Isolation valve for water lines', 'piece'],
            ['Kitchen sink mixer tap', 'Plumbing', 'Chrome finish, single lever', 'piece'],
            ['Water tank 1000 L', 'Plumbing', 'Polyethylene storage tank with lid', 'piece'],
            ['Surface water pump 1 HP', 'Plumbing', 'For boosting and tank filling', 'piece'],
            ['Portland cement 50 kg', 'Building materials', 'General purpose cement', 'bag'],
            ['Reinforcement bar Y12', 'Building materials', '12 m length', 'piece'],
            ['Corrugated roofing sheet', 'Building materials', 'Pre-painted, gauge 28', 'sheet'],
            ['Claw hammer', 'Hardware', 'Steel head, fibreglass handle', 'piece'],
            ['Padlock 50 mm', 'Hardware', 'Hardened steel shackle', 'piece'],
            ['Exterior wall paint 20 L', 'Hardware', 'Weather-resistant emulsion', 'bucket'],
        ];
        $st = $p->prepare('INSERT INTO products (name, category, description, unit) VALUES (?,?,?,?)');
        foreach ($seed as $r) $st->execute($r);
    }
}

/* ---------- language ---------- */
function lang(): string { return $_SESSION['lang'] ?? 'en'; }
function t(string $k): string {
    static $T = [
        'en' => [
            'home' => 'Home', 'products' => 'Products', 'contracts' => 'Building contracts', 'basket' => 'Quote list',
            'hero_h' => 'Plumbing, hardware and building contracts',
            'hero_p' => 'Zimbermanne Company Limited supplies materials and builds for homes, businesses and contractors in Moshi and across Kilimanjaro.',
            'cta_quote' => 'Browse and request a quotation', 'cta_contract' => 'Start a building project',
            'svc_pl' => 'Plumbing', 'svc_pl_p' => 'Pipes, fittings, tanks and pumps, with installation available.',
            'svc_hw' => 'Hardware supply', 'svc_hw_p' => 'Building materials, tools and fixings, in retail or bulk.',
            'svc_bc' => 'Building contracts', 'svc_bc_p' => 'New builds, extensions and renovations, with materials supplied by us.',
            'featured' => 'Available now', 'search' => 'Search products', 'all' => 'All categories', 'go' => 'Search',
            'add' => 'Add to quote', 'qty' => 'Quantity', 'in' => 'In stock', 'out' => 'Out of stock',
            'price_req' => 'Price on request', 'none' => 'No products match your search.',
            'basket_h' => 'Your quotation list', 'empty' => 'Your list is empty. Add products to request a quotation.',
            'update' => 'Update quantities', 'remove' => 'Remove', 'subtotal' => 'Estimated total (priced items)',
            'note_prices' => 'Prices are estimates. We confirm the final quotation by phone or WhatsApp.',
            'your_details' => 'Your details', 'name' => 'Your name', 'phone' => 'Phone number', 'email' => 'Email (optional)',
            'location' => 'Delivery or site location', 'message' => 'Message (optional)', 'send' => 'Send request',
            'thanks_h' => 'Request received', 'thanks_p' => 'We will contact you shortly. Your reference is',
            'wa_us' => 'Message us on WhatsApp', 'project' => 'Project type', 'budget' => 'Budget range (TZS)',
            'timeline' => 'When do you want to start?', 'contract_h' => 'Tell us about your project',
            'contract_p' => 'We will arrange a site visit and send a written quotation.',
            'contact' => 'Contact', 'err_req' => 'Please enter your name and a valid phone number.',
        ],
        'sw' => [
            'home' => 'Nyumbani', 'products' => 'Bidhaa', 'contracts' => 'Kazi za ujenzi', 'basket' => 'Orodha ya bei',
            'hero_h' => 'Mabomba, vifaa vya ujenzi na kandarasi za ujenzi',
            'hero_p' => 'Zimbermanne Company Limited inauza vifaa na kujenga kwa nyumba, biashara na wakandarasi Moshi na Kilimanjaro nzima.',
            'cta_quote' => 'Tazama bidhaa na omba bei', 'cta_contract' => 'Anza mradi wa ujenzi',
            'svc_pl' => 'Mabomba', 'svc_pl_p' => 'Mabomba, viungio, matanki na pampu, pamoja na ufungaji.',
            'svc_hw' => 'Vifaa vya ujenzi', 'svc_hw_p' => 'Vifaa vya ujenzi, zana na viungio, rejareja au jumla.',
            'svc_bc' => 'Kandarasi za ujenzi', 'svc_bc_p' => 'Nyumba mpya, upanuzi na ukarabati, vifaa vinatoka kwetu.',
            'featured' => 'Zilizopo sasa', 'search' => 'Tafuta bidhaa', 'all' => 'Aina zote', 'go' => 'Tafuta',
            'add' => 'Weka kwenye orodha', 'qty' => 'Idadi', 'in' => 'Ipo', 'out' => 'Haipo',
            'price_req' => 'Bei kwa ombi', 'none' => 'Hakuna bidhaa zilizopatikana.',
            'basket_h' => 'Orodha yako ya bei', 'empty' => 'Orodha yako ni tupu. Weka bidhaa ili kuomba bei.',
            'update' => 'Sasisha idadi', 'remove' => 'Ondoa', 'subtotal' => 'Jumla ya makadirio (bidhaa zenye bei)',
            'note_prices' => 'Bei ni makadirio. Tunathibitisha bei ya mwisho kwa simu au WhatsApp.',
            'your_details' => 'Taarifa zako', 'name' => 'Jina lako', 'phone' => 'Namba ya simu', 'email' => 'Barua pepe (si lazima)',
            'location' => 'Mahali pa kupelekewa au eneo la mradi', 'message' => 'Ujumbe (si lazima)', 'send' => 'Tuma ombi',
            'thanks_h' => 'Ombi limepokelewa', 'thanks_p' => 'Tutawasiliana nawe hivi karibuni. Namba yako ya kumbukumbu ni',
            'wa_us' => 'Tuandikie WhatsApp', 'project' => 'Aina ya mradi', 'budget' => 'Bajeti (TZS)',
            'timeline' => 'Unataka kuanza lini?', 'contract_h' => 'Tueleze kuhusu mradi wako',
            'contract_p' => 'Tutapanga ziara ya eneo na kukutumia nukuu ya bei kwa maandishi.',
            'contact' => 'Wasiliana nasi', 'err_req' => 'Tafadhali weka jina lako na namba sahihi ya simu.',
        ],
    ];
    return $T[lang()][$k] ?? $T['en'][$k] ?? $k;
}
