import { onBeforeUnmount, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { debounce } from "lodash-es"
import { ctrans } from "@/Composables/useTrans"
import type { routeType } from "@/types/route"

export type AutoSaveStatus = null | "loading" | "success" | "error"

const AUTO_SAVE_DEBOUNCE_MS = 2500
const STATUS_RESET_MS = 2500

/**
 * Debounced PATCH of a workshop payload, skipping requests when nothing changed
 * since the last successful save.
 */
export const useAutoSave = <T extends object>(saveRoute: routeType, getPayload: () => T) => {
    const status = ref<AutoSaveStatus>(null)

    let statusTimeout: ReturnType<typeof setTimeout> | null = null
    let lastSavedPayload = JSON.stringify(getPayload())

    const setStatus = (nextStatus: AutoSaveStatus) => {
        status.value = nextStatus

        if (statusTimeout) {
            clearTimeout(statusTimeout)
            statusTimeout = null
        }

        if (nextStatus === "success" || nextStatus === "error") {
            statusTimeout = setTimeout(() => (status.value = null), STATUS_RESET_MS)
        }
    }

    const save = (force = false) => {
        const payload = getPayload()
        const serializedPayload = JSON.stringify(payload)

        if (!force && serializedPayload === lastSavedPayload) {
            return
        }

        setStatus("loading")

        router.patch(route(saveRoute.name, saveRoute.parameters), payload as Record<string, any>, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                lastSavedPayload = serializedPayload
                setStatus("success")
            },
            onError: () => {
                setStatus("error")
                notify({
                    title: ctrans("Save failed"),
                    text: ctrans("Auto save failed"),
                    type: "error"
                })
            }
        })
    }

    const saveDebounced = debounce(() => save(), AUTO_SAVE_DEBOUNCE_MS)

    const saveNow = () => {
        saveDebounced.cancel()
        save(true)
    }

    const cancelPendingSave = () => {
        saveDebounced.cancel()
    }

    onBeforeUnmount(() => {
        cancelPendingSave()
        if (statusTimeout) {
            clearTimeout(statusTimeout)
        }
    })

    return { status, saveDebounced, saveNow, cancelPendingSave }
}
