<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Fri, 25 Sep 2026 Malaga, Spain
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { TransitionRoot, TransitionChild, Dialog, DialogPanel } from "@headlessui/vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes } from "@fal"
import { ref, watch } from "vue"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
library.add(faTimes)

interface InboundLine {
    type: string
    reference: string
    state: string
    state_label: string
    quantity: number
    eta: string | null
}

interface Shop {
    shop_code: string
    shop_name: string
    shop_state: string
    product_code: string
    is_for_sale: boolean
}

interface MonthlySale {
    month: string
    sales: number
}

interface OrganisationDetail {
    organisation: string
    name: string
    on_hand: number
    available: number
    allocated: number
    inbound: number
    next_expected_at: string | null
    inbound_lines: InboundLine[]
    days_of_cover: number | null
    state: string
    differs_from_group: boolean
    shops: Shop[]
    monthly_sales: MonthlySale[]
}

interface StatusChange {
    organisation: string
    from: string | null
    to: string | null
    reason: string | null
    source: string | null
    who: string | null
    at: string
}

interface Detail {
    id: number
    code: string
    name: string | null
    group_state: string
    all_retired: boolean
    organisations: OrganisationDetail[]
    status_history: StatusChange[]
}

const props = defineProps<{
    isOpen: boolean
    stockSlug: string | null
}>()

const emits = defineEmits<{ (e: "onClose"): void }>()

const loading = ref(false)
const errorMessage = ref<string | null>(null)
const detail = ref<Detail | null>(null)

const quantity = (value: number): string => new Intl.NumberFormat("en-GB", { maximumFractionDigits: 1 }).format(value)

const load = async () => {
    if (!props.stockSlug) return
    loading.value = true
    errorMessage.value = null
    detail.value = null
    try {
        const response = await axios.get(route("grp.goods.products.show", { stock: props.stockSlug }))
        detail.value = response.data
    } catch (error) {
        errorMessage.value = ctrans("Could not load this product")
    } finally {
        loading.value = false
    }
}

watch(
    () => [props.isOpen, props.stockSlug],
    ([isOpen]) => {
        if (isOpen) {
            load()
        }
    },
    { immediate: true }
)
</script>

<template>
    <TransitionRoot appear :show="isOpen" as="template">
        <Dialog as="div" class="relative" style="z-index: 30" @close="emits('onClose')">
            <TransitionChild
                as="template"
                enter="duration-200 ease-out"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="duration-150 ease-in"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-black/30" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-hidden">
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <TransitionChild
                        as="template"
                        enter="transform transition ease-in-out duration-300"
                        enter-from="translate-x-full"
                        enter-to="translate-x-0"
                        leave="transform transition ease-in-out duration-200"
                        leave-from="translate-x-0"
                        leave-to="translate-x-full"
                    >
                        <DialogPanel class="pointer-events-auto w-screen max-w-xl">
                            <div class="flex h-full flex-col overflow-y-auto bg-white shadow-xl">
                                <div class="flex items-start justify-between border-b border-gray-200 px-4 py-3">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900">{{ detail?.code }}</div>
                                        <div class="text-xs text-gray-500">{{ detail?.name }}</div>
                                    </div>
                                    <button type="button" :aria-label="ctrans('Close')" class="text-gray-400 hover:text-gray-600" @click="emits('onClose')">
                                        <FontAwesomeIcon icon="fal fa-times" fixed-width />
                                    </button>
                                </div>

                                <div v-if="loading" class="flex flex-1 items-center justify-center gap-2 text-sm text-gray-500">
                                    <LoadingIcon /> {{ ctrans("Loading") }}
                                </div>

                                <div v-else-if="errorMessage" class="p-4 text-sm text-red-600">{{ errorMessage }}</div>

                                <div v-else-if="detail" class="flex-1 space-y-4 px-4 py-3">
                                    <div v-if="detail.all_retired" class="rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700">
                                        {{ ctrans("Retired in every organisation") }}
                                    </div>

                                    <div v-for="organisation in detail.organisations" :key="organisation.organisation" class="rounded-lg border border-gray-200 p-3 text-xs">
                                        <div class="mb-2 flex items-center justify-between">
                                            <div class="text-sm font-semibold text-gray-900">{{ organisation.organisation }} <span class="font-normal text-gray-500">{{ organisation.name }}</span></div>
                                            <span
                                                class="rounded px-1.5 py-0.5 font-semibold"
                                                :class="organisation.differs_from_group ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600'"
                                            >
                                                {{ organisation.state }}
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-4 gap-2 text-gray-700">
                                            <div><span class="block text-gray-500">{{ ctrans("On hand") }}</span>{{ quantity(organisation.on_hand) }}</div>
                                            <div><span class="block text-gray-500">{{ ctrans("Allocated") }}</span>{{ quantity(organisation.allocated) }}</div>
                                            <div><span class="block text-gray-500">{{ ctrans("Available") }}</span>{{ quantity(organisation.available) }}</div>
                                            <div><span class="block text-gray-500">{{ ctrans("Cover") }}</span>{{ organisation.days_of_cover === null ? "-" : ctrans(":days d", { days: organisation.days_of_cover }) }}</div>
                                        </div>

                                        <div v-if="organisation.inbound_lines.length" class="mt-2">
                                            <div class="text-gray-500">{{ ctrans("Inbound") }}</div>
                                            <div v-for="(line, index) in organisation.inbound_lines" :key="index" class="flex items-center justify-between text-gray-700">
                                                <span>{{ line.reference }} · {{ line.state_label }}</span>
                                                <span class="tabular-nums">{{ quantity(line.quantity) }} · {{ line.eta ?? "-" }}</span>
                                            </div>
                                        </div>

                                        <div v-if="organisation.shops.length" class="mt-2">
                                            <div class="text-gray-500">{{ ctrans("Shops") }}</div>
                                            <div class="flex flex-wrap gap-1">
                                                <span
                                                    v-for="shop in organisation.shops"
                                                    :key="shop.shop_code"
                                                    class="rounded px-1.5 py-0.5"
                                                    :class="shop.is_for_sale ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'"
                                                    :title="shop.shop_name"
                                                >
                                                    {{ shop.shop_code }}
                                                </span>
                                            </div>
                                        </div>

                                        <div v-if="organisation.monthly_sales.length" class="mt-2">
                                            <div class="mb-1 text-gray-500">{{ ctrans("Sales, last 12 months") }}</div>
                                            <table class="w-full text-right tabular-nums">
                                                <tbody>
                                                    <tr>
                                                        <td v-for="sale in organisation.monthly_sales" :key="sale.month" class="px-0.5 text-gray-400">{{ sale.month.slice(5) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td v-for="sale in organisation.monthly_sales" :key="sale.month" class="px-0.5 font-medium text-gray-700">{{ Math.round(sale.sales) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div v-if="detail.status_history.length">
                                        <div class="mb-1 text-xs font-semibold text-gray-600">{{ ctrans("Status history") }}</div>
                                        <div class="space-y-1 text-xs text-gray-700">
                                            <div v-for="(change, index) in detail.status_history" :key="index" class="border-b border-gray-100 pb-1">
                                                <span class="font-medium">{{ change.organisation }}</span>
                                                {{ change.from }} → {{ change.to }}
                                                <span class="text-gray-400">· {{ change.reason }} · {{ change.who }} · {{ new Date(change.at).toLocaleDateString() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
