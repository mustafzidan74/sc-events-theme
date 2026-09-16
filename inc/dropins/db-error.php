<?php
/**
 * Drop-in: wp-content/db-error.php — what visitors see when the database stays out of reach.
 *
 * A short "busy" answer that retries by itself, instead of WordPress's page about
 * wrong passwords. Ajax callers get JSON with a 503, which the scanner treats as
 * "no connection" and answers from its saved list.
 *
 * Source: themes/sc_events/inc/dropins/db-error.php.
 */

if (!headers_sent()) {
    http_response_code(503);
    header('Retry-After: 5');
    header('Cache-Control: no-store');
}

$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
$wants_json = strpos($uri, 'admin-ajax.php') !== false || strpos($uri, '/wp-json/') !== false
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($wants_json) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo '{"success":false,"data":{"code":"busy","message":"The site is busy. Please try again in a few seconds."}}';
    return;
}
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="4">
<title>One moment…</title>
<style>
  body { margin: 0; min-height: 100vh; display: grid; place-items: center; font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; background: #F7F5F2; color: #1F1D1A; }
  main { max-width: 420px; padding: 24px; text-align: center; }
  h1 { margin: 0 0 8px; font-size: 22px; }
  p { margin: 0; color: #5E5850; }
  .dot { display: inline-block; width: 10px; height: 10px; margin-bottom: 16px; border-radius: 50%; background: #7C1314; animation: pulse 1s ease-in-out infinite alternate; }
  @keyframes pulse { to { opacity: .3; } }
  @media (prefers-reduced-motion: reduce) { .dot { animation: none; } }
</style>
</head>
<body>
<main>
  <span class="dot" aria-hidden="true"></span>
  <h1>A lot of people are here right now</h1>
  <p>This page will try again by itself in a few seconds.</p>
  <p dir="rtl" lang="ar" style="margin-top:12px">الموقع مزدحم الآن — الصفحة هتحاول تاني لوحدها بعد ثواني.</p>
</main>
</body>
</html>
