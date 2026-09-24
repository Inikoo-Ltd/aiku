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
}
