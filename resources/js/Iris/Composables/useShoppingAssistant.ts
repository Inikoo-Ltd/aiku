import { computed, inject, onMounted, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"

export interface ShoppingAssistant {
    name: string | null
    hand_over?: boolean
    expected_seconds?: number
}

export const shoppingAssistantQuestionKey = "shopping_assistant_question"
export const shoppingAssistantConversationKey = "shopping_assistant_conversation"

// ponytail: naive rule (product codes and product names are 1-3 words), replace with data from the search logs
export const isLittleBirdName = (name?: string | null): boolean => (name ?? "").trim().toLowerCase() === "little bird"

export const askPayload = (fields: Record<string, string | boolean | null>, images: File[]): Record<string, unknown> | FormData => {
    if (!images.length) {
        return fields
    }
    const form = new FormData()
    Object.entries(fields).forEach(([key, value]) => {
        if (value !== null) {
            form.append(key, typeof value === "boolean" ? (value ? "1" : "0") : value)
        }
    })
    images.forEach((image) => form.append("images[]", image))

    return form
}

export const looksLikeANeed = (query: string): boolean => query.trim().split(/\s+/).length >= 4 || query.includes("?")

export const useShoppingAssistant = () => {
    const layout: any = inject("layout", retinaLayoutStructure)

    const isMounted = ref(false)
    onMounted(() => {
        isMounted.value = true
    })

    const assistant = computed<ShoppingAssistant | null>(() => isMounted.value ? layout?.iris?.shopping_assistant ?? null : null)

    const isOfferedNow = (): boolean => {
        if (layout?.iris?.shopping_assistant) {
            return true
        }
        try {
            return !!JSON.parse(localStorage.getItem("iris") || "{}")?.shopping_assistant
        } catch {
            return false
        }
    }

    const askLabel = computed(() => assistant.value?.name
        ? ctrans("Ask :name", { name: assistant.value.name })
        : ctrans("Ask our range advisor"))

    const offerLabel = computed(() => ctrans("Ask :name to find products for your shop", {
        name: assistant.value?.name || ctrans("our range advisor"),
    }))

    const openAssistant = (question?: string) => {
        const text = question?.trim()
        if (text) {
            sessionStorage.setItem(shoppingAssistantQuestionKey, text)
        }
        if (route().current()?.startsWith("iris.")) {
            router.get("/assistant")
        } else {
            window.location.href = "/assistant"
        }
    }

    const buttonLabel = computed(() => assistant.value?.name || ctrans("Ask"))

    const isLittleBird = computed(() => isLittleBirdName(assistant.value?.name))

    return { assistant, isOfferedNow, askLabel, buttonLabel, offerLabel, looksLikeANeed, openAssistant, isLittleBird }
}
