---
paths:
  - 'lang/**'
---

# Lang

## lang/*.json is the only copy of translations (POEditor retired Oct 2026)
POEditor is gone (HELP-4316): edit lang/<code>.json directly, keep the file's existing key order and formatting. For a mistranslated label, also add the term to resources/translation-glossary.json so t:fill / t:new machine runs keep the fix. Never reintroduce a pull from an external translation store: it overwrote corrected labels.

## Add only your new keys; never commit a regenerated lang file
On 2 Nov 2026, 9662732381 committed all 24 lang files regenerated on a stale branch and put 138 corrected translations back to old wording (restored in 80f5beb7f3). Before committing, `git diff lang/` must show only `+` lines for keys you added. If an existing value changed, run `git checkout origin/main -- lang/` and add only your keys again. Pull main right before adding keys. On a merge conflict in lang/*.json, take main's file and add your keys on top. Change an existing translation only in its own commit that names the ticket.
