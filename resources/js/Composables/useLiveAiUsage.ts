/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { onMounted, onBeforeUnmount, ref } from "vue"
import { usePage, router } from "@inertiajs/vue3"

export const useLiveAiUsage = (only: string[]) => {
    const groupId = (usePage().props.layout as any)?.group?.id
    const channelName = `grp.${groupId}.general`
    const lastUpdate = ref<Date | null>(null)

    let debounceTimer: ReturnType<typeof setTimeout> | undefined

    const handler = () => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.reload({ only, preserveScroll: true, preserveState: true, onSuccess: () => (lastUpdate.value = new Date()) })
        }, 800)
    }

    onMounted(() => {
        if (groupId) {
            window.Echo.private(channelName).listen(".ai-usage-changed", handler)
        }
    })

    onBeforeUnmount(() => {
        clearTimeout(debounceTimer)

        if (groupId) {
            window.Echo.private(channelName).stopListening(".ai-usage-changed", handler)
        }
    })

    return { lastUpdate, isLive: !!groupId }
}
