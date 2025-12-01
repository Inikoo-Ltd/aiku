/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface DispatchedEmailResource {
    id:string,
    mailshot_id: string,
    outbox_id: string,
    recipient_type: string
    recipient_id: string
    state: string
    created_at: string
    updated_at: string
    number_reads: string
    number_clicks: string

    sent_at: string
    email_address: string
    mask_as_spam: {
        tooltip: string
        icon: string
    }[]
    
    number_email_tracking_events: string
    shop_code: string
    shop_slug: string
    organisation_name: string
    organisation_slug: string


}
