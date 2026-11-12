# PDFForge

PDFForge is a self-hosted PHP toolkit for Apache, MySQL, and XAMPP. All 13 listed PDF tools have processing workflows: image and office-format conversions, merge/split/rotate/watermark, compression, and password protection/unlocking.

## Features

- Responsive homepage, tool directory/search, FAQ, and persistent light/dark theme.
- Server-validated PDF/image uploads (extension, detected MIME type, file signature, and combined size).
- Random internal upload names, protected local storage, CSRF checks, session/account ownership, and authorized downloads.
- Poppler rendering/text extraction, FPDI page editing, QPDF password and rotation operations, Ghostscript compression, and ZIP packaging for multi-page outputs.
- DOCX, XLSX, and PPTX creation from selectable PDF text. These conversions do not OCR scans or preserve the original page layout.
- PDO/MySQL schema, account registration/login/profile/password change, a basic dashboard, and admin-role-gated overview.
- Configurable one-hour default retention with a command-line cleanup script.
- Shared styles and browser interactions are bundled into one CSS and one JavaScript file. PDF tool page URLs and API URLs remain unchanged and route through shared handlers.

## Requirements

- Windows 10/11 with XAMPP (Apache, MySQL, PHP 8.0+).
- PHP extensions: PDO MySQL, Fileinfo, mbstring, and ZipArchive.
- Composer 2.
- Poppler for Windows (`pdftoppm.exe`, `pdftotext.exe`), QPDF (`qpdf.exe`), and Ghostscript (`gswin64c.exe`).

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

   Composer installs FPDI and FPDF, which power PDF assembly and page editing.

   Run the local PDF-editing smoke test after installing dependencies and enabling ZIP support:

   ```powershell
   php -d extension=zip tests\pdf-tools-smoke.php
   ```
7. Configure PHP upload sizes in `C:\xampp\php\php.ini`. Set `upload_max_filesize` to at least `50M`, `post_max_size` to at least `55M`, and `max_execution_time` to an appropriate value for the PDFs you process. Save the file and restart Apache.
8. Configure Poppler, QPDF, and Ghostscript as described below, then open `http://localhost/PDFForge/`.

## Configure PDF engines on Windows

Install trusted 64-bit Windows builds of Poppler, QPDF, and Ghostscript. Set the following `.env` values to the executable itself or its containing folder:

   ```dotenv
   PDF_ENGINE_PATH=C:\tools\poppler\Library\bin
   QPDF_PATH=C:\tools\qpdf\bin
   GHOSTSCRIPT_PATH=C:\Program Files\gs\gs10.05.1\bin
   ```

The directories must contain `pdftoppm.exe` and `pdftotext.exe`, `qpdf.exe`, and `gswin64c.exe`, respectively. Direct paths to the relevant executables are also accepted. Do not copy a personal machine path into source code.

Ensure the Windows account running Apache can execute the configured binaries and write to `storage\uploads`, `storage\processed`, `storage\temp`, and `storage\logs`. Enable PHP's `proc_open` and ZIP support, then restart Apache. Poppler renders page images at `PDF_DPI` (default 150; configurable from 72 to 300).

PDF-to-Word and PDF-to-Excel extract selectable text; Excel detects columns using spacing in Poppler's layout output. PDF-to-PowerPoint creates one editable text slide per PDF page. These are text conversions, not OCR or pixel-perfect layout conversions. Scanned PDFs require an OCR engine, which is not included.

The split tool packages individual pages into a ZIP when no page range is entered. Enter a range such as `1-3,5` to extract those pages into one PDF. Merge accepts up to 20 PDFs; JPG-to-PDF accepts up to 20 JPG/PNG images.

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
| `QPDF_PATH` | QPDF executable or its containing folder | Unset |
| `GHOSTSCRIPT_PATH` | Ghostscript executable or its containing folder | Unset |
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
- **Engine not configured:** verify the `.env` path points to the required Poppler, QPDF, or Ghostscript executable or its directory and that Apache can execute it.
- **Conversion fails:** use a valid PDF and ensure the PHP `proc_open` function is not disabled. Encrypted or malformed PDFs may not render.
- **Access denied to storage:** ensure the Apache account has write permission to the four `storage` subdirectories.
- **Links point to the wrong folder:** set `APP_URL` to the exact local app URL in `.env`, then restart Apache.

Application errors are written to `storage/logs/application.log`; passwords, reset tokens, and PDF contents are not intentionally logged. Keep the log directory private and rotate logs for long-running deployments.

## Production notes

This is a local-development starter, not a fully hardened commercial service. Before public use, configure HTTPS, a restricted MySQL account, secure backups, request throttling at the web server, operational monitoring, privacy/terms appropriate to your service, retention scheduling, and a trusted Poppler build. Run supported PHP and Apache updates. Do not enable public access to an unconfigured XAMPP installation.
