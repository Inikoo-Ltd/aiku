/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { computed } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import { useLayoutStore } from "@/Stores/layout"

const isVerbaUrl = (url: string) => new URL(url, window.location.origin).pathname.startsWith("/vx")
let lastPageOutsideVerba: string | null = null
const joinPreview = typeof window !== "undefined" ? new URLSearchParams(window.location.search).get("join_preview") : null
let isWatchingPages = false

export const watchPagesOutsideVerba = () => {
    if (isWatchingPages || typeof window === "undefined") {
        return
    }
    isWatchingPages = true
    if (!isVerbaUrl(window.location.href)) {
        lastPageOutsideVerba = window.location.pathname + window.location.search
    }
    router.on("navigate", (event) => {
        const url = event.detail.page.url
        if (!isVerbaUrl(url)) {
            lastPageOutsideVerba = url
        }
    })
}

export const leaveVerbaJoin = () => router.visit(lastPageOutsideVerba ?? route("grp.dashboard.show"))

export type VerbaSlackJoin = {
    linked: boolean
    company: string
    consent_version: string
    terms_version: string
    start_route: { name: string }
}

export const useVerbaJoin = () => {
    const layout = useLayoutStore()
    const preview = layout.app?.environment === "local" ? joinPreview : null

    const slackJoin = computed<VerbaSlackJoin | null>(() => layout.user?.verba_slack_join
        ?? (preview ? { linked: preview !== "unlinked", company: "AW", consent_version: "preview", terms_version: "preview", start_route: { name: "grp.chat.staff.slack.connect.private.start" } } : null))
    const mustJoin = computed(() => (layout.currentRoute ?? "").startsWith("grp.chat.staff.") && !!slackJoin.value)

    return { slackJoin, mustJoin }
}

export const recordVerbaJoin = (event: string) => axios.post(route("grp.chat.staff.slack.connect.event"), { event }).catch(() => {
    // ponytail: analytics only, a lost row changes nothing for the person
})
