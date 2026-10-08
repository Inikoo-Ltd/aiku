<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue"
import Popover from "primevue/popover"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBoxOpen, faShippingFast, faCalendarAlt, faClock, faInfinity } from "@fas"
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
const isAvailable = computed(() => Boolean(props.product?.is_on_demand) || availableStock.value > 0)

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

        <Popover v-if="isEnabled" ref="_popover" class="max-w-[90vw]" :pt="{ content: { class: '!p-2' } }">
            <div class="min-w-[180px] text-xs text-gray-700" @mouseenter="cancelHide" @mouseleave="hidePopover">
                <div
                    v-if="isStockShown"
                    class="flex items-center justify-between gap-4 rounded px-2 py-1"
                    :class="isAvailable ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                >
                    <span class="flex items-center gap-1.5">
                        <FontAwesomeIcon :icon="faBoxOpen" :class="isAvailable ? 'text-green-500' : 'text-red-500'" fixed-width aria-hidden="true" />
                        {{ ctrans("Available now") }}
                    </span>
                    <span class="text-sm font-bold tabular-nums">
                        <FontAwesomeIcon v-if="product.is_on_demand" :icon="faInfinity" aria-hidden="true" />
                        <template v-else>{{ locale.number(availableStock) }}</template>
                    </span>
                </div>

                <div v-if="isIncomingStockShown" class="px-2" :class="isStockShown ? 'mt-1.5' : ''">
                    <div class="flex items-center gap-1.5 font-semibold text-amber-600">
                        <FontAwesomeIcon :icon="faShippingFast" fixed-width aria-hidden="true" />
                        {{ ctrans("On its way") }}
                        <span v-if="!incomingStocks.length" class="ml-auto font-normal text-gray-500">{{ ctrans("Nothing on order") }}</span>
                    </div>

                    <div
                        v-for="incoming in incomingStocks"
                        :key="`${incoming.eta}-${incoming.is_estimate}`"
                        class="mt-0.5 flex items-center justify-between gap-4"
                    >
                        <span class="font-bold tabular-nums text-amber-600">+{{ locale.number(incoming.quantity) }}</span>
                        <span
                            class="flex items-center gap-1 whitespace-nowrap text-gray-600"
                            v-tooltip="incoming.eta && incoming.is_estimate ? ctrans('Estimated date') : undefined"
                        >
                            <FontAwesomeIcon :icon="incoming.eta && !incoming.is_estimate ? faCalendarAlt : faClock" class="text-amber-500" fixed-width aria-hidden="true" />
                            {{ incoming.eta ? (incoming.is_estimate ? "~ " : "") + useFormatTime(incoming.eta, { formatTime: "mdy" }) : ctrans("To be confirmed") }}
                        </span>
                    </div>
                </div>
            </div>
        </Popover>
    </span>
</template>
