---
paths:
  - 'app/Actions/Web/WebBlock/**'
---

# Web Block

## NEW IN is a system page, not a collection
Since 9 Nov 2026 each b2b, b2c and dropshipping website's /new is the NEW IN system page (sub type new_in_page, websites.new_in_page_id). It holds the same families and product list blocks as the catalogue pages, taken from the website templates (families-4 needs a department, so it gets families-3), and the families and products fillers (GetIrisWebBlockFamilies, GetWebBlockProducts and their workshop twins) hand a NEW IN page over to the GetIrisWebBlockNewIn*/GetWebBlockNewIn* data: products.first_available_at in the last ProductBadgeEnum::NEW_IN_DAYS of the website's shop (GetIrisNewInProducts, WithNewInFamiliesQuery). Never keep NEW IN as a hand-picked collection or store its members. repair:create_system_pages only creates it, unpublished, at /new, or at /new-in when a webpage or a redirect already has /new; it never publishes it or touches the old page. Going live is by hand per website: website:switch_to_new_in_page {slug} [--replace=new-arrivals] moves a page at /new to /new-old, puts the NEW IN page live at /new, points the redirects that led to the old pages at it and closes them with a 301 to it. Arrivals break the page cache when first_available_at is stamped; departures are broken once a night (webpages:break_new_in_pages_cache), never hourly, because each break purges the whole website at the Cloudflare edge.
