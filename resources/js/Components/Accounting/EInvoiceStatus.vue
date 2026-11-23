<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperPlane } from "@fal"
import { ctrans } from "@/Composables/useTrans"

library.add(faPaperPlane)

export interface EInvoiceSummary {
    status: string
    label: string
    tone: "success" | "danger" | "waiting"
    error: string | null
    transaction_id: string | null
    updated_at: string | null
    attempts: number
    send_route: { name: string, parameters: Record<string, number>, method: string } | null
}

const props = defineProps<{
    eInvoice: EInvoiceSummary
}>()

const toneClass = computed(() => ({
    success: "text-green-600",
    danger: "text-red-600",
    waiting: "text-amber-600",
}[props.eInvoice.tone]))

const isSending = ref(false)
const sendError = ref<string | null>(null)

const sendAgain = () => {
    if (!props.eInvoice.send_route) {
        return
    }
    router.post(route(props.eInvoice.send_route.name, props.eInvoice.send_route.parameters), {}, {
        preserveScroll: true,
        onStart: () => {
            isSending.value = true
            sendError.value = null
        },
        onError: (errors) => {
            sendError.value = Object.values(errors).join(" ")
        },
        onFinish: () => {
            isSending.value = false
        },
    })
}

const tooltip = computed(() => [
    props.eInvoice.error,
    props.eInvoice.transaction_id ? ctrans("Transaction :id", { id: props.eInvoice.transaction_id }) : null,
    props.eInvoice.attempts > 1 ? ctrans(":count attempts", { count: props.eInvoice.attempts }) : null,
].filter(Boolean).join(" · "))
</script>

<template>
    <dl class="pl-1 flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="ctrans('E-invoice')" class="flex-none">
            <span class="sr-only">{{ ctrans("E-invoice") }}</span>
            <FontAwesomeIcon icon="fal fa-paper-plane" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
        </dt>
        <dd class="text-base flex items-center gap-x-2 min-w-0" v-tooltip="tooltip || undefined">
            <span :class="toneClass" class="whitespace-nowrap">{{ eInvoice.label }}</span>
            <span v-if="eInvoice.tone === 'danger' && eInvoice.error" class="text-xs text-gray-500 truncate">{{ eInvoice.error }}</span>
            <Button v-if="eInvoice.send_route" type="tertiary" size="xxs" :label="ctrans('Send again')" :loading="isSending" @click="sendAgain" />
            <span v-if="sendError" class="text-xs text-red-600">{{ sendError }}</span>
        </dd>
    </dl>
</template>
