---
description: Autonomous end-to-end Directus CMS deployment & Frontend integration
---

# 🤖 Agent Instructions: Directus Deployment & Frontend Integration

**System Prompt / Guidelines:**
You are an autonomous AI Agent tasked with deploying a Headless CMS (Directus) to a blank remote Linux VPS, parsing a static HTML webpage, migrating its textual data into the CMS, rewriting the HTML to fetch this data dynamically, and finally hosting the result on the VPS via Nginx. 

**CRITICAL CONTEXT:** This workflow contains battle-tested workarounds for Directus 11 permission systems, Docker volume permissions, and Nginx proxy CORS restrictions. You must follow these steps precisely to avoid `401`, `403`, and `503` errors.

---

## Quick Start Guardrails (Read First)
Before doing anything else, keep these high-level constraints in mind:

1. Build schema only from the real KANT pages (`index.html`, `modules.html`, `module-1.html`, `publications.html`), not from generic demo assumptions.
2. Treat localization as a core requirement for all user-facing text.
3. Keep the implementation aligned with current frontend structure and reusable templates.
4. Use detailed validation and rendering rules from Step 4.KANT and Step 5 (do not duplicate or override them here).

## Step 1: Initialization & Information Gathering (KANT-specific)
1. Ask the User to provide:
   - Server host / IP (for this project typically `home612902638.1and1-data.host`), SSH username (usually `root`), and password or SSH key.
   - The absolute local path to the KANT project root (containing `index.html`, `modules.html`, `module-1.html`, `publications.html`).
2. Read and parse the following HTML files:
   - `index.html`
   - `modules.html`
   - `module-1.html` (as a template for module detail pages)
   - `publications.html`
3. DO NOT invent synthetic sections like “benefits”, “testimonials”, or “pricing”. All schema must be derived from existing sections:
   - Hero sections
   - About Project (+ about modal)
   - Modules listing and module detail
   - Our position
   - Publications
   - Our Authors
   - Footer and social links
4. Assume all user-facing text and labels are localized. Every collection with text MUST follow the localization contract in Step 4.1.

## Step 2: VPS Docker & Directus Setup
1. Connect via SSH.
2. Install Docker (`curl -fsSL https://get.docker.com | sh`).
3. Prepare a `docker-compose.yml` for Directus + PostgreSQL (NO Redis caching to save RAM, NO local `volumes` for directus uploads/extensions to avoid `EACCES: permission denied` errors on host).

**Sample Compose (Bulletproof):**
```yaml
version: "3"
services:
  database:
    image: postgres:15
    environment:
      POSTGRES_USER: 'directus'
      POSTGRES_PASSWORD: 'directus'
      POSTGRES_DB: 'directus'
    restart: always

  directus:
    image: directus/directus:latest
    ports:
      - "8055:8055"
    depends_on:
      - database
    environment:
      KEY: '1df5c4a7-8f5c-4f7f-8e41-0f2c4b2a3a1f'
      SECRET: 'a2b3c4d5e6f7g8h9i0j1k2l3m4n5o6p7'
      DB_CLIENT: 'pg'
      DB_HOST: 'database'
      DB_PORT: '5432'
      DB_DATABASE: 'directus'
      DB_USER: 'directus'
      DB_PASSWORD: 'directus'
      CACHE_ENABLED: 'false'
      ADMIN_EMAIL: 'admin@nuxt.network'
      ADMIN_PASSWORD: 'STRONG_PASSWORD_HERE'
      PUBLIC_URL: 'http://<SERVER_IP>:8055'
      CORS_ENABLED: 'true'
      CORS_ORIGIN: '*'
    restart: always
```
4. Run `docker compose up -d`. Wait ~40 seconds for Postgres to initialize and Directus to become healthy.

## Step 3: The Authentication Hack (Crucial)
Directus 11 radically changed Public Policies, causing REST API `/permissions` to frequently fail with `403` or `401`. Furthermore, updating an admin token via REST is sometimes silently ignored.
**Solution:** Inject a static read-only token natively into the Postgres DB.
1. Run this terminal command via SSH to force the token:
   `docker exec directus-database-1 psql -U directus -d directus -c "UPDATE directus_users SET token = 'STATIC_TOKEN_12345' WHERE email = 'admin@nuxt.network';"`
2. Restart the API cache: `docker restart directus-directus-1`
3. All subsequent frontend API requests MUST use `?access_token=STATIC_TOKEN_12345`. This bypasses all fragile public permissions.

