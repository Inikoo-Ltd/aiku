## Ticket knowledge base

Customer tickets (type `customer`, references starting with `CUS-`) have their own knowledge base in
`resources/markdown/knowledge-base/customer/`. Help desk (`HELP-`) and engineering (`INI-`) tickets use the
public help docs in `resources/markdown/aiku-public/docs/` instead.

- Before answering, triaging or writing a reply for a customer ticket, always search
  `resources/markdown/knowledge-base/customer/` for an article that already covers it, and link that article
  (its `source_url`) in the reply.
- When a customer ticket reveals an answer that is not in the knowledge base yet, or an article is wrong or
  outdated, add or update the article in the same change.
- `index.md` in that folder lists every article with its category and source page; keep it in sync when you
  add, rename or remove an article.

Every article is one markdown file named after its source page slug, with this front matter:

```
---
title: <article title>
summary: <one sentence: what problem this article solves for a dropshipping customer>
category: <getting-started | sales-channels | products | clients | orders | users | payments | export | faq | troubleshooting>
tags: <comma-separated lowercase tags>
keywords: <comma-separated words a customer would use when asking about this>
source_url: <the page this article was taken from>
---
```

The ticket page suggests articles from these files through `SuggestTicketArticles`, which scores the title,
keywords, tags and summary against the ticket's subject and description, so keep `keywords` rich with the words
customers actually use (platform names, error messages).
