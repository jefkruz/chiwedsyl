# Chi & Syl — Wedding Website

A beautiful wedding website built with **plain PHP**, SQLite, and no frameworks.

## Features

- **Home page**: Hero image, countdown to wedding date, calendar with heart on wedding day, “We would love to have you celebrate…” text, ring photo, Our Story, wedding & reception details, colour of the day (chocolate brown, gold, cream, navy blue), RSVP CTA with phone numbers
- **RSVP / Register**: Guests register to attend; after submitting they see a **QR code** to show at the venue
- **Admin**: Login to manage gifts, view guests and their QR codes, check guests in, view uploaded receipts
- **Gifts page**: Bank details, list of gift ideas (uploaded by admin), form to upload payment receipt

## Setup

1. **PHP**: Needs PHP 7.4+ with PDO SQLite and `session` enabled.
2. **Web server**: Point document root to this folder (or run `php -S localhost:8000` in the project folder).
3. **Photos**: Add your images so paths match:
   - `assets/images/DSC02228.jpg` — opening/hero
   - `assets/images/ring.jpg` — ring photo
   - `assets/images/DSC02357.jpg` — second photo
   - `assets/images/DSC02087.jpg` — third photo
4. **Config**: Edit `config.php`:
   - `WEDDING_DATE` — your wedding date (YYYY-MM-DD)
   - `ADMIN_PASS_PLAIN` — admin password (change it)
   - `$bank_details` — bank name, account name, account number, sort code
   - Ceremony/reception text is in `index.php` (search for “Venue name” and “Reception venue”).

## Admin

- **URL**: `/admin/`
- **Default login**: username `admin`, password `chiandsyl2026` (change in `config.php`).
- **Guests**: List of registrations and QR codes; use “Check in” or scan QR at the venue.
- **Gifts**: Add/edit/delete gift items and images.
- **Receipts**: View all uploaded receipts.

## QR codes

Each guest gets a unique code. The QR image is generated via an external API. At the venue, use the admin “Check in” button or any QR scanner that opens a URL/code — you can later add a simple “scan and lookup” page if needed.

## File structure

```
chiwedsly/
├── config.php           # Site and admin settings
├── index.php            # Home page
├── register.php         # RSVP form + QR success
├── gifts.php            # Gifts + bank details + receipt upload
├── includes/
│   ├── db.php           # SQLite connection + schema
│   ├── header.php
│   ├── footer.php
│   └── admin-auth.php
├── admin/
│   ├── index.php        # Login
│   ├── dashboard.php
│   ├── guests.php       # Guests + QR + check-in
│   ├── gifts.php        # Add gifts
│   ├── gift-edit.php
│   ├── receipts.php
│   └── logout.php
├── assets/
│   ├── css/style.css
│   ├── js/main.js
│   └── images/          # Your photos
├── data/                # SQLite DB (auto-created)
└── uploads/             # Gift images + receipts
    ├── gifts/
    └── receipts/
```

## Clean URLs (no .php)

- **Apache**: Enable `mod_rewrite` and use the included `.htaccess`. URLs: `/`, `/register`, `/gifts`, `/admin`, `/admin/guests`, etc.
- **PHP built-in server**: Run with the router so clean URLs work:
  ```bash
  cd chiwedsly
  php -S localhost:8000 router.php
  ```
  Then open http://localhost:8000

## Run locally

```bash
cd chiwedsly
php -S localhost:8000 router.php
```

Then open http://localhost:8000 (use `router.php` for clean URLs; without it you’d use `index.php`, `register.php`, etc.)
