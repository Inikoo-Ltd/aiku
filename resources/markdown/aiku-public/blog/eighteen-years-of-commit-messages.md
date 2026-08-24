---
title: Eighteen years of commit messages, thank god for the typos
summary: 14,880 commits from 2008 to today, read not as a changelog but as a diary. The ticket prefixes that name the eras (KAKTUS, CSER, PIKA), the hour histogram with its 3am tail, a New Year's Day spent chasing a search bug with the typos getting worse by the hour, "brexit nightmare", "leap year hack", and the honest admission a commit message almost never makes: "Stock Levels don't add up..we think".
date: 2026-08-24
tags: history, aurora, origins
---

<aside class="tldr"><strong>TL;DR</strong>Aurora's log: 14,880 commits over 18 years, ~85% by one person. The eras announce themselves in ticket prefixes — KAKTUS (2010), CSER (2017), PIKA (2022). The clock never quite stops: 496 commits at midnight, 135 at 3am, a 57-day daily streak in 2019, and the biggest day ever — 91 commits — in 2024, the AI-agent era. The messages themselves are the diary: "brexit nightmare", "leap year hack", "sorry fix bug", and a whole New Year's Day of "fixing searh". Weekends, though: only ~10% — the log shows a life, not a burnout.</aside>

A commit message is written for a reader the author never really believes will come. Multiply that by 14,880 over eighteen years and you get something no diary can fake: a record of what one developer actually did, felt and broke, timestamped to the second, [with the city usually written down too](/blog/the-copyright-headers-were-a-travel-diary).

Here is what Aurora's log says, read as literature.

<figure><img src="/art/readme/draw-note-commit-log.svg" alt="Watercolor sketch of a notebook page listing real commit messages from 2008 to 2026 with a coffee ring stain, a clock showing 3am labelled 135 commits at this hour, and a crescent moon" width="1200" height="700" loading="eager"><figcaption>The log, annotated by coffee.</figcaption></figure>

## The eras have codenames

You can date any period of the repo by its ticket prefixes alone. The earliest tickets say `KAKTUS-304` — the project's first codename, back when [the system was still inikoo's direct descendant](/blog/born-in-a-singapore-coffee-shop). By 2017 they say `CSER-104`. By 2022, on the new repo, [`PIKA-2`](/blog/aiku-existed-inside-aurora). Even a one-person project had an issue tracker from the start — roughly three hundred prefixed commits across the KAKTUS and CSER years, plus 1,031 literal merge commits. The solo years were not the undisciplined years.

The very first week sets the tone. Day one, 3 October 2008, 22:58: *"Initial import"* — two words carrying a buried prehistory, because the log starts there only in git: the years before it lived in Bazaar, a version control system that, like the code it held, history has since walked away from. Six days later, a run-on sentence a diary would be proud of:

```
2008-10-09  "dinamic table static filter, paginator & sorting now working,
             todo changing number of rows ans filter name, a new filter mesg"
```

And 2009's finest, reporting on chart-rendering progress: *"pies almost done but other gres broken"* — pie charts almost done, but other graphs broken. Eighteen years later I still know exactly what that evening felt like.

## The clock

The hour histogram is the honest self-portrait. The peak is a respectable 15:00. But the tail: 496 commits at midnight, 326 at 1am, 206 at 2am, 135 at 3am, 77 at 4am. The only truly silent hours are 5 to 8 in the morning — the log knows when I slept. In 2019 there was a stretch of 57 consecutive days with at least one commit; the longest silence ever was 39 days, in early 2015 — the quiet before [the Singapore rewrite](/blog/born-in-a-singapore-coffee-shop) later that year.

And one number I want on the record because it surprises people: weekends are ~10% of commits. Eighteen years, one developer, and Saturday still mostly stayed Saturday.

The biggest single days tell their own story: for a decade the record was 65 commits (June 2014). Then 2024 blew past it — 77, 79, 91 commits in a day — the years AI agents joined the work. The graph of an 18-year-old codebase suddenly having its busiest days ever is its own blog post.

## The bad days

Some commits are incident reports in five words:

```
2021-01-04  "brexit nightmare"
2020-03-03  "leap year hack"
2017-10-25  "CSER-104, Urgent: Stock Levels don't add up..we think"
2018-07-25  "sorry fix bug"
2021-11-24  "fix stupid bug"
```

*"..we think"* is my favourite confession in the whole log — the moment a commit message admits what every developer knows and never writes down. And then there's 1 January 2019, a whole New Year's Day chasing one flaky search, the typos degrading in real time as the day wears on: *"fix searches"* → *"fix more searches"* → *"fixing searh"* → *"fix serach orers"* → *"fic not displaying shippers"*. You can watch the coffee wearing off.

## The good days

The wins are just as unguarded. *"finally fixed problem indexing"* (January 2020) — you can hear the exhale. *"fix problmes with product categories finally"* four days later, "finally" again, typo intact. The day production search came alive in the modern era got the log's most honest celebration — *"producton seaarch"*, followed by an expletive of pure joy, typos and all. And the quietest one, [16 October 2020 in Kuala Lumpur](/blog/aiku-existed-inside-aurora), three words nobody could have known were load-bearing: *"trains in aikus"* — the commit that created the trait that named everything that came after.

## Why this matters

None of these messages would pass a style guide. That's exactly why they're valuable. A polished log tells you what changed; this one tells you what happened — the hour, the mood, the misspelled panic, the "finally". Conventional commits are for teams; the log of a solo decade is allowed to be a diary, and eighteen years later the diary turns out to be the more useful document. If you work alone: commit often, write what's true, keep the typos. Future you is the reader the message was always for.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Aurora is public: <a href="https://github.com/inikoo/aurora">github.com/inikoo/aurora</a> — 14,880 commits, 2008-10-03 to today, ~85% one author across a dozen machine identities.</li>
<li>Hour histogram: <code>git log --format=%ad --date=format:%H | sort | uniq -c</code>. Weekday split: Sat 817 + Sun 713 of 14,880.</li>
<li>Streak/gap: 57 days (22 Sep – 17 Nov 2019); 39 days silent (28 Jan – 8 Mar 2015). Record days: 91 (29 Oct 2024), 79, 77.</li>
<li>Every quote in this post is verbatim, typos included: <code>git log --all -i --grep="&lt;phrase&gt;"</code> finds each one.</li>
</ul></aside>
