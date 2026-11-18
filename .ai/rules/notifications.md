---
paths:
  - 'app/Notifications/**'
  - 'app/Actions/**/Notifications/**'
  - 'app/Actions/Api/Group/**'
  - 'apps/**'
---

# Notifications

## A notification only goes to someone allowed to see what it is about
Before sending a notification about a record (delivery note, picking session, customer return, pallet return, stock delivery, and so on), check that the recipient still has the right to view that record in its warehouse or organisation: the same check the web page or the mobile API uses to open it. If they don't, skip them. This applies to every channel: the database notification behind the Inbox, mobile push (FCM) and web push. Being assigned to something does not grant the right, so check it even for the assignee (for example a customer return handler can be any active user of the group). Decided by Louis, 2026-11-17, for the aiku_droid work notifications (assigned, taken over, cancelled, waiting resolved, modified).

## The mobile Inbox shows only mobile-module notifications
The mobile Inbox (and its unread count and badge) lists only notifications made for the mobile module: the work notifications about delivery notes, picking sessions, customer returns and pallet returns (assigned, taken over, cancelled, waiting resolved, modified). Filter by an allow-list of those types on the server; never show everything and exclude a few. Every other notification in the table (the 2.1 million old MeasurementShare rows, tasks, tickets, purchase orders, stock arrivals and the rest) stays on the web only. The Android and iOS apps both use the same server endpoint, so neither app filters on its own. A new notification type appears in the mobile Inbox only when it is added to the allow-list on purpose. Decided by Louis, 2026-11-17.

## A new quiet write to lines or addresses must send the "modified" notice
Work notifications (`App\Notifications\WorkNotification`) catch assignee, state and note changes through model hooks (`SendsWorkNotifications` on DeliveryNote, PickingSession, ReturnDeliveryNote, PalletReturn). Line and address changes often bypass model events (`updateQuietly`, pivot attach/detach, query-builder deletes), so each of those paths calls `WorkNotifier::modified(...)` explicitly. When you add a new path that changes a delivery note's or pallet return's lines or delivery address without firing the model's `updated` event, add that call too, or the assigned picker or packer is never told. Decided for the aiku_droid work notifications, 2026-11-18.
