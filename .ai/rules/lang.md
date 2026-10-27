---
paths:
  - 'lang/**'
---

# Lang

## lang/*.json is the only copy of translations (POEditor retired Oct 2026)
POEditor is gone (HELP-4316): edit lang/<code>.json directly, keep the file's existing key order and formatting. For a mistranslated label, also add the term to resources/translation-glossary.json so t:fill / t:new machine runs keep the fix. Never reintroduce a pull from an external translation store: it overwrote corrected labels.
