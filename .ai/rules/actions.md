---
paths:
  - 'app/Actions/**/*TimeSeries*.php'
  - 'app/Actions/**'
---

# Actions

## A redo without --from/--to leaves records outside the current invoice-line range
HELP-3936 (13 Oct 2026). Redo*TimeSeries without a window rebuilds only between the first and last invoice line of each entity, so records outside that range survive when lines are re-dated or removed. This left about 20M of January 2025 sales (Aurora fetch dates, repaired by INI-1931) on products, families, master products, org stocks and trade units of organisation 1. After any change to invoice_transactions.date, rerun the redo with an explicit window covering the OLD dates. Only RedoOrgStockTimeSeries prunes outside the range by itself. Stock-bridge series (org stock, stock, trade unit and their families) are fed by the nightly run for lines dated that day, plus ProcessInvoiceTransactionTimeSeries for lines with an earlier date.

## Plain AsAction authorize does not run on non-asController routes
For a plain AsAction class (not an OrgAction and without WithAttributes), Laravel Actions auto-runs authorize() only when the route points at asController. A route aimed at another method (inShop, inFulfilment, inMetaChatSession…) skips it (ControllerDecorator::shouldValidateRequest ignores explicit methods). Such a method must call $request->validate() (which runs inspectAuthorization then the rules) or abort_unless the rule inline, otherwise the route is open to anyone signed in. OrgAction classes are unaffected: initialisation*() runs authorize through validateAttributes().

## Money fields in form blueprints are currency inputs
Vika, 3 Nov 2026: a form blueprint field that holds money (cost, price, expense, amount) is never `'type' => 'input'` or `'input'` with `inputType => number`, and never a label carrying the code ("Unit cost IDR"). Use `'type' => 'input_number'` with `'bind' => ['mode' => 'currency', 'currency' => <code>, 'min' => 0, 'minFractionDigits' => 2, 'maxFractionDigits' => 2]` so the input shows the currency symbol (EditCharge `amount` is the reference). Supplier unit costs and unit expenses are stored with 4 decimals: `maxFractionDigits => 4` (EditSupplierProduct `moneyInputBind()`). Cast the value to float (decimal columns arrive as strings) and take the currency from the model the amount belongs to.
