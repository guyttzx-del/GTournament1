# Ubuntu production deployment

Target: `gtournament.online` on Ubuntu 24.04 with Nginx, PHP-FPM 8.3 and Let’s Encrypt.

## Server preparation

Install PHP-FPM extensions, Nginx and Certbot. Create `/var/www/GTournament1`, deploy the reviewed branch there, and keep `.env` outside Git.

```bash
sudo apt update
sudo apt install -y nginx php8.3-fpm php8.3-curl php8.3-fileinfo php8.3-mbstring certbot python3-certbot-nginx
sudo mkdir -p /var/www/GTournament1/storage/sessions
sudo chown -R www-data:www-data /var/www/GTournament1/storage/sessions
sudo chmod 700 /var/www/GTournament1/storage/sessions
```

Copy `production.env.example` to `/var/www/GTournament1/.env`, replace the publishable Supabase key and generate a long `APP_KEY`. Set the file to `chmod 600` and owner `www-data`.

## DNS and TLS

Create A records for `gtournament.online` and `www.gtournament.online` pointing to the VPS. After DNS resolves:

```bash
sudo certbot --nginx -d gtournament.online -d www.gtournament.online
```

Install `deploy/nginx/gtournament.conf` as `/etc/nginx/sites-available/gtournament`, enable it, then validate and reload:

```bash
sudo ln -s /etc/nginx/sites-available/gtournament /etc/nginx/sites-enabled/gtournament
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl reload php8.3-fpm
```

## Verification

```bash
curl -I http://gtournament.online/
curl -i https://gtournament.online/?view=health
curl -I https://gtournament.online/.env
curl -I https://gtournament.online/database/migrations/001_initial.sql
```

Expected results: HTTP redirects to HTTPS, health returns `200` with no secrets, and protected files return `404`.

Do not run production migrations or seed data until a database backup exists and the Supabase Auth redirect/SMTP settings are configured.
