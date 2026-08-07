# Reading the numbers

For marketing and management. No technical knowledge assumed.

---

## The two figures everyone asks about

**ROAS — return on ad spend.** Revenue a channel earned, divided by what you spent on it. ROAS 4
means every €1 of spend brought back €4 of sales. It is a ratio, not a profit: it says nothing about
margin, returns, or the cost of the goods.

**CAC — cost per acquired customer.** Spend divided by the number of new customer registrations that
channel earned. It answers "what did it cost us to win one new account?"

Both need spend. **No spend imported, no ROAS and no CAC — the screen shows a dash.** That is the
current state of every shop: no advertising cost has been imported yet. A dash is not a zero and not
a fault.

## Two screens, two different questions

| Screen | Question it answers | Time span |
| --- | --- | --- |
| Marketing dashboard | "How did we do lately?" | The selected period. **Defaults to the last 30 days.** |
| Traffic sources listing | "What has this channel ever earned us?" | All time, always. |

They will not match, and are not meant to. Choose "All time" on the dashboard's period selector if
you want to compare them. The dashboard offers last 7 / 30 / 90 days, month to date, year to date,
and all time.

---

## Why the revenue looks so low

This is the single most important thing on this page.

**A channel is credited only with revenue invoiced *after* the touch that claims it, and within 90
days of that touch.**

Before this rule existed, the system credited a channel with everything the customer had *ever*
spent. A customer who registered in 2022 and clicked a newsletter link yesterday handed that
newsletter **€22,410** of trade the newsletter had nothing to do with. Applied across production,
that inflation was the difference between a headline **€652,672** and the real **€1,439**.

The smaller number is the true one. It says: *this is what the channel earned*, not *this is what its
customers happen to have spent over their lifetime*.

Two consequences to plan around:

1. **The numbers start near zero and build.** Only trade after a recorded touch counts, and touches
   have only been recorded since 7 August 2026. Expect weeks of thin figures. This is the system
   filling up, not the system failing.
2. **Wholesale reorder cycles are slow.** 90 days is chosen to suit them (Google Ads itself defaults
   to 30). A customer who clicks in March and reorders in August is genuinely outside the window, and
   that August order is not the March click's doing.

The window can be set per shop if a shop's buying rhythm is different — ask engineering; it is a
settings change, not a code change.

## Share-weighting: why you will see 2.5 registrations

A customer usually meets you more than once. If two channels touched them, **each channel gets half
a customer, half their orders, half their revenue.** Three channels, a third each.

That is why the screens show fractions — production currently shows registrations like `2.50`,
`0.50`, `14.66`. A registration of 0.50 is one real customer whom this channel shares with another.

The rule this guarantees: **every customer's shares add to exactly 1.00.** No channel can claim a
customer another channel already claimed. Add up every channel of a shop and you get the shop's real
total — never a number larger than reality. Alternative to a "the last click gets everything" model,
which is easier to read but systematically over-credits whichever channel happens to close.

---

## What this system cannot tell you

Be sceptical of any conclusion that depends on one of these.

- **No cross-device tracking before login.** Touches are stored in the browser. Someone who sees the
  ad on their phone and buys on their laptop looks like two different visitors — until they log in,
  at which point the two histories are merged. Anonymous mobile→desktop journeys are simply lost.
- **No view-through attribution.** An ad someone saw but did not click counts for nothing. Display
  and social awareness campaigns will always look worse here than in the ad platform's own reporting,
  which does count impressions. This is not a like-for-like comparison and never will be.
- **Organic is only what we can recognise.** A visitor is credited to organic search or social only
  if their browser tells us they arrived from a known site — Google, Bing, Facebook, Instagram,
  Threads, Messenger, WhatsApp, YouTube, LinkedIn, Pinterest, TikTok, Twitter/X. Everything else —
  typed the address, a bookmark, a link in a WhatsApp message on a phone, a private browsing session,
  a referrer the browser withheld — records no touch at all and is invisible.
- **Cleared cookies erase history.** A visitor who clears their browser data starts again from
  nothing.
- **Nothing before 7 August 2026.** There is no historic data to recover, and no way to backfill it.
- **Email counts clicks, not opens.** Only a click on a newsletter or marketing mailshot is a touch.
  Clicks in order confirmations, dispatch notices and invoices are deliberately ignored — those are
  service emails to a customer who already bought.

## Sanity-checking a figure that looks wrong

Open the customer's page and go to the **marketing journey**. It shows every recorded touch and every
invoice on one timeline, plus the resulting split.

Work through it in this order:

1. **Is there a touch at all before the invoice?** If the first touch is after the sale, that sale is
   correctly not credited to it.
2. **How many days between the touch and the invoice?** More than the window (90 by default) and the
   revenue is correctly excluded.
3. **How many channels touched this customer?** That is your denominator. Two channels and this
   customer contributes 0.5 to each — the halved figure is right.
4. **Is the channel you expected missing entirely?** Then no touch was recorded. Check the list of
   blind spots above before assuming a bug; most "missing" traffic is untrackable traffic.

If the timeline itself contradicts the totals, that is a real fault — see
[troubleshooting.md](troubleshooting.md).

## Rules of thumb

- Compare channels within the same period, never one channel's all-time against another's 30 days.
- Do not compare this system's revenue to the ad platform's own. They measure different things, count
  impressions differently, and use different windows.
- Treat a channel with very few registrations as noise. Under about twenty, one large order moves the
  ROAS by a multiple.
- Revenue is measured from **invoices**, not orders. An order placed but not yet invoiced counts for
  nothing yet.
