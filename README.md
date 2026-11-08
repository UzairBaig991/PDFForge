# PDFForge

PDFForge is a self-hosted PHP PDF-tools starter for Apache, MySQL, and XAMPP. The implemented conversion is **PDF to JPG**, rendered by Poppler's `pdftoppm`. A one-page PDF produces a JPG download; multi-page PDFs produce a ZIP containing each page as a JPG. Tools marked **Coming soon** do not claim to process files.

## Features

- Responsive homepage, tool directory/search, FAQ, and persistent light/dark theme.
- Server-validated PDF upload (extension, detected MIME type, PDF signature, and size).
- Random internal upload names, protected local storage, CSRF checks, session/account ownership, and authorized downloads.
- Real Poppler PDF rendering, PDF-to-text extraction, ZIP packaging for multi-page PDFs, and actual upload progress.
- PDO/MySQL schema, account registration/login/profile/password change, a basic dashboard, and admin-role-gated overview.
- Configurable one-hour default retention with a command-line cleanup script.

No PDF conversion Composer library is bundled; PDF-to-JPG and PDF-to-Text need a separately installed Poppler package (`pdftoppm.exe` and `pdftotext.exe`). PDF-to-Word, OCR, billing, email verification, password reset email delivery, and the other tools are not implemented.

## Requirements

- Windows 10/11 with XAMPP (Apache, MySQL, PHP 8.0+).
- PHP extensions: PDO MySQL, Fileinfo, mbstring, and ZipArchive.
- Composer 2.
- Poppler for Windows, including `pdftoppm.exe`.

## XAMPP setup

1. Install XAMPP and start **Apache** and **MySQL** in the XAMPP Control Panel. This project is intended for XAMPP; Laragon is not required.
2. Put the project folder at `C:\xampp\htdocs\PDFForge\`. The current workspace may have a different folder name; rename/copy it to `PDFForge` to use the expected `http://localhost/PDFForge/` URL. If you keep another name, set `APP_URL` in `.env` to that URL.
3. Open `http://localhost/phpmyadmin/`, create a database named `pdfforge`, choose the `utf8mb4_unicode_ci` collation, then import `database/database.sql`.
4. Copy `.env.example` to `.env`. For the default XAMPP MySQL installation, `DB_USER=root` and an empty `DB_PASSWORD` are typical local defaults. Change these for your installation; do not use a root account for a public deployment.
5. In `C:\xampp\php\php.ini`, confirm `extension=pdo_mysql`, `extension=fileinfo`, `extension=mbstring`, and `extension=zip` are enabled. XAMPP may ship with `;extension=zip` commented out; remove the leading semicolon. Save and restart Apache. These extensions must also be enabled for the CLI PHP used by Composer and the cleanup task.
6. Install Composer dependencies from the project directory:

   ```powershell
   cd C:\xampp\htdocs\PDFForge
   composer install
   ```

   There are no third-party PHP packages to download. Composer checks the required PHP version/extensions and creates the autoloader.
7. Configure PHP upload sizes in `C:\xampp\php\php.ini`. Set `upload_max_filesize` to at least `50M`, `post_max_size` to at least `55M`, and `max_execution_time` to an appropriate value for the PDFs you process. Save the file and restart Apache.
8. Configure Poppler as described below, then open `http://localhost/PDFForge/`.

## Configure Poppler on Windows

1. Install/extract a trusted 64-bit Poppler for Windows distribution. Confirm the extracted package contains `pdftoppm.exe` (commonly under `Library\bin`).
2. Set `PDF_ENGINE_PATH` in `.env` to the **directory containing** `pdftoppm.exe` and `pdftotext.exe`, for example:

   ```dotenv
   PDF_ENGINE_PATH=C:\tools\poppler\Library\bin
   ```

   A direct path to `pdftoppm.exe` is also accepted; the sibling `pdftotext.exe` is used for text extraction. Do not copy a personal machine path into source code.
3. Ensure the Windows account running Apache can read/execute the Poppler binaries and write to `storage\uploads`, `storage\processed`, `storage\temp`, and `storage\logs`.
4. Restart Apache. The application checks that the configured executable exists before conversion and reports **“PDF processing engine is not configured.”** if it is missing or not executable.
5. Test using a small, unencrypted PDF at `http://localhost/PDFForge/tools/pdf-to-jpg.php` or `pdf-to-text.php`. The server also needs PHP's `proc_open` function enabled. PDF-to-Text extracts selectable text only; scanned pages require OCR, which is not included.

Poppler renders page images at `PDF_DPI` (default 150; configurable from 72 to 300). The tool reports errors instead of returning files when rendering fails.

## Configuration

Available `.env` settings:

| Key | Purpose | Default |
| --- | --- | --- |
| `APP_NAME` | Display name | `PDFForge` |
| `APP_URL` | Canonical app URL | Derived from the folder under Apache |
| `APP_ENV` | Environment label | `local` |
| `DEBUG_MODE` | PHP display/debug switch | `false` |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | MySQL connection | Local XAMPP defaults |
| `MAX_UPLOAD_SIZE` | Maximum PDF bytes | `52428800` (50 MiB) |
| `FILE_RETENTION_SECONDS` | Upload/result expiry | `3600` |
| `PDF_ENGINE_PATH` | Poppler executable or its containing folder | Unset |
| `PDF_DPI` | Render resolution | `150` |

Keep `.env` private. Apache access to `app`, `config`, `database`, `includes`, `storage`, and `vendor` is denied by `.htaccess`; keep Apache `AllowOverride` enabled so these protections take effect.

## Accounts and admin access

Import the schema before registering an account. Create an account through `register.php`. To explicitly grant a local account admin access, use phpMyAdmin's SQL tab and update that account's role:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your-address@example.com';
```

Admin pages check the stored role on every request. Do not make a public registration flow grant admin access.

## Cleanup

Expired database records are checked at download time, but disk deletion requires the cleanup script. Run it manually:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\PDFForge\cron\cleanup.php
```

For ongoing use, create a Windows Task Scheduler task that runs this command at a suitable interval (for example, every 15 minutes). The script is CLI-only.

## Troubleshooting

- **Database connection error:** start MySQL, verify `.env`, import the schema, and ensure `pdo_mysql` is enabled.
- **Upload rejected by PHP:** check `upload_max_filesize`, `post_max_size`, and the application `MAX_UPLOAD_SIZE`; restart Apache after `php.ini` edits.
- **PDF engine not configured:** verify the `.env` path points to `pdftoppm.exe` or its directory and that Apache can execute it.
- **Conversion fails:** use a valid PDF and ensure the PHP `proc_open` function is not disabled. Encrypted or malformed PDFs may not render.
- **Access denied to storage:** ensure the Apache account has write permission to the four `storage` subdirectories.
- **Links point to the wrong folder:** set `APP_URL` to the exact local app URL in `.env`, then restart Apache.

Application errors are written to `storage/logs/application.log`; passwords, reset tokens, and PDF contents are not intentionally logged. Keep the log directory private and rotate logs for long-running deployments.

## Production notes

This is a local-development starter, not a fully hardened commercial service. Before public use, configure HTTPS, a restricted MySQL account, secure backups, request throttling at the web server, operational monitoring, privacy/terms appropriate to your service, retention scheduling, and a trusted Poppler build. Run supported PHP and Apache updates. Do not enable public access to an unconfigured XAMPP installation.
