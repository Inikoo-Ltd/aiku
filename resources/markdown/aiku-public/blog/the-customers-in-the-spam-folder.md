---
title: The customers in the spam folder
summary: We read only the Gmail inbox, on purpose, and real customers were getting lost in Gmail spam. We now read the spam folder too. Rules clear most of it, a System One model sorts the rest for about four cents a month, anybody who has bought from us gets straight through, and nothing out of spam gets to open a file on its own.
date: 2026-09-29
tags: production, ai, email, chat, security
---

<aside class="tldr"><strong>TL;DR</strong>Customer service found two genuine customer emails in a shop's Gmail spam that never reached aiku. That was by design: we read only the inbox, so junk never reached the chat queue. We checked 1,416 real spam emails from 12 shop mailboxes. That scales to about 5,600 spam emails a month, and about 90 of them came from known customers or were replies to our own threads: missing parts, a short delivery, refunds, a dropshipper stuck connecting their store. Now aiku reads the spam folder too. Customers who have bought from us and replies to our own conversations come straight in. Rules drop newsletters and machine mail for free. Everything else goes to Jev, TypeSafe's System One model, reached through OpenRouter, which says what kind of email it is. We tried two ways of asking. Asking "which kind of email is this?" beat asking "is this genuine?": the same real customers came in and fewer scams did. The model costs about four cents a month for all our shops. Anything taken out of spam is marked, labelled, and its attachments stay in Gmail until a person asks for them.</aside>

## Inbox only, on purpose

When we connected the shop mailboxes to the chat inbox, we made one choice early: read the Gmail inbox and nothing else. Gmail already runs one of the best spam filters there is. Reading its spam folder would mean doing its job again, badly, with junk landing in a queue that people answer by hand.

Then a ticket came in from customer service: two genuine customer questions were sitting in the dropshipping mailbox's Gmail spam and were nowhere in aiku. The answer "that's by design, check Gmail now and then" was correct and useless. The whole point of the chat inbox is that nobody has to go to Gmail.

## Looking at the real spam

Before building anything we wanted to know what is in there. A read-only script on the production box pulled up to 100 spam emails per connected mailbox, 500 for the busiest one. For each email it recorded the sender, subject and body, and whether the sender was a customer or replying to one of our threads, which only the production database can answer. The file went to a folder outside the repo and the evaluation ran locally. Nothing was imported, relabelled or deleted.

The first run had no pause between Gmail requests and hit Gmail's per-user quota within minutes. Live email import uses the same quota, so for a few minutes our export was competing with real customer mail. We checked afterwards: mail kept arriving at its usual rate, every mailbox had been checked within the last minute, and nothing failed, because anything still in the inbox is picked up on the next check anyway. The script now waits a quarter of a second between messages and backs off 5, 10, 20, 40 seconds when Gmail says no. It is still a lesson worth writing down: a one-off script shares its limits with production.

What 1,416 real spam emails from 12 mailboxes look like, scaled to a month:

| | per month |
|---|---|
| Spam arriving across all mailboxes | ~5,600 |
| Caught by rules: an unsubscribe header, no-reply and machine senders | ~4,400 |
| From known customers or replies to our threads | ~90 |
| Left for a model to judge | ~1,040 |

One mailbox gets close to a thousand spam emails a month on its own. In the sample, 31 emails came from known customers or were replies to our own threads. Some were noise: out-of-office replies, one customer answering our newsletters six times. The rest were exactly the mail the chat inbox exists for: a missing part on an urgent order, items missing from a delivery, a credit request, an account deletion, a store connection that would not work. None of them ever reached us.

## A model that answers instead of writing

The ~1,040 unknown senders a month are the hard part. They are mostly factories pitching, SEO agencies, Shopify "experts", phishing and fake invoices, with the odd shop that actually wants to stock our products.

A chat model can judge that, but we would be paying for text we throw away to read one word. Jev, TypeSafe's first System One model, doesn't write text at all. You give it the email and a typed question, and it returns a calibrated probability: yes/no, one option from a list you define, or a position on a scale. OpenRouter serves it on its Decisions API, so it is one HTTP call with the key we already use, and no new package.

It is cheap enough to be irrelevant. Our first real call used 427 tokens and cost $0.000018. The 263 unknown senders in the sample cost under a cent to classify.

## Asking the right question

The first version asked yes/no: did a real person write this to us wanting something as a customer or a future customer? On eight emails we made up, it separated perfectly: real enquiries at 0.75–0.81, junk at 0.05 or less. On real spam, with a cutoff of 0.2, it let in 31 of the 263. The top of the list was right: dropshipping enquiries, a B2B request for reed diffusers, an order problem, a compliance question. The bottom was not: a "payment copy" phishing email, a "long-term bulk purchase contract", a "partnership for 2026–2030", and a handful of Gmail addresses asking "Aw dropship?" to start a sales conversation.

So we asked a different question: which kind of email is this? Customer request, prospect, supplier pitch, service pitch, scam, automated. The option descriptions carry what the yes/no question could not. A prospect is a named business that says what it sells or where. A service pitch includes freelancers who ask vague questions about your store to open a conversation. A scam includes bulk-purchase requests with no company or product details. An email comes in when customer request plus prospect is at least 0.3.

On the same 263 emails:

| | comes in |
|---|---|
| Both questions | 24 |
| Only yes/no | 7, all junk |
| Only "which kind" | 1, harmless |
| "Which kind" alone | 25 (yes/no: 31) |

