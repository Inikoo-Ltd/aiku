---
title: Watching how people actually use the MCP server
summary: We log every call an assistant makes to our MCP server — who, which tool, how long, did it fail and why — and put it on a sysadmin dashboard. Two weeks of data found a user whose assistant had made 454 calls with a 100% error rate, and fixed the server's instructions instead of the user.
date: 2026-08-23
tags: mcp, ai, observability, sysadmin
---

<aside class="tldr"><strong>TL;DR</strong>We log every call to aiku's staff MCP server — user, tool, duration, error and error text — and surface it on a sysadmin dashboard. Two weeks of data showed one user driving 63% of traffic and another with 454 calls at a 100% error rate, because her assistant kept reaching for tools she had no permission for. The fix was server-side: register denied tools with a reason instead of "unknown tool", and route the instructions so the model's first guess is right.</aside>

When we opened the [staff MCP server](/blog/an-mcp-server-for-a-whole-business), the question we could not answer was the obvious one: *is anyone using it, and is it working for them?* A model talking to an API fails quietly. It gets a permission error, tries a different tool, gets another, gives up, and the person on the other end sees a vague "I couldn't find that" — and never files a bug.

So the first thing we added after the tools was a log.

## One row per call

Every tool call writes a row: the user, the tool, the arguments, the duration in milliseconds, whether it errored, and — after the incident below — the error text. It is a plain table with three indexes; the middleware that writes it is a dozen lines. The arguments are kept because "what did the model ask for" is the whole debugging story; the results are not, because they would be a second copy of the business.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>The logging middleware is [app/Http/Middleware/LogMcpRequest.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Http/Middleware/LogMcpRequest.php); access gating is [app/Http/Middleware/EnsureCanUseMcp.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Http/Middleware/EnsureCanUseMcp.php).</li>
<li>The server and its tools live under [app/Mcp/Servers/AikuServer.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Mcp/Servers/AikuServer.php) and [app/Mcp/Tools](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Mcp/Tools/SqlQueryTool.php), including the SQL-gating trait [WithMcpSqlAccess.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Mcp/Tools/WithMcpSqlAccess.php).</li>
<li>Built on <code>laravel/mcp</code> — see the <a href="https://laravel.com/docs/mcp">Laravel MCP docs</a> for tool registration and authorization patterns.</li>
</ul></aside>

## The dashboard

On the sysadmin dashboard there is a small "MCP" widget, and behind it a page for the last 30 days:

- **Totals** — calls, errors, error rate, average latency, how many distinct people.
- **By day** — calls split into purpose‑built tool calls and raw SQL calls (a separately gated permission), with errors overlaid. The shape tells you when a new client got connected, and when someone's assistant went into a loop.
- **By tool** — which tools are used, how often they fail, how long they take. The long tail of never‑called tools is a prompt to either improve their descriptions or delete them.
- **By user** — calls, errors, whether they hold SQL access, and when they last used it.

Counts and metadata only; no result bodies. It is the same pattern we later reused for [staff chat analytics](/blog/staff-chat-for-people-holding-a-scanner): a sysadmin needs the shape of the usage, not the content.

## What two weeks of data found

The first read of the dashboard, in August 2026, produced two facts we would not have guessed.

One user accounted for **63% of all traffic**. Fine — a power user in a management role, mostly asking sales and stock questions. The tools were doing what they were built for.

Another user had made **454 calls with a 100% error rate.** Her role gave her read access to about nineteen shops and no SQL access. Her assistant had, on every conversation, reached for the SQL tool first, been told the tool did not exist, tried the "describe tables" tool, been told the same, and looped — never once calling the sales tools she was entirely entitled to use. From her side the server had simply never worked.

That is not a user problem; it is a server‑instruction problem. The fix was three small things:

1. **Register the SQL tools for everyone, and deny with a reason.** "Unknown tool" makes a model retry; "you do not have SQL access — use `product-lookup` or `shop-sales` for this" makes it go and do that.
2. **Route in the instructions.** The server's guide now says explicitly which tools answer which questions for people without SQL, so the model's first guess is a good one.
3. **Store the error text** on the log row, so the next loop is diagnosable from the dashboard instead of from a conversation export.

We will re‑read her rows a few days after the change. Success looks like sales‑tool calls appearing and the SQL errors stopping; if the errors move to "no permission on shop X", that is the next fix.

## Making the server smarter, a little every week

The log turned the MCP server from a thing we built into a thing we tune. Most weeks now there is a small change driven by what the rows say: a tool description rewritten because models kept passing the wrong argument; a new lookup tool because three people were asking for the same thing through SQL; a denial message made more specific because the old one sent the model down the wrong corridor; the guide served with the tools amended with one more "if you want X, call Y". None of these are features. They are the server learning to talk to its callers, and the dashboard is how we know which sentence to change next.

## The hard part: walls that the model cannot see over

The thing we spend the most care on is not the tools; it is what the tools must never reveal.

The same group holds several companies, and a person in one of them must not learn the margins, customers or suppliers of another. A shop admin may see their shop's sales and not the neighbouring shop's. Some columns — costs, supplier prices, personal data — are visible to some roles and not to others. In the web app those walls are enforced screen by screen, and a human who hits one sees a 403 and moves on.

A model does not move on. It rephrases. It tries the tool with a different slug. It asks for the "group" view instead of the "shop" view. It asks for a CSV export. It will, with complete good faith, probe every door, and it only needs one that was left ajar. So the rule for every tool is: **authorise inside the tool, with the same checks the web page uses, and scope the query before anything is fetched** — never filter after. A tool that fetches the group's numbers and then hides the ones you may not see is a leak waiting for a clever prompt.

Three consequences fell out of that:

- **Slugs, not names.** Asking for "the UK shop" forces the model through `my-access` to learn which slugs it may use. Guessing is refused, not corrected.
- **Denials that teach without revealing.** "You do not have access to shop X" confirms X exists. So the denial for a shop you cannot see is the same as for a shop that does not exist, and it points at `my-access`.
- **SQL is the exception, and it is walled separately.** The raw SQL tool is the only one that cannot reason about rows; it is gated behind a permission we do not grant ourselves, and its use shows up as its own line on the dashboard precisely so that a growing line is a conversation, not a surprise.

This is ongoing work, and it will stay ongoing: every new tool is a new surface, and the model is a tireless tester of surfaces. The log is what tells us when a wall has a draught.

## Why this matters more for AI clients than for humans

A human who hits a wall tells you. A model that hits a wall tries another door, and the person watching sees only that the assistant seemed confused. Without the log we would have concluded that the MCP server was lightly used and fine. With it we learned that one of our more engaged users had been failing silently for two weeks — and that the cheapest fix was a sentence in the server's instructions.

If you run an MCP server for real people: log every call with the error text, put it where a sysadmin looks weekly, and read it as "what is the model being told" rather than "what is the user doing". The model is a user with infinite patience and no voice.

<aside class="tldr bottom"><strong>In one paragraph</strong>An MCP server fails silently unless you log every call with its error text and read the log as feedback on the server's own instructions, not on the user.</aside>
