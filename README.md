# Flowline Pro

Website and admin portal for a plumbing and water-works business: quote requests, gallery,
quotations, proposals, invoices, payments and receipts. PHP 8 + MySQL, built for shared hosting.
Branding and contact details are set in `includes/config.php` and `includes/content.php`.


## What is inside `public_html/`

| Path | What it does |
|---|---|
| `index.php` | The website: hero, services, how we work, coverage, gallery, FAQs, quote form |
| `quote.php` | Saves quote requests to the database and emails them to the office |
| `admin/` | Admin portal: quote requests with email replies, gallery photos and videos, password change, SMTP test |
| `includes/mailer.php` | Sends all email through the Hostinger mailbox over SMTP |
| `includes/seed.php` | Starter gallery list (83 photos, 12 videos) with captions |
| `admin/documents.php` | Quotations, business proposals, invoices, payments and receipts |
| `doc.php` | The client's secure view link: view, print/PDF, accept online, receipts |
| `includes/doc-templates.php` | Default terms, proposal section wording, item price-list suggestions, VAT rate |
| `install.php` | One-time setup. **Delete after running.** |
| `includes/config.php` | Phone, email, domain and database settings |
| `includes/content.php` | All service lists, steps and FAQ wording — edit text here |
| `assets/img/logo.svg` | Full logo (icon + wordmark). `logo-mark.svg` is the icon only |
| `uploads/gallery/` | Gallery photos and videos (starter set included) |

## Secrets

No passwords are stored in this repository. `includes/config.php` holds placeholders only.
On the server, copy `includes/config.local.example.php` to `includes/config.local.php`
and put the database password, SMTP password, KRA PIN and payment details there.
`config.local.php` is git-ignored, and `includes/` is blocked from the web by `.htaccess`.

## Deploy on Hostinger

1. **Create the database** — hPanel → Databases → MySQL Databases. Note the database name, username and password (they look like `u123456789_power`).
2. **Create the mailbox** `info@powerplumbing.top` in hPanel → Emails (if not already there) and note its password.
3. **Create `includes/config.local.php`** from `config.local.example.php` (or edit `includes/config.php` on the server only, never in git):
   - `db` section: database name, user and password from step 1.
   - `smtp` → `pass`: the info@ mailbox password. Host `smtp.hostinger.com`, port `465`, SSL are already set (use port `587` with `'secure' => 'tls'` if 465 is ever blocked).
   - Check `site_url`, `email` and `notify_email`.
   - `kra_pin` and `payment_details` (M-Pesa Paybill/Till and bank account) — these print on every invoice.
4. **Upload** — hPanel → Files → File Manager → `public_html`. Upload the zip contents (or the zip, then Extract) so `index.php` sits directly in `public_html`.
5. **Turn on SSL** — hPanel → Security → SSL → install the free certificate. The `.htaccess` forces HTTPS.
6. **Raise the upload limit for videos** — hPanel → Advanced → PHP Configuration → set `upload_max_filesize` and `post_max_size` to at least 64M.
7. **Run the installer** — open `https://yourdomain/install.php`, choose an admin username and a strong password. This also loads the starter photos and videos.
8. **Delete `install.php`** from File Manager.
9. **Check email** — Admin → Account → *Send a test email*. If it fails, the message says why (usually the mailbox password).
10. **Admin portal** — `https://yourdomain/admin/`

Already installed the first version? Just upload the new files over the old ones. The new database columns are added automatically the first time you open the admin, and Admin → Gallery → *Load the starter photos and videos* adds the new media without duplicating anything.

## Using the admin portal

- **Quote requests**: every form submission, newest first. Set status (New → Contacted → Quoted → Job won / Closed), tap the phone number to call, or WhatsApp straight from the list. **Reply by email** opens a box under each request that has an email address; it sends from info@ through Hostinger, keeps a copy under the request, and moves "New" to "Contacted". Customer replies land in the info@ inbox. Download CSV for records.
- **Email the site sends**: the office gets every new request (hit Reply in your mail app to answer the customer directly), and the customer gets a short "we received your request" note. Turn that off with `'auto_reply' => false`.
- **Gallery**: upload photos (resized, GPS data removed) and MP4 videos from a phone. Pick a category and caption. Tick **On the job** for photos of the team working — those scroll in the "Our team on the job" strip. Lower "Order" numbers show first; the home page shows three rows at a time with a *Show more* button.
- **Videos**: hosting can't re-encode video, so keep uploads short (under a minute) or compress them first. The starter videos are already cropped to full-frame 9:16 (no black bars), muted, capped at 60 seconds and compressed — 12 clips total 32 MB.
- **Account**: change the admin password; send a test email.

## Quotes, proposals, invoices and receipts

Admin → **Quotes & proposals**. Numbering is automatic: `PPS-Q-2026-0001` (quotation), `PPS-P-…` (proposal), `PPS-INV-…` (invoice), `PPS-R-…` (receipt).

1. **Create** — from a quote request (*Create quote* / *Create proposal* fills in the client's name, phone, email, site and request) or blank.
2. **Edit** — add line items (start typing for price-list suggestions; the unit fills in), VAT 16% on/off, discount. Totals update as you type. Proposals come with eleven ready-written sections (introduction, about us, scope, methodology, programme, materials, safety, pricing, warranty, next steps) that you edit, reorder or remove.
3. **Preview / print / PDF** — branded A4 letterhead with a faded logo watermark and a status stamp (DRAFT, ACCEPTED, INVOICED, PART PAID, PAID). Use the browser's *Save as PDF*.
4. **Send** — email a secure link from info@, or send the link on WhatsApp. The client can view, print and **accept online** (typed name + tick). You get an email when they accept, and the stamp changes to ACCEPTED.
5. **Convert to invoice** — one click copies the client and items into a numbered invoice that carries the bank/M-Pesa details and a due date.
6. **Record payments** — amount, method (M-Pesa, bank, cash, cheque), reference and date. Each payment issues an official receipt (amount in words, balance remaining) and can email it to the client. The invoice moves to *Partly paid* or *Paid* automatically, and the list shows the total still outstanding.

Edit default terms, proposal wording and the price-list in `includes/doc-templates.php`.

## Security built in

- Passwords hashed with `password_hash`; login locks for 15 minutes after 5 failed attempts per IP; session ends after 30 minutes idle; session ID regenerated at login.
- CSRF tokens on every form; all database queries use prepared statements; all output escaped.
- SMTP over SSL with certificate verification; header injection blocked in names and subjects.
- Quote form: hidden honeypot field, minimum fill time, limit of 5 requests per hour per IP.
- Uploads: type checked by content (not file name). Photos are re-encoded with GD (strips EXIF and anything hidden); videos must be genuine MP4 files. Random file names, and `uploads/.htaccess` refuses to serve anything except images and MP4s.
- `includes/` and `data/` blocked from the web; security headers and a Content-Security-Policy in `.htaccess`.
- CSV export neutralises spreadsheet formula injection.
- Client document links use 192-bit random tokens, are not indexed by search engines, and send no referrer.

## Things to confirm with the client

Placeholder wording you may want them to approve (all in `includes/content.php` or `index.php`):
- Working hours: Mon–Sat 7:30am–6:00pm, emergencies any time
- Payment: M-Pesa or bank transfer, deposit on larger jobs
- Borehole drilling coordinated with a licensed driller
- Countries listed under coverage
- Domain: `site_url` is set to `https://powerplumbing.co.ke` while the email is `@powerplumbing.top` — use one domain for both if possible
