<?php
declare(strict_types=1);

require __DIR__ . '/../src/core.php';
require __DIR__ . '/../src/views.php';
require __DIR__ . '/../src/admin.php';

$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') check_csrf();

try {
    match ("$method $path") {
        'GET /'                    => view_home(),
        'GET /products'            => view_products(),
        'POST /basket/add'         => basket_add(),
        'GET /basket'              => view_basket(),
        'POST /basket/update'      => basket_update(),
        'POST /basket/submit'      => basket_submit(),
        'GET /contracts'           => view_contracts(),
        'POST /contracts'          => contracts_submit(),
        'GET /thanks'              => view_thanks(),
        'GET /lang'                => set_lang(),
        'GET /admin'               => admin_home(),
        'POST /admin/login'        => admin_login(),
        'POST /admin/logout'       => admin_logout(),
        'GET /admin/requests'      => admin_requests(),
        'GET /admin/request'       => admin_request(),
        'POST /admin/request/save' => admin_request_save(),
        'GET /admin/quote'         => admin_quote(),
        'GET /admin/products'      => admin_products(),
        'GET /admin/product'       => admin_product_form(),
        'POST /admin/product/save' => admin_product_save(),
        'POST /admin/product/delete' => admin_product_delete(),
        'GET /admin/export'        => admin_export(),
        default                    => not_found(),
    };
} catch (Throwable $ex) {
    error_log((string)$ex);
    http_response_code(500);
    page('Error', '<section><div class="wrap"><h1>Something went wrong</h1><p>Please try again in a moment.</p></div></section>');
}