Every genuine customer and prospect came in with both. The cost was the same: one call per email, about $0.00004 each. Across 1,040 a month that is about four cents. The same check on gpt-4o, at list prices, would be around two dollars. The price hardly matters. What we get for it is a probability to set a cutoff on, and a label.

## Who gets trusted

The first version let anyone we knew straight through: a web user or customer with that email address, in that shop. Then the obvious question: anybody can register, and a registered bad actor is more dangerous than a stranger because we trust them. Gmail puts things in spam for reasons like suspected phishing, and that is exactly where trust should be earned.

So for mail in spam, only customers who have bought from us, with at least one invoice, and replies to our own conversations skip the model. Someone who registered and never bought goes to Jev like everybody else.

We expected that to cost us customers, so we checked. Of the 20 known senders in the spam sample, 14 had never been invoiced, among them dropshippers still setting up: a Wix connection, a Shopify connection, a VAT question about the product file, an account deletion. Jev let all ten of their genuine emails in, as customer request or prospect, and kept out the other ten: out-of-office replies, six replies to our newsletters, and a forwarded scam. The stricter rule cost no customers and cut the noise.

## Nothing from spam opens a file on its own

For a stranger's email, aiku already held attachments back: small pictures come in, anything else waits in Gmail until an agent replies. For mail taken out of spam we went further. Attachments wait even for paying customers, and replying does not bring them in. They come in only when an agent clicks **Show attachments**. Customer accounts get hacked, and a hacked account sending an invoice PDF is exactly what ends up in spam.

Staff see why. Every email taken out of spam is flagged in the database (`is_rescued_from_spam`), and its message carries an amber note: Gmail put this email in its spam folder, attachments are kept in Gmail for your safety, only open them if you were expecting them. The note starts with Jev's label, stored alongside the flag (`spam_rescue_kind`). An email that got in with "Possible scam" on it is still useful: the agent reads it knowing what the model thought.

## How it runs

Every minute, the mailbox check that already sweeps the inbox also asks Gmail for up to 25 spam emails it has not read yet, back to the day the mailbox was connected. For each one:

1. A customer who has bought, or a reply in one of our threads: it comes in.
2. An unsubscribe header or a machine sender: it stays in Gmail spam.
3. Anything else: Jev says what kind it is. A likely customer request or prospect comes in with its label; the rest stays.

Whatever stays gets an `aiku/spam-checked` label in Gmail, and the next check leaves labelled mail out of its search. Our first version remembered its decisions in the cache and listed the newest 100 spam emails every run. Those 100 never left the folder, so a mailbox with 900 spam emails would never have got past the newest 100. With the label, each run moves on to mail nobody has read. A backlog of 900 clears in under an hour, 25 at a time, which also keeps well under the Gmail quota we tripped over, and a lost cache cannot make us read anything twice. If Jev doesn't answer, the email is tried again an hour later, not every minute. Whatever comes in has Gmail's spam label removed, which also tells Gmail it was wrong.

## Tightening the detector

The first afternoon on production brought in 36 emails. The real customers were there, but so were about a dozen that were not: "White Label Manufacturers" twice, a textile factory looking for used weaving looms, a "procurement manager" wanting precious metals paid in crypto, three identical "send us your product list" emails, and several "purchase enquiries" from sourcing companies in three countries. Jev had labelled most of them prospects. By our own descriptions, they were: a business that wants to buy from us.

So the descriptions were the problem, not the model. We added two kinds that are not wanted: a vague buying request, which is any buying or partnership request that could go unchanged to any company, with no product named; and a buyer of goods we do not sell. We also tightened the wanted ones. A prospect now has to say what it sells and which of our kinds of products it wants. A customer request has to carry its details in the email. A scam now includes anything whose order, invoice or damage report is only behind a link. That last one caught a "defective product" complaint that turned out to be phishing.

We also tried spending a little more: a second call on everything that passed, asking whether it names our products, whether the details are behind a link, and whether it reads like a template. It made things worse. Genuine dropshippers write about Shopify connections, VAT and onboarding, not products, and the second check threw out 13 of them.

On the 292 emails that reach the model, with the 30 genuine ones marked by hand:

| | genuine kept | junk let in |
|---|---|---|
| First wording | 30/30 | 15 |
| Sharper kinds | 30/30 | 1 |
| Sharper kinds plus a second check | 17/30 | 0 |

One rule went on top: when the model's first pick is a scam, the email stays out, whatever the other scores say. It lost no genuine email in the test and catches the fake "SWIFT payment copy" that had a customer request score just over the line. Re-run on everything the first wording let in on production, the new one keeps all 11 genuine emails and would have left out all 16 junk ones. The longer descriptions cost about one cent a month more.

The descriptions were written looking at these same emails, so real traffic will do a little worse than 30 out of 30 and 1 junk. They describe what a buyer writes, not who sent it, which is why we expect them to hold.

## What we would keep

Before building the filter, read the spam. One read-only export told us the volume, what the rules catch for free, where the real customers were, and what a month of the model would cost.

Try more than one way of asking the question. Yes/no and "which kind" cost the same and look alike on made-up examples. On real spam, the option descriptions were where the difference came from, and when the model got it wrong, it was usually doing exactly what our descriptions said.

And decide who is trusted per folder, not per sender. The same customer's email means one thing in the inbox and another in spam.