## Step 4: Schema & Data Migration
1. Using the Directus REST API (authenticated with the admin email/password), programmatically `POST /collections` to create the KANT schema described in Step 4.KANT.
2. Ensure you initialize Collections with an explicit Primary Key field (e.g. `id`) and locale-aware fields in accordance with Step 4.1.
3. Iterate over the extracted data from `index.html`, `modules.html`, `module-1.html`, and `publications.html` and `POST /items/:collection_name` to populate the CMS.

## Step 4.1: Localization Contract (Multi-language) - Mandatory
The frontend language switcher is already implemented and stores locale in `localStorage` under `kant-locale`, supports URL override via `?lang=<locale>`, and updates `<html lang="...">`.
Directus integration MUST follow this contract exactly.

1. Locale set:
   - Supported locales are defined by available language options in the frontend (`.lang-switcher__option[data-lang]`) and by CMS content availability.
   - Default locale is `en` unless explicitly changed in project config.
   - Normalize incoming locale to lowercase before API requests.
2. Data modeling rule:
   - Every collection that contains user-facing text MUST include locale-aware data.
   - Use either:
     - one row per locale with a `locale` field (`en`, `ru`, `ka`, etc.), OR
     - a translations collection linked by item id.
   - Choose one strategy and keep it consistent across all collections.
3. Query rule:
   - Every content fetch MUST include locale filtering and deterministic ordering.
   - Always keep `sort=id` (or equivalent stable key) to prevent DOM reshuffling.
4. Fallback rule:
   - If requested locale content is missing, automatically fallback to `en` for that record/section.
   - Fallback must happen silently without breaking layout or throwing fatal runtime errors.
5. URL/state sync rule:
   - Preserve existing switcher behavior:
     - Default locale (`en`): URL has no `lang` parameter.
     - Non-default locale: URL includes `?lang=<locale>`.
   - CMS data loading must react to the active locale selected by the switcher.
6. Definition of done for i18n:
   - Switching between any configured locales updates all wired dynamic text blocks on the page.
   - Page reload keeps selected language.
   - Direct URL open with `?lang=<locale>` renders that locale immediately (or fallback if missing).
   - Missing locale entries correctly display English fallback.

## Step 4.KANT: Project-specific schema for KANT

All user-facing text MUST be localizable according to Step 4.1. Every “label” you see in the current HTML should come from CMS, unless explicitly marked static below.

### 4.K.1 `site_settings` (singleton)
Global settings and footer/social links.

- `id`: primary key.
- `footer_copyright` (translated string or rich text, required).
- `social_youtube_url` (string, required).
- `social_twitter_url` (string, required).
- `social_instagram_url` (string, required).
- `social_facebook_url` (string, required).

### 4.K.2 `hero_sections`
Hero content per page, except the home hero which is read-only and remains static in HTML.

- `id`: primary key.
- `page_key` (string, required, unique): one of `modules`, `module_detail`, `publications`.
- `title` (translated string, required).
- `subtitle` (translated string, optional).
- `background_image` (file, optional).

Rules:
- `index` hero is NOT editable via Directus UI. Do not create a writable record for `page_key = 'index'` and do not attempt to sync it back to HTML.
- `subtitle` is optional for all hero records. In particular, `modules` and `publications` pages may include or omit subtitle independently.
- Frontend MUST render hero subtitle only when `subtitle` is non-empty.

### 4.K.3 `about_project`
Shared About Project block for `index.html` and `modules.html`, including videos and modal.

- `id`: primary key.
- `section_title` (translated string, required) — e.g. “About Project”.
- `sticker_text` (translated rich text, required).
- `video_url_primary` (string, optional).
- `video_url_secondary` (string, optional).
- `video_title_primary` (translated string, optional) — used in iframe `title`/aria attributes.
- `video_title_secondary` (translated string, optional).
- `modal_body` (translated rich text, required) — full body for `#about-modal`, editable as WYSIWYG (paragraphs, bold, links, etc.).

Rules:
- Frontend MUST use the same `about_project` content on both `index.html` and `modules.html`.
- If a video URL is empty, its tab/panel must be hidden.

### 4.K.4 `modules`
Core modules used everywhere (home grid, modules list, and module detail).

