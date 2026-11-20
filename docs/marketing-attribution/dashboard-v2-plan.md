# Marketing dashboard v2: plan

Status: phases 1–4 built on 21 October 2026, and W-shaped and U-shaped added the same day. Customer lifetime value (CLTV) next to CAC, and the compare period, added on 22 October 2026. The rest of phase 5 is still to do.

A second marketing dashboard that compares attribution models side by side: **linear** against
**first touch**, **U-shaped** or **W-shaped**. The current dashboard stays exactly as it is. The new page is reachable
by URL only and has no entry in the shop navigation.

The visual reference is the "Aiku Attribution Dashboard" design canvas (three artboards:
Acquisition, Orders, Journey). Its layout is followed. Its sidebar menu is not: the page uses the
standard Aiku `Tabs`, and its colours come from the Aiku theme.

## Goals

- Show how credit moves between channels when the model changes, per channel, in orders and revenue.
- Give paid channels a fair reading on B2B shops, where a buyer finds us through an ad and orders
  days later through search or by typing the address.
- Leave room for more models without reworking the page.

## Non-goals

- No change to the stored attribution. `model_has_traffic_sources` keeps its linear shares, and
  the existing dashboard, listings, hydrators and `marketing_*` views read it as before.
- No navigation entry, no settings screen, and no per-shop model choice.
- No comparison with Google Ads' own data-driven attribution yet. We hold no Google conversion
  data, so the "three lenses" panel in the design is left out.

## Route and access

| | |
| --- | --- |
| URL | `/org/{organisation}/shops/{shop}/marketing/dashboard-v2` |
| Route name | `grp.org.shops.show.marketing.dashboard_v2`, in `routes/grp/web/org/shops/marketing.php` |
| Action | `App\Actions\UI\Dropshipping\Marketing\ShowMarketingDashboardV2` (an `OrgAction`) |
| Page | `resources/js/Pages/Grp/Org/Marketing/MarketingDashboardV2.vue` |
| Tabs | `App\Enums\UI\Marketing\MarketingDashboardV2TabsEnum`: `acquisition`, `orders`, `journey` |
| Permission | Same as the current dashboard: `marketing.{shop}.view` |
| Period | `WithMarketingPeriod`, the same saved interval and `DashboardSettings` bar as the current dashboard |

`GetShopNavigation` is not touched. Each tab's prop is loaded only for the open tab
(`Inertia::optional` otherwise), as the current dashboard does.

## How credit is calculated

Attribution is computed **at read time** from the raw touch history in `customers.traffic_sources`,
parsed with `ParseTrafficSourceTouches`. Nothing is written. Every model reads the same touches, so
the comparison is like for like, and adding a model means adding one class.

### The unit is the order

Orders placed in the period are counted: state not `creating` or `cancelled`, not deleted, and
placed at `COALESCE(orders.submitted_at, orders.date)`, the same `ORDER_PLACED_AT` rule as
`WithTouchCausality`. Revenue is `orders.net_amount`, in the shop's currency, including orders not
yet invoiced.

Each order is split between the touches its customer had **before the order was placed**. Every
order's shares sum to 1.00. An order with no qualifying touch goes to **No touch**, so channels plus
No touch always add up to the number of orders. This is the "Credit check" on the Orders tab.

### Models

| Model | Split of one order |
| --- | --- |
| `linear` | Evenly over every unique channel + campaign pair whose touches qualify under the attribution window: the first touch of the pair is before the order, and the order is no later than the pair's last touch + the shop's window (90 days by default, `GetAttributionWindow`). This matches today's linear reading, measured per order instead of per invoice. |
| `first_touch` | 100% to one touch per customer. For a **new** customer it is the acquisition touch (below), and it claims orders placed within **365 days** of that touch. For an **existing** customer it is the first recorded touch, and it claims orders placed within **90 days** of it. In both cases the touch must come before the order. |
| `u_shaped` | 40% to the first touch, 40% to the last touch before the order, and 20% over the touches between them (below). |
| `w_shaped` | 30% to each of three milestones and 10% over the touches between them (below). |

### U-shaped and W-shaped

Both are position-based: a few milestones of the journey take most of the order, and the touches
between them share the rest. U-shaped uses two milestones, First and Before order, at **40%** each
with **20%** between. W-shaped adds Registration and uses three milestones at **30%** each with
**10%** between. U-shaped here is the e-commerce form (first and last touch). The B2B form, first
touch and registration, would give nothing to touches after registering, and would read almost
the same as first touch.

The milestones of the journey to an order:

