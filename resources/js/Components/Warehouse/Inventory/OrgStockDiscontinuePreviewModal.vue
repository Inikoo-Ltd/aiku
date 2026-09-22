<script setup lang="ts">
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import axios from "axios"
import { inject, ref, watch } from "vue"

interface CountWithReferences {
    count: number
    references: string[]
}

interface Preview {
    id: number
    code: string
    name: string | null
    state_label: string
    updated_at: string | null
    organisations: Record<string, string>
    quantity: number
    days_of_cover: number | null
    number_products: number
    purchase_orders: CountWithReferences
    stock_deliveries: CountWithReferences
    portfolios: { count: number; customers: number; by_platform: Record<string, number> }
    external_shops: { code: string; status: string; shop_code: string; shop_name: string }[]
    webpages: { count: number; urls: string[] }
    mailshots: { known: boolean; reason: string }
    orders: CountWithReferences & { quantity: number }
    is_exclusive: boolean
}

const props = defineProps<{
    isOpen: boolean
    orgStockIds: number[]
    previewRoute: routeType
}>()

const emits = defineEmits<{ (e: "onClose"): void }>()

const locale = inject("locale", aikuLocaleStructure)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const previews = ref<Preview[]>([])

const loadPreview = async () => {
    isLoading.value = true
    errorMessage.value = null
    previews.value = []
    try {
        const response = await axios.get(route(props.previewRoute.name, props.previewRoute.parameters), {
            params: { org_stock_ids: props.orgStockIds },
        })
        previews.value = response.data
    } catch (error) {
        errorMessage.value = ctrans("Could not load the preview")
    } finally {
        isLoading.value = false
    }
}

watch(() => props.isOpen, (isOpen) => {
    if (isOpen) {
        loadPreview()
    }
})

const references = (block: CountWithReferences) => block.references.join(", ")
const platformSummary = (byPlatform: Record<string, number>) =>
    Object.entries(byPlatform).map(([platform, count]) => `${platform} ${count}`).join(", ")
</script>

<template>
    <Modal :isOpen="isOpen" @onClose="emits('onClose')" width="w-full max-w-5xl">
        <div class="flex flex-col gap-4">
            <div>
                <h3 class="text-lg font-semibold">{{ ctrans("Discontinue preview") }}</h3>
                <p class="text-sm text-gray-500">{{ ctrans("What still hangs off the selected SKOs. Nothing is changed yet.") }}</p>
            </div>

            <div v-if="isLoading" class="flex items-center gap-2 py-8 justify-center text-gray-500">
                <LoadingIcon /> {{ ctrans("Loading") }}
            </div>

            <div v-else-if="errorMessage" class="text-red-600 text-sm">{{ errorMessage }}</div>

            <div v-else class="max-h-[60vh] overflow-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="py-2 pr-3">{{ ctrans("SKO") }}</th>
                            <th class="py-2 pr-3 text-right">{{ ctrans("Stock") }}</th>
                            <th class="py-2 pr-3 text-right">{{ ctrans("Cover") }}</th>
                            <th class="py-2 pr-3">{{ ctrans("Purchase orders") }}</th>
                            <th class="py-2 pr-3">{{ ctrans("Deliveries") }}</th>
                            <th class="py-2 pr-3">{{ ctrans("Portfolios") }}</th>
                            <th class="py-2 pr-3">{{ ctrans("Marketplaces") }}</th>
                            <th class="py-2 pr-3">{{ ctrans("Web pages") }}</th>
                            <th class="py-2 pr-3">{{ ctrans("Customer orders") }}</th>
                            <th class="py-2 pr-3">{{ ctrans("Flags") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="preview in previews" :key="preview.id" class="border-b border-gray-100 align-top">
                            <td class="py-2 pr-3">
                                <div class="font-medium">{{ preview.code }}</div>
                                <div class="text-gray-500">{{ preview.name }}</div>
                                <div class="text-gray-400">{{ preview.state_label }}</div>
                                <div class="text-xs text-gray-400">
                                    <span v-for="(state, organisation) in preview.organisations" :key="organisation" class="mr-2">{{ organisation }}: {{ state }}</span>
                                </div>
                            </td>
                            <td class="py-2 pr-3 text-right tabular-nums">{{ locale.number(preview.quantity) }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums">
                                {{ preview.days_of_cover === null ? "-" : ctrans(":days days", { days: preview.days_of_cover }) }}
                            </td>
                            <td class="py-2 pr-3" :class="preview.purchase_orders.count ? 'text-amber-700' : 'text-gray-400'">
                                {{ preview.purchase_orders.count }}
                                <div v-if="preview.purchase_orders.count" class="text-xs text-gray-500">{{ references(preview.purchase_orders) }}</div>
                            </td>
                            <td class="py-2 pr-3" :class="preview.stock_deliveries.count ? 'text-amber-700' : 'text-gray-400'">
                                {{ preview.stock_deliveries.count }}
                                <div v-if="preview.stock_deliveries.count" class="text-xs text-gray-500">{{ references(preview.stock_deliveries) }}</div>
                            </td>
                            <td class="py-2 pr-3" :class="preview.portfolios.count ? 'text-amber-700' : 'text-gray-400'">
                                {{ preview.portfolios.count }}
                                <div v-if="preview.portfolios.count" class="text-xs text-gray-500">
                                    {{ ctrans(":count customers", { count: preview.portfolios.customers }) }} · {{ platformSummary(preview.portfolios.by_platform) }}
                                </div>
                            </td>
                            <td class="py-2 pr-3" :class="preview.external_shops.length ? 'text-amber-700' : 'text-gray-400'">
                                {{ preview.external_shops.length }}
                                <div v-for="listing in preview.external_shops" :key="listing.shop_code + listing.code" class="text-xs text-gray-500">
                                    {{ listing.shop_name }}: {{ listing.code }} ({{ listing.status }})
                                </div>
                            </td>
                            <td class="py-2 pr-3" :class="preview.webpages.count ? 'text-amber-700' : 'text-gray-400'">
                                {{ preview.webpages.count }}
                                <div v-for="url in preview.webpages.urls" :key="url" class="text-xs text-gray-500 break-all">{{ url }}</div>
                            </td>
                            <td class="py-2 pr-3" :class="preview.orders.count ? 'text-amber-700' : 'text-gray-400'">
                                {{ preview.orders.count }}
                                <div v-if="preview.orders.count" class="text-xs text-gray-500">
                                    {{ ctrans(":quantity units", { quantity: locale.number(preview.orders.quantity) }) }} · {{ references(preview.orders) }}
                                </div>
                            </td>
                            <td class="py-2 pr-3 text-xs text-gray-500">
                                <div v-if="preview.is_exclusive" class="text-purple-700">{{ ctrans("Exclusive range") }}</div>
                                <div v-if="!preview.number_products">{{ ctrans("No products") }}</div>
                                <div v-if="!preview.mailshots.known" v-tooltip="preview.mailshots.reason">{{ ctrans("Mailshots: check by hand") }}</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-2">
                <Button type="cancel" :label="ctrans('Close')" @click="emits('onClose')" />
                <Button type="negative" :label="ctrans('Discontinue')" disabled v-tooltip="ctrans('Confirming is not available yet')" />
            </div>
        </div>
    </Modal>
</template>
