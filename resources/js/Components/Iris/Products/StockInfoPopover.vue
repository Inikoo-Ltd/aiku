<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue"
import Popover from "primevue/popover"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"

interface IncomingStock {
    quantity: number
    eta: string | null
    is_estimate: boolean
}

const props = defineProps<{
    product: {
        stock?: number
        is_on_demand?: boolean
        allow_stocks_to_be_shown_on_iris?: boolean
        allow_incoming_stocks_to_be_shown_on_iris?: boolean
        incoming_stock?: IncomingStock[]
    }
    stock?: number | null
}>()

const locale = useLocaleStore()

const HIDE_DELAY_IN_MS = 150

const isStockShown = computed(() => Boolean(props.product?.allow_stocks_to_be_shown_on_iris))
const isIncomingStockShown = computed(() => Boolean(props.product?.allow_incoming_stocks_to_be_shown_on_iris))
const isEnabled = computed(() => isStockShown.value || isIncomingStockShown.value)

const availableStock = computed(() => Number(props.stock ?? props.product?.stock ?? 0))

const incomingStocks = computed(() => {
    const groupedByEta = new Map<string, IncomingStock>()

    for (const incoming of props.product?.incoming_stock ?? []) {
        const groupKey = `${incoming.eta ?? ""}|${incoming.is_estimate ? 1 : 0}`
        const group = groupedByEta.get(groupKey)

        if (group) {
            group.quantity += Number(incoming.quantity)
        } else {
            groupedByEta.set(groupKey, { ...incoming, quantity: Number(incoming.quantity) })
        }
    }

    return [...groupedByEta.values()].sort((a, b) => {
        if (!a.eta || !b.eta) {
            return Number(!a.eta) - Number(!b.eta)
        }

        return a.eta.localeCompare(b.eta)
    })
})

const hasEstimatedEta = computed(() => incomingStocks.value.some(incoming => incoming.eta && incoming.is_estimate))

const _popover = ref()
const _trigger = ref<HTMLElement | null>(null)
let hideTimeout: ReturnType<typeof setTimeout> | undefined

const cancelHide = () => clearTimeout(hideTimeout)

const showPopover = (event: Event) => {
    cancelHide()
    _popover.value?.show(event, _trigger.value)
}

const hidePopover = () => {
    cancelHide()
    hideTimeout = setTimeout(() => _popover.value?.hide(), HIDE_DELAY_IN_MS)
}

onBeforeUnmount(cancelHide)
</script>

<template>
    <span
        ref="_trigger"
        class="inline-flex items-center gap-2"
        :class="isEnabled ? 'cursor-help' : ''"
        :tabindex="isEnabled ? 0 : undefined"
        @mouseenter="event => isEnabled && showPopover(event)"
        @mouseleave="hidePopover"
        @focus="event => isEnabled && showPopover(event)"
        @blur="hidePopover"
        @click="event => isEnabled && showPopover(event)"
    >
        <slot />

        <Popover v-if="isEnabled" ref="_popover" class="max-w-[90vw]">
            <div class="min-w-[220px] text-sm text-gray-700" @mouseenter="cancelHide" @mouseleave="hidePopover">
                <div v-if="isStockShown" class="flex items-center justify-between gap-6">
                    <span class="text-gray-500">{{ ctrans("Available now") }}</span>
                    <span class="font-semibold tabular-nums" :class="product.is_on_demand || availableStock > 0 ? 'text-green-600' : 'text-red-600'">
                        {{ product.is_on_demand ? ctrans("Unlimited") : locale.number(availableStock) }}
                    </span>
                </div>

                <div v-if="isIncomingStockShown" :class="isStockShown ? 'mt-3 border-t border-gray-200 pt-3' : ''">
                    <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("On its way") }}</div>

                    <table v-if="incomingStocks.length" class="w-full">
                        <thead>
                            <tr class="text-left text-xs text-gray-400">
                                <th class="py-1 font-normal">{{ ctrans("Quantity") }}</th>
                                <th class="py-1 text-right font-normal">{{ ctrans("ETA") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="incoming in incomingStocks" :key="`${incoming.eta}-${incoming.is_estimate}`" class="border-t border-gray-100">
                                <td class="py-1 font-medium tabular-nums">{{ locale.number(incoming.quantity) }}</td>
                                <td class="whitespace-nowrap py-1 pl-6 text-right text-gray-500">
                                    {{ incoming.eta ? (incoming.is_estimate ? "~ " : "") + useFormatTime(incoming.eta, { formatTime: "mdy" }) : ctrans("To be confirmed") }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="text-gray-500">{{ ctrans("Nothing on order") }}</div>

                    <div v-if="hasEstimatedEta" class="mt-2 text-xs text-gray-400">~ {{ ctrans("Estimated date") }}</div>
                </div>
            </div>
        </Popover>
    </span>
</template>
