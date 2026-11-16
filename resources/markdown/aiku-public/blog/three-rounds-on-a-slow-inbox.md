---
title: Three rounds on a slow inbox, and the one where we stopped
summary: In October a third of our customer service team told us the chat inbox was slow or froze. Over three rounds and two and a half weeks we cut the inbox's typical load time by about 40%, through a websocket storm, a route lookup that scanned 4,000 routes, a list fetched too late, and a menu sent whole with every page. The third round made the server 40% faster and the people using the inbox didn't feel it. How we measured, what each round fixed, the work the numbers made us drop, and how we knew it was time to stop.
date: 2026-11-14
tags: performance, inertia, websockets, caching, measurement, customer service
---

<aside class="tldr"><strong>TL;DR</strong>In an October survey, 8 of 25 customer service staff said the chat inbox was slow or froze. We set targets and built browser timings into the inbox before changing anything. Round one found a websocket storm that grew with the square of open tabs, a route helper scanning 4,000 routes on every call, and a session recorder eating half the CPU profile. Round two fetched the conversation list before the page asked for it. Round three stopped every full page load from carrying the whole menu, uncached counters and an 800 KB chat list. Comparing the same weekday with the same number of deploys, a full load went from 3.2 s to 1.9–2.2 s at p90, and an in-app visit from 1.1 s to 0.7 s. The last round cut the server's share by 40%, but nobody felt it. What's left is the browser running JavaScript it already has cached, plus the refreshes we ask for after each deploy. About six minutes of waiting remain across 21 people in a morning, and the next fix would save about thirty seconds of it. So we stopped.</aside>

## It started with a survey

In October we asked the customer service team what they thought of chat, WhatsApp and email in Aiku. Twenty-five people answered, from four countries. Speed wasn't their main complaint: email was. 21 of 25 found the email layout confusing, and 17 still kept Gmail open beside Aiku. Speed came thirteenth on the list of sixteen fixes, with 8 of 25 saying chat was slow or froze and 4 saying email was slow.

The survey asked for one thing on speed: agree a load-time target, and measure it before and after. That shaped everything that followed. We didn't start by fixing anything. We started by recording.

## Measure first

The inbox now reports its own timings from the browser into one table:

- how long it takes to open, split into an in-app visit (Inertia swaps the page) and a full page load;
- opening a conversation and sending a reply;
- and freezes, from the Long Animation Frames API, with the scripts that caused them.

We set targets ourselves and told the team to push back if they disagreed. At p90: under 1 s to open the inbox by visit, under 2.5 s by full load, and under 0.5 s to open a conversation or send a reply. On the first days we recorded, a full load took 3.2 s at p90 and a visit 1.1 s.

## Round one: the things that were simply wrong

**A websocket storm.** Typing indicators in the busiest UK hours took 0.5–2 s to arrive, and up to 17 s. Off hours they took 20 ms. A feature added a few days earlier had each tab ask every other tab on the staff presence channel which page it was on, so every tab that joined triggered frames for every tab already there. With about 700 staff tabs open, the websocket server was sending 70–104 GB an hour. Fixing it brought UK-hours typing p95 down to about 20 ms and traffic to 2–4 GB an hour. The [staff chat](/blog/staff-chat-for-people-holding-a-scanner) post has the presence design that replaced it.

**A route lookup that scanned every route.** Our front end resolves URLs with a route helper, and asking it "which route am I on?" tested the address against all 4,049 routes, building a regular expression for each. That was about a quarter of the frame that draws the inbox. Now it only tests the routes whose fixed segments fit the path, usually 56.

**A session recorder in half the profile.** Browser profiles from our error tracker showed its session replay processing DOM mutations in 48% of samples on the inbox. We removed replay and kept profiling.

Round one met every target on 3 November. A full load still felt slow, though, so we set tighter ones: under 600 ms by visit and under 1.5 s by full load.

## Round two: ask for the list sooner

Most of an in-app visit was the conversation list, fetched only once the page had mounted. We tried asking for it earlier in setup and measured: in the browser, the request leaves at the same moment either way, so we reverted it. What worked was remembering each person's first list and requesting it on Inertia's `before` event, while the previous page is still on screen. On a full load, the app asks for it at boot. 95% of visits now find the list already there, and visits dropped to 671 ms at p90.

The full load didn't move. By then the inbox's own work in a full load was small, and most of the cost was in something every page shares.

## Round three: what every full page load carries

Every staff page goes through one function that builds the layout: the menu, the counters, the staff chat dock. On a full load it sent:

- **the whole left menu**: every organisation and shop the user can reach, 100 KB for someone in one warehouse and 750 KB for someone with access to everything;
- **about ten badge counts**, such as orders waiting and tickets open. They weren't cached, and took about 50 queries;
- **the staff chat dock**: a conversation list of about 809 KB and a coworker list of about 273 KB.

All of it was cached under a key that included the deploy id, so every deploy started it cold for everyone.

We changed four things:

- **The menu cache survives deploys.** The key is now a hash of the files that build the menu and the language file.
- **A full load sends only the current organisation's menu.** Another organisation's menu comes from a small endpoint when you switch. For one user with access to everything, that was 730 KB down to 332 KB.
- **The badges load after the page**, from their own endpoint, and the websocket keeps them current as before.
- **The avatars go once per person.** Avatars were 81% of the conversation list: 512 participant rows for 89 people, each carrying eight signed image URLs. The screens draw two of those formats. Now each person's avatar goes once, with four URLs, and the list fell from 829 KB to 211 KB.

