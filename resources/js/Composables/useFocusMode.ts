import axios from "axios"
import { ref } from "vue"
import { useLayoutStore } from "@/Stores/layout"
import { useLiveUsers } from "@/Stores/active-users"

export const FOCUS_MINUTES = [30, 60, 240, 480] as const

export const focusUntil = ref<string | null>(null)

const storageKey = () => `aiku-focus-until-${useLayoutStore().user?.id ?? 0}`

const syncFocus = () => {
    try {
        const stored = JSON.parse(localStorage.getItem(storageKey()) ?? "null")
        if (stored && "until" in stored) {
            focusUntil.value = stored.until
            return
        }
    } catch {
        // ponytail: without storage the setting saved on the user is the only source
    }
    focusUntil.value = useLayoutStore().user?.settings?.focus_until ?? null
}

export const isFocusOn = (): boolean => {
    syncFocus()
    return !!focusUntil.value && Date.parse(focusUntil.value) > Date.now()
}

export const setFocus = async (minutes: number) => {
    await axios.patch(route("grp.models.profile.update"), { focus_minutes: minutes })
    const until = minutes ? new Date(Date.now() + minutes * 60_000).toISOString() : null
    try {
        localStorage.setItem(storageKey(), JSON.stringify({ until }))
    } catch {
        // ponytail: other tabs pick it up from the user on their next page load
    }
    focusUntil.value = until

    const me = useLiveUsers().liveUsers[useLayoutStore().user?.id ?? 0]
    if (me) {
        me.focus_until = until
        window.Echo?.join("grp.live.users").whisper("otherIsNavigating", me)
    }
}

export const isFocusingUntil = (until?: string | null, now = Date.now()): boolean => !!until && Date.parse(until) > now
