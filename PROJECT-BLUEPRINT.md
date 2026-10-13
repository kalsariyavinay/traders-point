# Traders Point Academy — Full Project Blueprint

> **What this file is.** A complete, self-contained specification of this Laravel
> application: every table, model, route, controller, view, design token, test and
> non-obvious decision. Hand this file to any AI coding assistant and it can
> rebuild the project from an empty folder, or pick it up and extend it.
>
> **Status:** Phases 1–5 complete, plus Upgrades 1 (Razorpay webhook),
> 2 (progress & resume), 3 (duration & completion), 4 (PDF certificates),
> 5 (coupons), 6 (coupon admin UI), 7 (public certificate verification),
> 8 (queued purchase receipts), 9 (rate limiting), 10 (global dark/light theme
> across both surfaces), 11 (admin UX hardening: loading states, validation
> feedback, upload progress, confirmation dialogs, graceful failures) and
> 12 (playback and admin-form repair: embed-URL normalisation, the
> hidden-invalid-field class of bug, in-place session renewal) and
> 13 (signed, viewer-bound, sliced video streaming -- see 13.12).
> **368 tests passing (1211 assertions).**
> **Last verified:** 2026-10-11, in PHPUnit *and* in a real headless Chrome.

---

## 0. How to use this document

**To rebuild from scratch:** follow §3 → §4 → §5 in order, then create the files
described in §6 – §12. Verify with §14.

**To extend the project:** read §13 (decisions you must not accidentally break)
and §15 (what is deliberately missing and what to build next).

**A note on the folder name.** The project lives in a folder called `skin-care`.
That is a historical accident — the product is **Traders Point Academy**, a paid
stock-market trading video platform. The folder name is intentional (the owner
knows); do not "fix" it, but be aware it appears in `.htaccess` (`RewriteBase`),
in `APP_URL`, and in the database name `skin_care`.

---

## 1. What the product is

A video course platform for Indian retail stock traders.

- Visitors browse a catalogue of trading lessons grouped into categories.
- **Free** lessons play for anyone, no account needed.
- **Paid** lessons show a locked preview and a price; buying one through
  Razorpay grants permanent access to that single video.
- Learners get a dashboard listing everything they bought plus every free
  lesson they opened.
- An admin panel manages categories, videos (upload or external link), and
  shows revenue and order history.

Business model: **one-time purchase per video**, not a subscription.
Currency: **INR (₹)**, amounts charged in paise.

---

## 2. Tech stack

| Layer | Choice | Version used |
|---|---|---|
| Language | PHP | 8.2.12 |
| Framework | Laravel | 12.69.3 |
| Auth scaffolding | laravel/breeze (Blade stack) | 2.4.2 |
| Payments | razorpay/razorpay | 2.9.3 |
| PDF | barryvdh/laravel-dompdf | 3.1.2 (dompdf 3.1.6) |
| DB | MySQL (MariaDB via XAMPP) | utf8mb4_unicode_ci |
| CSS | Tailwind CSS **v3** via PostCSS | 3.4.19 |
| JS | Alpine.js (bundled by Vite) | 3.x |
| Build | Vite | 7.x |
| Tests | PHPUnit + Mockery | 11.5.57 / 1.6.15 |
| Fonts | Bunny Fonts (Google Fonts mirror) | — |

### composer.json

```json
"require": {
    "php": "^8.2",
    "laravel/framework": "^12.0",
    "laravel/tinker": "^2.10.1",
    "razorpay/razorpay": "^2.9",
    "barryvdh/laravel-dompdf": "^3.1"
},
"require-dev": {
    "fakerphp/faker": "^1.23",
    "laravel/breeze": "^2.4",
    "laravel/pail": "^1.2.2",
    "laravel/pint": "^1.24",
    "laravel/sail": "^1.41",
    "mockery/mockery": "^1.6",
    "nunomaduro/collision": "^8.6",
    "phpunit/phpunit": "^11.5.50"
}
```

### package.json (devDependencies)

```json
"@tailwindcss/forms": "^0.5.2",
"alpinejs": "^3.4.2",
"autoprefixer": "^10.4.2",
"axios": "^1.11.0",
"concurrently": "^9.0.1",
"laravel-vite-plugin": "^2.0.0",
"postcss": "^8.4.31",
"tailwindcss": "^3.1.0",
"vite": "^7.0.7"
```

> ⚠️ Laravel 12's default skeleton ships `@tailwindcss/vite` (v4). Breeze 2.4
> installs Tailwind **v3** and a `postcss.config.js`, and removes the v4 plugin
> from `vite.config.js`. The v4 package was **uninstalled** here — having two
> Tailwind majors present is a trap. Keep only v3.

---

## 3. Environment (XAMPP on Windows)

| Thing | Value |
|---|---|
| Project path | `C:\xampp-8.2\htdocs\skin-care` |
| PHP CLI | `C:\xampp-8.2\php\php.exe` |
| php.ini | `C:\xampp-8.2\php\php.ini` |
| MySQL CLI | `C:\xampp-8.2\mysql\bin\mysql.exe` (not on PATH) |
| Apache config | `C:\xampp-8.2\apache\conf\httpd.conf` |
| Browse URL | `http://localhost/skin-care` → redirects to `/skin-care/public/` |

**Required PHP extensions** (all present in XAMPP 8.2 by default):
`bcmath, ctype, curl, dom, fileinfo, filter, gd, hash, intl, mbstring, openssl,
pcre, pdo_mysql, pdo_sqlite, session, tokenizer, xml, zip`

- `pdo_sqlite` is needed because the **test suite runs on SQLite in memory**.
- `gd` is needed for `UploadedFile::fake()->image()` in tests.

**Upload limits** (already sufficient; raise in `php.ini` for bigger videos):

```ini
upload_max_filesize = 128M
post_max_size       = 128M
memory_limit        = 512M
```

**Apache requirements:** `mod_rewrite` enabled and `AllowOverride All` on the
`htdocs` directory block. Both are XAMPP defaults.

---

## 4. From-scratch setup

```bash
cd C:/xampp-8.2/htdocs

# 1. Laravel
composer create-project laravel/laravel skin-care
cd skin-care

# 2. Database
"C:/xampp-8.2/mysql/bin/mysql.exe" -u root -e \
  "CREATE DATABASE skin_care CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Packages
composer require laravel/breeze --dev
composer require razorpay/razorpay
php artisan breeze:install blade --no-interaction   # also runs npm install + build

# 4. Remove the leftover Tailwind v4 plugin (Breeze uses v3 via PostCSS)
npm remove @tailwindcss/vite

# 5. Storage symlink for public thumbnails
php artisan storage:link

# 6. Configure .env (see §5), then:
php artisan config:clear
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoContentSeeder   # optional sample catalogue
npm run build
```

### Default accounts after seeding

| Role | Email | Password |
|---|---|---|
| Admin | `admin@skincare.test` | `password` |
| User | `test@example.com` | `password` |

---

## 5. Configuration

### 5.1 `.env` (the values that differ from a stock install)

```dotenv
APP_NAME="Traders Point Academy"
APP_URL=http://localhost/skin-care/public      # ⚠️ MUST include /public — see §13.1

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=skin_care
DB_USERNAME=root
DB_PASSWORD=

RAZORPAY_KEY=
RAZORPAY_SECRET=
RAZORPAY_WEBHOOK_SECRET=
RAZORPAY_CURRENCY=INR
```

Everything else stays at Laravel 12 defaults (`SESSION_DRIVER=database`,
`QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `MAIL_MAILER=log`).

### 5.2 `config/services.php` — append

```php
'razorpay' => [
    'key' => env('RAZORPAY_KEY'),
    'secret' => env('RAZORPAY_SECRET'),
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    'currency' => env('RAZORPAY_CURRENCY', 'INR'),
],
```

### 5.3 `config/academy.php` — all marketing copy lives here

A single file so a non-developer can reword the landing page without touching
Blade. Keys: `brand`, `hero.{eyebrow,headline,subheadline}`, `stats[]`
(`value`, `label`), `testimonials[]` (`quote`, `name`, `role`), `faqs[]`
(`question`, `answer`).

> The stats (`2,400+ students`, `6 trainers`, `100%`, `92%`) are **placeholders**.
> Replace before going live.

### 5.4 `tailwind.config.js` — the design tokens

```js
darkMode: 'class',

const themed = (v) => `rgb(var(${v}) / <alpha-value>)`;

theme: {
  extend: {
    fontFamily: {
      sans:    ['Figtree', ...defaultTheme.fontFamily.sans],   // admin (Breeze) only
      display: ['Sora', ...defaultTheme.fontFamily.sans],      // public headings
      body:    ['"DM Sans"', ...defaultTheme.fontFamily.sans], // public prose
      data:    ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono], // every figure
    },
    colors: {
      ink:   { DEFAULT: themed('--c-ink'), raised: themed('--c-ink-raised'), line: themed('--c-ink-line') },
      paper: themed('--c-paper'),
      muted: themed('--c-muted'),
      bull:  themed('--c-bull'),   // free / open / success
      bear:  themed('--c-bear'),   // failure / down candle
      gold:  themed('--c-gold'),   // paid / price
    },
    letterSpacing: { eyebrow: '0.18em' },
  },
},
plugins: [forms],
```

Colours are CSS custom properties holding **space-separated RGB channels**, so
opacity modifiers (`bg-ink/60`, `text-paper/80`) still work while the whole
palette flips from one block in `app.css`.

**The names are roles, not literal colours.** `ink` is the page surface and
`paper` is the text on it — so in light mode `ink` is near-white and `paper` is
near-black. See §13.9.

**Keep `sans` as Figtree.** The admin panel is Breeze-based and uses
`font-sans`; changing it restyles the admin by accident.

### 5.5 `resources/css/app.css`

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

[x-cloak] { display: none !important; }

@layer base {
  html { scroll-behavior: smooth; }
  .site [id] { scroll-margin-top: 5.5rem; }     /* clears the sticky header */
  .site :focus-visible {                        /* gold ring, readable on either surface */
    outline: 2px solid rgb(var(--c-gold)); outline-offset: 3px; border-radius: 3px;
  }
}

@layer components {
  .glass { /* gradient of --glass-top/--glass-bottom + --glass-border + --glass-shadow + blur */ }
  .glass-interactive { transition: border-color .2s, transform .2s }
  .glass-interactive:hover { border-color: rgba(148,173,255,.3); transform: translateY(-2px) }
  .graticule { /* two linear-gradients of --graticule-line, 3.5rem × 4.5rem cells */ }
}

@layer utilities { .tabular { font-variant-numeric: tabular-nums } }

/* Hero animation */
@keyframes candle-rise { from { transform: scaleY(0); opacity: 0 } to { … } }
@keyframes level-draw  { from { stroke-dashoffset: 760 } to { stroke-dashoffset: 0 } }
@keyframes soft-in     { from { opacity: 0 } to { opacity: 1 } }
.candle { transform-box: fill-box; transform-origin: bottom;
          animation: candle-rise .5s cubic-bezier(.2,.8,.2,1) both }
.level  { stroke-dasharray: 760; animation: level-draw 1.8s ease-out .9s both }
.soft-in { animation: soft-in .6s ease-out both }

@media (prefers-reduced-motion: reduce) {
  html { scroll-behavior: auto }
  .candle, .level, .soft-in { animation: none }
  .level { stroke-dasharray: none }
  .glass-interactive:hover { transform: none }
}
```