- `id`: primary key.
- `slug` (string, required, unique): URL-friendly identifier (e.g. `module-1-origins-of-militarism`). Used for routing and internal references.
- `order` (integer, required): manual order across all modules.
- `module_number` (integer, required): human-facing module number (e.g. 1, 2, 3…).
- `title` (translated string, required).
- `short_description` (translated rich text, required).
- `languages` (string or array, required) — e.g. `"EN, RU, DE, KZ"`.
- `formats` (string, optional) — e.g. `"Video, Podcast, PDF"`.
- `list_duration_display` (string, optional) — e.g. `"14:23"`; manually entered, DO NOT attempt to auto-derive from YouTube in this workflow.
- `hero_kicker` (translated string, optional) — e.g. “Модуль 1”.
- `hero_subtitle` (translated string, optional).
- `hero_background_image` (file, optional).

Lecture section (optional; hidden if effectively empty):
- `lecture_enabled` (boolean, default false).
- `lecture_label` (translated string, optional) — e.g. “Лекция:”.
- `lecture_title` (translated string, optional).
- `lecture_video_url_primary` (string, optional).
- `lecture_video_url_secondary` (string, optional).
- `lecture_video_title_primary` (translated string, optional).
- `lecture_video_title_secondary` (translated string, optional).

Presentation section (optional; hidden if effectively empty):
- `presentation_enabled` (boolean, default false).
- `presentation_label` (translated string, optional) — e.g. “Презентация:”.
- `presentation_title` (translated string, optional).
- `presentation_video_url_primary` (string, optional).
- `presentation_video_title_primary` (translated string, optional).
- `presentation_file` (file, optional, PDF).

Navigation relations:
- `prev_module` (relation to `modules`, optional).
- `next_module` (relation to `modules`, optional).

Rules:
- Lecture section MUST NOT be rendered if `lecture_enabled` is false OR both `lecture_title` and all lecture video URLs are empty.
- Presentation section MUST NOT be rendered if `presentation_enabled` is false AND there is no `presentation_title` and no `presentation_file`.
- Homepage modules block:
  - Fetch modules sorted by `order` (then `id` as tiebreaker).
  - Render only the first N modules, where N is a configurable parameter (min 3, max 5).

Module detail pages:
- For each module, generate or serve a detail page based on the `module-1.html` template structure.
- Use a consistent URL pattern such as `module-<module_number>.html` or a route based on `slug` (e.g. `/module/<slug>`), but the HTML structure MUST follow the current `module-1.html` layout (hero, lecture, presentation, reading materials, transcripts, literature, navigation).

### 4.K.5 `module_transcripts`
Downloadable transcripts per module (used in the “Транскрипции к лекции” modal).

- `id`: primary key.
- `module` (relation to `modules`, required).
- `file` (file, required, PDF).
- `display_name` (translated string, required) — visible link text.
- `order` (integer, optional).

Rules:
- Render the transcript list sorted by `order` then `id`.

### 4.K.6 `module_readings`
Per-module reading materials for “Материалы для чтения”, with optional linkage to global publications and the same XOR file/link rules as publications.

- `id`: primary key.
- `module` (relation to `modules`, required).
- `linked_publication` (relation to `publications`, optional).
- `custom_title` (translated string, optional).
- `custom_url` (string, optional).
- `custom_file` (file, optional, PDF).
- `custom_cover_image` (file, optional) — explicit cover for this reading.

Rules:
- If `linked_publication` is set:
  - Use publication data by default, but allow `custom_title`, `custom_url`, `custom_file`, and `custom_cover_image` to override publication fields when provided.
- If `linked_publication` is NOT set:
  - Enforce XOR for link/file: exactly one of `custom_url` or `custom_file` MUST be present; `custom_title` MUST be present.
- Cover logic for frontend:
  - If `custom_cover_image` exists, use it.
  - Else if `linked_publication.cover_image` exists, use it.
  - Else fall back to the default “paper” icon.

### 4.K.7 `publication_types` and `publications`

`publication_types`:
- `id`: primary key.
- `slug` (string, required, unique) — e.g. `articles`, `books`.
- `name` (translated string, required) — labels for tabs.

`publications`:
- `id`: primary key.
- `title` (translated string, required).
- `type` (relation to `publication_types`, required).
- `description` (translated rich text, optional).
- `cover_image` (file, optional) — if empty, frontend MUST use a default icon.
- `file` (file, optional).
- `url` (string, optional).
- `published_at` (datetime, required).
- `display_order` (integer, required).

Rules:
- Validation rule (XOR): exactly one of `file` or `url` MUST be provided per publication. Never allow both to be set, and never allow both to be empty.
- Homepage:
  - Fetch latest 3 publications using `published_at` desc (then `display_order`/`id` as tie-breakers).
