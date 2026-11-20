<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import AutoComplete from "primevue/autocomplete"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import Checkbox from "primevue/checkbox"
import DatePicker from "primevue/datepicker"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus, faTimes, faPaperPlane, faBolt } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faPlus, faTimes, faPaperPlane, faBolt)

type OrgStockOption = { id: number, code: string, name: string | null, warehouse_skos: number, factory_skos: number, packed_in: number, box_units: number | null }
type DropPoint = { id: number, name: string }
type Unit = { value: string, label: string }

type Line = {
    key: number
    orgStock: OrgStockOption | null
    requested_quantity: number | null
    requested_unit: string
    production_drop_point_id: number | null
    is_urgent: boolean
    needed_by: Date | null
    note: string
}

const props = defineProps<{
    productionId: number
    dropPoints: DropPoint[]
    units: Unit[]
}>()

const locale = useLocaleStore()

let nextKey = 1
const lastDropPoint = ref<number | null>(null)

const newLine = (): Line => ({
    key: nextKey++,
    orgStock: null,
    requested_quantity: null,
    requested_unit: "box",
    production_drop_point_id: lastDropPoint.value,
    is_urgent: false,
    needed_by: null,
    note: "",
})

const lines = ref<Line[]>([newLine()])
const suggestions = ref<OrgStockOption[]>([])
const isSending = ref(false)
const errors = ref<Record<string, string>>({})

async function searchOrgStocks(event: { query: string }) {
    const search = event.query.trim()
    if (!search) {
        suggestions.value = []
        return
    }
    const response = await axios.get(route("grp.json.production.material_org_stocks.index", { production: props.productionId }), { params: { search } })
    suggestions.value = response.data
}

function setDropPoint(line: Line, dropPointId: number | null) {
    line.production_drop_point_id = dropPointId
    lastDropPoint.value = dropPointId
}

const today = new Date(new Date().setHours(0, 0, 0, 0))
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

function comesTo(line: Line): string | null {
    const packedIn = line.orgStock?.packed_in ?? 0
    const quantity = line.requested_quantity ?? 0
    if (!packedIn || !quantity) {
        return null
    }
    if (line.requested_unit === "box" && line.orgStock?.box_units) {
        const units = quantity * line.orgStock.box_units
        const skos = ctrans(":skos SKOs", { skos: locale.number(units / packedIn) })
        return packedIn === 1 ? `= ${skos}` : `= ${skos} (${ctrans(":units units", { units: locale.number(units) })})`
    }
    if (line.requested_unit === "sko" && packedIn !== 1) {
        return `= ${ctrans(":units units", { units: locale.number(quantity * packedIn) })}`
    }
    return null
}

const isReady = (line: Line) => !!line.orgStock && !!line.production_drop_point_id && (line.requested_unit === "all" || (line.requested_quantity ?? 0) > 0)
const readyLines = computed(() => lines.value.filter(isReady))

const lineError = (index: number) => Object.entries(errors.value)
    .filter(([key]) => key.startsWith(`lines.${index}.`))
    .map(([, message]) => message)
    .join(" ")

function send() {
    if (!readyLines.value.length || readyLines.value.length !== lines.value.length) {
        errors.value = { lines: ctrans("Fill the SKO, quantity and drop point of every line, or remove it") }
        return
    }
    router.post(route("grp.models.production.material_requests.store", { production: props.productionId }), {
        lines: lines.value.map(line => ({
            org_stock_id: line.orgStock?.id,
            requested_quantity: line.requested_unit === "all" ? null : line.requested_quantity,
            requested_unit: line.requested_unit,
            production_drop_point_id: line.production_drop_point_id,
            is_urgent: line.is_urgent,
            needed_by: line.needed_by ? toLocalIsoDate(line.needed_by) : null,
            note: line.note || null,
        })),
    }, {
        preserveScroll: true,
        onStart: () => {
            isSending.value = true
            errors.value = {}
        },
        onError: (formErrors) => errors.value = formErrors as Record<string, string>,
        onSuccess: () => lines.value = [newLine()],
        onFinish: () => isSending.value = false,
    })
}
</script>

