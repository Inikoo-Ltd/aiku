/*
 * Author: eka yudinata <ekayudinatha@gmail.com>
 * Created: Tue, 01 Dec 2025 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Eka Yudinata
 */

export interface EmailBulkRun {
    id:string,
    group_id:string,
    organisation_id:string,
    shop_id:string,
    subject:string,
    outbox_id:string,
    email_ongoing_run_id:string,
    email_id:string,
    snapshot_id:string,
    state:string,
    scheduled_at:string,
    recipients_stored_at:string,
    start_sending_at:string,
    sent_at:string,
    cancelled_at:string,
    stopped_at:string,
    data:string,
    created_at:string,
    updated_at:string,
    fetched_at:string,
    last_fetched_at:string,
    source_id:string
}