### 5.6 Root `.htaccess` (project root, **not** `public/`)

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /skin-care/
    RewriteRule ^public/ - [L]
    RewriteRule ^(.*)$ public/$1 [R=302,L]
</IfModule>

<FilesMatch "^(\.env.*|composer\.(json|lock)|package(-lock)?\.json|artisan)$">
    Require all denied
</FilesMatch>

Options -Indexes
```

See §13.1 for **why this is a redirect and not an internal rewrite**.

### 5.7 `phpunit.xml` — one added line

```xml
<env name="APP_URL" value="http://localhost"/>
```

Without it, 22 tests fail with 404s. See §13.2.

---

## 6. Database schema

Seven app tables (plus Laravel's `cache`, `jobs`, `sessions`,
`password_reset_tokens`, `migrations`).

### Migration order matters

```
0001_01_01_000000_create_users_table      (Breeze + the `role` column added inline)
2026_10_10_160203_create_categories_table
2026_10_10_160204_create_videos_table     (FK → categories)
2026_10_10_160205_create_orders_table     (FK → users, videos)
2026_10_10_160206_create_video_user_table (FK → users, videos)
2026_10_10_171314_create_video_views_table(FK → users, videos)
2026_10_10_174336_add_progress_seconds_to_video_views_table
2026_10_10_180638_add_duration_seconds_to_videos_table
2026_10_10_180638_add_completed_at_to_video_views_table
2026_10_10_180638_create_coupons_table
2026_10_10_180639_add_coupon_to_orders_table     (FK → coupons)
2026_10_11_041538_add_is_active_to_coupons_table
2026_10_11_041538_create_certificates_table      (FK → users, categories)
2026_10_11_193000_add_status_index_to_orders_table
```

> ⚠️ `artisan make:migration` can hand `orders` and `videos` the **same
> timestamp**, and Laravel sorts alphabetically within a timestamp — `orders`
> would then run before `videos` and the foreign key fails. Rename the files so
> timestamps strictly increase in dependency order.

### 6.1 `users`

```sql
id              bigint unsigned PK
name            varchar(255)
email           varchar(255) UNIQUE
email_verified_at timestamp NULL
password        varchar(255)
role            enum('admin','user') NOT NULL DEFAULT 'user'   -- indexed
remember_token  varchar(100) NULL
timestamps
```

The `role` column is added **inside the stock Breeze users migration**, not as a
separate migration (the table had never shipped).

### 6.2 `categories`

```sql
id    bigint unsigned PK
name  varchar(255)
slug  varchar(255) UNIQUE
timestamps
```

### 6.3 `videos`

```sql
id          bigint unsigned PK
category_id bigint unsigned  FK → categories(id) ON DELETE CASCADE
title       varchar(255)
slug        varchar(255) UNIQUE
description text NULL
thumbnail   varchar(255) NULL        -- path on the `public` disk
video_path  varchar(255)             -- path on the `local` disk OR an http(s) URL
duration_seconds int unsigned NULL   -- admin-entered; drives the 90% completion rule
price       decimal(10,2) DEFAULT 0.00
is_free     boolean DEFAULT false
status      enum('published','draft') DEFAULT 'draft'
timestamps
INDEX (status, is_free)
```

`video_path` is **polymorphic by convention**: if it starts with `http://` or
`https://` it is an external embed, otherwise it is a file on the private disk.
`Video::isExternalVideo()` is the single place that decides.

### 6.4 `orders`

```sql
id                  bigint unsigned PK
user_id             FK → users(id)  ON DELETE CASCADE
video_id            FK → videos(id) ON DELETE CASCADE
coupon_id           FK → coupons(id) NULL ON DELETE SET NULL
razorpay_order_id   varchar(255) NULL UNIQUE
razorpay_payment_id varchar(255) NULL  INDEX
amount              decimal(10,2)            -- what was actually charged
discount_amount     decimal(10,2) DEFAULT 0  -- knocked off the list price
status              enum('pending','success','failed') DEFAULT 'pending'
timestamps
INDEX (user_id, status)
INDEX (status, created_at, amount)   -- the admin list's default query
```

> The second index is covering on purpose. The admin orders screen filters by
> status, sorts by date and sums `amount`; the `(user_id, status)` index cannot
> serve that, so it was a full scan plus a filesort. `amount` sits third so the
> revenue figure is answered from the index alone.

### 6.5 `video_user` — **paid access only**

```sql
id       bigint unsigned PK
user_id  FK → users(id)  ON DELETE CASCADE
video_id FK → videos(id) ON DELETE CASCADE
timestamps
UNIQUE (user_id, video_id)
```

A row here means "this user owns this video, forever". Nothing else writes here.

### 6.6 `video_views` — **watch history, deliberately separate**

```sql
id               bigint unsigned PK
user_id          FK → users(id)  ON DELETE CASCADE
video_id         FK → videos(id) ON DELETE CASCADE
progress_seconds int unsigned DEFAULT 0   -- playhead, clamped to the video duration
completed_at     timestamp NULL           -- set at 90%; sticky, never cleared
timestamps                                -- created_at = first opened, updated_at = last opened
UNIQUE (user_id, video_id)
```

> **Do not merge these two tables.** Watching a free lesson must never read as a
> purchase. A test asserts exactly this (`test_a_watch_is_not_a_purchase`).

### 6.7 `coupons`

```sql
id               bigint unsigned PK
code             varchar(255) UNIQUE   -- stored upper-case
discount_percent tinyint unsigned      -- 1..100
is_active        boolean DEFAULT true  -- the admin's pause switch
max_uses         int unsigned NULL     -- null = unlimited
used_count       int unsigned DEFAULT 0
expires_at       timestamp NULL        -- null = never
timestamps
```

`used_count` rises only when an order is **fulfilled**, never when a checkout is
merely opened, so abandoned carts do not burn a limited coupon. `is_active` is
separate from expiry and the usage cap so an admin can pull a live coupon
without rewriting its dates — `statusLabel()` returns Live / Paused / Expired /
Used up.

### 6.8 `certificates`

```sql
id            bigint unsigned PK
user_id       FK → users(id)      ON DELETE CASCADE
category_id   FK → categories(id) ON DELETE CASCADE
reference     varchar(255) UNIQUE   -- TPA-{cat:02d}-{user:05d}-{8 hex HMAC}
lessons_count int unsigned          -- lessons in the category when issued
completed_at  timestamp             -- the last lesson's completion
timestamps                          -- created_at = issued
UNIQUE (user_id, category_id)
```

Issued once, then permanent — see §13.4c.

---

## 7. Domain layer

### 7.1 Enums (`app/Enums/`) — native PHP backed enums, cast on the models

| Enum | Cases | Used by |
|---|---|---|
| `UserRole` | `Admin='admin'`, `User='user'` | `User::$role` |
| `VideoStatus` | `Published='published'`, `Draft='draft'` | `Video::$status` |
| `OrderStatus` | `Pending`, `Success`, `Failed` | `Order::$status` |

Each has a `label(): string` for display.

### 7.2 `app/Models/Concerns/HasUniqueSlug.php`

```php
static::uniqueSlug(string $value, ?int $ignoreId = null): string
```

Slugifies, then appends `-2`, `-3`… until unique. Used by `Category` and `Video`
so admins never see a "slug already taken" error. Slugs are only regenerated
when the name/title actually changed (`$model->isDirty('title')`).

### 7.3 Models

**`User`** (`fillable`: name, email, password, role)
```php
casts: email_verified_at => datetime, password => hashed, role => UserRole::class

orders(): HasMany<Order>
purchasedVideos(): BelongsToMany<Video>   // via video_user, withTimestamps
watchedVideos():  BelongsToMany<Video>    // via video_views, withPivot('progress_seconds')
isAdmin(): bool
hasPurchased(Video): bool
recordWatch(Video): void                   // syncWithoutDetaching + touch updated_at
recordProgress(Video, int $seconds): void  // clamps, and decides completion
progressFor(Video): int
hasCompleted(Video): bool
resumePositionFor(Video): int              // 0 once the lesson is complete
hasCompletedCategory(Category): bool       // every PUBLISHED lesson completed
completedCountIn(Category): int
categoryCompletedAt(Category): ?Carbon     // the certificate date
```

**`Category`** (`fillable`: name, slug) — `use HasUniqueSlug`
```php
videos(): HasMany<Video>
getRouteKeyName() => 'slug'
```

**`Video`** (`fillable`: category_id, title, slug, description, thumbnail,
video_path, price, is_free, status) — `use HasUniqueSlug`
```php
casts: price => decimal:2, is_free => boolean, status => VideoStatus::class

category(): BelongsTo<Category>
orders():   HasMany<Order>
buyers():   BelongsToMany<User>   // video_user
viewers():  BelongsToMany<User>   // video_views

scopePublished() / scopeFree() / scopePaid()
isPublished(): bool
isExternalVideo(): bool            // remote URL rather than a file on our disk
isEmbeddedVideo(): bool            // remote AND not a direct media file -> needs an <iframe>
embedUrl(): string                 // VideoEmbed::resolve(video_path) -- what the iframe gets
playbackUrl(): string              // <video> source: the stream route, or the remote file
hasPlaceholderLink(): bool         // points at a reserved domain, so it cannot ever play
completionThreshold(): ?int        // ceil(duration_seconds * 0.9), null without a duration
thumbnailUrl(): ?string            // Storage::disk('public')->url(...)
isAccessibleBy(?User): bool        // free → true; else admin or hasPurchased
getRouteKeyName() => 'slug'
```

**`Order`** (`fillable`: user_id, video_id, razorpay_order_id,
razorpay_payment_id, amount, status)
```php
casts: amount => decimal:2, status => OrderStatus::class

user(): BelongsTo<User>
video(): BelongsTo<Video>
scopeSuccessful()
isPaid(): bool
amountInPaise(): int               // (int) round(amount * 100) — Razorpay bills in paise
coupon(): BelongsTo<Coupon>
```

**`Coupon`** (`fillable`: code, discount_percent, max_uses, used_count, expires_at)
```php
static normaliseCode(string): string   // trim + upper
static findByCode(string): ?Coupon
isExpired() / isExhausted() / isUsable(): bool
scopeUsable()
discountOn(float $price): float        // rupees off, rounded to paise
```

### 7.4a `app/Support/VideoEmbed.php` — making a pasted link playable

