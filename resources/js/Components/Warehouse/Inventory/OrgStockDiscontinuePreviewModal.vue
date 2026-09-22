<script setup lang="ts">
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import { notify } from "@kyvg/vue3-notification"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { computed, inject, ref, watch } from "vue"

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
    discontinueRoute?: routeType | null
}>()

const emits = defineEmits<{ (e: "onClose"): void; (e: "onDone"): void }>()

const stateOptions = computed(() => ({
    discontinuing: ctrans("Discontinuing"),
    discontinued: ctrans("Discontinued"),
    suspended: ctrans("Suspended"),
    active: ctrans("Active"),
}))

const form = ref({
    state: "discontinuing",
    reason: "",
    effective_at: "",
    organisation_states: {} as Record<string, string>,
})
const isSubmitting = ref(false)
const submitError = ref<string | null>(null)

const organisationCodes = computed(() => {
    const codes = new Set<string>()
    previews.value.forEach((preview) => Object.keys(preview.organisations).forEach((code) => codes.add(code)))
    return Array.from(codes).sort()
})

const canConfirm = computed(() =>
    !!props.discontinueRoute && previews.value.length > 0 && (form.value.state === "active" || form.value.reason.trim().length > 0)
)

const onConfirm = () => {
    if (!props.discontinueRoute || !canConfirm.value) return
    isSubmitting.value = true
    submitError.value = null
    const organisation_states = Object.fromEntries(Object.entries(form.value.organisation_states).filter(([, state]) => state))
    router.post(
        route(props.discontinueRoute.name, props.discontinueRoute.parameters),
        {
            org_stock_ids: props.orgStockIds,
            state: form.value.state,
            reason: form.value.reason || null,
            effective_at: form.value.effective_at || null,
            organisation_states,
            expected_updated_at: Object.fromEntries(previews.value.map((preview) => [preview.id, preview.updated_at])),
            source: "ui",
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                notify({ title: ctrans("Done"), text: ctrans("SKO state updated"), type: "success" })
                emits("onDone")
                router.reload()
            },
            onError: (errors) => {
                submitError.value = Object.values(errors).flat().join(" ")
            },
            onFinish: () => {
                isSubmitting.value = false
            },
        }
    )
}

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

            <div v-if="discontinueRoute && !isLoading && previews.length" class="grid gap-3 border-t border-gray-200 pt-4 md:grid-cols-3">
                <label class="text-sm">
                    <span class="block text-gray-500 mb-1">{{ ctrans("New state, every organisation") }}</span>
                    <select v-model="form.state" class="w-full rounded-md border-gray-300 text-sm">
                        <option v-for="(label, value) in stateOptions" :key="value" :value="value">{{ label }}</option>
                    </select>
                </label>
                <label class="text-sm">
                    <span class="block text-gray-500 mb-1">{{ ctrans("Effective from (empty = now)") }}</span>
                    <input v-model="form.effective_at" type="date" class="w-full rounded-md border-gray-300 text-sm" />
                </label>
                <div class="text-sm">
                    <span class="block text-gray-500 mb-1">{{ ctrans("Exceptions per organisation") }}</span>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="code in organisationCodes" :key="code" class="flex items-center gap-1">
                            <span class="font-medium">{{ code }}</span>
                            <select v-model="form.organisation_states[code]" class="rounded-md border-gray-300 text-xs">
                                <option value="">{{ ctrans("follow") }}</option>
                                <option v-for="(label, value) in stateOptions" :key="value" :value="value">{{ label }}</option>
                            </select>
                        </label>
                    </div>
                </div>
                <div class="md:col-span-3 text-sm">
                    <span class="block text-gray-500 mb-1">{{ form.state === "active" ? ctrans("Reason (optional)") : ctrans("Reason (required)") }}</span>
                    <PureTextarea v-model="form.reason" :rows="2" full :placeholder="ctrans('Why this SKO changes state')" />
                </div>
                <div v-if="submitError" class="md:col-span-3 text-sm text-red-600">{{ submitError }}</div>
            </div>

            <div class="flex justify-end gap-2">
                <Button type="cancel" :label="ctrans('Close')" @click="emits('onClose')" />
                <Button type="negative" :label="ctrans('Confirm')" :disabled="!canConfirm" :loading="isSubmitting" @click="onConfirm" />
            </div>
        </div>
    </Modal>
</template>
