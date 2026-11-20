<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 15 Oct 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { reactive, ref, watch } from "vue"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes, faExclamationTriangle, faBolt, faBan } from "@fal"

library.add(faCheck, faTimes, faExclamationTriangle, faBolt, faBan)

type MaterialItem = {
    id: number
    submitted_at: string
    asked_by: string | null
    production_code: string
    to_location: string | null
    drop_point: string | null
    is_urgent: boolean
    needed_by: string | null
    note: string | null
    org_stock_code: string
    org_stock_name: string
    location_code: string | null
    bring: string
    skos_to_bring: number | null
    remaining_skos: number | null
    delivered_skos: number
    unbooked_skos: number
    warehouse_skos: number
    warehouse_short: boolean
    job_orders: string[]
    state: "pending" | "delivered" | "short"
    state_label: string
    short_reason: string | null
    brought_by: string | null
    done_at: string | null
    minutes_to_done: number | null
    from_locations: { location_org_stock_id: number, code: string, quantity: number }[]
}

const props = defineProps<{
    resource: { data: MaterialItem[] }
    scope: "open" | "done"
    canWork: boolean
}>()

const locale = useLocaleStore()
const todayIso = new Date().toLocaleDateString("sv-SE")

const source = reactive<Record<number, number | null>>({})
const quantity = reactive<Record<number, number | null>>({})
const errors = reactive<Record<number, string>>({})
const inProgress = ref<number | null>(null)

watch(() => props.resource?.data, items => {
    items?.forEach(item => {
        source[item.id] = item.from_locations[0]?.location_org_stock_id ?? null
        quantity[item.id] = item.remaining_skos ?? null
    })
}, { immediate: true })

const sourceQuantity = (item: MaterialItem) => item.from_locations.find(location => location.location_org_stock_id === source[item.id])?.quantity ?? 0
const willBeUnbooked = (item: MaterialItem) => (quantity[item.id] ?? 0) > sourceQuantity(item)

const routeParameters = (item: MaterialItem) => ({ ...route().params, productionMaterialRequestItem: item.id })

const requestOptions = (item: MaterialItem, onSuccess?: () => void) => ({
    preserveScroll: true,
    onStart: () => {
        inProgress.value = item.id
        delete errors[item.id]
    },
    onError: (formErrors: Record<string, string>) => errors[item.id] = Object.values(formErrors)[0],
    onSuccess,
    onFinish: () => inProgress.value = null,
})

function deliver(item: MaterialItem) {
    if (!((quantity[item.id] ?? 0) > 0)) {
        errors[item.id] = ctrans("Say how many SKOs you brought")
        return
    }
    router.post(route("grp.org.warehouses.show.inventory.production_materials.deliver", routeParameters(item)), {
        location_org_stock_id: source[item.id],
        quantity: quantity[item.id],
    }, requestOptions(item))
}

const cantBringItem = ref<MaterialItem | null>(null)
const cantBringReason = ref("")

function openCantBring(item: MaterialItem) {
    cantBringItem.value = item
    cantBringReason.value = ""
}

function cantBring() {
    const item = cantBringItem.value
    if (!item || !cantBringReason.value.trim()) return
    router.post(route("grp.org.warehouses.show.inventory.production_materials.short", routeParameters(item)), {
        reason: cantBringReason.value.trim(),
    }, requestOptions(item, () => cantBringItem.value = null))
}

const leadTime = (minutes: number | null) => {
    if (minutes === null) return null
    if (minutes < 60) return ctrans(":minutes min", { minutes: String(minutes) })
    if (minutes < 60 * 48) return ctrans(":hours h", { hours: locale.number(Math.round(minutes / 6) / 10) })
    return ctrans(":days days", { days: String(Math.round(minutes / 60 / 24)) })
}
</script>