Every provider serves two URLs for one video: the **page** a human visits and
the **frame** another site may embed. The page form answers
`X-Frame-Options: SAMEORIGIN`, so putting it in an `<iframe>` renders an empty
box and reports nothing. Admins paste what their address bar shows, which is
always the page form.

```php
VideoEmbed::isRemote(?string): bool        // http(s) scheme
VideoEmbed::isDirectMedia(?string): bool   // path ends .mp4/.webm/.ogg/.ogv/.mov/.m4v
VideoEmbed::resolve(?string): string       // the embeddable form, or the URL untouched
VideoEmbed::wasRewritten(?string): bool    // resolve() changed it -- shown in the admin form
VideoEmbed::isPlaceholder(?string): bool   // an RFC 2606 reserved domain: can never resolve
```

`resolve()` maps:

| Pasted | Framed as |
|---|---|
| `youtube.com/watch?v=ID`, `youtu.be/ID`, `/shorts/ID`, `/live/ID`, `m.youtube.com` | `youtube.com/embed/ID` |
| `…&t=90s`, `…&t=1m30s`, `…&start=90` | `…/embed/ID?start=90` (the player only reads `start`, in seconds) |
| `…&list=PL…` | `…/embed/ID?list=PL…` |
| `vimeo.com/ID`, `vimeo.com/ID/HASH` | `player.vimeo.com/video/ID[?h=HASH]` |
| `drive.google.com/file/d/ID/view` | `drive.google.com/file/d/ID/preview` |
| anything else | **unchanged** |

Two rules that matter:

- **Unrecognised hosts pass through untouched.** A host we have no mapping for
  may already be a working embed; guessing would break links that work. A
  YouTube id that is not the documented 11 characters is also left alone.
- **Normalisation happens at render, never on save.** The column keeps exactly
  what the admin pasted, which is what makes the "Open this lesson at the
  source" fallback honest and lets the admin recognise their own link. The
  edit form shows the resolved URL back to them (`wasRewritten()`).

### 7.4 The one access rule

```php
Video::isAccessibleBy(?User $user): bool
{
    if ($this->is_free)      return true;    // free is free, even for guests
    if (! $user)             return false;
    return $user->isAdmin() || $user->hasPurchased($this);
}
```

Every gate in the app (page, player, stream route) goes through this.

---

## 8. HTTP layer

### 8.1 Middleware

`app/Http/Middleware/EnsureUserIsAdmin.php` — redirects guests to `login`,
`abort(403)` for non-admins. Registered in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias(['admin' => \App\Http\Middleware\EnsureUserIsAdmin::class]);
})
```

### 8.2 Routes (`routes/web.php`) — 51 entries in `route:list --except-vendor`,
of which the table below is the application's own; the rest are Breeze's

| Method | URI | Name | Controller | Middleware |
|---|---|---|---|---|
| GET | `/` | `home` | `LandingController` | — |
| GET | `/videos/{video}` | `videos.show` | `PublicVideoController@show` | — |
| GET | `/stream/{video}/{token}` | `videos.stream` | `VideoStreamController` | — + signed & viewer-bound, see 13.12 |
| GET | `/csrf-token` | `csrf-token` | closure → `{"token": …}` | — |
| GET | `/verify/{reference}` | `certificates.verify` | `CertificateVerificationController` | **none** + `throttle:verification` |
| POST | `/videos/{video}/purchase` | `razorpay.order` | `Api\RazorpayController@store` | auth + `throttle:checkout` |
| POST | `/payment-success` | `payment.success` | `CheckoutController@success` | auth |
| POST | `/payment-failed` | `payment.failed` | `CheckoutController@failed` | auth |
| POST | `/videos/{video}/progress` | `videos.progress` | `VideoProgressController` | auth + `throttle:progress` |
| GET | `/certificates/{category}` | `certificates.download` | `CertificateController` | auth |
| POST | `/razorpay/webhook` | `razorpay.webhook` | `Api\RazorpayWebhookController` | **none** (CSRF-exempt) |
| GET | `/dashboard` | `dashboard` | `DashboardController` | auth, verified |
| GET/PATCH/DELETE | `/profile` | `profile.*` | `ProfileController` | auth |
| GET | `/admin` | `admin.dashboard` | `Admin\DashboardController` | auth, admin |
| resource | `/admin/categories` | `admin.categories.*` | `Admin\CategoryController` | auth, admin |
| resource | `/admin/videos` | `admin.videos.*` | `Admin\VideoController` | auth, admin |
| resource | `/admin/coupons` | `admin.coupons.*` | `Admin\CouponController` | auth, admin |
| PATCH | `/admin/coupons/{coupon}/toggle` | `admin.coupons.toggle` | `Admin\CouponController@toggle` | auth, admin |
| GET | `/admin/orders` | `admin.orders.index` | `Admin\OrderController@index` | auth, admin |

Both resources use `->except('show')`. `{video}` and `{category}` bind by
**slug** (`getRouteKeyName`). Breeze's `routes/auth.php` is required at the end.

### 8.3 Public controllers

**`LandingController`** (invokable) → `welcome` view.
Published videos only, `?category=<slug>` and `?free=1` filters, `orderByDesc('is_free')`
then `latest()`, paginate 9 with `->fragment('courses')`. Also passes `categories`
(only those with published videos, with counts), `freeCount`, and `ownedIds`
(pre-resolved so cards avoid an N+1).

**`PublicVideoController@show`** → `videos.show`.
- `abort_unless($video->isPublished() || $user?->isAdmin(), 404)` — drafts are admin-only previews.
- Computes `$hasAccess` once.
- If signed in **and** has access **and** published → `$user->recordWatch($video)`.
- Passes `related` (3 published siblings in the same category).

**`VideoStreamController`** (invokable) → `BinaryFileResponse`.
- 404 if draft (non-admin), **403** if not accessible, 404 if external, 404 if the file is gone.
- `response()->file(Storage::disk('local')->path($video->video_path), [...])`.
- `BinaryFileResponse` answers **Range requests**, so seeking works in the player.

**`DashboardController`** (invokable) → `dashboard`.
"Enrolled" = purchased ∪ watched, published only. Filters: `all` | `purchased` | `free`.
Ordering uses two correlated subqueries aliased as `last_watched_at` /
`purchased_at`, then `orderByRaw('COALESCE(last_watched_at, purchased_at) DESC')`
(works on both MySQL and SQLite). Also returns `purchasedCount`, `watchedCount`,
`spent`, `ownedIds`.

### 8.4 Checkout controllers

**`Api\RazorpayController@store`** — JSON only.

Guards, in order: `abort_unless(published, 404)` → free video `422` → already
owned `409` → gateway unconfigured `503` → **coupon invalid `422`**. Then it
reuses the user's open pending order (or creates one), saves it, calls
`createOrder()`, and on a gateway exception returns `502` leaving
`razorpay_order_id` null.

**Coupons.** The request may carry `coupon_code` and nothing else about money.
The code is trimmed and upper-cased, rejected with 422 if unknown, paused,
expired or exhausted, and the arithmetic happens here:
`payable = max(1.00, round(price - price * percent / 100, 2))` — ₹1 because
Razorpay will not accept a smaller order. `amount`, `discount_amount` and
`coupon_id` are all written to the order row. Returns:

```json
{ "key": "...", "order_id": "order_...", "amount": 149900, "currency": "INR",
  "name": "Traders Point Academy", "description": "<video title>",
  "prefill": { "name": "...", "email": "..." },
  "notes": { "video": "<slug>" }, "theme": { "color": "#E8B339" },
  "coupon": null | { "code", "discount_percent", "discount_amount", "list_price" } }
