# Sending Google Ads spend to Aiku automatically

Aiku already knows how much money each advertising campaign **made**. It does not know how much
each campaign **cost** until someone tells it. That is what this page is about.

Once spend is arriving, the marketing dashboard can show ROAS — return on ad spend, i.e. revenue
divided by cost. Until then ROAS shows a dash, because dividing by a cost nobody has entered is
not possible.

The old way was to export a CSV from Google Ads every month and hand it to a developer. The new
way is a small script that lives inside your Google Ads account and sends yesterday's spend to
Aiku every night, on its own, forever. Setting it up takes about ten minutes, once.

**Who this is for:** anyone who manages a Google Ads account for one of our shops — our own
marketing staff, or an outside agency. You do not need to understand any code. You will copy
some text, paste it in two places, and press a few buttons.

---

## Before you start

You need three things:

1. **Access to the Google Ads account** with permission to create scripts. In Google Ads terms
   this is "Standard" or "Admin" access. If you can see **Tools & Settings** in the top menu and
   there is a **Scripts** entry under **Bulk Actions**, you have it.
2. **Your shop slug.** This is the short name Aiku uses for the shop the Ads account advertises,
   e.g. `uk`. It is written on the same message as your token — use exactly what it says, in
   lower case.

   Two things it is **not**, both of which get you rejected with a 403:

   - It is not the website address. `ancientwisdom.biz` is the website `aw`, but the shop it
     belongs to is `uk`.
   - It is not the shop's display code. Aiku matches this value exactly, letter for letter, so
     the code `UK` is not accepted where the slug `uk` is meant.
3. **Your token.** This is a long secret string, roughly like
   `47|kQ8vNc3ZpR9wLxT2mF6yH4bJ0sD1aG5eU7iO`. See the next section for how to get one.

---

## Getting your token

You cannot generate the token yourself. Ask the Aiku team (or your contact at Inikoo) for
**"a traffic source cost token"**, and tell them:

