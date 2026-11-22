# Adding a website language to a shop

How a shop's website, catalogue and sales channels get a second language, for example German on
aw-dropship.eu (shop 26, `dssk`, website `awdsk`). It was first done in INI-481 and INI-510. Follow the
same steps for every new language.

## How it works

- **The visitor's language.** It comes from `?locale=xx` (`SetWebsiteLocale`), or from the language a
  signed-in customer chose (`SetWebUserLocale`). It is stored on the request (`App\Helpers\WebsiteLanguage`),
  so queued jobs, commands and the staff app always see the shop's own language.
- **Fallback.** Every text falls back to the shop's own text field by field. A missing translation shows
  English (or the shop's language), never an empty space.
- **Caches.** The page cache, sidebar cache and "last ordered" cache are kept per language. Varnish and
  Cloudflare already key on the full URL, so `?locale` is split there too.
- **Where the texts live:**
  - **Products, departments, sub-departments, families, collections:** the `name_i8n`, `description_i8n`
    and `description_extra_i8n` jsonb columns, keyed by language code.
  - **Web blocks, header, top bar, menu, footer, sidebar, webpage titles:** the `website_text_translations`
    table, one row per English text per language. A row written by a person is never replaced by the AI.
- **Languages on the shop:**
  - `settings.enabled_languages`: every customer can choose them.
  - `settings.preview_languages`: only staff customer accounts (`customers.is_staff`) are offered them,
    but anyone who opens a `?locale=xx` link still sees the page in that language.
  - `channel_languages`: customers may give each sales channel (Shopify, eBay, ...) its own language.

## Recipe

Run every command on production from `~/aiku/current`. Run each one first without `--apply` (a dry run)
and read the output, then run it again with `--apply`.

The dry run of the catalogue commands still sends the texts to the AI and pays for them. For a large
shop, use `--limit=20` on the dry run.

### 1. Add the language as a preview language

In the shop's settings (Edit shop), add the language under "Languages staff can preview on the website".
Customers are not offered it yet. Staff check it on `https://<website>/?locale=xx` and on their own staff
customer accounts.

### 2. Product texts

```bash
php artisan products:fill-translations <shop> <xx> --limit=20
php artisan products:fill-translations <shop> <xx> --apply
```

- Texts are copied from the open shop of the same organisation that is already written in that language,
  when there is one (`--source-shop=` picks it). Anything else is machine translated.
- Bundles are skipped.
- A product whose answer fails the checks keeps the shop's text and is marked `failed`. The checks are:
  numbers lost, HTML tags changed, placeholders changed, not translated, empty.
- The command runs in the foreground and takes hours for a large shop. Run it inside tmux or under a
  long timeout. If it is cut off, run it again: products already filled are skipped.

### 3. Department, sub-department, family and collection texts

```bash
php artisan product-categories:fill-translations <shop> <xx> --limit=20
php artisan product-categories:fill-translations <shop> <xx> --apply
```

### 4. Website texts (web blocks, header, footer, menu, sidebar, titles)

```bash
php artisan website:translate_texts <website-slug> --locale=xx --dry-run
php artisan website:translate_texts <website-slug> --locale=xx
```

This runs on the low-priority queue; awdsk took about 80 minutes for 4,340 texts. After that, each publish
of a webpage, header, footer, menu, sidebar or template translates only the texts that have no row yet.

### 5. Retry the rejected answers

```bash
php artisan products:fill-translations <shop> <xx> --retry-failed --apply
php artisan product-categories:fill-translations <shop> <xx> --retry-failed --apply
```

Each run starts again from the first failed product. A run that is cut off re-pays for the products it had
already retried, so give it enough time to finish.

Reasons that come back on every retry are real:
- the English contradicts itself (the name says 200g, the description says 190g);
- a stray typo ("attention.0");
- a measure the AI converts (`10"` to 25 cm, `6oz`).

A person fixes the English text or writes the translation in the staff app.

### 6. Check coverage

```sql
select count(*) filter (where coalesce(name_i8n->>'xx','') = '') as no_name
from products
where shop_id = <shop> and state = 'active' and not is_bundle;
```

The shop's language readiness page (`GetShopLanguageReadiness`) shows the same numbers per kind.

### 7. Test

- Test the storefront in that language: home page, a family, a product, search, basket and checkout.
- Test the language switch for a signed-in customer.
- Test every channel integration with a staff customer account whose channel uses the new language.

### 8. Open it to customers

Move the language from preview to enabled languages in the shop settings. The website cache is broken
when the shop's languages change.

## Not covered yet

Each of these is in progress under INI-510, and this list is updated as each one lands:
- search in the visitor's language;
- banners and meta descriptions;
- the `/app` customer area header;
- staff editing of translations;
- tags, FAQ and the labelling guide;
- the cookie banner (a setting in the CookieScript account).