<template>
    <Table :resource="resource" name="production_materials">
        <template #cell(org_stock_code)="{ item }">
            <div class="flex items-center gap-1.5">
                <FontAwesomeIcon v-if="item.is_urgent && scope === 'open'" v-tooltip="ctrans('Urgent')" icon="fal fa-bolt" class="text-amber-600" fixed-width aria-hidden="true" />
                <span class="whitespace-nowrap font-medium">{{ item.org_stock_code }}</span>
            </div>
            <div class="text-xs text-gray-500">{{ item.org_stock_name }}</div>
        </template>

        <template #cell(bring)="{ item }">
            <div class="font-semibold tabular-nums">{{ item.bring }}</div>
            <div v-if="item.needed_by && scope === 'open'" class="text-xs" :class="item.needed_by <= todayIso ? 'text-red-600' : 'text-amber-700'">
                {{ ctrans("Needed by :date", { date: useFormatTime(item.needed_by) }) }}
            </div>
            <div v-if="item.note" class="text-xs text-gray-600">{{ item.note }}</div>
            <div v-if="item.warehouse_short" class="text-xs text-red-600">
                <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
                {{ ctrans("Only :skos in the warehouse", { skos: locale.number(item.warehouse_skos) }) }}
            </div>
        </template>

        <template #cell(drop_point_name)="{ item }">
            <div class="font-medium">{{ item.drop_point ?? item.to_location ?? "-" }}</div>
            <div class="text-xs text-gray-500">{{ item.production_code }}<template v-if="item.job_orders.length"> · {{ item.job_orders.join(", ") }}</template></div>
        </template>

        <template #cell(submitted_at)="{ item }">
            <div class="whitespace-nowrap">{{ useFormatTime(item.submitted_at, { formatTime: "hm" }) }}</div>
            <div class="text-xs text-gray-500">{{ item.asked_by }}</div>
        </template>

        <template #cell(done_at)="{ item }">
            <div v-if="item.state === 'delivered'" class="text-green-700">
                <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                {{ ctransChoice(":count SKO|:count SKOs", item.delivered_skos, { count: locale.number(item.delivered_skos) }) }}
            </div>
            <div v-else class="text-red-700">
                <FontAwesomeIcon icon="fal fa-ban" fixed-width aria-hidden="true" />
                {{ item.short_reason ?? item.state_label }}
            </div>
            <div class="text-xs text-gray-500">
                {{ useFormatTime(item.done_at, { formatTime: "hm" }) }}<template v-if="item.brought_by"> · {{ item.brought_by }}</template><template v-if="leadTime(item.minutes_to_done)"> · {{ leadTime(item.minutes_to_done) }}</template>
            </div>
            <div v-if="item.unbooked_skos > 0" class="text-xs text-amber-700">
                <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
                {{ ctrans(":skos SKOs were not in Aiku, count it", { skos: locale.number(item.unbooked_skos) }) }}
            </div>
        </template>

        <template #cell(location_sort_code)="{ item }">
            <span v-if="item.location_code" class="whitespace-nowrap font-mono text-base">{{ item.location_code }}</span>
            <span v-else class="text-xs text-red-600">{{ ctrans("Not on any shelf in Aiku") }}</span>
        </template>

        <template #cell(actions)="{ item }">
            <div v-if="canWork" class="flex flex-col items-end gap-1">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <Select v-if="item.from_locations.length" v-model="source[item.id]" :options="item.from_locations" :optionLabel="location => `${location.code} (${locale.number(location.quantity)})`" optionValue="location_org_stock_id" size="small" class="font-mono text-sm" :aria-label="ctrans('From location')" />
                    <InputNumber v-model="quantity[item.id]" :min="0" :maxFractionDigits="3" :placeholder="ctrans('SKOs')" inputClass="w-20 text-right" :aria-label="ctrans('SKOs brought')" />
                    <Button type="tertiary" size="s" :label="ctrans('Delivered')" :loading="inProgress === item.id" :disabled="inProgress !== null" @click="deliver(item)">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="tertiary" size="s" :label="ctrans('Can\'t bring')" :disabled="inProgress !== null" @click="openCantBring(item)">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-ban" class="text-amber-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
                <div v-if="willBeUnbooked(item) && (quantity[item.id] ?? 0) > 0" class="text-xs text-amber-700">
                    {{ ctrans("Aiku has less here, the rest will be flagged to count") }}
                </div>
                <div v-if="errors[item.id]" class="text-xs text-red-600">{{ errors[item.id] }}</div>
            </div>
        </template>
    </Table>

    <Dialog :visible="!!cantBringItem" modal :header="ctrans('Can\'t bring :code', { code: cantBringItem?.org_stock_code ?? '' })" :style="{ width: '28rem' }" @update:visible="(open: boolean) => { if (!open) cantBringItem = null }">
        <label class="block text-sm text-gray-700">
            <span>{{ ctrans("Why") }} <span class="text-red-500">*</span></span>
            <InputText v-model="cantBringReason" autofocus maxlength="255" class="mt-1 w-full !text-sm" :placeholder="ctrans('Out of stock, ordered from supplier...')" @keyup.enter="cantBring" />
        </label>
        <div v-if="cantBringItem && errors[cantBringItem.id]" class="mt-1 text-xs text-red-600">{{ errors[cantBringItem.id] }}</div>
        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="cantBringItem = null">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="primary" full :label="ctrans('Tell production')" :disabled="!cantBringReason.trim()" :loading="inProgress !== null" @click="cantBring">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
