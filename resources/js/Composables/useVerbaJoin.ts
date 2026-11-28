/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { computed, ref } from "vue"
import axios from "axios"
import { useLayoutStore } from "@/Stores/layout"

const joinPreview = typeof window !== "undefined" ? new URLSearchParams(window.location.search).get("join_preview") : null

export const isJoinLaterToday = ref(false)

export const joinLater = () => {
    isJoinLaterToday.value = true
    axios.patch(route("grp.models.profile.update"), { verba_join_later: true }).catch(() => {
        // ponytail: not saved means the page asks again on the next visit, nothing lost
    })
}

export type VerbaSlackJoin = {
    linked: boolean
    returning: boolean
    company: string
    consent_version: string
    terms_version: string
    start_route: { name: string }
}

export const useVerbaJoin = () => {
    const layout = useLayoutStore()
    const preview = layout.app?.environment === "local" ? joinPreview : null

    const slackJoin = computed<VerbaSlackJoin | null>(() => isJoinLaterToday.value ? null : layout.user?.verba_slack_join
        ?? (preview ? { linked: preview !== "unlinked", returning: true, company: "AW", consent_version: "preview", terms_version: "preview", start_route: { name: "grp.chat.staff.slack.connect.private.start" } } : null))
    const mustJoin = computed(() => (layout.currentRoute ?? "").startsWith("grp.chat.staff.") && !!slackJoin.value)

    return { slackJoin, mustJoin }
}

export const recordVerbaJoin = (event: string) => axios.post(route("grp.chat.staff.slack.connect.event"), { event }).catch(() => {
    // ponytail: analytics only, a lost row changes nothing for the person
})
