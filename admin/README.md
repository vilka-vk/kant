# KANT PHP Admin (IONOS)

## 1) Configure

1. Copy `admin/lib/config.local.example.php` to `admin/lib/config.local.php`.
2. Fill MySQL credentials and set a strong `install_secret`.

## 2) Install

1. Open `/admin/install.php`.
2. Enter:
   - install secret
   - admin email
   - admin password
3. Installer creates all tables from `database/schema.sql`, seeds defaults, and creates admin account.

## 3) Login

- Open `/admin/login.php`.
- Use created credentials.

## 4) Available sections

- Modules
- Site settings
- About project
- Hero sections
- Publication types
- Publications
- Authors

Notes:
- Module transcripts/readings are managed inside each module edit page.
- Legacy video column cleanup is available at `/admin/migrate.php`.

## 5) Security after install

- Restrict or delete `/admin/install.php`.
- Keep `admin/lib/config.local.php` out of git.
- Use HTTPS only.
