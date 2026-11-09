import { onBeforeUnmount, Ref, watch } from "vue"

const storageKey = (key: string) => `composer-draft:${key}`

export const readComposerDraft = (key: string): string => {
    try {
        return localStorage.getItem(storageKey(key)) ?? ""
    } catch {
        return ""
    }
}

export const writeComposerDraft = (key: string, text: string) => {
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
        text.value = currentKey ? readComposerDraft(currentKey) : ""
    }, { immediate: true })

    watch(text, (value) => {
        const currentKey = key()
        if (currentKey) {
            writeComposerDraft(currentKey, value ?? "")
        }
    })

    return () => {
        const currentKey = key()
        if (currentKey) {
            writeComposerDraft(currentKey, "")
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

export type StoredComposerFile = { file: File, isImage: boolean, pictureId?: string }

// ponytail: a chat abandoned with files in its reply keeps them until the agent sends or removes them; sweep by age if it ever matters
const openFileStore = (): Promise<IDBDatabase> => new Promise((resolve, reject) => {
    const request = indexedDB.open("composer-draft-files", 1)
    request.onupgradeneeded = () => request.result.createObjectStore("files")
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
})

const fileStoreRequest = async <T>(mode: IDBTransactionMode, run: (store: IDBObjectStore) => IDBRequest): Promise<T | undefined> => {
    try {
        const database = await openFileStore()

        return await new Promise<T>((resolve, reject) => {
            const request = run(database.transaction("files", mode).objectStore("files"))
            request.onsuccess = () => resolve(request.result)
            request.onerror = () => reject(request.error)
        }).finally(() => database.close())
    } catch {
        return undefined
    }
}

export const readComposerFiles = async (key: string): Promise<StoredComposerFile[]> =>
    (await fileStoreRequest<StoredComposerFile[]>("readonly", (store) => store.get(key))) ?? []

export const writeComposerFiles = (key: string, files: StoredComposerFile[]) =>
    fileStoreRequest("readwrite", (store) => files.length
        ? store.put(files.map(({ file, isImage, pictureId }) => ({ file, isImage, pictureId })), key)
        : store.delete(key))
