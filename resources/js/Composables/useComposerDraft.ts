import { Ref, watch } from "vue"

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

export const useComposerAttachmentDraft = <T>(key: () => string | null | undefined, state: Ref<T>, empty: () => T) => {
    const stashed = new Map<string, T>()

    watch(key, (currentKey, previousKey) => {
        if (previousKey) {
            stashed.set(previousKey, state.value)
        }

        const restored = currentKey ? stashed.get(currentKey) : undefined
        if (currentKey) {
            stashed.delete(currentKey)
        }
        state.value = restored === undefined ? empty() : restored
    })
}