```

> **The amount is always read from `videos.price`.** Nothing from the request
> influences it. A test posts `{"amount":1,"price":1}` and asserts ₹2000 is still
> charged.

**`CheckoutController@success`** — the `payment-success` route.

1. Validate the three `razorpay_*` fields.
2. Look up the order **by `razorpay_order_id` AND `user_id`** (so one user cannot claim another's order).
3. If already `success` → idempotent redirect, no second grant.
4. `RazorpayGateway::verifyPayment()`; on failure mark the order `failed` and redirect back with an error — **no access granted**.
5. On success, inside a `DB::transaction`: set `status = success` + `razorpay_payment_id`, then `purchasedVideos()->syncWithoutDetaching([...])`.
6. Redirect to `dashboard` with a `success` flash.

**`CheckoutController@failed`** — marks a **pending** order `failed` (never
downgrades a `success`), so declined cards don't sit as "pending" in the admin forever.

**`Api\RazorpayWebhookController`** (invokable) — the server-to-server safety net.

Unauthenticated and CSRF-exempt; the signature is the only authentication.
Order of checks: webhook secret configured (`503`) → `verifyWebhook()` over the
**raw body** (`400`) → event is `payment.captured` (otherwise ack and ignore) →
`order_id` and payment id present → order exists → **captured amount ≥ the order
amount** → `FulfilPaidOrder`.

Anything it cannot act on is answered **200 and logged**, because a non-2xx makes
Razorpay retry forever for something a retry will never fix.

**`VideoProgressController`** (invokable) — the playback beacon. 404 for drafts,
**403** unless `isAccessibleBy`, validates `progress_seconds` (integer, 0–86400),
calls `User::recordProgress()`, answers **204** with no body.

**`CertificateController`** (invokable) — the PDF download. 404 when the
category has no published lessons. If a `Certificate` row already exists it is
reused; otherwise `hasCompletedCategory()` is re-checked (403) and
`Certificate::issueFor()` mints one. Then `Pdf::loadView(...)
->setPaper('a4', 'landscape')->download()`.

**`CertificateVerificationController`** (invokable, public) — looks a reference
up (upper-cased, trimmed) and renders `certificates.verify` either way.
Answers **200 even when nothing is found**: "we have no record of this" is a
legitimate answer to a legitimate question, not an error. Throttled, because
this is the one endpoint that confirms whether a reference exists.

**`Admin\CouponController`** — resource CRUD plus a `toggle` action.
- `index` lists coupons with real usage: `withCount` of **successful** orders
  and `withSum` of `discount_amount` and `amount`, so an abandoned checkout
  never inflates the figures. Searchable by code.
- `toggle` flips `is_active` without touching dates.
- `destroy` **refuses** a coupon that appears on any order (`coupon_id` is
  `nullOnDelete`, so deleting would quietly erase what was discounted) and
  tells the admin to pause it instead.

### 8.4a `App\Actions\FulfilPaidOrder`

The single place that turns a paid order into access. Both
`CheckoutController@success` and the webhook call it, and either may arrive first
or twice, so the whole thing runs inside `DB::transaction` behind a
`lockForUpdate()` re-read:

```php
__invoke(Order $order, string $paymentId): bool   // true when THIS call granted access
```

If the locked row is already `success` it returns `false` and changes nothing.

When it *does* grant, it increments the coupon's `used_count` (inside the
transaction) and then — **outside** it, so the job is never picked up before the
order is committed — queues a `PurchaseReceipt`. A receipt that cannot be queued
is reported, never thrown: nobody loses access they paid for because the mail
server was unreachable.

### 8.5 Admin controllers

**`Admin\DashboardController`** — `totalUsers` (role=user only), `totalVideos`,
`totalOrders` (all statuses), `totalRevenue` (successful only), `recentOrders` (5).

**`Admin\CategoryController`** — index (search + `withCount('videos')` + paginate 10),
create/store/edit/update/destroy.
**Refuses to delete a category that still has videos** (the FK cascades and would
wipe the catalogue) → redirect with an `error` flash.

**`Admin\VideoController`** — index (search over title, description and category
name; paginate 10), full CRUD.
- Uploaded videos → `local` disk (private, outside the web root), path `videos/…`.
- Thumbnails → `public` disk, path `thumbnails/…`.
- `resolveVideoPath()` returns the URL for `video_source=link`, a newly stored
  file for `video_source=upload`, or the existing path when the file input is empty.
- Replacing or switching away from an upload deletes the old file.
- **Refuses to delete a video that has successful orders** (would destroy payment
  records) → suggests setting it to draft instead.

**`Admin\OrderController@index`** — status filter defaulting to `success`
(plus `pending`, `failed`, `all`), search over user name/email, video title, and
both Razorpay ids. Paginate 15. Also returns lifetime `revenue`.

### 8.6 Form requests (`app/Http/Requests/Admin/`)

`StoreCategoryRequest` / `UpdateCategoryRequest` — `name` required, unique
(ignoring self on update).

`StoreVideoRequest` — constants `VIDEO_MIMETYPES`
(`video/mp4,webm,quicktime,x-msvideo,x-matroska`), `MAX_VIDEO_KB = 122880`
(120 MB, under `post_max_size`), `MAX_THUMBNAIL_KB = 2048`.

`prepareForValidation()` normalises the unchecked checkbox (`is_free` →
`boolean`) and **forces `price = 0` when the video is free**.

Rules: `title`, `category_id` (exists), `description` ≤ 5000, `status`
(`Rule::enum`), `is_free` boolean, `price` required when paid with `min:1`
(Razorpay's floor), `video_source` in `upload|link`, `video_url` required when
`link`, `video_file` required when `upload`, `thumbnail` image ≤ 2 MB.

`UpdateVideoRequest extends StoreVideoRequest` and only overrides `video_file`:
required **only** when switching to `upload` while the current record is
external (i.e. there is no stored file to keep).

### 8.7 Admin write UX

Six things sit between an admin and a lost afternoon.

**Double-submit guard.** Every admin form carries `x-data="adminForm"` (or
`adminUpload`) and every submit goes through `<x-admin.submit-button>`, which
binds `:disabled="submitting"`, swaps the label for "Saving…" and shows a
spinner. A second click cannot fire a second request.

**Validation feedback, twice over.** `<x-admin.form-errors>` prints a counted
summary at the top (so a failure below the fold is still obvious) and
`<x-admin.field-error>` prints red helper text under each input. The field
component renders **both** sources: messages Laravel flashed on a normal round
trip, and messages the XHR upload mapped back without reloading.

**Upload progress.** The video form posts via `XMLHttpRequest` rather than a
native submit, so `upload.onprogress` can drive a real percentage bar. Three
things fall out of that:

- Validation errors arrive as JSON and are shown **without reloading**, so
  everything already typed into the other fields survives.
- A file larger than the server limit is caught in the browser first. Left to
  PHP, an oversize POST is truncated, the CSRF token vanishes with it, and the
  admin gets a baffling "page expired".
- `VideoController::store/update` use `RedirectsOrJson::finish()`. An XHR must
  **not** be handed a 302 — XMLHttpRequest follows redirects itself, which
  would consume the success flash on a request nobody sees. It gets
  `{"redirect": "…"}` instead and the browser navigates.

**No form can be blocked by a field the admin cannot see.** Every branch the
video form hides is also `disabled`, which excludes it from HTML constraint
validation *and* from the payload. See §13.10 — this is the bug that made the
edit form look completely dead.

**A blocked submit always says so.** Each admin form carries
`x-on:invalid.capture="reportBlocked($event)"`. `invalid` fires even for a
control the browser refuses to focus, and it does **not** bubble, hence
`.capture` on the form. One batch of invalid events is collected in a
`setTimeout(…, 0)` — they all dispatch inside one task — and summarised into
the existing `failure` banner, naming any field that is hidden. With the
`disabled` fix above this should never fire for an invisible control; it is the
net under that, because a silently dead button is the one failure an admin
cannot report usefully.

**Confirmations.** `<x-admin.confirm-action>` replaces every `window.confirm()`
— used for deleting a category, a video or a coupon, and for pausing/resuming a
coupon. It renders a real `<form>` with CSRF and method spoofing inside a
dialog that is `x-teleport`-ed to `<body>`, because every admin table sits in an
`overflow-x-auto` wrapper that would otherwise clip it.

---

## 9. Services

### `app/Services/RazorpayGateway.php`

The only class that touches the Razorpay SDK. Bound as a singleton in
`AppServiceProvider::register()` from `config('services.razorpay.*')`.

```php
isConfigured(): bool        // filled(key) && filled(secret)
key(): string
currency(): string
createOrder(int $amountInPaise, string $receipt, array $notes = []): string
verifyPayment(string $orderId, string $paymentId, string $signature): bool