<template>
    <div class="rounded border border-gray-200 bg-white text-sm">
        <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-4 py-2">
            <div class="font-semibold text-gray-700">{{ ctrans("Ask the warehouse") }}</div>
            <slot name="actions" />
        </div>

        <div v-if="!dropPoints.length" class="px-4 py-3 text-amber-700">
            {{ ctrans("There are no drop points yet, ask the production manager to add them") }}
        </div>

        <div v-else class="divide-y divide-gray-100">
            <div v-for="(line, index) in lines" :key="line.key" class="px-4 py-2">
                <div class="flex flex-wrap items-start gap-2">
                    <div class="w-72">
                        <AutoComplete
                            v-model="line.orgStock"
                            :suggestions="suggestions"
                            optionLabel="code"
                            forceSelection
                            :delay="250"
                            :placeholder="ctrans('SKO code or name')"
                            class="w-full"
                            :pt="{ pcInputText: { root: { class: '!w-full !py-1 !text-sm' } } }"
                            :aria-label="ctrans('SKO')"
                            @complete="searchOrgStocks">
                            <template #option="{ option }">
                                <div class="flex flex-col">
                                    <span class="whitespace-nowrap font-medium">{{ option.code }}</span>
                                    <span class="text-xs text-gray-500">{{ option.name }}</span>
                                    <span class="text-xs tabular-nums text-gray-400">
                                        {{ ctrans("Warehouse :warehouse · Factory :factory", { warehouse: locale.number(option.warehouse_skos), factory: locale.number(option.factory_skos) }) }}
                                    </span>
                                </div>
                            </template>
                        </AutoComplete>
                        <div v-if="line.orgStock" class="mt-0.5 truncate text-xs text-gray-500">
                            {{ line.orgStock.name }}
                            <span class="tabular-nums" :class="line.orgStock.warehouse_skos > 0 ? 'text-gray-400' : 'text-red-600'">
                                · {{ ctrans("Warehouse :skos SKOs", { skos: locale.number(line.orgStock.warehouse_skos) }) }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-start gap-2">
                            <InputNumber
                                v-if="line.requested_unit !== 'all'"
                                v-model="line.requested_quantity"
                                :min="0"
                                :maxFractionDigits="3"
                                :placeholder="ctrans('Qty')"
                                :pt="{ pcInputText: { root: { class: '!w-20 !py-1 !text-sm !text-right' } } }"
                                :aria-label="ctrans('Quantity')" />

                            <Select v-model="line.requested_unit" :options="units" optionLabel="label" optionValue="value" class="w-36" :pt="{ label: { class: '!py-1 !text-sm' } }" :aria-label="ctrans('Unit')" />
                        </div>
                        <div v-if="comesTo(line)" class="mt-0.5 whitespace-nowrap text-xs tabular-nums text-gray-500">{{ comesTo(line) }}</div>
                    </div>

                    <Select
                        :modelValue="line.production_drop_point_id"
                        :options="dropPoints"
                        optionLabel="name"
                        optionValue="id"
                        :placeholder="ctrans('Drop point')"
                        class="w-44"
                        :pt="{ label: { class: '!py-1 !text-sm' } }"
                        :aria-label="ctrans('Drop point')"
                        @update:modelValue="(dropPointId: number | null) => setDropPoint(line, dropPointId)" />

                    <label class="flex items-center gap-1.5 py-1.5 text-sm" :class="line.is_urgent ? 'text-amber-700' : 'text-gray-600'">
                        <Checkbox v-model="line.is_urgent" binary />
                        <FontAwesomeIcon icon="fal fa-bolt" fixed-width aria-hidden="true" />
                        {{ ctrans("Urgent") }}
                    </label>

                    <DatePicker
                        v-model="line.needed_by"
                        :minDate="today"
                        dateFormat="d M yy"
                        :manualInput="false"
                        showClear
                        :placeholder="ctrans('Needed by')"
                        class="[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"
                        :pt="{ pcInputText: { root: { class: '!w-32 !py-1 !text-sm' } } }"
                        :aria-label="ctrans('Needed by')" />

                    <InputText v-model="line.note" :placeholder="ctrans('Note (optional)')" maxlength="500" class="min-w-40 flex-1 !py-1 !text-sm" :aria-label="ctrans('Note')" />

                    <Button v-if="lines.length > 1" type="tertiary" size="s" :label="ctrans('Remove')" @click="lines.splice(index, 1)">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
                <div v-if="lineError(index)" class="mt-1 text-xs text-red-600">{{ lineError(index) }}</div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2">
                <Button type="tertiary" size="s" :label="ctrans('Add line')" @click="lines.push(newLine())">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <div class="flex items-center gap-3">
                    <span v-if="errors.lines" class="text-xs text-red-600">{{ errors.lines }}</span>
                    <Button type="tertiary" :label="ctrans('Send to warehouse')" :loading="isSending" :disabled="!readyLines.length" @click="send">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-paper-plane" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
