<?php
declare(strict_types=1);
function flash(string $key, ?string $value = null): ?string { if ($value !== null) { $_SESSION['_flash'][$key] = $value; return null; } $message = $_SESSION['_flash'][$key] ?? null; unset($_SESSION['_flash'][$key]); return $message; }