- which shop it is for, and
- who will hold it (your name, or your agency's name, e.g. "AW Advantage").

They run one command and send you back a token. Some things worth knowing about it:

- **A token only works for one shop.** It physically cannot write costs for any other shop, even
  by accident, even if you change the script. If you manage three of our shops you get three
  tokens.
- **It is shown once.** Aiku stores only a scrambled version, so nobody — including us — can read
  it back to you later. If you lose it, we issue a new one and cancel the old one.
- **It is named after you.** If your token ever leaks it is cancelled on its own, and nobody
  else's uploads stop.
- **Treat it like a password.** Do not email it around, do not put it in a shared doc, do not
  paste it into a support ticket. It goes in one place: the script.

If you ever suspect it has been seen by someone who should not have it, tell us immediately —
cancelling it takes seconds.

---

## Setting up the script (single Google Ads account)

Do this once per Google Ads account.

### 1. Open the Scripts page

1. Sign in to Google Ads and make sure you are looking at the **right account** — the account
   name and its ten-digit number appear at the top right. If you manage several, switch to the
   one you are setting up now.
2. In the top menu bar click **Tools & Settings** (the spanner icon).
3. In the column headed **Bulk Actions**, click **Scripts**.
4. You will land on a page listing scripts (probably empty). Click the round blue **+** button.

### 2. Paste the script

1. You now see a code editor with a few lines of sample text in it, and a name field at the top
   left saying something like "Untitled script".
2. Click in the name field and rename it to **Aiku daily cost upload**. This matters only so the
   next person knows what it is.
3. Click anywhere inside the big code area, select everything (Ctrl+A on Windows, Cmd+A on Mac)
   and delete it. The box should be completely empty.
4. Open the file `google-ads-script.js` that came with this guide, select all of it, copy it, and
   paste it into the empty box.

### 3. Fill in your three settings

Near the top of what you just pasted there is a short block that looks like this:

```js
var CONFIG = {
  ENDPOINT: 'https://aiku.io/webhooks/traffic-source-costs',
  TOKEN: 'PASTE-YOUR-TOKEN-HERE',
  SHOP: 'PASTE-YOUR-SHOP-SLUG-HERE'
};
```

Replace the two placeholder texts, keeping the quote marks around them:

- `PASTE-YOUR-TOKEN-HERE` → your token.
- `PASTE-YOUR-SHOP-SLUG-HERE` → your shop slug, e.g. `uk`.

Leave `ENDPOINT` alone unless the message that came with your token gave you a different address
(it will have, if you are setting this up against a test environment).

A filled-in version looks like:

```js
var CONFIG = {
  ENDPOINT: 'https://aiku.io/webhooks/traffic-source-costs',
  TOKEN: '47|kQ8vNc3ZpR9wLxT2mF6yH4bJ0sD1aG5eU7iO',
  SHOP: 'uk'
};
```

### 4. Authorise it

Google will not let a script talk to the outside world until you say it may.

1. Click **Authorise** (or **Authorize**) — the button is above the code area.
2. A Google sign-in window pops up. Choose the account you are already signed in with.
3. You will see a warning that the script wants to "connect to an external service". That is
   Aiku. Click **Allow**.

If you skip this, the script fails the first time it runs with a permissions error.

### 5. Test it once, by hand

1. Click **Preview** (some accounts call it **Run without changes** or **Run preview**).
2. Wait — it usually takes ten to thirty seconds.
3. Look at the **Logs** panel that appears at the bottom.

You want to see two lines roughly like:

```
Sending 6 campaign(s) for 2026-08-06, total 153.22 GBP for shop uk.
OK — {"shop":"uk","source":"google-ads","stored":6,"errors":[]}
```

`stored: 6` means six campaign-days of spend were saved. If you see something else, jump to
[When it goes wrong](#when-it-goes-wrong) below.

If the log says "No campaign spend on … — nothing to send", that is not an error: the account
genuinely spent nothing yesterday. Try again after a day with spend.

### 6. Schedule it daily

1. Click **Save**.
2. Back on the Scripts list, find your script's row and look at the **Frequency** column. It says
   something like "Not scheduled" or shows a clock icon — click it.
3. Choose **Daily**, and pick a time. **Early morning, between 05:00 and 08:00 account time, is
   the right answer.** Google finalises the previous day's figures overnight, and running before
   they settle can send a slightly low number.
4. Click **Save**.

That is the whole setup. From tomorrow it runs on its own.

---

## Checking it worked

The next morning, do two checks.

**In Google Ads:** go back to **Tools & Settings > Bulk Actions > Scripts**. Your script's row
shows the last time it ran and whether it succeeded. A red exclamation mark means the last run
failed — click the row to read the log.

**In Aiku:** open the shop, go to the **Marketing** dashboard, and pick a date range that
includes yesterday. You should now see:

- a **Cost** figure that is no longer zero,
- a **ROAS** figure — a number like `4.2x` — where it previously showed a dash,
- the same for the individual campaigns in the campaign table underneath.

Costs may take a couple of minutes to appear after the script runs, because Aiku recalculates the
totals in the background.

Two things that are normal and not worth reporting:

- **The cost in Aiku is in the shop's currency, the one in Google Ads is in the account's.** If
  the Ads account bills in EUR and the shop sells in GBP, the numbers will not match — Aiku
  converts using the exchange rate of the day the money was spent, on purpose, so that last
  quarter's ROAS does not change every time the exchange rate moves.
- **A campaign shows cost but no revenue.** That campaign genuinely brought no orders in the
  period, or brought them outside the attribution window.

---

## When it goes wrong

Everything the script does ends up in its log. Open **Tools & Settings > Bulk Actions > Scripts**,
click the script, and read the **Logs** panel. Match what you see below.

### "Aiku rejected the token (403)"

One of three things:

1. The token was typed or pasted incompletely. It must include the number and the `|` bar at the
   start. Re-copy it in full.
2. The `SHOP` value in the script is not the shop the token belongs to. It must be the shop
   **slug**, matched exactly and in lower case — not the website address, and not the shop's
   display code. Check the message the token came in.
3. The token has been cancelled. Ask for a new one.

### "Aiku could not read the data (422)"

Something in the request did not make sense to Aiku. The message after the code says what — most
often it is a shop that has no Google Ads traffic source set up yet, or a currency Aiku does not
know. Send us the log line; this one is on our side to fix.

### "Aiku returned 500" or a network / timeout error

Aiku had a problem, or was briefly unreachable. Do nothing. The next night's run sends yesterday
again, and if you want the missed day back, just press **Preview** by hand — it is safe (see
below). If it fails for two nights running, tell us.

### "authorisation" or "permissions" error

Step 4 was skipped or was authorised by a Google user who has since lost access to the account.
Click **Authorise** again while signed in as a user with access.

### The script runs fine, but Aiku still shows no cost

- Check you are looking at the right shop and a date range that includes the uploaded day.
- Check the log actually says `stored` with a number above zero.
- Give it five minutes and reload.

If it is still empty, tell us the shop, the date, and the log line — that is everything we need.

### Is it safe to run it again?

Yes, always. Aiku stores at most one figure per campaign per day: sending the same day again
**replaces** that figure, it never adds to it. That is deliberate — Google revises the previous
day's costs slightly for a day or two after the fact, and the later number is the better one. So
running the script twice, or re-running it after a failure, cannot inflate your spend.

---

## One script per account, or one script for everything?

Both work. This is the question to answer before you set anything up.

### Per-account scripts

Each Google Ads account gets its own copy of `google-ads-script.js`, with its own token, its own
schedule, and its own log.

**Use this when** different people or different agencies manage different accounts, or when an
account is managed from outside your manager account.

- Whoever manages an account holds exactly one token, for exactly one shop, and never sees anyone
  else's.
- If a token leaks, one shop stops uploading until it is replaced. Nothing else is affected.
- Handing an account over to another agency means: issue a new token, cancel the old one. Done.
- The cost is repetition: setting up ten accounts means doing the ten-minute setup ten times, and
  checking ten scripts when something breaks.

### One MCC (manager-account) script

`google-ads-script-mcc.js` runs in the manager account and loops over the child accounts you list
in it, uploading each one.

**Use this when** one team manages all the accounts from a single manager account.

- One script, one schedule, one log with a line per account. Adding an account is one line.
- **The token scoping does not change.** There is no "manager token" that can write to every
  shop. The MCC script holds a list of accounts, and each entry carries the token for *its* shop.
  Aiku deliberately provides no way to create a token that spans shops — spend is written against
  the shop the token belongs to, and no other.
- The trade-off is concentration: the manager script contains several tokens in one place, and
  anyone with edit access to that script can read all of them. Anyone able to edit a script in
  the manager account can already run reports across every child account, so this does not open
  a new door, but it does mean a compromised manager account means re-issuing every token in the
  list rather than one.
- Second trade-off: shared blast radius. A mistake in the manager script stops every account's
  upload, not one.

### What we recommend

**Run the MCC script for the accounts your own team manages centrally, and give each external
agency its own per-account script with its own token.**

Concretely: AW Advantage manages `ancientwisdom.biz` externally, so they get their own token and
their own copy of the single-account script, in their own Ads account, and nothing they hold can
touch any other shop. Accounts managed in-house from our manager account go in the MCC script's
list, where one schedule and one log cover them all.

The two models mix freely. The endpoint cannot tell them apart and does not care — it only ever
sees a token, and a token is only ever good for one shop.

---

## Microsoft Advertising (Bing) ads

Microsoft Advertising has its own Scripts feature, so Bing works exactly like Google: a script inside
the ad account pushes yesterday's spend to the same endpoint. Two files come with this guide,
`bing-ads-script.js` for a single account and `bing-ads-script-mcc.js` for a manager account, and the
setup is the same ten minutes as above.

**The token is the shop's, not the platform's.** A shop that already uploads Google Ads spend needs
no second token: the same one posts `bing-ads` from the Microsoft script. Ask for a new token only
when a different person or agency runs the Bing account, which is the point of one token per holder.

Where to paste it:

- **Single account:** **Tools > Scripts**, then the **+** button. Same authorise, preview, and
  schedule buttons as Google.
- **Manager account:** the Scripts editor reached from **Accounts Summary**. Multi-account scripts
  only exist there — the editor inside a single account has no way to reach the other accounts. Each
  entry in `ACCOUNTS` takes the account number shown in Accounts Summary (`accountNumber`) or the
  numeric account id (`accountId`), plus its shop and that shop's token.

### Tag the ads, or campaign rows stay empty

A Bing click arrives with `msclkid` and nothing else: unlike Google's `gclid`, it names no campaign.
Aiku therefore reads the campaign from `utm_campaign`, and only when it is Microsoft's dynamic
`{CampaignId}` parameter — the numeric id, which is what the script uploads. Set the account's
tracking template or final URL suffix to carry `utm_medium=paid&utm_campaign={CampaignId}` before
turning the script on.

Without it nothing breaks: the Bing channel total and its ROAS are still right, because every click
is still a Bing Ads click. Only the campaign rows show cost against no revenue. A `utm_campaign`
holding a campaign *name* rather than the id is ignored on purpose — it would never meet the cost
row that the id keys, and would quietly split one campaign into two.

### Two things the log will tell you

Microsoft Advertising Scripts has no equivalent of Google's `report()` call, so the script adds up
what the campaign selectors report instead. That has two visible consequences.

- **Campaign type is mostly blank.** Microsoft has separate selectors for Shopping and Performance
  Max, and those two are labelled; everything else comes from one mixed list that does not say what
  kind of campaign it holds, so Aiku records no type rather than guessing `SEARCH`.
- **Check the date on the first Preview run.** Scripts does not document the time zone it runs in,
  so the script resolves yesterday in the account's own zone and prints both the date and the zone.
  Compare that date with the day Microsoft Advertising itself shows, once. If they differ, tell us
  and we will pin the zone in the script.

---

## What actually gets sent (for the curious)

One request per account per day. It contains: the shop slug, the word `google-ads`, the account's
currency, and one line per campaign with the date, the Google campaign id, the campaign name, the
campaign type and the amount spent. No customer data, no order data, nothing personal. Campaign
ids are the same numbers Google shows in your Ads account, which is how Aiku lines the cost up
against the revenue it already attributed to that campaign.

The campaign type is Google's own word for what kind of campaign it is — `SEARCH`, `SHOPPING`,
`VIDEO` (that is YouTube), `PERFORMANCE_MAX` and so on. It changes nothing about how the money is
reported: all of it still counts as Google Ads, because a click arriving on the website carries no
such label and there is no honest way to tell a YouTube visitor from any other Google Ads visitor.
It is recorded so that the question "how much of our Google Ads money actually goes on YouTube?"
can be answered from the figures rather than from memory. If that share turns out to be large, a
separate YouTube channel becomes worth building; today there is nothing to measure it with.

If you set up your script before this was added, it keeps working exactly as before — the type is
simply left blank. Re-paste the script when convenient.

### Issuing and cancelling tokens (Aiku team only)

```
php artisan traffic-source:cost-token <shop-slug> "AW Advantage"   # mint, prints the token once
php artisan traffic-source:cost-token <shop-slug> --list           # ids, names, last used
php artisan traffic-source:cost-token --revoke=<id>                # cancel one, immediately
```

The mint command prints the exact endpoint URL for the environment it runs in — send that along
with the token, and the shop slug.

Bing has its own pair of scripts against the same endpoint (see
[Microsoft Advertising (Bing) ads](#microsoft-advertising-bing-ads)); any other platform can post the
same shape with `pinterest-ads` and so on in `source`. The one-off CSV route
(`traffic-source:import-costs`) still exists for backfilling history.

### Meta (Facebook/Instagram) ads

Meta has no equivalent of Google Ads Scripts, so nothing can push from inside the platform. Aiku
pulls instead, nightly at 06:00 UTC:

```
php artisan traffic-source:fetch-meta-costs                    # every configured shop, yesterday
php artisan traffic-source:fetch-meta-costs uk --days=30 --dry-run
```

To connect a shop, set `settings.meta_ads.ad_account_id` on it (the digits of the ad account) and
put a Business Manager **system user** token in `META_ADS_ACCESS_TOKEN`. One system user token
normally covers every ad account in the business; a shop whose account belongs to somebody else's
business — an agency's — can carry its own in `settings.meta_ads.access_token`.

One thing has to be done on the Meta side for **campaign-level** figures to line up: the ads' URLs
must carry `utm_medium=paid` and `utm_campaign={{campaign.id}}`, Meta's dynamic parameter for the
campaign id. That is what the click already stores, and what the spend is keyed on. Without it the
channel total and its ROAS are still right, but campaign rows show cost against no revenue.

#### Instagram as its own channel

Instagram ads are bought in the same ad account as Facebook ones and have no separate API, so both
sides of the split come from a parameter rather than a second integration:

- **Cost**: the Insights call is broken down by `publisher_platform`. Rows served on Instagram go to
  the `instagram-ads` source; Facebook, Audience Network, Messenger and Threads are summed back into
  `meta-ads`.
- **Revenue**: the ads' URLs carry `utm_source={{site_source_name}}`, which Meta fills in with `ig`,
  `fb`, `an`, `msg` or `th`. A paid Meta click whose `utm_source` is `ig` is classified as
  `instagram-ads`, everything else stays `meta-ads` — the mirror image of the cost rule.

**`utm_source={{site_source_name}}` is what makes the split honest.** An ad that omits it has all of
its Instagram clicks counted as Facebook while its Instagram spend is counted as Instagram, so both
channels' ROAS are wrong. Check the account's URL parameters before turning anything on: as of Aug
2026 every paid Meta click reaching Aiku carried it.

Instagram campaign references are the Meta campaign id prefixed with `ig-`, because a campaign
reference is unique across all sources and the Facebook half of the same campaign already holds the
bare id. Both the click and the spend build the same prefixed string, so they still meet on one row.

Two things do not split retroactively. Clicks recorded before the change are all `meta-ads`,
including the Instagram ones, and attribution windows are long — so **do not backfill Meta costs
across the switch-over date** (`--days=30` over it moves Instagram spend off revenue that is still
filed under Meta). Run `traffic-source:seed` before the first fetch, or the command stops with
`shop has no instagram-ads traffic source`.
