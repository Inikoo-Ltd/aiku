import { computed, ref } from "vue"
import { usePage } from "@inertiajs/vue3"

const PREFIX = "desk-draft:slack:"

const ownPrefix = () => `${PREFIX}${usePage().props?.auth?.user?.id ?? "none"}:`

const keys = ref<string[]>([])

const storage = (): Storage | null => {
    try {
        return window.localStorage
    } catch {
        return null
    }
}

const keyOf = (channelId: number, threadTs?: string | null) => `${ownPrefix()}${channelId}:${threadTs ?? ""}`

const scan = () => {
    try {
        const found: string[] = []
        const held = storage()
        for (let index = 0; held && index < held.length; index++) {
            const key = held.key(index)
            if (key?.startsWith(PREFIX) && held.getItem(key)) found.push(key)
        }
        keys.value = found
    } catch {
        keys.value = []
    }
}

if (typeof window !== "undefined") {
    scan()
    window.addEventListener("storage", (event) => {
        if (!event.key || event.key.startsWith(PREFIX)) scan()
    })
}

export const loadDraft = (channelId: number, threadTs?: string | null): string => {
    try {
        return storage()?.getItem(keyOf(channelId, threadTs)) ?? ""
    } catch {
        return ""
    }
}

export const saveDraft = (channelId: number, threadTs: string | null | undefined, text: string) => {
    const key = keyOf(channelId, threadTs)
    try {
        if (text.trim()) storage()?.setItem(key, text)
        else storage()?.removeItem(key)
    } catch {
        return
    }
    keys.value = text.trim() ? [...new Set([...keys.value, key])] : keys.value.filter((held) => held !== key)
}

/** Channels with an unsent message, in the channel box or in one of its threads. */
export const draftChannelIds = computed(() => {
    const own = ownPrefix()
    return new Set(keys.value.filter((key) => key.startsWith(own)).map((key) => Number(key.slice(own.length).split(":")[0])))
})

/** On a shared machine nobody inherits the last person's unsent messages. */
export const clearAllDrafts = () => {
    try {
        const held = storage()
        for (const key of keys.value) held?.removeItem(key)
        keys.value = []
    } catch {
        return
    }
}
