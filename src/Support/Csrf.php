<?php
declare(strict_types=1);
function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">'; }
function verify_csrf(): void { if (!is_string($_POST['_csrf'] ?? null) || !hash_equals((string) ($_SESSION['_csrf'] ?? ''), $_POST['_csrf'])) { http_response_code(419); throw new RuntimeException('เซสชันหมดอายุ กรุณาลองใหม่อีกครั้ง'); } }
