/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 08 Dec 2023 02:34:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

import { defineStore } from 'pinia'
import { notify } from '@kyvg/vue3-notification'

interface NotificationData {
    title: string
    text: string
    type: string
}

export const useEchoGrpGeneral = defineStore(
    'echo-grp-general', {
        state: () => ({
            prospectsDashboard: {},
        }),
        actions: {
            subscribe(groupId: string) {
                if (!groupId) {
                    return 
                }
                let abcdef = window.Echo.private(`grp.${groupId}.general`).
                    listen('.notification', (e: NotificationData) => {
                        // console.log('From echo-org-general', e)
                        notify({
                            title: e.title || 'General',
                            text: e.text || 'From echo-org-general',
                            type: e.type || 'success',
                        })
                    })
                // console.log('Subscribed to General: :', abcdef)
            },
        },
    }
)
