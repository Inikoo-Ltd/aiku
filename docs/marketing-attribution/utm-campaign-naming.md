# UTM and campaign naming standard

Proposed. For whoever builds ad links — in-house or agency.

## What capture actually requires today

Checked against `GetTrafficSourceFromUrl` on 7 August 2026. These are the only things that make a
paid click register:

| Channel | Landing URL must contain | Campaign reference taken from |
| --- | --- | --- |
| Google Ads | `gclid` | `gad_campaignid`, if present |
| Meta Ads | `fbclid` **and** `utm_medium=paid` | `utm_campaign` |
| Bing Ads | `msclkid` | none — Bing records no campaign |

`gclid`, `fbclid` and `msclkid` are appended by the platforms themselves when auto-tagging is on. You
do not add them; you must not strip them.

Two notes that correct older internal guidance:

- **`gclid` alone is now sufficient for Google.** An earlier version required `gad_source` *and*
  `gad_campaignid` *and* `gclid`, and clicks arriving with only `gclid` — custom tracking templates,
  certain placement types — fell through to the organic referer fallback. That gap is closed. Adding
  `gad_campaignid` still buys you the campaign breakdown.
- **Bing campaign-level reporting does not exist and is not a bug.** `msclkid` is unique per click, so
  using it as a campaign reference made every single click its own campaign, over-weighted Bing in
  the share split, and matched no imported cost row ever. Bing reports at channel level only.

## The hard constraint

> A campaign row is created automatically **only** for a reference of 1–20 digits
> (`^\d{1,20}$`, `AttachTrafficSourcesToModel`).

Touch strings come from a cookie the visitor controls. Without that restriction anyone could mint
unlimited campaign rows named whatever they liked.

So a readable string in `utm_campaign` — `summer-sale-2026` — **is silently discarded as a campaign
reference.** The click still counts for the channel; it just gets no campaign breakdown. This is why
production currently has exactly one paid campaign row, `21723300927`, alongside 38 `mailshot-N` rows
created server-side.

**Therefore: campaign references stay numeric platform ids. Human readability lives in the campaign
name inside the ad platform, not in the URL.** Anything else needs a code change.

## The standard

### 1. Name campaigns in the ad platform, to a fixed shape

The platform's own campaign name is what a person reads. Lower case, hyphens, five fields:

```
<shop>-<objective>-<audience>-<offer>-<yyyymm>
```

| Field | Values |
| --- | --- |
| `shop` | Shop slug exactly as in Aiku: `uk`, `es`, `freu`, `iteu`, `plsk`, `ade`, `eu`, … |
| `objective` | `acq` (acquisition), `ret` (retention), `brand` |
| `audience` | `trade`, `retail`, `lapsed`, `lookalike`, `remarketing` |
| `offer` | Short slug, no spaces: `teak25`, `backtoschool`, `evergreen` |
| `yyyymm` | Month the campaign starts. |

```
uk-acq-trade-teak25-202608
es-ret-lapsed-evergreen-202609
```

Keep it stable for the life of the campaign. Renaming mid-flight breaks the join between imported
spend and attributed revenue.

### 2. Let the platform supply the click id — auto-tagging on

- **Google Ads:** auto-tagging **on** (Account settings → Auto-tagging). This is what produces
  `gclid`; without it the click is invisible to attribution. Leave the final URL suffix alone unless
  you are adding `gad_campaignid`.
- **Microsoft/Bing Ads:** auto-tagging **on**, producing `msclkid`.
- **Meta:** auto-tagging produces `fbclid`, but Meta clicks additionally require `utm_medium=paid` —
  see below.

### 3. UTM parameters

| Parameter | Google | Meta | Bing |
| --- | --- | --- | --- |
| `utm_source` | `google` | `meta` | `bing` |
| `utm_medium` | `cpc` | **`paid`** (required) | `cpc` |
| `utm_campaign` | platform campaign id | **Meta campaign id, numeric** | platform campaign id |
| `utm_content` | free — ad or creative name | free | free |

Two things are load-bearing and everything else is convenience:

- **Meta must send `utm_medium=paid`.** Any other value and the click is not recognised as Meta Ads.
  It falls through to the organic-referrer check and is credited to organic Meta or dropped entirely.
- **`utm_campaign` must be the numeric campaign id, not the name.** On Meta use the dynamic macro
  `{{campaign.id}}`; the readable name is the one you set in step 1.

Meta URL template:

```
https://<shop-domain>/<landing-path>?utm_source=meta&utm_medium=paid&utm_campaign={{campaign.id}}&utm_content={{ad.name}}
```

Google — auto-tagging supplies `gclid`; add the campaign id explicitly to get the breakdown:

```
?utm_source=google&utm_medium=cpc&utm_campaign={campaignid}&gad_campaignid={campaignid}
```

Bing needs nothing beyond auto-tagging. UTMs on Bing links are harmless documentation.

### 4. Never put these in a campaign reference

`|`, `,` or whitespace. The touch string uses `|` as its separator, so either character inside a
reference shatters the visitor's whole history into garbage on parse. Capture sanitises them to `-`,
but the reference then no longer matches your cost import.

### 5. Email

Mailshot references are generated server-side as `mailshot-<id>` and named from the mailshot subject.
Nothing to configure, and nothing to name by hand.

## Making it show up as a readable name

Auto-created campaign rows are named after their numeric reference. Give them the platform name from
step 1 in one of two ways:

- **The Google Ads ingestion script does it for you.** It sends `campaign.name` alongside
  `campaign.id`, and the webhook creates or names the campaign row from it (see
  [../google-ads-cost-ingestion/README.md](../google-ads-cost-ingestion/README.md)). Name your Google
  campaigns to the step-1 shape and the dashboard reads properly with no further work.
- **The CSV import does not.** `traffic-source:import-costs` has no name column and *fails the row*
  if the campaign reference does not already exist for that source and shop. Use it for spend on
  campaigns that have already been seen in a click.
- Otherwise edit the campaign row's `name` directly. The `reference` must never be edited.

## Known gaps

- **Campaign-level attribution is Google and Meta only.** Bing is channel-level by design.
- **Non-numeric campaign references are dropped.** If you ever need readable references, campaign
  auto-creation needs a server-side allow-list — a code change, not a naming decision.
- **No view-through.** Impression-only exposure is not captured on any channel.
- **`utm_source` and `utm_content` are recorded nowhere.** They are for the ad platform's own
  reporting and cost imports; Aiku attribution ignores them. Only the three parameters in bold above
  change what Aiku records.