| Milestone | Touch | Claims orders |
| --- | --- | --- |
| First | The `first_touch` touch: the acquisition touch of a new customer, or the first recorded touch of anyone else | Within 365 days (new) or 90 days (existing), as `first_touch` |
| Registration (W only) | The **last** touch in the 90 days before registering. New customers only | Within 365 days of it |
| Before order | The last touch before the order | Within the shop's attribution window (90 days by default) |

The share between milestones is split evenly over the touches between the earliest milestone and
the before-order touch that are not milestones of that model themselves (under U-shaped the
registration touch is one of them) and fall within the attribution window. As
in linear, each channel + campaign pair counts once there, through its latest touch.

- A touch that is more than one milestone adds the shares up: the acquisition touch is often also
  the registration touch.
- A milestone that is missing, or a journey with no touches in between, leaves its share out, and
  what remains is scaled up to 100%. Under W-shaped an existing customer has no registration
  milestone, so their order is split first 30 : before order 30 : between 10, which comes to
  43% · 43% · 14%. An order more than 90 days after the last touch has no before-order milestone,
  so under U-shaped a new customer's acquisition touch takes all of it.
- Example: an ad on day 0, then organic search on day 2, registering and ordering the same day.
  W-shaped: the ad is the first touch (30) and organic search is both the registration touch and
  the before-order touch (60), so **33% ad, 67% organic search**. U-shaped: **50% each**.
- An order with no milestone goes to No touch. Both models credit every order that linear or first
  touch credits, so their No touch is never larger than either.

### Acquisition touch: who brought the customer in

The acquisition touch is the **earliest touch in the 90 days before registration**, from
`customers.created_at − 90 days` to `customers.created_at`.

- A touch earlier than that is ignored. If the very first touch was 120 days before registration,
  the earliest touch still inside the 90 days takes its place.
- Touches after registration never acquire anyone.
- Example: a customer clicks an ad today and registers through organic search two days later. The
  customer is acquired by the ad, because the ad is the earliest touch within the 90 days. Organic
  search still shares the credit under linear, but it is not the channel that brought the customer
  in.

A customer with an acquisition touch is **new**. A customer without one is counted as **No first
touch**: no touch at all in the 90 days before registering, or registered before tracking began
on 7 August 2026. For the orders of such a customer, `first_touch` falls back to their first
recorded touch, with the 90-day window of an existing customer.

### Engine

```
app/Enums/CRM/TrafficSource/AttributionModelEnum.php   linear, first_touch, u_shaped, w_shaped
app/Actions/CRM/TrafficSource/Attribution/
    SplitOrderAttribution.php      (touches, registered at, order placed at, model) → shares; acquisition, registration touch and W milestones
    GetShopOrderAttribution.php    Orders tab: every order in the period under every model
    GetShopAcquisitionCohorts.php  Acquisition tab: registrations, buyers, windows, monthly cohorts
    GetShopOrderJourney.php        Journey tab: one order and the customer's other orders, touch by touch
```

`GetShopOrderAttribution` loads the orders in the period, then their customers' touch strings in
chunks, and runs every model over each order. The Orders and Acquisition results are cached per shop
and period for 10 minutes, under a key that names the models, so adding one never serves a cached
result without it. Touches with no timestamp, Direct touches and referral hosts that fail
validation are left out, as `AttachTrafficSourcesToModel` does. A long period on a large shop parses tens of thousands of touch strings, so
now that U- and W-shaped exist the shares should be precomputed into an `order_attribution_shares` table
when an order is submitted. That is a later phase.

## Compare period

A **Compare** button beside the period bar (Acquisition and Orders, not Journey) sets the period to
compare with: **Previous period** (the same number of days right before), **Previous month** and
**Previous year** (the same dates shifted), or **Custom** dates. It lives in the URL (`compare`,
plus `compare_from` and `compare_to` for custom), so it does not touch the interval the other
dashboards share, and a link carries it. The page keeps it when the tab changes. With the period
set to All there is nothing to compare, so the button is disabled.

The compared figures come from the same actions over the other dates, cached the same way, under
`previous` in each tab's prop. A period that starts before tracking began (7 August 2026) is cut
there and says so; one that ends before it shows the dates and no changes. Changes show as ▲/▼
badges, green when good and red when bad (CAC, cost per order and No touch are better down), grey
for spend. In Acquisition the customers of the earlier period have had longer to buy, so their
buyers and CLTV read a little higher.

- **Acquisition:** each KPI (paid LTV:CAC included), CAC per paid channel, and CLTV, CAC and LTV:CAC
  in the lifetime value table.
- **Orders:** orders and revenue, paid orders under the compared model, No touch share, each
  channel's compared-model orders or revenue, and per paid channel ROAS, cost per order, spend and
  orders.