- `publications.html`:
  - Fetch all publications.
  - Drive tabs from `publication_types` (types and labels from CMS).

### 4.K.8 `authors`
Authors for the “Our Authors” slider.

- `id`: primary key.
- `first_name` (translated string, required).
- `last_name` (translated string, required).
- `full_name` (translated string, optional; frontend may fall back to first+last).
- `photo` (file, required).
- `affiliation` (translated string, required).
- `display_order` (integer, required).

Rules:
- Slider items sorted by `display_order` then `id`.

## Step 5: Frontend JavaScript Injection
Rewrite the local `HTML` file to inject a dynamic data fetching script before `</body>`.

**CRITICAL FETCH RULES:**
- **URL Path:** Use conditional routing to prevent CORS/file scheme failures. If local (`file://`), use `http://<SERVER_HOST_OR_IP>:8055`. If on server, use `/api` (to be proxied by Nginx).
- **Sort Parameter:** ALWAYS append `&sort=id`. PostgreSQL returns updated rows randomly, causing DOM elements to shuffle if not sorted.
- **Auth Parameter:** ALWAYS append `&access_token=STATIC_TOKEN_12345` to bypass auth and avoid Nginx stripping the Authorization header.
- **Locale Parameter:** Always include active locale (derived from switcher state / URL / `kant-locale`) in each content query.
- **Browser Locale Bootstrap:** On first visit, if URL has no `lang` and `kant-locale` is missing, detect browser locale from `navigator.language` / `navigator.languages`. If it resolves to Russian (`ru`), initialize active locale as `ru`; if browser locale is not in supported locales, fallback to `en`.
- **Default Locale URL Rule:** Only non-default locale should be written to query string (`?lang=<locale>`). Keep default locale URLs clean.
- **Cache Busting:** Always use `fetchOpts = { cache: 'no-store' }`.

**Example JS:**
```html
<script>
async function loadData() {
  try {
    const isLocal = window.location.protocol === 'file:';
    const baseUrl = isLocal ? 'http://<SERVER_HOST_OR_IP>:8055' : '/api';
    const locale = (window.kantCurrentLocale || 'en').toLowerCase();
    const commonQuery = `access_token=STATIC_TOKEN_12345&sort=id&locale=${encodeURIComponent(locale)}`;
    // Example: fetch modules for homepage
    const modulesRes = await fetch(`${baseUrl}/items/modules?${commonQuery}`, { cache: 'no-store' });
    const modules = (await modulesRes.json()).data;
    // Map modules[index] back to DOM elements...
  } catch (e) { console.error(e); }
}
document.addEventListener('DOMContentLoaded', loadData);
</script>
```

### Step 5.1: Required fetch map per page (KANT)
Use this map as the canonical source of truth. Do not invent additional endpoints or omit required datasets.

#### A) `index.html` (home)
Fetch in this order:
1. `site_settings` (singleton) for footer/social links.
2. `about_project` (singleton) for reusable About block and about modal.
3. `modules`:
   - sort by `order` asc, then `id` asc.
   - render first `N` modules only, where `N` is configurable and constrained to `[3..5]`.
4. `publications`:
   - sort by `published_at` desc, then `display_order` asc, then `id` asc.
   - render first 3 items only.
5. `authors`:
   - sort by `display_order` asc, then `id` asc.

Rules:
- Home hero stays static and is not fetched from CMS.
- Any missing translated value must fallback to `en`.
- Locale initialization priority MUST be:
  1) `?lang=<locale>` from URL,
  2) `kant-locale` from localStorage,
  3) browser locale detection (`ru` if browser is Russian and supported),
  4) fallback `en`.

#### B) `modules.html` (modules list page)
Fetch:
1. `site_settings`.
2. `hero_sections` filtered by `page_key = modules`.
3. `about_project` (same content as home).
4. `modules` full list:
   - sort by `order` asc, then `id` asc.

Rules:
- Render all modules, no top-5 limit here.
- Hide lecture/presentation-specific metadata if missing for each module card.

#### C) Module detail pages (template based on `module-1.html`)
Page identity:
- Resolve module by `slug` or by `module_number` depending on routing strategy.
- Render using the `module-1.html` section structure.

Fetch:
1. `site_settings`.
2. target `modules` record (single item) with all detail fields.
3. `module_transcripts` filtered by `module = current_module_id`:
   - sort by `order` asc, then `id` asc.