On the server it worked. Full inbox loads, the same Thursday hours a week apart:

| | Before | After |
|---|---|---|
| Server time, median | 366 ms | 218 ms |
| Server time, p90 | 685 ms | 451 ms |
| HTML | 198 KB | 106 KB |
| Queries | 125 | 71 |

That's on every staff page's full load, not just the inbox. In the browser, the full-load median moved by 1–12%, depending on the day.

## Where the rest of the time goes

To find out, we added one more sample to each full load: Navigation Timing, the long tasks before the page is drawn (with `buffered: true`, so it catches the ones before the app starts), and how many scripts came from cache. Friday morning, median, away from a deploy:

| Step | Time from navigation start |
|---|---|
| First byte of HTML | 261 ms |
| Last byte of HTML | 317 ms |
| DOMContentLoaded | 587 ms |
| Page drawn | 880 ms |

About 560 ms of that comes after the HTML has arrived: running the module scripts, booting the app and drawing the page. Around 300 ms of it is long tasks. All 85 script files came from the browser cache. The server was a quarter of the total.

## Why the full loads happen

We assumed full loads were people opening new tabs. Mostly they weren't.

When a deploy changes the front end, every open tab gets a message asking to refresh to the new version. Full loads follow deploys: on one Monday, 80 of 97 inbox full loads came within fifteen minutes of one. Freezes follow full loads: 63–75% of inbox freezes landed within a minute of the same person's full load. In a week with twice the deploys, we saw twice the freezes.

Those refreshes are expected. Someone who clicks "refresh to get the latest version" doesn't mind waiting a second, and a moment of jank after an update doesn't feel like the app being slow.

## Two things the numbers made us drop

**Hashing the route names.** The first menu cache key also hashed every route name. Six of the next thirteen deploys added a route, which reset everyone's menu for nothing. A renamed menu link already changes a menu file, so we took the route names out.

**Narrowing the translation hash.** Most remaining resets came from language files, because new features ship in 23 languages. Before building a narrower key we checked what it would buy. Over 19 deploys, every one that touched the front end changed the build, and none touched only translations. Every deploy it would have saved still forced the refresh. We dropped it half-written.

## Where it ended

The same weekday a fortnight apart, with the same number of deploys in the morning:

| 07–12 UTC | First days recorded, p50 / p90 | After three rounds, p50 / p90 |
|---|---|---|
| Full load, Thursday (8 deploys) | 2,067 / 3,182 ms | 1,334 / 2,189 ms |
| Full load, Friday (5 deploys) | 1,980 / 3,240 ms | 1,199 / 1,926 ms |
| In-app visit, Thursday | 643 / 1,069 ms | 433 / 671 ms |
| In-app visit, Friday | 687 / 1,109 ms | 429 / 721 ms |

Across the whole arc that's about 40% off every number, and people would notice that. Most of it came from rounds one and two.

Then we added up what was left. On Friday morning, 21 people waited a total of 199 s on full loads, 101 s on freezes and 57 s on visits: about six minutes, or 17 seconds each. On the first Friday we recorded it was about eight minutes, even though staff now open the inbox by visit almost twice as often. A cheaper app boot might save 200 ms per full load, about 30 seconds a morning across everyone. The 1.5 s target can't be reached in code while every deploy asks the whole team to refresh. The lever left is when we deploy, not what we deploy.

So we stopped, like [the last time](/blog/ten-days-at-sixteen-per-cent) a number wouldn't move.

## What we'd tell ourselves in October

- **Start with what people said.** The survey gave us the problem, the people affected and a way to judge it: a target, before and after.
- **Measure in the browser, not just on the server.** We could have seen on day one that the third round's work would be a quarter of the load.
- **Ask why the slow thing happens.** A full load that we asked people to make doesn't have to be fast.
- **Price the next step before you build it.** Twice a half-built change turned out to save almost nothing, and we dropped it.
- **Add up the total.** "p90 2.1 s" sounds like a problem. Six minutes across a team in a morning is a number you can weigh against ten more days of work.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Browser timings: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/resources/js/Composables/useInboxTimings.ts">useInboxTimings.ts</a> posts samples to <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Chat/Reports/StoreChatInboxTimings.php">StoreChatInboxTimings</a>. Each full load also records <code>responseStart</code>, <code>responseEnd</code>, <code>domContentLoadedEventEnd</code>, long tasks from a <code>PerformanceObserver</code> with <code>buffered: true</code>, and script transfer sizes from Resource Timing.</li>
<li>Layout and menu cache: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/UI/Grp/GetFirstLoadProps.php">GetFirstLoadProps</a>, keyed by <code>menuVersion()</code>. Deferred endpoints: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/UI/Grp/Json/GetLayoutBadges.php">GetLayoutBadges</a> and <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/UI/Grp/Json/GetLayoutOrganisationNavigation.php">GetLayoutOrganisationNavigation</a>.</li>
<li>Avatars once per person: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Chat/Staff/Json/GetStaffConversations.php">GetStaffConversations</a> sends an <code>avatars</code> map, and <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/resources/js/Stores/staff-messaging.ts">staff-messaging.ts</a> puts them back on the participants.</li>
<li>The post-deploy refresh: the <code>.post-deployed</code> listener in <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/resources/js/Layouts/Grp.vue">Grp.vue</a>.</li>
<li>Every comparison used the same UTC hours on the same weekday, with the deploy count beside each, and split loads within fifteen minutes of a deploy from the rest. Without the deploy count, the before-and-after numbers mostly measured how busy the deploy pipeline was that day.</li>
</ul></aside>
