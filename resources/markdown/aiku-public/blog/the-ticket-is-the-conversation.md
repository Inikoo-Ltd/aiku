---
title: The ticket is the conversation
summary: How support requests flow from a picker, an accountant or a customer‑service agent into a Jira project, how they come back out as commits, and the rules we hold when we answer — plain words, what we did, why it happened, what you do next. And a tease: we may build our own ticket system, because we can, and because we would do it our way.
date: 2026-08-21
tags: support, workflow, jira, communication
---

Every system that real people use every day generates a stream of "this is wrong / could this do X / what happened to order 48213". For us that stream goes into a Jira project named after its purpose — *help* — and comes back out as commits. This note is how that works, the rules we hold when we answer, and why we keep wondering whether we should write the ticket system ourselves.

## Where tickets come from

Three doors, one project.

- **Staff** raise a ticket from the staff app — there is a link in the footer on every page — or by emailing the project, which is how most of them arrive; the subject becomes the title, the body becomes the description, screenshots attached.
- **Customer service** raises one *from a chat*: the customer chat panel has a button that opens a ticket pre‑filled with the conversation, the customer, the shop and the order, with the project, issue type, priority and labels picked from the agent's own Jira settings. The ticket points back at the chat; the chat shows the ticket.
- **Engineering** raises them for itself, from an alert or a log, when something needs a paper trail.

Each ticket carries the reporter, the shop or organisation, and — when it is about a record — a link to that record in the staff app, because the first ten minutes of any investigation is "which order, which customer, which date", and a link answers that.

## How it comes back out

Work lands on `main` and ships in [that day's releases](/blog/369-production-releases-in-five-months). The ticket's title is often the commit subject, because the title was already the shortest true description of the problem. When the fix is live the ticket is closed with three lines, in this order:

1. What we did.
2. Why it happened, in plain words.
3. What you need to do now, if anything. (Usually nothing; then the line is omitted.)

Assigned to one named engineer so ownership is visible; no greetings, no apologies, no "let me know if". It reads a little cold. That is deliberate: the reporter is a picker or an accountant, they want to know it is handled and whether anything is left for them, and the kindest thing is to tell them in the fewest words.

## The plain‑words rule

Everything addressed to staff — ticket comments, the messages that go to a warehouse or an accounts team — is written with **zero technical vocabulary**. Not "the webhook retried", not "a validation rule", not "a rounding issue in the float" — *the order was marked paid twice because the bank told us twice; we now ignore the second message; your orders from last week are correct.* If a sentence needs a term the reporter would have to look up, the sentence is wrong, not the reporter.

The rule is held hardest on the tickets that come from customer service, because they forward our words to a customer.

## Sweeping the backlog

Tickets rot. Every few months someone walks the oldest hundred open ones with two tools: match the *title* against recent commit subjects — the fix often shipped months ago under the same words and nobody closed the ticket — and *read the threads*, where reporters have written "we can close this" and been ignored. Duplicates are linked and closed; delivered ones are closed with the release; the genuinely big feature requests are **not designed in a triage session** — each gets its own day, or it gets written down as deferred with the reason. The rule for the sweep is *close only the easy ones*; the rest deserve attention, not a tidy‑up.

One small lesson from a sweep: a ticket whose title begins "Re:" and whose parent is already closed is *not* automatically a duplicate. Two of them carried a new symptom each. Read before closing.

## Honest part: this is still the biggest challenge

Everything above is the part that works. It would be dishonest to stop there.

Requests still arrive by WhatsApp, by phone, on a piece of paper left on a desk, in a corridor. Some of them are acted on before they ever become a ticket, which means the ticket system is not the record of what was asked — it is the record of what was asked *politely*. Two people ask for exactly opposite things in the same week, each certain, and the engineer in the middle has to decide which one the business actually wants. A feature gets built because person A asked for it, and person B is offended to discover it in production — even when the heads‑up was sent, because person B was busy that day and the heads‑up was one more unread line. A customer‑service agent asks, in good faith, for a change that would alter how accounting works. Someone asks for a change that would quietly alter the business model — how a thing is priced, who pays for what — without having asked a manager first, because from where they sit it is obviously just a small tweak to a screen.

None of that is a tooling problem and no ticket system, ours or anyone's, will fix it on its own. The honest answer is process and habit: every request, however it arrives, gets written down in the one place; conflicting requests are surfaced to both people together rather than decided in private; anything that touches money, policy or another team's work goes back up to the person who owns that decision before a line of code is written, however small it looks; changes that touch somebody's screen get a note *before* they ship, and we accept that the note will sometimes be ignored and do it anyway. We try our best. We do not always manage. Shipping seventeen times a week makes this harder, not easier, and we would rather say so than pretend the flow on this page is the whole story.

## The tease

We keep an eye on what Jira gives us — a form, a thread, a status, a link to a record — and on how much of our time goes to the seams: pasting references, copying context from the chat, writing the same three lines, walking backlogs by hand. And we run a system that already has the customer, the order, the delivery note, the chat, the user and the audit trail, one join away from each other.

So there is a standing temptation to build our own ticket system inside aiku. Not because Jira is bad — it is fine — but **because we can, and because we would do it our way**: a ticket *on* the record it is about, not linked to it; the three closing lines as fields, not prose; the backlog sweep as a query, not an afternoon; the reporter's chat, the engineer's commit and the release number on one screen. The same argument that gave us [our own CMS](/blog/your-website-your-rules), [our own search](/blog/what-people-type-into-the-search-box) and [our own staff chat](/blog/staff-chat-for-people-holding-a-scanner).

We have not decided. When we do, it will be a ticket — titled, plainly, in the words of the person who asked for it.
