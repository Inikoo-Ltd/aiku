import { computed, nextTick, onBeforeUnmount, ref, watch, type Ref } from "vue"
import { debounce } from "lodash-es"

const HISTORY_DEBOUNCE_MS = 500
const HISTORY_LIMIT = 50

/**
 * Snapshot based undo/redo for the banner layout. Snapshots are taken once a
 * burst of edits settles, so dragging a slider records one step and not one per
 * pixel.
 */
export const useBannerHistory = <T>(state: Ref<T>) => {
    const past = ref<string[]>([])
    const future = ref<string[]>([])
    const revision = ref(0)

    let lastCommitted = JSON.stringify(state.value)
    let isApplying = false

    const commit = () => {
        const serialized = JSON.stringify(state.value)

        if (serialized === lastCommitted) {
            return
        }

        past.value.push(lastCommitted)
        if (past.value.length > HISTORY_LIMIT) {
            past.value.shift()
        }

        future.value = []
        lastCommitted = serialized
    }

    const commitDebounced = debounce(commit, HISTORY_DEBOUNCE_MS)

    const apply = (serialized: string) => {
        commitDebounced.cancel()
        isApplying = true
        state.value = JSON.parse(serialized)
        lastCommitted = serialized
        revision.value++

        nextTick(() => (isApplying = false))
    }

    const undo = () => {
        if (!past.value.length) {
            return
        }

        const previous = past.value.pop() as string
        future.value.push(JSON.stringify(state.value))
        apply(previous)
    }

    const redo = () => {
        if (!future.value.length) {
            return
        }

        const next = future.value.pop() as string
        past.value.push(JSON.stringify(state.value))
        apply(next)
    }

    watch(
        state,
        () => {
            if (isApplying) {
                return
            }

            commitDebounced()
        },
        { deep: true }
    )

    const onKeydown = (event: KeyboardEvent) => {
        if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== "z") {
            return
        }

        const target = event.target as HTMLElement | null
        if (target?.isContentEditable || ["INPUT", "TEXTAREA", "SELECT"].includes(target?.tagName ?? "")) {
            return
        }

        event.preventDefault()
        event.shiftKey ? redo() : undo()
    }

    if (typeof window !== "undefined") {
        window.addEventListener("keydown", onKeydown)
    }

    onBeforeUnmount(() => {
        commitDebounced.cancel()
        if (typeof window !== "undefined") {
            window.removeEventListener("keydown", onKeydown)
        }
    })

    return {
        canUndo: computed(() => past.value.length > 0),
        canRedo: computed(() => future.value.length > 0),
        revision,
        undo,
        redo,
        flushHistory: () => commitDebounced.flush()
    }
}
