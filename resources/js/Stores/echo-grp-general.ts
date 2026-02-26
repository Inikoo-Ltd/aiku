/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 08 Dec 2023 02:34:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

import { defineStore } from 'pinia'
import { notify } from '@kyvg/vue3-notification'
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'

interface NotificationData {
    title: string
    text: string
    type: string
    id: string
    read?: boolean
}

export const useEchoGrpGeneral = defineStore(
    'echo-grp-general', {
        state: () => ({
            prospectsDashboard: {},
        }),
        actions: {
            subscribe(groupId: string) {
                if (!groupId) {
                    console.log("WS General Failed (Group id isn't provided)")
                    return
                }

                const layout = layoutStructure

                window.Echo.private(`grp.${groupId}.general`).
                    listen('.notification', (e: any) => {
                        const data = e.data || e;

                        // Show toast notification
                        notify({
                            title: data.title || 'General',
                            text: data.text || 'Notification received',
                            type: 'info',
                        })

                        // Fetch unread notifications from API and update layout structure
                        axios.get(route('grp.models.notifications.unread'))
                            .then((response) => {
                                if (layout && layout.notifications) {
                                    layout.notifications = response.data.notifications.map((n: any) => ({
                                        ...n,
                                        read: !!n.read_at
                                    }))
                                }
                            })
                            .catch((error) => {
                                console.error('Failed to fetch unread notifications:', error)
                            })
                    })
            },
        },
    }
)
