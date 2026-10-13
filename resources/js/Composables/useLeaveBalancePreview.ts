import { ref } from "vue"
import axios from "axios"
import type { LeaveBalanceChange } from "@/Components/HumanResources/LeaveBalanceChangesPreview.vue"

type BalanceChoices = Record<string, "carry" | "recorded">

export const useLeaveBalancePreview = (previewUrl: () => string, payload: () => Record<string, any>) => {
    const entries = ref<LeaveBalanceChange[]>([])
    const choices = ref<BalanceChoices>({})
    const requiresConfirmation = ref(false)
    const isLoading = ref(false)
    const errors = ref<Record<string, string>>({})

    const load = async (keepChoices = false): Promise<boolean> => {
        isLoading.value = true
        errors.value = {}

        try {
            const response = await axios.post(previewUrl(), {
                ...payload(),
                ...(keepChoices ? { balance_choices: choices.value } : {}),
            })

            entries.value = response.data.entries ?? []
            requiresConfirmation.value = !!response.data.requires_confirmation
            choices.value = Object.fromEntries(
                entries.value.filter((entry) => entry.has_choice).map((entry) => [entry.key, choices.value[entry.key] ?? entry.choice ?? "carry"])
            )

            return true
        } catch (error: any) {
            const responseErrors = error?.response?.data?.errors ?? {}
            errors.value = Object.fromEntries(
                Object.entries(responseErrors).map(([key, messages]) => [key, Array.isArray(messages) ? String(messages[0]) : String(messages)])
            )

            if (!Object.keys(errors.value).length) {
                errors.value = { balance_changes: error?.response?.data?.message ?? error?.message ?? "" }
            }

            return false
        } finally {
            isLoading.value = false
        }
    }

    const reset = () => {
        entries.value = []
        choices.value = {}
        requiresConfirmation.value = false
        errors.value = {}
    }

    return { entries, choices, requiresConfirmation, isLoading, errors, load, reset }
}