isWebhookConfigured(): bool                                // RAZORPAY_WEBHOOK_SECRET set?
verifyWebhook(string $rawBody, ?string $signature): bool   // HMAC over the RAW body
```

`verifyWebhook()` instantiates `Razorpay\Api\Utility` directly —
`verifyWebhookSignature()` takes the secret as an argument, so it needs no API
keys and makes no network call.

`createOrder()` is the **only** method that needs the network — tests mock just
that one. `verifyPayment()` is pure HMAC
(`hash_hmac('sha256', "{$orderId}|{$paymentId}", $secret)`), so tests exercise
the **real** implementation by computing a signature locally. `api()` throws a
`RuntimeException` naming `RAZORPAY_KEY` when keys are missing.

### 9.2 Mail — `App\Mail\PurchaseReceipt`

`Mailable implements ShouldQueue`, so `Mail::to($user)->send(...)` pushes a job
rather than blocking the request that just unlocked a purchase. Rendered from
`resources/views/emails/purchase-receipt.blade.php` — **table-based HTML with
inline styles**, because email clients are not browsers (no Tailwind, no
flexbox, no external CSS).

Subject: `Your receipt for "<video title>"`. Body: lesson, category, list price
and discount (only when one was applied), paid-on, Razorpay payment id, total,
and buttons to the lesson and the dashboard.

In development `MAIL_MAILER=log`, so the rendered email lands in
`storage/logs/laravel.log`. `QUEUE_CONNECTION=database`, so it sits in the
`jobs` table until `php artisan queue:work` runs.

### 9.3 Rate limiting

Named limiters are declared in `AppServiceProvider::boot()` and applied as
route middleware. All are keyed **per signed-in user, falling back to IP**, so
one abusive account cannot lock out everyone behind the same address.

| Limiter | Limit | Applied to | Why |
|---|---|---|---|
| `checkout` | 10/min | `razorpay.order` | Writes an order row and calls Razorpay |
| `progress` | 60/min | `videos.progress` | Saves every ~15s; 60 leaves room for several tabs |
| `verification` | 20/min (per IP) | `certificates.verify` | Public, and the only endpoint confirming a reference exists |

The webhook is deliberately **not** throttled — Razorpay retries, and dropping
a legitimate delivery is worse than the traffic.

---

## 10. Frontend

### 10.1 Two surfaces, two themes — on purpose

| Surface | Layout | Base theme | Fonts |
|---|---|---|---|
| Public + learner (`/`, `/videos/*`, `/dashboard`, `/profile`) | `layouts/public.blade.php` via `<x-public-layout>` | Dark "trading terminal" | Sora / DM Sans / IBM Plex Mono |
| Admin (`/admin/*`) | `layouts/app.blade.php` via `<x-app-layout>` (Breeze) | Light | Figtree |
| Auth screens | `layouts/guest.blade.php` (Breeze) | Light | Figtree |

All three support **both** themes — see §10.7. "Base theme" is simply the one
each surface was designed around first.

`app/View/Components/PublicLayout.php` declares `public ?string $title` and
`public ?string $description` as constructor properties — **required**, or
`<x-public-layout :title="...">` lands in `$attributes` and every page shows only
the brand name in `<title>`.

Fonts load from Bunny Fonts:
`https://fonts.bunny.net/css?family=sora:600,700|dm-sans:400,500|ibm-plex-mono:400,500&display=swap`

### 10.2 Design rules

- **Colour encodes the business model:** `bull` green = free/open/success,
  `gold` amber = paid/price, `bear` red = failure/down-candle. Never use gold for
  a generic CTA — it means "money".
- **Every number is monospaced** (`font-data` + `.tabular`): prices, stats,
  counts, dates, Razorpay ids. Prose is `font-body`, headings `font-display`.
- **Eyebrow labels**: `font-data text-[0.65–0.7rem] uppercase tracking-eyebrow text-muted`.
- One surface: `.glass` panels on the `ink` background.
- Headings use `text-balance` and `clamp()` sizes.

### 10.3 Blade components

**Public (dark)**
| Component | Purpose |
|---|---|
| `x-public-layout` | Dark shell: sticky header, `<x-toast>`, `<main>`, footer with the "not investment advice" disclaimer, `@stack('scripts')` |
| `x-hero-chart` | The signature. A seeded 30-candle OHLC SVG: sell-off → support → recovery, candles animate up in sequence, gold support line drawn last. Price axis hidden below `lg`. |
| `x-chart-thumb` | Fallback card art when a video has no thumbnail: a candlestick sparkline seeded from `crc32($video->slug)`, tinted green (free) or gold (paid) |
| `x-video-card` | Catalogue card — whole card is one `<a>` |
| `x-enrolled-card` | Dashboard card — `<article>` with a separate **Watch** button (valid HTML; no button inside an anchor) |
| `x-toast` | Dark toast reading `success` / `error` / `info`, `role="status"`, auto-dismiss 9 s |
| `x-dark-label` / `x-dark-input` / `x-dark-error` / `x-dark-button` | Dark form controls (profile pages). `x-dark-button` has `tone="primary\|ghost\|danger"` |

**Admin (light)**
`x-admin.nav`, `x-admin.stat-card`, `x-admin.badge` (tone gray/green/amber/red/blue),
`x-admin.toast` (light counterpart of `x-toast`).

**Breeze originals** (`x-text-input`, `x-primary-button`, `x-modal`, …) are used
**only** by the admin and auth screens. Do not restyle them — the dark pages have
their own `x-dark-*` set precisely so the admin stays untouched.

**`resources/views/certificates/category.blade.php`** is rendered by DomPDF,
**not a browser**: no Tailwind, no flexbox, no grid, no remote fonts. Tables and
block elements with a `<style>` block, and DejaVu Sans because that is the font
DomPDF ships. See §13.8 for the one-page trap.

**Pagination:** `resources/views/vendor/pagination/dark.blade.php`, called as
`$paginator->links('vendor.pagination.dark')` on dark pages. Admin pages use
Laravel's default.

### 10.4 JavaScript

`resources/js/app.js`
```js
import './bootstrap';
import Alpine from 'alpinejs';
import razorpayCheckout from './razorpay-checkout';
import videoProgress from './video-progress';
window.Alpine = Alpine;
Alpine.store('theme', theme);
Alpine.data('adminForm', adminForm);
Alpine.data('adminUpload', adminUpload);
Alpine.data('playerGuard', playerGuard);
Alpine.data('razorpayCheckout', razorpayCheckout);
Alpine.data('videoProgress', videoProgress);
Alpine.start();
```

`resources/js/razorpay-checkout.js` — the Alpine component behind the Buy button:
1. `POST` to `orderUrl` with the CSRF token from `<meta name="csrf-token">`.
2. On a non-2xx, show `body.message` inline (this is how the 503/409/502 messages surface).
3. `new window.Razorpay({...options, handler, modal.ondismiss})`, plus
   `checkout.on('payment.failed', …)`.
4. The handler builds a hidden form and POSTs the signed result to
   `payment-success` (or `payment-failed`) so Laravel can redirect normally.

Razorpay's `checkout.js` is loaded **only on pages that can open it**, via
`@push('scripts')` inside `@auth @unless($hasAccess)` on the video page.

`resources/js/admin-form.js` — two Alpine components behind every admin form:

- `adminForm` — the plain case: a double-submit guard over a native submit.
- `adminUpload` — replaces the submit with XHR so a 120 MB upload reports real
  progress and validation errors arrive without discarding the form.

Both are built from a `baseState()` **factory**. It must not become a shared
object literal: a spread would hand every component the same `errors`
reference, so one form's errors would appear in another's fields. A spread also
*evaluates* getters rather than copying them, so anything derived from state
belongs here as a plain method, never `get thing()`.

`adminUpload` additionally handles:

- **419** — rejects with `{expired: true}`, then `renewSession()` fetches
  `/csrf-token`, writes the new value into this form's `_token` (scoped to the
  form: the page also carries logout tokens) and the `<meta>`, and says the
  retry is now safe. Previously the message said "try again" while the DOM
  still held the dead token, so every retry 419'd identically — a dead end.
- **Disabled inputs** — a disabled file input keeps its `files` but is left out
  of `FormData`, so `pickedFile()` skips it. An abandoned pick must not be
  size-checked, nor shown as uploading.

`resources/js/video-progress.js` — remembers the playhead on the native player:

- `loadedmetadata` → seek to `resumeAt`, but only if it is > 10 s **and** more
  than 15 s short of the end; otherwise start from the top.
- `timeupdate` → save when the position has moved >= 15 s in either direction
  (so scrubbing backwards is captured too).
- `pause` → save. `ended` → save **0**, which is how "watched to the end" is
  recorded without storing a duration anywhere.
- `pagehide` / `visibilitychange -> hidden` → `navigator.sendBeacon` with a
  `FormData` carrying `_token`, because sendBeacon cannot set a CSRF header.
  Ordinary saves use `fetch(..., { keepalive: true })` with the header.

The component only renders when the viewer is **signed in**, the video is
accessible, and it is **not** an embedded player (`isEmbeddedVideo()` — a
cross-origin iframe's position is unreadable). A guest would otherwise fire
beacons that only bounce off the auth wall. Note the test is *embedded*, not
*external*: a remote `.mp4` plays in our own `<video>` element and therefore
does track progress.

### 10.4a Dark / light theming

One switch, two strategies, because the two surfaces start from opposite ends.

**Public site — tokens flip, no `dark:` anywhere.** Every colour is a CSS
variable (§5.4/§5.5), so `bg-ink` means "the page surface" in both themes and
the entire site re-themes from one `.dark` block. SVG colours use Tailwind
`fill-*` / `stroke-*` utilities for the same reason — there is no hardcoded hex
left in the hero chart, the card sparklines or the logo mark.

> Tailwind only generates classes it can **see as literals**. `class="fill-{{ $tone }}"`
> would silently produce nothing, so the chart components build the whole class
> name in PHP (`$fillClass = $isBull ? 'fill-bull' : 'fill-bear';`).

**Admin — ordinary `dark:` variants** on Breeze's gray scale, mapped to slate:

| light | dark | | light | dark |
|---|---|---|---|---|
| `bg-gray-100` (page) | `dark:bg-slate-950` | | `border-gray-200` | `dark:border-slate-800` |
| `bg-white` (panel) | `dark:bg-slate-900` | | `border-gray-300` | `dark:border-slate-700` |
| `bg-gray-50` (thead) | `dark:bg-slate-800/60` | | `divide-gray-200` | `dark:divide-slate-800` |
| `text-gray-900/800` | `dark:text-slate-100` | | `text-indigo-600` | `dark:text-indigo-400` |
| `text-gray-700` | `dark:text-slate-300` | | `bg-gray-800` (button) | `dark:bg-slate-100 dark:text-slate-900` |
| `text-gray-600/500` | `dark:text-slate-400` | | `text-red-600` | `dark:text-red-400` |

Covered: `layouts/app`, `layouts/guest`, `layouts/navigation`, every Breeze
component (input, label, error, buttons, dropdown, modal, nav links), every
admin view and `components/admin/*`, plus the published Tailwind paginator.

**Flash prevention.** `resources/views/partials/theme-script.blade.php` is an
inline, synchronous script included in all three layouts **above the first
stylesheet** — it reads `localStorage.theme` and sets the class before the first
paint. A test asserts that ordering.

No stored choice falls back to **dark**: the public site is designed dark and
that is the intended first impression. To follow the OS instead, change the one
`var dark = …` line to read `matchMedia('(prefers-color-scheme: dark)').matches`.

**State.** `Alpine.store('theme')` (`resources/js/theme.js`) reads the class
back on boot and, on `toggle()`, flips `<html>.dark`, sets
`documentElement.style.colorScheme` and writes `localStorage.theme` inside a
try/catch (private browsing still toggles, just without persisting).

**Toggle.** `<x-theme-toggle />` — a real `<button>` with a sun/moon icon and an
aria-label that follows the state. `surface="admin"` picks the gray/slate
styling; the default rides on the public tokens. It sits in the public header,
the admin navigation (desktop and mobile) and the guest layout.

### 10.5 Page inventory

| View | Route | Notes |
|---|---|---|
| `welcome.blade.php` | `/` | Hero (chart background + glass card) · stats band · catalogue with category tabs + "Free only" · testimonials · `<details>` FAQ accordion · closing CTA |
| `videos/show.blade.php` | `/videos/{slug}` | Three states: free→player, paid+owned→player, paid+locked→teaser with lock, price, benefits and the Razorpay button (guests see "Sign in to buy"). Uploaded → `<video>` + `<source>` at the stream route; external → `<iframe allowfullscreen>` |
| `dashboard.blade.php` | `/dashboard` | Greeting · 3 figures · "My enrolled videos" with All/Bought/Free-watched tabs · enrolled cards with Watch buttons |
| `profile/edit.blade.php` + 3 partials | `/profile` | Dark: profile info, password, delete account (Alpine dialog with `role="dialog"`, `aria-modal`, Esc to close) |
| `admin/dashboard` | `/admin` | 4 stat cards + recent successful orders |
| `admin/videos/*` | `/admin/videos` | List (search, pagination) + create/edit form |
| `admin/categories/*` | `/admin/categories` | List + create/edit |
| `admin/orders/index` | `/admin/orders` | Status tabs, search, revenue |

### 10.6 The admin video form (Alpine)

`admin/videos/partials/form.blade.php` holds three pieces of interactivity:

- **Video source** radio (`upload` / `link`) with `x-model="source"` toggling two
  `x-show` blocks. Each block's input also carries
  `x-bind:disabled="source !== '…'"`, which is **load-bearing**, not tidiness:
  see §13.10.
- **Free/Paid switch**: a real `<input type="checkbox" class="peer sr-only">`
  preceded by `<input type="hidden" name="is_free" value="0">` (the classic
  Laravel pattern — works even if JS dies), styled as a switch with
  `peer-checked:` + `after:content-['']`. `x-model="isFree"` drives the price
  field's visibility **and** its `x-bind:disabled="isFree"` (§13.10).
- **Thumbnail preview** via `URL.createObjectURL`.
- **Resolved embed URL**: when the stored link is one `VideoEmbed` rewrites, the
  form shows what it will actually be framed as, so a bad paste is caught
  before a learner finds it.

All conditional blocks carry `x-cloak`.

---

## 11. Seeders and factories

| Seeder | Runs by default | Creates |
|---|---|---|
| `AdminUserSeeder` | yes | `admin@skincare.test` / `password`, role admin, verified |
| `CategorySeeder` | yes | Basics of Stock Market, Technical Analysis, Options Trading |
| `DatabaseSeeder` | yes | calls the two above + `test@example.com` / `password` |
| `DemoContentSeeder` | **no** — `php artisan db:seed --class=DemoContentSeeder` | 7 videos (free/paid, published/draft) on `player.example.com` URLs, 1 successful + 1 failed order, 1 purchase |

Factories: `UserFactory` (+ `admin()` state), `CategoryFactory`,
`VideoFactory` (+ `paid(float $price = 499)` and `draft()` states),
`OrderFactory` (+ `successful()` and `failed()` states).

---

## 12. Test suite

`php artisan test` → **368 tests, 1211 assertions, all passing.**
Runs on **SQLite in memory** (`phpunit.xml`), so it never touches `skin_care`.

> **A warning this suite had to learn.** Three of the worst bugs this project
> has had were invisible to every test above: the browser cancelled the submit
> before a request was ever made, so there was nothing for PHPUnit to assert
> on. A green suite says the server is correct, not that the page works. For
> anything involving `x-show`, constraint attributes or an iframe, drive a real
> headless Chrome as well — and make the harness prove its click connected
> (`elementFromPoint` plus a click counter), because a click that misses looks
> exactly like a click that was refused.

| File | Tests | Covers |
|---|---|---|
| `Unit/VideoEmbedTest` | 44 | every link form an admin can paste → its frame URL; start offsets in s/m/h; playlists; Vimeo and Drive; **unrecognised hosts returned untouched**; direct media told apart from a player page; reserved placeholder domains |
| `Unit/RazorpayGatewayTest` | 6 | `isConfigured`, currency, real HMAC verify (valid / wrong secret / wrong order), missing-keys exception |
| `Feature/AdminAccessTest` | 5 | guest→login, user→403, admin→200, role default + enum cast |
| `Feature/Admin/DashboardTest` | 3 | the four headline figures, revenue excludes failed orders |
| `Feature/Admin/CategoryManagementTest` | 9 | CRUD, search, slug generation + collision suffixing, delete guard |
| `Feature/Admin/VideoManagementTest` | 22 | CRUD, uploads to the private disk, thumbnails to public, free forces price 0, mimetype rejection, file replace/delete, paid-order delete guard, **a free video saves whether the browser sends its price, sends 0.00 or omits it**, a link longer than the column is a field error not a crash, a pasted page URL is stored verbatim and normalised at render, the list flags a placeholder link |
| `Feature/Admin/OrderTrackingTest` | 5 | default success filter, `all`, bad filter fallback, search |
| `Feature/LandingPageTest` | 8 | hero/stats/FAQ render, drafts hidden, category + free filters, pagination, owned badges |
| `Feature/VideoShowTest` | 8 | the three access states, admin preview, draft 404, related videos, page `<title>`/meta |
| `Feature/VideoStreamTest` | 10 | entitlement only: guest free OK, paid 403, buyer/admin stream, **206 Range**, 416 past the end, external 404, missing file 404, draft |
| `Feature/VideoStreamSecurityTest` | 19 | the signing/binding rules: tampered and absent signatures, a stretched `expires`, an expired link, the window edge, a link leaked to another **session** and to another **user**, a guest link reused after signing in, a token replayed on a different lesson, playback surviving past the window on a grant, a grant that does not help another session, an expired grant, **whole-file pulls capped to one slice**, no-store headers, player attributes, and the tamper-after-a-legitimate-stream regression |
| `Feature/Checkout/CheckoutScriptTest` | 5 | Razorpay's checkout.js actually reaches a buyer's page (it did not), is absent for guests/owners/free lessons, and `payment-success` explains itself instead of throwing when keys are blank |
| `Feature/PerformanceGuardTest` | 5 | the related rail does not query per card and does not grow with the card count, oversized thumbnails are downscaled, small ones are untouched, and the JS bundle stays under 80 KB |
| `Feature/VideoAccessTest` | 6 | `isAccessibleBy`, pivot uniqueness, scopes, `amountInPaise` |
| `Feature/Checkout/CreateRazorpayOrderTest` | 9 | pending order + options JSON, **price cannot be set by the client**, reuse of an open order, free/owned/draft/unconfigured/outage paths |
| `Feature/Checkout/RazorpayWebhookTest` | 13 | raw-body signature verification; forged / missing / tampered rejected; unconfigured secret; non-captured events ignored; unknown order; **underpaid capture withheld**; retry idempotency; browser-first and webhook-only paths |
| `Feature/VideoCompletionTest` | 13 | the 90% threshold exactly (539 vs 540), no duration = never completes, forged beacons clamped to the duration, completion is sticky across a rewatch, completed lessons resume from 0, dashboard badge, admin duration validation, category completion ignores drafts |
| `Feature/Admin/CouponManagementTest` | 16 | CRUD, code upper-casing, validation incl. past expiry, uniqueness, pause/resume, the delete guard on a used coupon, usage/revenue figures that ignore abandoned checkouts, search, nav link |
| `Feature/CertificateVerificationTest` | 10 | issued once with a stable HMAC reference, public verification with no session, case-insensitive lookup, unknown and forged references, the verify URL on the PDF, survives a new lesson, never-earned still refused, **rate limited at 20/min** |
| `Feature/PurchaseReceiptTest` | 10 | queued (not inline) on fulfilment, via both the callback and the webhook, never twice, never on a failed payment, subject and body contents, discount rows present or absent |
| `Feature/VideoPlaybackTest` | 6 | a pasted YouTube link is framed as `/embed/`; the fallback "open at the source" link; a locked video exposes neither; a remote `.mp4` uses our own `<video>` **and** tracks progress; an upload still streams privately; an embed claims no progress tracking |
| `Feature/Admin/AdminUxTest` | 27 | every form guards a double submit and shows a spinner; upload progress and the client-side size limits; validation summary counts; XHR upload returns 422 field errors / a JSON redirect that preserves the flash; dialogs replace `window.confirm` and post real CSRF forms; an unexpected error becomes a toast with input intact (HTML) or JSON (XHR); expired sessions explained and **renewable in place**; 403/404/422 and GETs left alone; **no hidden branch of the video form can block a submit**; every form can explain a block it cannot show |
| `Feature/ThemeTest` | 20 | `darkMode: 'class'` is configured; all three layouts ship the pre-paint bootstrap **before the first stylesheet** and a toggle; the toggle is a real labelled button; public markup carries tokens and no baked-in hex; the hero chart follows the theme; admin chrome, tables, Breeze controls and the paginator all have dark variants; **no admin view is left light-only** |
| `Feature/RateLimitTest` | 3 | checkout 10/min, progress 60/min, and the limit being per-user rather than global |
| `Feature/CertificateTest` | 10 | guest redirect, unfinished 403, empty category 404, a real `%PDF-` download with the right filename, the certificate's text, drafts do not block it, **an issued certificate is not revoked by a new lesson**, dashboard progress and download states |
| `Feature/Checkout/CouponTest` | 12 | discount applied to the amount sent to Razorpay, case/space-insensitive codes, unknown / expired / exhausted refused, unlimited coupons, the ₹1 floor on a 100% discount, usage counted only on fulfilment and only once, the webhook checks the **discounted** total |
| `Feature/VideoProgressTest` | 14 | beacon stores and overwrites the playhead, 204 reply, locked video 403, draft 404, validation, a watch is not a purchase, `resumeAt` reaches the player, embeds and guests get no tracking, dashboard Resume label |
| `Feature/Checkout/VerifyPaymentTest` | 10 | valid signature unlocks; forged, replayed, other-user, unknown-order and guest attempts all refused; idempotent repeat; failed-payment reporting |
| `Feature/DashboardTest` | 14 | purchases + watched listing, watch recording, **a watch is not a purchase**, filters, figures, drafts excluded, pagination, Watch links |
| `Feature/ProfileTest` | 5 | Breeze profile behaviour (unchanged) |
| `Feature/Auth/*` | 19 | Breeze auth + the login greeting toast |
| `Unit/ExampleTest`, `Feature/ExampleTest` | 2 | Laravel defaults; the Feature one needed `RefreshDatabase` once `/` began querying the catalogue |

---

## 13. Decisions an AI must not break

### 13.1 `APP_URL` must include `/public`

The app is served from an **htdocs subfolder**. Two consequences:

1. **The root `.htaccess` redirects (`R=302`) instead of rewriting internally.**
   An internal `RewriteRule ^(.*)$ public/$1 [L]` makes Apache keep
   `REQUEST_URI=/skin-care/` while `SCRIPT_NAME` becomes
   `/skin-care/public/index.php`. Symfony then computes `pathInfo` as
   `/skin-care/` and **every route 404s**. The redirect keeps the two aligned.
   `RewriteBase /skin-care/` is required or the redirect resolves to a filesystem
   path (`http://localhost/C:/xampp-8.2/...`).

2. **`APP_URL=http://localhost/skin-care/public`.** The `public` filesystem disk
   builds its URL as `rtrim(env('APP_URL'),'/') . '/storage'`. Without `/public`
   every thumbnail URL 404s. Password-reset links also come out right because the
   root redirect preserves path and query string.

### 13.2 `phpunit.xml` overrides `APP_URL`

Laravel resolves relative test URLs against `config('app.url')`. With the
sub-path in there, `$this->get('/login')` requests `/skin-care/login` and 22 tests
404. Hence `<env name="APP_URL" value="http://localhost"/>`.

### 13.3 Tailwind v3 only

`postcss.config.js` + `tailwind.config.js` + `@tailwind` directives = v3.
`vite.config.js` must **not** reference `@tailwindcss/vite`. If a future upgrade
moves to v4, the config format changes completely (CSS-first `@theme`).

### 13.4 Cascade deletes are guarded, not disabled

`orders.video_id` and `videos.category_id` both `ON DELETE CASCADE`. Rather than
weaken the FKs, the controllers refuse the dangerous delete and explain why.
If you add a "force delete", make sure payment records are archived first.

### 13.4a Fulfilment runs through one action, under a lock

`CheckoutController@success` (browser) and `RazorpayWebhookController` (server)
can both arrive, in either order, more than once. Neither writes the grant
itself — both call `App\Actions\FulfilPaidOrder`, which re-reads the order with
`lockForUpdate()` inside a transaction and returns early if it is already paid.
**If you add a third way to fulfil an order (an admin "mark as paid", a reconcile
command), route it through the same action.**

The webhook also refuses to unlock when the captured amount is **less** than
`Order::amountInPaise()`.

### 13.4b Duration is admin-entered, and completion is judged on the server

`videos.duration_seconds` is **never reported by the player**. Completion is what
unlocks a certificate, so a browser that could declare its own duration could
mint one. The admin types the length; the beacon only sends a playhead, which is
**clamped to that duration** before anything is judged.

- Threshold: `ceil(duration_seconds * 0.9)`.
- No duration ⇒ `completionThreshold()` is null ⇒ the lesson can never complete.
- `completed_at` is **sticky**: rewatching resets the playhead, not the badge.
- On `ended` the player posts the full length (not 0), which is how a short
  lesson still crosses 90% before the last `timeupdate` would have fired.

**Remaining gap, stated plainly:** a determined user can still `curl` the beacon
with a high playhead and self-certify. Closing that needs watch-time accounting
(summing real elapsed intervals), not a bigger threshold.

### 13.4c Certificates are issued once, then permanent

**Earning** one is re-checked on every download, but **holding** one is a
record: the first successful download writes a `certificates` row and the
reference never changes.

This replaced an earlier derive-on-the-fly design, under which publishing a new
lesson silently invalidated every certificate already printed or linked from a
profile. Now the row stores `lessons_count` and `completed_at` as they were at
the time, so a certificate stays truthful and `/verify/{reference}` keeps
answering. There are tests for both halves: an issued certificate survives a new
lesson, and somebody who never earned one still cannot get it.

The reference is still an HMAC (`TPA-{cat}-{user}-{8 hex}` against `APP_KEY`),
so the two ids on the front do not make it guessable.

### 13.4d Coupons never let the client touch the price

The browser sends a `coupon_code` string and nothing else. Lookup, expiry,
usage limit and arithmetic all happen in `Api\RazorpayController`, and
`used_count` is incremented inside `FulfilPaidOrder` — so an abandoned checkout
never burns a limited coupon, and a replayed callback never counts twice.

Coupons are managed at `/admin/coupons`. `is_active` is a separate switch from
expiry and the usage cap, and a coupon that appears on any order cannot be
deleted — only paused — so the record of what was discounted survives.

### 13.5 Money

- Stored as `decimal(10,2)` rupees; sent to Razorpay as **paise**
  (`Order::amountInPaise()`).
- Minimum paid price is ₹1 (`price` rule `min:1`) because that is Razorpay's floor.
- Revenue = successful orders only, everywhere.

### 13.6 Flash-message convention

`success` / `error` / `info` only. `<x-toast>` (dark) and `<x-admin.toast>`
(light) render them. Breeze's own `status` key is left alone — it carries machine
values like `profile-updated` and is rendered inline by the profile partials and
`<x-auth-session-status>`.

### 13.6b The certificate PDF must stay one page

DomPDF's usable height on A4 landscape is noticeably less than the nominal
595.28pt once nested borders and padding are counted: a sheet of 500pt spilled
onto a second page while 460pt did not. The view therefore carries **no fixed
heights at all** — the frame wraps its content and the page padding supplies the
margin, which holds for short and very long names alike.

If you edit that view, re-check the page count; `/Type /Page` also matches
`/Type /Pages` (the tree node), so count with a regex like
`~/Type\s*/Page[^s]~` rather than a plain substring.

### 13.8b An admin write never ends on a white error page

`bootstrap/app.php` registers a `$exceptions->render()` callback: for a
**non-safe** request under `admin/*`, an unexpected throwable becomes a
redirect back with the input intact and an `error` toast (or JSON for the XHR
upload). ValidationException, 403/404 and auth failures are passed through
untouched, and Laravel has already reported the exception, so nothing is
swallowed.

Two things that cost time here, both proven by tests:

1. **This cannot be middleware.** Illuminate's routing pipeline converts a
   controller exception into a response *before* route middleware sees it, so a
   `try/catch` in middleware never fires.
2. **Match 419 by status, not only by class.** `Handler::prepareException()`
   has already turned a `TokenMismatchException` into an `HttpException(419)`
   by the time render callbacks run, so a `instanceof TokenMismatchException`
   check alone silently misses every real CSRF expiry.

### 13.9 Token names are roles, and two theming strategies coexist

`ink` is the **page surface** and `paper` is the **text on it** — so in light
mode `ink` is near-white and `paper` is near-black. Accents darken in light mode
so that both uses stay legible: `bg-gold text-ink` (surface-coloured text on an
accent) and `text-gold` (accent as text) each keep contrast in both themes.
Renaming these to `surface`/`content` would be clearer but touches ~25 Blade
files; it was left alone deliberately.

Do **not** add `dark:` variants to public views — the tokens already flip, and a
`dark:` there would fight them. Do **not** rely on tokens in admin views — that
surface is Breeze's gray scale plus `dark:` variants. Each surface has exactly
one mechanism; mixing them is how this gets hard to reason about.

### 13.10 A hidden form field must be **disabled**, not just hidden

This one cost a day, so it is written out in full.

The video form hides the price field for a free video with `x-show="! isFree"`.
The field carried `min="1"`. A free video stores `price = 0.00`. So on the edit
page of **every free video**, the form held a control that was simultaneously
*invalid* and *not focusable*.

What a browser does then: it refuses to submit, and — because it cannot focus
the field to show its bubble — it says nothing. No submit event fires, so no
JavaScript runs either. The Save button looked completely dead: no spinner, no
error, no network request, nothing in `laravel.log`, because nothing ever left
the browser. The only signal anywhere was one DevTools line:

```
An invalid form control with name='price' is not focusable.
```

It also swallowed uploads on the same page, which is why "I cannot edit
anything" and "no video uploads" turned out to be one defect.

**The rule.** When a branch of a form is hidden, disable its controls:

```blade
<x-text-input id="price" name="price" type="number" min="1"
              x-bind:disabled="isFree" … />
```

`disabled` excludes a control from constraint validation *and* from the
payload, in one attribute. Relaxing the constraint instead (`:min="isFree ? 0 : 1"`)
is not enough — `stepMismatch` and `typeMismatch` would still fire.

Corollaries worth keeping:

- The server was never the obstacle: `StoreVideoRequest::prepareForValidation()`
  already forces `price => 0` for a free video, and validates it with `min:0`.
  The client-side `min="1"` contradicted the app's own rule. **If a constraint
  attribute disagrees with the server's rule, the attribute is the bug.**
- `type="hidden"` is exempt from constraint validation by spec, so the
  `is_free` hidden input was never part of this.
- `x-if` is immune (it removes the node); `x-show` is not (it sets
  `display: none`).
- The near-miss to protect: `profile/partials/delete-user-form.blade.php` has a
  password input inside an `x-show` modal. It has no `required` today. Adding
  one would recreate this bug exactly.

### 13.11 A video URL is normalised at render, never on save

`videos.video_path` stores exactly what the admin pasted. `VideoEmbed::resolve()`
rewrites it to the provider's frame URL at render time (§7.4a).

Storing the rewritten form instead would be lossy: the "Open this lesson at the
source" fallback could no longer point at a page a human can read, the admin
would no longer recognise their own link, and a mapping we later improve could
not be re-applied to rows already saved. Resolving on read costs nothing and
fixes existing rows with no migration.

### 13.12 An uploaded lesson has no fixed address

`/videos/{video}/stream` is gone. It was guessable from the slug and, once
found, worked for anybody who was entitled -- which made a copied URL as good
as a login. Uploads are now served only through
`/stream/{video}/{token}?expires=&signature=`, built by `App\Support\StreamToken`.

Three independent things must hold, and `VideoStreamController` checks them in
this order:

1. **Entitlement** -- the existing `isAccessibleBy()` rule. Unchanged.
2. **Binding.** `{token}` is `hmac(video id | user id | per-session key)` keyed
   on `APP_KEY`. A link lifted out of devtools and opened in another browser,
   or by another account, dies here even with a perfect signature. *This* is
   what stops sharing.
3. **Signature**, checked in two halves, and the order matters:
   - `URL::hasCorrectSignature()` -- a forged HMAC is fatal, always.
   - `URL::signatureHasNotExpired()` -- if fresh, the session is granted a
     longer window; if genuine but expired, only an existing grant carries it.

**Why the grant exists.** A `<video>` element keeps asking for bytes for as
long as the lesson plays -- on every seek and every buffer refill. A link that
expires in 60 seconds would cut every lesson off after a minute. So the 60
seconds applies to *starting* a stream; the first valid request records
`stream_grants.{id}` in the session, good for the lesson's length plus 30
minutes. A copied link is still worthless after 60 seconds, because the thief
has neither the session key nor the grant.

**The bug this ordering fixes, because it will be tempting to simplify.** An
earlier version asked `hasValidSignature()` -- which means correct *and*
unexpired -- and fell back to the grant whenever it said no. That let a session
which had already started the lesson legitimately go on to accept a **tampered**
signature, since the grant alone carried it. Every unit test passed, because no
test tampered *after* a successful stream. A live run against Apache caught it.
`test_a_tampered_signature_is_refused_even_after_a_legitimate_stream` is the
regression guard; do not collapse those two checks back into one.

**Binding is to a per-session key, not the session id.** Laravel rotates the
session id on login to defeat fixation, which would silently invalidate every
link a visitor was holding at the moment they signed in. `tpa.stream_key` is a
random value minted once per session and never sent to the client.

**Responses are sliced.** Every answer is a 206 capped at
`academy.streaming.chunk_bytes` (2 MB), whatever Range was asked for and even
when no Range header is sent. A download manager cannot pull a 24 MB lesson on
one connection; it needs hundreds of requests, each re-checked, and all of them
stop the moment the grant lapses or the viewer signs out. `Cache-Control` is
`no-store, private` so nothing outlives the grant that authorised it.

Knobs: `STREAM_LINK_TTL` (60), `STREAM_MAX_GRANT` (21600), `STREAM_CHUNK_BYTES`
(2097152).

### 13.13 The player hardening is a deterrent, not a control

`controlslist="nodownload noremoteplayback noplaybackrate"`,
`disablepictureinpicture`, the blocked context menu and the blocked F12 /
Ctrl+Shift+I / Ctrl+U / Ctrl+S bindings in `resources/js/player-guard.js` raise
the effort for a casual viewer. **They stop nobody who is trying.** Devtools
opens before the page loads, the keybindings are a fraction of the ways in, and
no browser-side trick survives a screen recorder.

Say so plainly when asked, rather than letting anyone believe the video is
safe because the right-click menu is gone. Everything that actually enforces
anything is in 13.12, on the server. The guard is also deliberately narrow --
it only fires while the pointer or focus is on the player, so copy, paste, find
and reload behave normally everywhere else on the page.

Genuine next steps, if the content is worth more than this: encrypted HLS with
per-segment keys, short-lived key delivery, and ultimately a licensed DRM
(Widevine / FairPlay / PlayReady). Those are the only things that raise the bar
further, and none of them is free.

### 13.14 Blade pushes a component's slot renders FIRST

`@push('scripts')` written **after** `</x-public-layout>` is silently thrown
away. A component renders its slot before its own view, so `@stack('scripts')`
inside the layout has already been emitted by the time the push registers.

This shipped: Razorpay's `checkout.js` sat outside the closing tag, so
`window.Razorpay` was never defined on the one page that needs it and the Buy
button did nothing at all. Nothing threw and nothing logged. Every `@push` must
live **inside** the component slot; `Feature/Checkout/CheckoutScriptTest`
guards it.

### 13.15 Things that are cheap to undo by accident

Each of these is invisible to correctness, so nothing else in the suite
notices. `Feature/PerformanceGuardTest` is the net.

- **Eager-load the related rail.** `PublicVideoController` loads `category`
  and resolves `ownedIds` once for the whole rail. Asking per card is an N+1
  that only shows up as a slow page.
- **`recordWatch` / `recordProgress` use a single-row `upsert`.** They used
  `syncWithoutDetaching()`, which first reads *every* pivot row the learner
  has, to decide what to change — unbounded, on every lesson open and every
  15-second beacon. `video_views` carries `unique(user_id, video_id)`, which is
  what lets the upsert resolve to one row. `completed_at` is only ever in the
  update list on the run that earns it, so a rewatch cannot overwrite the date.
- **Thumbnails are downscaled on upload** to 1280px / quality 82
  (`VideoController::downscaleThumbnail`). A real 2580×1440 upload went
  1.1 MB → 220 KB, 81% smaller. Deliberately forgiving: no GD, an undecodable
  file, or a result that is not actually smaller all keep the original. A heavy
  thumbnail is a far smaller problem than a lost upload.
- **No unused dependencies in the bundle.** `axios` was 60% of a 113 KB bundle
  with zero call sites anywhere; removing it and `resources/js/bootstrap.js`
  took the JS to 62 KB. The guard test fails above 80 KB.
- **`npm run build` clears the view cache first.** Tailwind's content globs
  include `storage/framework/views/*.php`, so without it the CSS size depends
  on which pages happened to be compiled — 75 KB vs a deterministic 61 KB, with
  stale classes baked in.
- **Hashed assets get `Cache-Control: immutable`**, matched by *path* in
  `public/.htaccess`. Not by extension: a `FilesMatch` would also brand any
  unhashed `.js`/`.css` added later. And not by a file inside `public/build/` —
  Vite empties that directory on every build.

### 13.16 `payment-success` must never reach the gateway unguarded

`RazorpayGateway::verifyPayment()` **throws** when the keys are blank, which is
the documented default `.env`. `CheckoutController::success()` called it with
no guard, so an ordinary learner got a white 500 page instead of being told the
payment could not be confirmed. It is reachable without any setup — the demo
seeder creates a `failed` order with a `razorpay_order_id`. The controller now
mirrors the `isConfigured()` check that `Api\RazorpayController@store` already
had.

### 13.17 The test suite refuses to run against a cached config

`phpunit.xml` overrides `APP_URL` to `http://localhost` (13.2). A cached config
is baked at the moment it was written and ignores env overrides entirely, so
**every route-based test 404s at once**, with nothing to suggest why — it cost
a full debugging pass. `composer test` clears the config first; `php artisan
test` does not, which is the trap.

`tests/TestCase::setUp()` now throws immediately if `configurationIsCached()`,
naming the fix. One clear failure beats 300 silent ones. Note the corollary for
production: `php artisan config:cache` is still the right thing to deploy with
— just clear it before running tests.

### 13.7 Headless-Chrome caveat (for whoever checks the UI)

`chrome --headless=new --window-size=390,1400 --screenshot` does **not** give a
truthful narrow-viewport render — content looks clipped when it is not. To check
mobile or measure overflow, load the page in a **same-origin iframe** of the
target width from a throwaway file in `public/`, and read
`documentElement.scrollWidth` vs `clientWidth`.

Measured result: **no horizontal overflow on 11 pages × 4 widths
(390 / 768 / 1024 / 1440)**.

---

## 14. Verification checklist

```bash
php artisan test                      # 368 passed
npm run build                         # Vite builds CSS + JS
php artisan route:list --except-vendor
php artisan migrate:status
php artisan about --only=environment,drivers
```

Manual smoke test:

| Check | Expect |
|---|---|
| `http://localhost/skin-care` | 302 → `/skin-care/public/`, 200 |
| `/videos/<free-slug>` as guest | player renders |
| `/videos/<paid-slug>` as guest | lock + "Sign in to buy", **no** player markup |
| `/videos/<paid-slug>/stream` as guest | **403** |
| `/videos/<free-slug>/stream` with `Range: bytes=0-99` | **206** + `Content-Range` |
| `/videos/<draft-slug>` as guest | **404** |
| `/dashboard` as guest | 302 → login |
| `/admin` as a normal user | **403** |
| Buy button with blank Razorpay keys | inline "Payments are not switched on yet" (503) |
| `/admin/videos/<free-slug>/edit`, change a field, **Save** | lands on `/admin/videos` with a success toast — see §13.10 for why this is on the list |
| Same page, DevTools console | **no** "An invalid form control … is not focusable" |
| `/videos/<slug>` whose link is a YouTube *watch* URL | a live player, not a black box; `<iframe src>` ends `/embed/ID` |
| `/admin/videos` | any lesson still on a placeholder host is flagged in red |

Everything below the line "Buy button…" is browser-only: PHPUnit cannot see a
submit the browser refused to make. Drive it in a real headless Chrome, and
make the harness prove its click landed before trusting a "no request" result.

To smoke-test the payment path without real keys: set any
`RAZORPAY_KEY`/`RAZORPAY_SECRET`, `php artisan config:clear`, click Buy — the
real API call returns *Authentication failed* and the app surfaces a clean 502.
Then POST to `/payment-success` with
`hash_hmac('sha256', "{$orderId}|{$paymentId}", $secret)` and the video unlocks.
**Blank the keys again afterwards.**

---

## 15. Known gaps and where to go next

### 15.1 Must-do before production

1. ~~**Razorpay webhook.**~~ **Done** (Priority Upgrade 1). `POST /razorpay/webhook`
   handles `payment.captured`. To switch it on: add the endpoint in the Razorpay
   dashboard pointing at `https://<your-host>/razorpay/webhook`, subscribe to
   `payment.captured`, and put the dashboard's signing secret in
   `RAZORPAY_WEBHOOK_SECRET`. Until that value is set the endpoint answers 503.
   Razorpay cannot reach `localhost` — use a tunnel (ngrok/cloudflared) to test
   against the real dashboard.
2. **Real content and copy.** Replace the placeholder stats/testimonials in
   `config/academy.php` and the `player.example.com` demo URLs.
3. **Production hardening.** `APP_DEBUG=false`, `APP_ENV=production`, HTTPS,
   a DB user that is not `root`, `php artisan config:cache route:cache view:cache`
   -- and `php artisan config:clear` again before ever running the suite (13.17).
4. **Refunds / order cancellation.** There is no path to reverse a purchase, and
   a refund today would leave both the access row and any coupon use in place.
5. **Turn on OPcache.** The single biggest win available, and it is one edit:
   `zend_extension=opcache` plus `opcache.enable=1` in `C:\xampp-8.2\php\php.ini`,
   then restart Apache. Measured at **~4.3x slower without it** -- roughly 114 ms
   of every request spent recompiling 694 PHP files that never changed. Not done
   here because php.ini is shared by every site on this XAMPP.
6. **Turn on compression.** `mod_deflate` is not loaded, so about 123 KB per page
   ships uncompressed. Uncomment **three** lines in
   `C:\xampp-8.2\apache\conf\httpd.conf` -- `mod_filter` (required: it owns
   `AddOutputFilterByType` in Apache 2.4), `mod_deflate`, and optionally
   `mod_expires` -- then restart. The directives are already waiting in
   `public/.htaccess` behind `<IfModule mod_deflate.c>`, so they switch
   themselves on.
7. **A real mail driver and a queue worker.** Receipts are written and queued,
   but `MAIL_MAILER=log` means nothing leaves the machine, and
   `QUEUE_CONNECTION=database` means they sit in the `jobs` table until
   `php artisan queue:work` runs. Point `MAIL_*` at a real service and run the
   worker under a supervisor before launch.

### 15.2 Natural next features

| Idea | Where it plugs in |
|---|---|
| **Courses/bundles** (buy a set of videos at once) | New `courses` table + `course_video` pivot; `orders` gains a nullable `course_id`, or becomes polymorphic |
| ~~**Resume playback**~~ | **Done** (Priority Upgrade 2) |
| ~~**Completion tracking**~~ | **Done** — `duration_seconds` on videos, `completed_at` on views, 90% rule |
| ~~**Certificates**~~ | **Done** — per-category PDF via dompdf |
| ~~**Coupons / discounts**~~ | **Done** — backend-validated `coupon_code` on order creation |
| ~~**Admin UI for coupons**~~ | **Done** — `/admin/coupons` with usage stats and a pause switch |
| ~~**Certificate verification page**~~ | **Done** — public `/verify/{reference}` |
| ~~**Rate limiting**~~ | **Done** — `checkout`, `progress`, `verification` limiters |
| **More transactional email** | The receipt exists; a welcome mail on registration and a "new lesson in a category you follow" nudge are the obvious next ones. A real mail driver is needed first (§15.1) |
| **Resumable uploads** | The XHR upload is all-or-nothing; a dropped connection at 90% starts over. tus or S3 multipart would fix it, and would also lift the 120 MB ceiling |
| **Coupon usage on the orders page** | The admin orders table does not yet show which coupon an order used, though `orders.coupon_id` is recorded |
| **Auto-detect duration** | Admins typing the runtime is the weak point. FFprobe on upload (`getID3` or a shelled `ffprobe`) would fill `duration_seconds` automatically and keep it trustworthy |
| **Watch-time accounting** | Sum real elapsed playback intervals instead of trusting the playhead, closing the self-certification gap in §13.4b |
| **Real "watched" signal** | A watch is still recorded on page *open*. Hook the `play` event instead once duration exists |
| **Progress for embeds** | External videos have no tracking. YouTube/Vimeo player APIs expose `getCurrentTime()`; wiring one would extend `video-progress.js` |
| **Video duration + quality ladder** | Store `duration_seconds`; transcode with FFmpeg and serve HLS instead of a single MP4 |
| **Sign of real scale** | Move uploads to S3 (`FILESYSTEM_DISK=s3`) and serve via signed CloudFront URLs; the `videos.stream` route becomes a redirect |
| ~~**Admin dark mode**~~ | **Done** — global toggle, both surfaces |
| **Follow the OS by default** | One line in `partials/theme-script.blade.php`; today a first visit always starts dark |
| **Remember the theme per account** | It is `localStorage` only, so a new device starts dark again. A `users.theme` column plus a server-rendered class would carry it across devices |
| **Search** | Laravel Scout over `videos.title/description` once the catalogue grows past a few hundred |

### 15.3 Smaller clean-ups

- `npm audit` reports 7 vulnerabilities in **build-time** deps (`braces`,
  `postcss-selector-parser`) pulled in by Tailwind 3. They never reach the
  browser. `npm audit fix --force` would upgrade Tailwind to v4 and break the
  config — do that only as a deliberate migration.
- No `git` repository has been initialised.
- `/dashboard` is routed through the `verified` middleware, but `User` does not
  implement `MustVerifyEmail`, so it is inert -- an unverified account gets in.
  Either implement the contract or drop the middleware; leaving it reads as a
  protection that is not there.
- Deleting an account cascade-deletes its paid orders and issued certificates,
  which is probably wrong for financial records. 13.4 guards video deletion the
  same way, but nothing guards user deletion.
- `php artisan pail` (quoted in 16) cannot run on Windows -- it needs `pcntl`.
  Use `tail -f storage/logs/laravel.log` instead.
- A public copy of one lesson sits at `storage/app/public/videos/` from a manual
  experiment. It bypasses the paywall entirely (13.12) -- delete it unless that
  lesson is meant to be free.
- `Admin\VideoController` is the largest controller; the file-handling private
  methods are a natural candidate for a `VideoFileManager` service once a second
  caller appears.
- Queue is `database`; purchase receipts already go through it (§9.2). Video
  post-processing (thumbnails, duration probing) is the obvious next job.
- The demo seeder points six lessons at `player.example.com`, which RFC 2606
  guarantees will never resolve. They are flagged in the admin list and need
  real links before they can play — this is seed data, not a defect.
- `VideoEmbed` maps YouTube, Vimeo and Google Drive. Any other provider passes
  through unchanged, which is safe but means an admin must paste that
  provider's embed URL themselves. Add a mapping when one is actually needed.

---

## 16. Quick reference

```bash
# Daily
php artisan test
npm run dev                      # or: npm run build
php artisan optimize:clear

# Database
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoContentSeeder
"C:/xampp-8.2/mysql/bin/mysql.exe" -u root -D skin_care -e "SHOW TABLES;"

# Logs
php artisan pail                 # live tail
```

| URL | Who |
|---|---|
| `http://localhost/skin-care` | everyone |
| `http://localhost/skin-care/public/dashboard` | signed-in learner |
| `http://localhost/skin-care/public/admin` | admin only |

| Account | Password |
|---|---|
| `admin@skincare.test` | `password` |
| `test@example.com` | `password` |
