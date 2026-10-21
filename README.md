# Evermonth Web (WordPress plugin)

This plugin puts the new site (`../Web/`) inside the client's WordPress at campamentoevermonth.com.
We do not change DNS or email. If something breaks, switch the plugin off and the old site comes back.

## What it does

- It answers `/` (home) and `/halloween/` with the new pages.
- It sends old links to the new pages with a 301: `/index.html`, `/halloween.html`, `/halloween-m.html`.
  When the site is live, it also sends `/halloween-evermonth/` (the client's old page) to `/halloween/`. It keeps `utm_*` and `fbclid`.
- Short campaign links (`evw_short_links()` in `inc/router.php`) send a 302 to a page with UTMs.
  `/payphone` → `/halloween/?utm_source=payphone&utm_medium=push&utm_campaign=hw26&utm_content=push1`.
- Everything else stays with WordPress: `/wp-admin`, other pages, search, email.
- Images, CSS, JS and video load from the plugin folder (`wp-content/plugins/evermonth-web/site/`).
- The test-link URLs (canonical, og:, JSON-LD) change to the real domain automatically.

## Settings (Ajustes > Evermonth Web)

Both switches start OFF. Activating the plugin changes nothing for visitors.

1. **Vista previa.** The "Ver inicio" / "Ver Halloween" buttons open the new site only for you (admin).
   A small "Vista previa" label shows bottom left. "salir" ends the preview.
2. **Sitio nuevo: en vivo.** The new site replaces the WordPress pages for everyone.
3. **Google.** Removes the `noindex` tag. Only works when the site is live.

Undo: clear "Sitio nuevo" and save. Or deactivate the plugin. Nothing gets deleted.

## First install

1. Build the zip:
```bash
bash release.sh 1.0.0
```
2. In WordPress: Plugins > Añadir nuevo > Subir plugin > `dist/evermonth-web.zip` > Activar.
   The zip is about 29 MB. If WordPress says the file is too big, upload the `dist/build/evermonth-web` folder
   to `wp-content/plugins/` with the hosting file manager instead.
3. Ajustes > Evermonth Web > Ver inicio. Check it. Then switch it live.

## Updates from GitHub

Repo: `Waaaachoo/evermonth-web`. It holds the plugin code. The site files go in the release zip only.

1. Edit `../Web/` as always.
2. Test the zip:
```bash
bash release.sh 1.0.1
```
3. Publish (commit, tag, push, GitHub release with the zip):
```bash
bash release.sh 1.0.1 --publish
```
4. WordPress shows "Hay una versión nueva" within 6 hours. "Buscar actualización" in the settings checks at once.
   Then Plugins > Actualizar. Settings stay.

Rules:
- Every release needs a higher version number. Fix = last number (1.0.0 → 1.0.1). New page or section = middle number (1.0.0 → 1.1.0).
- The repo must be public. The updater downloads the zip without a token.
- The release asset must be named `evermonth-web.zip`, with one top folder `evermonth-web/`. `release.sh` does this.
- Also bump `?v=N` in the HTML when CSS or JS changes (the browser cache rule from the main CLAUDE.md).

## Files

- `evermonth-web/evermonth-web.php` — plugin header, version, settings.
- `evermonth-web/inc/router.php` — which URLs it answers, link rewrite, preview.
- `evermonth-web/inc/updater.php` — reads the latest GitHub release.
- `evermonth-web/inc/admin.php` — the settings page.
- `release.sh` — builds `dist/evermonth-web.zip` from `../Web/` (without `ref/`).
