<x-aiku-public.layout title="aiku — the open source operating system for commerce" description="aiku runs a whole trading business from one codebase: ERP, warehouse and dispatch, 3PL fulfilment, storefronts, marketplaces, dropshipping, CRM, marketing and accounting. Free software, AGPL.">
    <div class="wrap">
        <section class="hero">
            <div class="eyebrow">Free software for people who ship things</div>
            <h1>The open source operating system for commerce.</h1>
            <p class="lede">One codebase that runs wholesale, retail, dropshipping, marketplaces, third‑party fulfilment and your own storefronts — across many companies, warehouses, countries and currencies. Built by a team that runs it every day.</p>
            <div class="actions">
                <a class="btn" href="https://github.com/Inikoo-Ltd/aiku" rel="noopener">Read the source</a>
                <a href="{{ route('aiku-public.blog.index') }}">Engineering notes →</a>
            </div>
        </section>

        <figure>
            <img src="{{ url('art/readme/draw-dashboard.svg') }}" alt="Hand-drawn sketch of the group dashboard: sales, invoices and registrations per organisation" width="1200" height="750" loading="eager">
            <figcaption>The group dashboard, drawn from the real thing. Every screen on this page is a sketch of a real build running on generated data.</figcaption>
        </figure>

        <section class="chapter narrow">
            <h2>Why one system</h2>
            <p>Most trading businesses glue together an ERP, a warehouse system, a shop platform, a marketplace connector, a mailing tool and a CRM — and then spend their lives reconciling them. aiku is all of those at once, with one source of truth: a product is defined once and sold everywhere; stock moves once and every channel knows; a customer is one record whether they bought on your website, on a marketplace or through your sales team.</p>
            <p>It is not a prototype. aiku is the production system behind a multi‑country wholesale and retail operation, and it is released as free software under the AGPL so that anyone can run it, read it, and improve it.</p>
        </section>

        <section class="chapter narrow">
            <h2>Wholesale first</h2>
            <p>Most of what aiku does is the unglamorous core of a trading business: trade customers with credit terms and price lists, B2B orders keyed by sales staff or placed on a trade portal, proformas, invoices and credit notes, purchase orders to suppliers, stock that is valued properly, and a warehouse that picks and packs what was sold — in several companies, currencies and countries at once.</p>
            <p>Retail storefronts, marketplaces and dropshipping sit on top of that core and reuse it. They are real and they work; they are not the point.</p>
        </section>

        <figure>
            <img src="{{ url('art/readme/draw-orders.svg') }}" alt="Hand-drawn sketch of the orders list with states from basket to dispatched" width="1200" height="750" loading="lazy">
            <figcaption>Orders: basket, submitted, picking, packed, dispatched — and every state counted live.</figcaption>
        </figure>

        <section class="chapter">
            <h2>What it does</h2>
            <ul class="modules">
                <li><b>Multi‑entity</b><span>One group, many organisations, shops, warehouses and brands. Shared masters and stock; separate pricing, tax, invoicing and websites per market.</span></li>
                <li><b>Masters &amp; catalogue</b><span>Define a product once at group level — trade units, barcodes, ingredients, GPSR data — and publish it to any number of shop catalogues with their own prices and RRPs.</span></li>
                <li><b>Ordering &amp; billing</b><span>B2B and B2C orders, baskets, pricing rules, offers, credit, VAT, invoices, credit notes, refunds, proformas.</span></li>
                <li><b>Warehouse &amp; dispatch</b><span>Locations, stock valuation, delivery notes, picking routes and sessions, packing, boxes, trolleys, printers, a handheld app, and carrier APIs for DPD, GLS, APC, CTT, Packeta and ITD.</span></li>
                <li><b>Procurement</b><span>Suppliers and agents, purchase orders, stock deliveries, supplier products, landed costs.</span></li>
                <li><b>3PL fulfilment</b><span>Pallet storage, deliveries and returns, stored items, recurring bills and invoicing for third‑party clients, with their own portal.</span></li>
                <li><b>Dropshipping &amp; marketplaces</b><span>Two‑way sync of products, orders and shipments with Shopify, WooCommerce, Magento, TikTok Shop, Amazon, eBay, Allegro and Faire.</span></li>
                <li><b>Websites</b><span>A built‑in CMS and storefront engine: web blocks, banners, menus, merchandised search, SEO, GDPR pages — server‑rendered and cached.</span></li>
                <li><b>Customer portal</b><span>Self‑service for B2B, dropshipping and fulfilment customers: orders, invoices, pallets, channel connections, saved cards, API keys.</span></li>
                <li><b>CRM, marketing &amp; comms</b><span>Customers, prospects, reviews, polls, back‑in‑stock reminders, transactional and marketing email with delivery tracking, live chat, attribution by traffic source.</span></li>
                <li><b>Accounting</b><span>Payments and gateways, payment accounts, top‑ups, invoice categories, financial reports.</span></li>
                <li><b>People &amp; production</b><span>Employees, clocking, timesheets, leave, roles. A manufacture module for artefacts, raw materials and job orders.</span></li>
                <li><b>Analytics, search &amp; AI</b><span>Time‑series metrics, margins, exports; group‑wide search and semantic search; a permission‑scoped MCP server so assistants can answer questions about the business safely.</span></li>
            </ul>
        </section>

        <figure>
            <img src="{{ url('art/readme/draw-warehouse.svg') }}" alt="Hand-drawn sketch of warehouse racks, a picker with a trolley and a picking list" width="1200" height="750" loading="lazy">
            <figcaption>The warehouse: locations, a picking route, a scanner, pick → pack → ship.</figcaption>
        </figure>

        <section class="chapter narrow">
            <h3 style="margin-top:0">And, on the side: channels</h3>
            <p style="color:var(--muted)">The same catalogue can also be listed on marketplaces, or sold by your own customers on their shops (dropshipping), with those orders landing in the same warehouse queue. A small part of the system; a useful one.</p>
            <div class="logos" style="margin-top:16px">
                <img src="{{ url('assets/channel_logo/shopify.svg') }}" alt="Shopify">
                <img src="{{ url('assets/channel_logo/woocommerce.svg') }}" alt="WooCommerce">
                <img src="{{ url('assets/channel_logo/magento.svg') }}" alt="Magento">
                <img src="{{ url('assets/channel_logo/tiktok.svg') }}" alt="TikTok Shop">
                <img src="{{ url('assets/channel_logo/amazon.svg') }}" alt="Amazon">
                <img src="{{ url('assets/channel_logo/ebay.svg') }}" alt="eBay">
            </div>
        </section>

        <section class="chapter narrow">
            <h2>Under the hood</h2>
            <p class="stack">PHP 8.4 · Laravel · Octane · Horizon · Passport · Pennant · Scout · laravel/mcp<br>PostgreSQL + pgvector · Redis · Typesense · Meilisearch<br>Vue 3 · Inertia · Tailwind · Vite · TypeScript · React Native<br>Pest · Playwright</p>
            <p>Around six thousand actions, one per thing the business can do; hydrators that keep every count and total honest at the point of change; time‑series tables for every dimension anyone has ever asked a question about.</p>
            <h3>On AI</h3>
            <p>We believe AI is a tool to empower human ingenuity, not to replace it. aiku ships a permission‑scoped MCP server so assistants can answer questions about the business; the decisions stay with people. <a href="{{ route('aiku-public.blog.show', 'an-mcp-server-for-a-whole-business') }}">How we drew that line →</a></p>
        </section>

        <section class="chapter">
            <h2>Yes, it's real</h2>
            <p style="color:var(--muted);max-width:40em">Some of the other screens, small on purpose. A demo group seeded with generated data; nothing here belongs to a real customer.</p>
            <div class="tease">
                @foreach (['invoices','families','employees','marketing','delivery-notes','warehouse-inventory','departments','masters'] as $thumb)
                    <img src="{{ url("art/readme/thumb-{$thumb}.jpg") }}" alt="" width="320" height="200" loading="lazy">
                @endforeach
            </div>
        </section>

        @if ($posts->isNotEmpty())
            <section class="chapter">
                <h2>Engineering notes</h2>
                <p style="color:var(--muted)">How parts of aiku came to be the way they are. <a href="{{ route('aiku-public.blog.index') }}">All notes →</a></p>
                <ul class="posts">
                    @foreach ($posts as $post)
                        <li>
                            <time datetime="{{ $post['date']->toDateString() }}">{{ $post['date']->format('j M Y') }}</time>
                            <div>
                                <h3><a href="{{ route('aiku-public.blog.show', $post['slug']) }}">{{ $post['title'] }}</a></h3>
                                <p>{{ $post['summary'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-aiku-public.layout>
