<script setup lang="ts">
import { ref } from "vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    url: string
    flagged?: boolean
    reason?: string | null
}>()

const emit = defineEmits<{
    (e: "flagged", reason: string): void
}>()

const isOpen = ref(false)
const reasonText = ref("")
const isSending = ref(false)
const flaggedReason = ref<string | null>(props.flagged ? (props.reason ?? "") : null)

const send = async () => {
    const reason = reasonText.value.trim()
    if (!reason || isSending.value) return

    isSending.value = true
    try {
        await axios.post(props.url, { reason })
        flaggedReason.value = reason
        isOpen.value = false
        emit("flagged", reason)
    } catch (error: any) {
        if (error?.response?.status === 422 && !error.response.data?.errors) {
            flaggedReason.value = ""
            isOpen.value = false
        }
        notify({
            title: ctrans("Could not mark it as wrong"),
            text: error?.response?.data?.message ?? "",
            type: "error",
        })
    } finally {
        isSending.value = false
    }
}
</script>

<template>
    <span class="inline-flex flex-col items-start gap-1">
        <span v-if="flaggedReason !== null" class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700 ring-1 ring-inset ring-red-200"
            v-tooltip="flaggedReason || null">
            {{ ctrans("Flagged as wrong") }}<template v-if="flaggedReason">: {{ flaggedReason }}</template>
        </span>
        <button v-else-if="!isOpen" type="button" @click="isOpen = true"
            class="rounded-md px-2 py-0.5 text-xs text-red-700 ring-1 ring-inset ring-red-200 hover:bg-red-50">
            {{ ctrans("Wrong?") }}
        </button>
        <form v-else class="flex w-72 flex-col gap-1.5 rounded-md bg-white p-2 text-left ring-1 ring-red-200" @submit.prevent="send">
            <label class="text-xs text-gray-600">{{ ctrans("Why is it wrong? We use this to correct the automatic replies.") }}</label>
            <textarea v-model="reasonText" rows="2" maxlength="500" required autofocus
                class="w-full rounded-md border-gray-300 text-xs focus:border-red-400 focus:ring-red-400"
                :placeholder="ctrans('e.g. the customer already told us the products and quantities')" />
            <span class="flex justify-end gap-1.5">
                <button type="button" class="rounded-md px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-100" @click="isOpen = false">
                    {{ ctrans("Cancel") }}
                </button>
                <button type="submit" :disabled="!reasonText.trim() || isSending"
                    class="rounded-md bg-red-600 px-2 py-0.5 text-xs text-white hover:bg-red-700 disabled:opacity-50">
                    {{ ctrans("Mark as wrong") }}
                </button>
            </span>
        </form>
    </span>
</template>
