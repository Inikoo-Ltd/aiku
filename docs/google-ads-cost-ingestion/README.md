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
2. **Your shop code.** This is the short code Aiku uses for the shop the Ads account advertises,
   e.g. `aw` or `uk`. If you do not know it, ask whoever gave you the token — it is written on
   the same message.
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
  SHOP: 'PASTE-YOUR-SHOP-CODE-HERE'
};
```

Replace the two placeholder texts, keeping the quote marks around them:

- `PASTE-YOUR-TOKEN-HERE` → your token.
- `PASTE-YOUR-SHOP-CODE-HERE` → your shop code, e.g. `aw`.

Leave `ENDPOINT` alone unless the message that came with your token gave you a different address
(it will have, if you are setting this up against a test environment).

A filled-in version looks like:

```js
var CONFIG = {
  ENDPOINT: 'https://aiku.io/webhooks/traffic-source-costs',
  TOKEN: '47|kQ8vNc3ZpR9wLxT2mF6yH4bJ0sD1aG5eU7iO',
  SHOP: 'aw'
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
Sending 6 campaign(s) for 2026-08-06, total 153.22 GBP for shop aw.
OK — {"shop":"aw","source":"google-ads","stored":6,"errors":[]}
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
2. The `SHOP` code in the script is not the shop the token belongs to. Check the message the
   token came in.
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

## What actually gets sent (for the curious)

One request per account per day. It contains: the shop code, the word `google-ads`, the account's
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

Other ad platforms (Bing, Pinterest) can use the same endpoint — swap `google-ads` for `bing-ads`,
`pinterest-ads` and post the same shape. The one-off CSV route (`traffic-source:import-costs`) still
exists for backfilling history.

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
