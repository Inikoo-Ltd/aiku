<script setup lang="ts">
import { ref } from "vue"
import CustomerClaimForm from "@/Components/Chat/Customer/CustomerClaimForm.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faXmark } from "@fortawesome/free-solid-svg-icons"

const props = defineProps<{ orders: { id: number, reference: string, dispatched_at: string | null }[] }>()
const emit = defineEmits<{ (e: "close"): void, (e: "made", text: string): void }>()

const orderId = ref<number | null>(props.orders.length === 1 ? props.orders[0].id : null)
const reference = () => props.orders.find((order) => order.id === orderId.value)?.reference ?? ""

const made = (claim: { reference: string }) => emit("made", ctrans("I reported a problem with order :order (claim :reference).", { order: reference(), reference: claim.reference }))
</script>

<template>
    <div class="max-h-[60vh] overflow-y-auto border-t bg-white px-3 py-2">
        <div class="flex items-center justify-between">
            <p class="text-sm font-semibold">{{ ctrans("Problem with an order") }}</p>
            <button type="button" class="text-gray-400 hover:text-gray-600" :aria-label="ctrans('Close')" @click="emit('close')">
                <FontAwesomeIcon :icon="faXmark" fixed-width />
            </button>
        </div>
        <div v-if="!orderId" class="mt-2 space-y-1">
            <p class="text-xs text-gray-600">{{ ctrans("Which order?") }}</p>
            <button v-for="order in props.orders" :key="order.id" type="button" class="flex w-full justify-between rounded border px-3 py-2 text-left text-sm hover:bg-gray-50" @click="orderId = order.id">
                <span class="font-semibold">{{ order.reference }}</span>
                <span v-if="order.dispatched_at" class="text-xs text-gray-500">{{ ctrans("Dispatched :date", { date: useFormatTime(order.dispatched_at) }) }}</span>
            </button>
        </div>
        <CustomerClaimForm v-else :order-id="orderId" source="chat" @made="made" />
    </div>
</template>
