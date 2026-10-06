import { onBeforeUnmount, Ref, watch } from "vue"

const storageKey = (key: string) => `composer-draft:${key}`

const readDraft = (key: string): string => {
    try {
        return localStorage.getItem(storageKey(key)) ?? ""
    } catch {
        return ""
    }
}

const writeDraft = (key: string, text: string) => {
    try {
        if (text.trim()) {
            localStorage.setItem(storageKey(key), text)
        } else {
            localStorage.removeItem(storageKey(key))
        }
    } catch {
        return
    }
}

export const useComposerDraft = (key: () => string | null | undefined, text: Ref<string>) => {
    watch(key, (currentKey) => {
        text.value = currentKey ? readDraft(currentKey) : ""
    }, { immediate: true })

    watch(text, (value) => {
        const currentKey = key()
        if (currentKey) {
            writeDraft(currentKey, value ?? "")
        }
    })

    return () => {
        const currentKey = key()
        if (currentKey) {
            writeDraft(currentKey, "")
        }
    }
}

const stashedAttachmentDrafts = new Map<string, unknown>()

export const useComposerAttachmentDraft = <T>(name: string, key: () => string | null | undefined, state: Ref<T>, empty: () => T) => {
    const stashKey = (sessionKey: string) => `${name}:${sessionKey}`

    const stash = (sessionKey: string | null | undefined) => {
        if (sessionKey) {
            stashedAttachmentDrafts.set(stashKey(sessionKey), state.value)
        }
    }

    watch(key, (currentKey, previousKey) => {
        stash(previousKey)

        const restored = currentKey ? stashedAttachmentDrafts.get(stashKey(currentKey)) as T | undefined : undefined
        if (currentKey) {
            stashedAttachmentDrafts.delete(stashKey(currentKey))
        }
        state.value = restored === undefined ? empty() : restored
    }, { immediate: true })

    onBeforeUnmount(() => stash(key()))
}
