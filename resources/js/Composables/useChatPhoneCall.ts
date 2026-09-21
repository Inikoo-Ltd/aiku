/**
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright: 2026
 */
import { computed, reactive, ref } from "vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"

export type PhoneCallShop = {
    id: number
    name: string
    organisation_id: number
}

export type ActivePhoneCall = {
    id: number
    status: string
    shop_id: number | null
    started_at: string
    elapsed_seconds: number
    contact_type: "customer" | "guest" | null
    customer_id: number | null
    chat_session_id: number | null
    contact_name: string | null
    notes: string | null
}

// One call at a time per person, so one piece of state for the whole application: the banner in
// the layout and the modal in the inbox are two windows onto the same call, never two calls.
const state = reactive<{
    loaded: boolean
    call: ActivePhoneCall | null
    shops: PhoneCallShop[]
    maxMinutes: number
    warnBeforeMinutes: number
}>({
    loaded: false,
    call: null,
    shops: [],
    maxMinutes: 60,
    warnBeforeMinutes: 10,
})

const modalOpen = ref(false)
const busy = ref(false)

// The inbox knows which shop is being worked; the banner on every other page does not, so the
// inbox leaves it here for whichever window opens the modal next.
const preferredShopId = ref<number | null>(null)

// The elapsed time is counted from what the server said, not from the browser clock reading the
// start time: a machine whose clock is minutes out would otherwise show minutes that never passed.
const baseElapsed = ref(0)
const syncedAt = ref(0)
const tick = ref(Date.now())
const warned = ref(false)

let ticker: ReturnType<typeof setInterval> | null = null

const startTicking = () => {
    if (ticker) return

    ticker = setInterval(() => {
        tick.value = Date.now()
    }, 1000)
}

const stopTicking = () => {
    if (!ticker) return

    clearInterval(ticker)
    ticker = null
}

const applyPayload = (data: any) => {
    state.call = data?.call ?? null
    state.shops = data?.shops ?? []
    state.maxMinutes = data?.max_minutes ?? state.maxMinutes
    state.warnBeforeMinutes = data?.warn_before_minutes ?? state.warnBeforeMinutes
    state.loaded = true

    if (state.call) {
        baseElapsed.value = state.call.elapsed_seconds ?? 0
        syncedAt.value = Date.now()
        tick.value = Date.now()
        startTicking()
    } else {
        warned.value = false
        stopTicking()
    }
}

const elapsedSeconds = computed(() => {
    if (!state.call) return 0

    return baseElapsed.value + Math.max(0, Math.floor((tick.value - syncedAt.value) / 1000))
})

const formattedElapsed = computed(() => {
    const total = elapsedSeconds.value
    const hours = Math.floor(total / 3600)
    const minutes = Math.floor((total % 3600) / 60)
    const seconds = total % 60
    const pad = (value: number) => String(value).padStart(2, "0")

    return hours > 0 ? `${hours}:${pad(minutes)}:${pad(seconds)}` : `${pad(minutes)}:${pad(seconds)}`
})

// A call nobody ends is closed by the server at the limit, which would take the minutes with it.
// The warning is the one chance to end it properly, so it is said once and not nagged.
const minutesLeft = computed(() => state.maxMinutes - Math.floor(elapsedSeconds.value / 60))

const isRunningOut = computed(() => state.call !== null && minutesLeft.value <= state.warnBeforeMinutes)

const post = async (routeName: string, payload: Record<string, any> = {}) => {
    busy.value = true

    try {
        const { data } = await axios.post(route(routeName), payload)
        applyPayload(data)

        return data
    } finally {
        busy.value = false
    }
}

export const useChatPhoneCall = () => {
    const fetchActive = async () => {
        try {
            const { data } = await axios.get(route("grp.chat.phone_calls.active"))
            applyPayload(data)
        } catch (error) {
            state.loaded = true
        }
    }

    const start = (shopId: number | null) => post("grp.chat.phone_calls.start", { shop_id: shopId })

    const end = (payload: Record<string, any>) => post("grp.chat.phone_calls.end", payload)

    const cancel = () => post("grp.chat.phone_calls.cancel")

    const openModal = () => {
        modalOpen.value = true
    }

    const setPreferredShop = (shopId: number | null) => {
        preferredShopId.value = shopId
    }

    const closeModal = () => {
        modalOpen.value = false
    }

    const warnIfRunningOut = () => {
        if (!isRunningOut.value || warned.value) return

        warned.value = true

        notify({
            title: ctrans("You are still on a phone call"),
            text: ctrans("It will be closed automatically in :minutes minutes and no notes will be kept.", {
                minutes: String(Math.max(0, minutesLeft.value)),
            }),
            type: "warning",
            duration: 10000,
        })
    }

    return {
        state,
        modalOpen,
        busy,
        preferredShopId,
        setPreferredShop,
        elapsedSeconds,
        formattedElapsed,
        minutesLeft,
        isRunningOut,
        fetchActive,
        start,
        end,
        cancel,
        openModal,
        closeModal,
        warnIfRunningOut,
    }
}
