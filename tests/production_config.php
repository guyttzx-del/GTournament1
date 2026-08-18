<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

$_ENV = ['APP_ENV' => 'production', 'LOCAL_MOCK' => 'false', 'APP_URL' => 'https://gtournament.example', 'SUPABASE_URL' => 'https://project.supabase.co', 'SUPABASE_ANON_KEY' => 'real-anon-key', 'SUPABASE_AUTH_REDIRECT_URL' => 'https://gtournament.example/?view=auth', 'SESSION_SECURE_COOKIE' => 'true'];
if (app_uses_mock() || production_config_errors() !== [] || !app_configured()) throw new RuntimeException('valid production configuration was rejected');
$_ENV['SUPABASE_ANON_KEY'] = 'replace-with-supabase-anon-key';
if (app_configured()) throw new RuntimeException('placeholder production configuration was accepted');
echo "Production config tests passed\n";