4. `module_readings` filtered by `module = current_module_id`:
   - sort by `id` asc unless custom ordering is introduced.
   - include linked `publications` relation when present.

Render rules:
- Lecture section:
  - render only if enabled and has meaningful content (title and/or video URL).
- Presentation section:
  - render only if enabled and has meaningful content.
- Transcripts modal:
  - build list from `module_transcripts`.
- Literature block/modal:
  - render rich text/content from module fields.
- Reading materials cards:
  - if reading has `custom_cover_image`, use it;
  - else if linked publication has `cover_image`, use it;
  - else use default paper icon.
- Reading item target resolution:
  - strict XOR for target source:
    - direct item: exactly one of `custom_url` or `custom_file`.
    - linked publication: exactly one of `publication.url` or `publication.file`.

#### D) `publications.html` (publications catalog)
Fetch:
1. `site_settings`.
2. `hero_sections` filtered by `page_key = publications`.
3. `publication_types`:
   - sort by `id` asc (or explicit order field if added later).
4. `publications` full list:
   - sort by `display_order` asc, then `published_at` desc, then `id` asc.

Render rules:
- Tabs are generated from `publication_types` and localized labels.
- Each publication card:
  - cover image if present, else default paper icon.
  - click target from XOR source:
    - use file download URL if `file` is set,
    - else use external `url`.
- Never render items violating XOR validation.

## Step 7.1: Definition of Done (KANT Integration Checklist)
Before declaring the task complete, verify all checks below:

1. Infrastructure:
   - Directus and PostgreSQL containers are healthy.
   - Nginx proxy `/api/` works without 401/403/503 regressions.
2. Schema:
   - KANT-specific collections from Step 4.KANT exist (including relations).
   - No unrelated generic collections (e.g., `benefits`, `pricing`) were introduced.
3. Localization:
   - All required user-facing text fields are locale-aware.
   - Locale fallback to `en` works when localized content is missing.
   - Browser-locale bootstrap works on first visit:
     - Russian browser -> initial locale `ru` (if supported),
     - unsupported locale -> `en`.
4. Hero behavior:
   - `index.html` hero remains static/read-only (not CMS-editable).
   - Other heroes are populated from `hero_sections`.
5. About Project:
   - `about_project` content is shared and consistent on `index.html` and `modules.html`.
   - About videos and modal rich text render correctly.
6. Modules:
   - Homepage shows first N modules from one shared `modules` collection, with N constrained to 3..5.
   - `modules.html` shows full ordered module list.
   - Module detail pages follow `module-1.html` template layout.
   - Lecture/Presentation sections hide when corresponding data is absent.
7. Transcripts and readings:
   - Transcript modal is populated from `module_transcripts` and file downloads work.
   - `module_readings` enforce XOR for file/link where applicable.
   - Reading material cover fallback works: custom cover -> linked publication cover -> default icon.
8. Publications:
   - `publications` enforce strict XOR (`file` xor `url`).
   - `publications.html` filters by CMS categories (`publication_types`) and ordering works.
   - Homepage publications block shows latest 3 entries.
9. Authors:
   - Authors slider is fed by `authors` collection and sorted by `display_order`.
10. Footer/social:
   - Footer text and social links are loaded from `site_settings`.
11. Frontend stability:
   - No console errors from CMS fetch/render flow on all pages.
   - Missing optional fields degrade gracefully without layout breaks.

## Step 6: Nginx Reverse Proxy (Production Ready)
To ensure the webpage is available publicly on standard port 80 without exposing port 8055 explicitly to browsers (which Adblockers often block), configure `Nginx`.
1. Install Nginx: `apt-get install -y nginx`
2. Configure `/etc/nginx/sites-available/default` (Beware of JavaScript Template Literal `$` variable evaluation errors if automating this! Escape `$uri` with `\$uri` or use plain strings).
```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html;
    index index.html;

    location / {
        try_files $uri $uri/ =404;
    }

    # CRITICAL: the trailing slash in the proxy_pass url is mandatory 
    # to implicitly strip the `/api` prefix from the forwarded path.
    location /api/ {
        proxy_pass http://127.0.0.1:8055/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```
3. Copy local `home.html`, `/css`, and `/assets` directories via SCP/SFTP to `/var/www/html/` on the server.
4. Run `systemctl restart nginx`.
5. Run `ufw allow 80/tcp` (if firewall is active).

## Step 7: Completion
Notify the user that the site is live at `http://<SERVER_IP>/` and provide Admin Dashboard credentials at `http://<SERVER_IP>:8055/admin`.