- Cohort growth and Spend and lifetime value by month are already by month and do not change.

## Tab 1 · Acquisition, "who brought them in?"

The cohort is the customers who **registered** in the period. Each one counts whole for the channel
of their acquisition touch, or for No first touch when they have none. This tab does not depend on the
model choice.

- **KPIs:** registrations; new buyers (at least one submitted order) and their rate; brought by
  ads (share of registrations whose acquisition touch is a paid channel, and their buyers); paid CAC
  (ad spend in the period ÷ new buyers from paid channels); No first touch (registrations with no touch in
  the 90 days before registering).
- **Buyers by acquisition channel:** donut and legend per channel, No first touch included.
- **CAC per buyer:** one bar per paid channel.
- **Revenue per buyer:** per channel, with a 30 / 90 / 180 / 365 days since registration toggle. A
  window the cohort has not fully lived through shows "Not reached yet" instead of a partial figure.
- **Customer lifetime value:** per channel, the buyers, the gross margin per buyer so far, the
  projected CLTV, CAC, LTV:CAC and payback, with a 1 / 2 / 3 years toggle (3 years by default, the
  marketing team's horizon). The paid CAC card also shows the paid LTV:CAC. See
  [Customer lifetime value](#customer-lifetime-value) below.
- **Spend and lifetime value by month:** for one paid channel, each registration month with its ad
  spend, buyers, CAC, CLTV and LTV:CAC, to see whether more spend brings buyers worth more.
- **Cohort growth:** for one selected channel (default Google Ads), one row per registration month
  and columns at 30 / 90 / 180 / 365 days, over the last six months since tracking began. A cell is
  **Final** when every customer of the month is past the window, **Growing** when the oldest has at
  least passed the previous window, and **Not yet** otherwise (it would only repeat the shorter
  window). The Revenue per buyer toggle uses the same states.

## Customer lifetime value

CLTV is the gross margin a buyer brings over a set lifetime, counted from their first order, so it
compares with CAC (also per buyer). Revenue would flatter it: CAC is paid from margin.

**Gross margin.** The `profit_amount` and `margin` columns of orders, invoices and their lines are
empty, so margin comes from product cost: each product line's net amount less its quantity times the
current `effective_cost` of the product's master asset, converted with the line's own rate
(`net_amount / grp_net_amount`). Shipping and charges are left out. Lines without a cost (about 1–3%
of product revenue) take the shop's average margin of the last year. The cost is today's, not the
one at the time of sale. `GetOrderGrossMargins` does this for both the hydrator and the dashboard.

**Projection.** Touches exist only from 7 August 2026, so no attributed buyer is old enough for a
lifetime figure. The shop's own history fills the gap. Once a month
`ShopHydrateCustomerValueCurve` (command `hydrate:shop-customer-value-curve {shop?}`, scheduled on
the 1st at 04:00 UTC, queue `hydrators-slave-low-priority` so it runs on litio against the replica)
stores in `shop_crm_stats.customer_value_curve`, for each point of 7, 14, 30, 60, 90, 180, 365,
730 and 1095 days after the first order, how the margin of the buyers whose first order fell in the
two years before that point could be reached grew from each base of 7, 14, 30, 60 and 90 days.

On the dashboard each buyer takes the longest base they have completed (a buyer under 7 days old
uses the 7-day base with the margin so far), and their margin in that base is multiplied by the ratio
to each point. The channel's CLTV is the average per buyer at 365, 730 or 1095 days. Payback is the
day the average cumulative margin per buyer passes CAC, interpolated between points; past 1095 days
it shows "Over 36 months". LTV:CAC is green from 3, amber from 1 and red below 1.

UK on 22 October 2026: average margin 54%; the three-year margin is 3.3 × the first 90 days and
5.6 × the first 7 days. Google Ads since 7 August: CAC 311, CLTV over 3 years 708, LTV:CAC 2.28,
payback 9.4 months.

After a deploy that adds a shop, or to see the figures before the 1st, run
`php artisan hydrate:shop-customer-value-curve` (all open shops) or with a shop slug. Until a shop
has a curve, the section says it is not calculated yet.

## Tab 2 · Orders, "who closed the sale?"

A **Compare linear with** toggle at the top picks the compared model: First touch (default),
U-shaped or W-shaped. Everything below except the last chart follows it.

- **KPIs:** orders split into first and repeat (an order is *first* when it is the customer's first
  submitted order ever); paid ads credit change from linear to the compared model, in orders;
  credit check (Σ shares = order count, for every model); no touch (count and share).
- **Linear vs compared model:** one row per channel with a thin bar for linear and a thick bar for
  the compared model, an Orders / Revenue toggle, and the change in %.
- **Paid channels:** one card per paid channel with ROAS (compared-model revenue ÷ spend), the
  linear ROAS beneath, cost per order, spend and orders.
- **First vs repeat orders:** for each channel, how its credit under the model picked on the card
  (any of the four) divides between first orders and repeat orders.

Spend is the period's `traffic_source_costs`, read the way `GetShopMarketingOverview` reads it.

## Tab 3 · Journey, "every touch counts, some more"

- **Find order:** an order reference search within the shop. The URL carries it (`?order=…`) so a
  journey can be linked to.
- **Header:** the customer, the channel that acquired them (or No first touch), the acquisition-touch
  date, registration date, and their number and value of orders.
- **Per order** (the searched one, plus the customer's other orders, newest first): the touches up to
  that order on a timeline. Each touch is tagged Acquisition where it applies, and with its
  milestones (First and Before order for U- and W-shaped, Registration for W-shaped only). A toggle picks the model whose share fills each
  touch's circle (W-shaped by default); the other models' shares are listed beneath it. Below the
  timeline, a stacked bar per model. Touches outside the 90-day
  acquisition range are drawn faded.
- **Rules:** a short panel stating the active rules: models, windows, the 90-day acquisition range,
  new vs existing, the U-shaped 40 · 20 · 40, the W-shaped 30 · 30 · 30 · 10, and Σ 1.00 per order.

## Look and feel

- `PageHeading` and Aiku `Tabs`, with `DashboardSettings` for the period, as on the current dashboard.
- Interactive colour from the organisation theme: `--app-accent`, `-strong`, `-soft`, `-muted`,
  `-deep`, `-text`. This applies to KPI highlights, the selected toggle and the model badge.
- Model colours are fixed: linear grey `#86857f` (the neutral baseline), first touch `#2a78d6`,
  U-shaped `#e07b1f`, W-shaped `#c2549b`. W-shaped and linear are close for deuteranopia, so the
  comparison always pairs colour with bar thickness, values and a legend.
- Channel colours are a fixed categorical palette that carries meaning, defined once in a shared
  TypeScript map so every chart and legend agrees. Green and red stay for good and bad (ROAS ≥ 1 or
  < 1); amber is for No touch.
- PrimeVue controls: `SegmentedToggle` for the Orders / Revenue and window toggles, `Select` for the
  cohort channel, and `InputText` or `AutoComplete` for the order search. Every string goes through
  `ctrans`.
- Charts follow the design: a CSS conic-gradient donut and CSS bars. No new dependency.

## Files

| Path | What |
| --- | --- |
| `app/Actions/UI/Dropshipping/Marketing/ShowMarketingDashboardV2.php` | Page action, props per tab |
| `app/Enums/UI/Marketing/MarketingDashboardV2TabsEnum.php` | The three tabs |
| `app/Enums/CRM/TrafficSource/AttributionModelEnum.php` | The models |
| `app/Actions/CRM/TrafficSource/Attribution/*` | Splitter and the data of each tab |
| `routes/grp/web/org/shops/marketing.php` | One `GET dashboard-v2` route |
| `resources/js/Pages/Grp/Org/Marketing/MarketingDashboardV2.vue` | Page with tabs |
| `app/Actions/Catalogue/Shop/Hydrators/ShopHydrateCustomerValueCurve.php` | Monthly value curve per shop for CLTV |
| `app/Enums/Helpers/Period/ComparePeriodEnum.php` | Compare modes and their date ranges |
| `resources/js/Components/Marketing/V2/*` | `AcquisitionTab`, `OrdersTab`, `JourneyTab`, `ComparePeriod`, `DeltaBadge`, and `palette.ts` with the chart and channel colours |

## Phases

1. Route, action, tabs enum and an empty page with the period bar.
2. Engine (`linear`, `first_touch`) and the Orders tab.
3. Acquisition tab.
4. Journey tab.
5. W-shaped and U-shaped (done). Still to do: precomputed `order_attribution_shares`; Google Ads
   data-driven comparison.

## Known limits

- CLTV is a projection from the shop's past buyers, not an observation, until the attributed buyers
  are old enough. A month with few buyers swings a lot.
- Touches exist only from 7 August 2026, so a 365-day window cannot fill until August 2027. Cohort
  cells show Growing or Not yet until then.
- Anonymous journeys across devices are lost until the customer logs in, and Direct visits are not
  touches. Both end up in No touch.
- Results follow the touch history as it is today. A login that merges a device's earlier touches
  into a customer can change the split of their past orders. This lasts until shares are
  precomputed at submission (phase 5).
