---
title: What people type into the search box
summary: We replaced a third-party storefront search with our own — Typesense, typo tuning measured against real queries, synonyms shared across fourteen languages, merchandising boosts for staff, a hybrid semantic arm tuned with a harness instead of a hunch — and then turned the queries that found nothing into a shopping list for the buyers. Total AI spend for the whole programme: about six cents.
date: 2026-08-05
tags: search, typesense, iris, ai, merchandising
---

For years the search box on our storefronts was a hosted third‑party service. It worked, it cost money, and it kept the most interesting data we had — *what people wanted and could not find* — in somebody else's dashboard. In the summer of 2026 we brought it home. This note is what we learned doing it.

## Our own index, our own rules

The storefront search runs on Typesense: one collection per shop, fed from the same product data the warehouse uses, including the barcode (the first "no results" bug we fixed was trade customers typing a barcode from a box — the field simply was not in the schema). Queries go through a single action that builds a multi‑search — products, categories, content — in one round‑trip, with a fallback to the plain driver if the engine is unreachable, so a search outage is a degraded search, not a dead page.

Every search is logged: the query, how many results, whether the user clicked anything, and — once we added a semantic arm — how many hits came from the keyword side and how many from the vector side. That log is the point of the whole exercise.

## Typo tolerance, measured rather than guessed

Typesense's typo tolerance has a handful of knobs. We tuned them against two lists that came with the old provider's exports: 14,600 queries that had returned nothing, and the ~700 queries that work today and must keep working. Splitting and joining tokens (so "aromcandles" finds aromatherapy candles) and the minimum word length before a second typo is allowed were the two that mattered. **Tuning alone fixed 69% of the historical no‑result queries**, and the regression list lost nothing.

We then measured one more notch — allowing two typos only from seven letters instead of six — and found it was free: zero trending queries lost, zero top hits moved, and half the junk queries ("guitar" on a giftware shop) stopped returning five hundred irrelevant products. Measure one notch at a time; the notch after that is where it costs.

## Synonyms, shared across fourteen languages

The queries that tuning could not rescue were mostly vocabulary: a stone's alternative name, a regional word for a product type. We had a language model propose synonyms from the no‑result list, a human validated them, and 371 survived — across fourteen languages. They live in one synonym set per language, shared by every storefront in the group that speaks that language, so a synonym learned on one shop helps the next one silently.

Staff can add or prune synonyms from the search analytics page. A weekly job proposing new ones from fresh no‑result queries, staged behind an approve button, is the next step.

## Boosts: merchandising, not magic

Management wanted a hand on the tiller. So the search analytics page has a rocket button: pick up to three products to boost for a query, and they float to the top — unless they are out of stock, in which case they are excluded automatically. Three is a deliberate limit; a boost list longer than that is a second ranking algorithm nobody documented.

## The semantic arm, and the harness that tuned it

The seductive part of modern search is the vector arm: embed the products, embed the query, and "shungite" finds crystals even if no product says shungite. Our pilot rescued 13 of 13 dead queries and made vague intents work ("gift for mum"). It also, with the default settings, returned a hundred nearest neighbours for every query that had no good answer. A tail like that is worse than no results.

So we built a harness: embed every live product on one shop, sweep the distance threshold against twenty known‑good queries, fifteen known‑junk queries and the 250 most common real no‑result queries, and pick the threshold by F1. Two findings were worth the day it took:

- **Thresholds are model‑specific and do not transfer.** The same "0.6" that was right for one embedding model was nonsense for another, which compresses distances into a tenth of the range. Tune per model, every time.
- **Hybrid adds less than the pilot implied — once typo tuning is on.** Measured against the real production query configuration, the semantic arm newly rescued one typo query; the typo tuning had already caught the rest. Where it genuinely wins is the *assortment gap*: the things customers ask for that we do not sell, which the keyword arm can never find and the vector arm finds as "the nearest thing we do sell".

We chose the small multilingual model that the search engine can run in‑process: no external API call on the customer's search path, no key, one model for every language, and it embedded nine thousand products in nine seconds on a laptop. The larger hosted model separated good from junk slightly better; that edge was not worth a dependency in the storefront's hot path.

And one finding that revised our whole view: **the dominant search‑quality problem was the keyword arm, live today**, not the missing semantic one. Natural‑language queries ("where is my order", "account") matched descriptions and returned a hundred products. The fix is weighting and drop‑token thresholds on the keyword side — unglamorous, measurable, and worth more than any embedding.

## The best part: telling the buyers what people want

Queries that return nothing are not a search problem; they are a demand signal. Once our own logs held them, the reports wrote themselves: per shop and across the group, the most‑searched things we do not stock, with volumes. The first run handed the buyers about six hundred assortment gaps per market — "trousers", a couple of specific stones, a filter brand — things nobody had asked for because nobody had been able to see that customers were asking.

That report is the reason to own your search box. The engine is a means.

## What it cost

The language model work — proposing synonyms, embedding products for the tuning harness — cost roughly **six cents** in API fees across the whole programme. The expensive part was the engineer‑day with the harness, and that is exactly where the money should go.
