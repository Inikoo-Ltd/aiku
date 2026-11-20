<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 20 Nov 2026 Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import InputNumber from "primevue/inputnumber"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faExclamationTriangle } from "@fal"
library.add(faCheck, faExclamationTriangle)

export interface PercentageLine {
    raw_material_id: number
    code: string
    description: string
    unit: string
    percent: number
    sample_quantity: number | null
    kg_per_unit: number | null
    units_per_kg: number | null
    manual: boolean
}

export interface Percentages {
    sample_size: number | null
    sample_unit: string
    lines: PercentageLine[]
    trial_run?: string
    status?: { label: string, color: string }
    made_by?: string | null
    approved_by?: string | null
    approved_at?: string | null
    pending?: number
    can_set_units?: boolean
    units_route?: routeType
}

const props = defineProps<{
    percentages: Percentages
    withBatch?: boolean
}>()

const unitLabels: Record<string, string> = { kilogram: "kg", liter: "L", unit: "unit", pack: "pack", carton: "carton" }
const format = (value: number, digits = 4) => Number(value.toFixed(digits)).toLocaleString(undefined, { maximumFractionDigits: digits })
const formatDate = (date: string | null | undefined) => date ? useFormatTime(date, { formatTime: "d MMM yyyy" }) : ""

const totalPercent = computed(() => props.percentages.lines.reduce((sum, line) => sum + line.percent, 0))
const pending = computed(() => props.percentages.lines.filter((line) => line.units_per_kg === null).length)
const editable = (line: PercentageLine) => !!props.percentages.can_set_units && (line.units_per_kg === null || line.manual)

const units = ref<Record<number, number | null>>(Object.fromEntries(props.percentages.lines.filter(editable).map((line) => [line.raw_material_id, line.units_per_kg])))
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const batchKg = ref<number | null>(null)

const changed = computed(() => props.percentages.lines.filter((line) => editable(line) && units.value[line.raw_material_id] && units.value[line.raw_material_id] !== line.units_per_kg))

function saveUnits() {
    const route_ = props.percentages.units_route
    if (!route_ || !changed.value.length) {
        return
    }
    router.post(route(route_.name, route_.parameters), {
        lines: changed.value.map((line) => ({ raw_material_id: line.raw_material_id, units_per_kg: units.value[line.raw_material_id] })),
    }, {
        preserveScroll: true,
        onStart: () => { saving.value = true; errors.value = {} },
        onFinish: () => saving.value = false,
        onError: (validationErrors) => errors.value = validationErrors,
    })
}

const generalError = computed(() => Object.values(errors.value)[0] ?? null)
</script>

<template>
    <div class="space-y-2">
        <p v-if="percentages.trial_run" class="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-600">
            <span class="whitespace-nowrap">{{ ctrans("Trial run :number", { number: percentages.trial_run }) }}<template v-if="percentages.status"> · {{ percentages.status.label }}</template></span>
            <span v-if="percentages.approved_by" class="text-green-700">{{ ctrans("Approved by :name on :date", { name: percentages.approved_by, date: formatDate(percentages.approved_at) }) }}</span>
            <span v-if="percentages.made_by">{{ ctrans("Made by :name", { name: percentages.made_by }) }}</span>
        </p>

        <div class="overflow-x-auto rounded-md border border-gray-200">
            <table class="w-full text-sm">
                <thead class="whitespace-nowrap bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-3 py-1.5 font-medium">{{ ctrans("Ingredient") }}</th>
                        <th class="px-3 py-1.5 text-right font-medium">%</th>
                        <th class="px-3 py-1.5 text-right font-medium">{{ ctrans("In the sample") }}</th>
                        <th class="px-3 py-1.5 text-right font-medium">{{ ctrans("1 unit weighs") }}</th>
                        <th class="px-3 py-1.5 text-right font-medium">{{ ctrans("Units in 1 kg") }}</th>
                        <template v-if="withBatch && batchKg">
                            <th v-if="percentages.sample_unit === 'g'" class="px-3 py-1.5 text-right font-medium">{{ ctrans("In :kg kg", { kg: format(batchKg, 3) }) }}</th>
                            <th class="px-3 py-1.5 text-right font-medium">{{ ctrans("Units for :kg kg", { kg: format(batchKg, 3) }) }}</th>
                        </template>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="line in percentages.lines" :key="line.raw_material_id" :class="line.units_per_kg === null ? 'bg-amber-50/60' : ''">
                        <td class="px-3 py-1.5">
                            <span class="whitespace-nowrap font-medium text-gray-900">{{ line.code }}</span>
                            <span class="ml-1.5 text-gray-600">{{ line.description }}</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums font-medium text-gray-900">{{ format(line.percent) }}%</td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-gray-700">{{ line.sample_quantity !== null ? `${format(line.sample_quantity, 3)} ${percentages.sample_unit}` : "—" }}</td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-gray-500">{{ line.kg_per_unit ? `${format(line.kg_per_unit, 3)} kg` : "—" }}</td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-gray-800">
                            <div v-if="editable(line)" class="flex items-center justify-end gap-1.5">
                                <InputNumber v-model="units[line.raw_material_id]" :min="0" :maxFractionDigits="6" :useGrouping="false" size="small" inputClass="w-24 text-right" :placeholder="ctrans('Units')" :invalid="line.units_per_kg === null && !units[line.raw_material_id]" />
                                <span class="text-xs text-gray-500">{{ unitLabels[line.unit] ?? line.unit }}</span>
                            </div>
                            <template v-else-if="line.units_per_kg !== null">{{ format(line.units_per_kg, 6) }} {{ unitLabels[line.unit] ?? line.unit }}</template>
                            <span v-else class="text-xs text-amber-700">{{ ctrans("Units to enter") }}</span>
                        </td>
                        <template v-if="withBatch && batchKg">
                            <td v-if="percentages.sample_unit === 'g'" class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-gray-700">{{ format(batchKg * line.percent / 100, 3) }} kg</td>
                            <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-gray-900">{{ line.units_per_kg !== null ? `${format(batchKg * line.units_per_kg, 3)} ${unitLabels[line.unit] ?? line.unit}` : "—" }}</td>
                        </template>
                    </tr>
                </tbody>
                <tfoot class="border-t border-gray-200 bg-gray-50 text-xs">
                    <tr>
                        <td class="px-3 py-1.5 font-medium text-gray-700">{{ ctrans("Total") }}</td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums font-medium" :class="Math.abs(totalPercent - 100) > 0.05 ? 'text-red-600' : 'text-gray-900'">{{ format(totalPercent, 2) }}%</td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-gray-700">{{ percentages.sample_size ? `${format(percentages.sample_size, 3)} ${percentages.sample_unit}` : ctrans("in :unit", { unit: percentages.sample_unit }) }}</td>
                        <td :colspan="withBatch && batchKg ? (percentages.sample_unit === 'g' ? 4 : 3) : 2" />
                    </tr>
                </tfoot>
            </table>
        </div>

        <p v-if="pending" class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
            <span>{{ percentages.sample_unit === "ml"
                ? ctrans("The sample was measured in ml: aiku has no density to turn its shares into kg, so each ingredient's units in 1 kg of the blend are entered by hand. Until they all are, the blend stays a draft and is not an ingredient of the finished product.")
                : ctrans(":count ingredients have no weight in aiku, so their units in 1 kg of the blend are entered by hand. Until they are, the blend stays a draft and is not an ingredient of the finished product.", { count: String(pending) }) }}</span>
        </p>

        <div v-if="percentages.can_set_units && percentages.lines.some(editable)" class="flex flex-wrap items-center gap-2">
            <Button type="tertiary" size="xs" :label="ctrans('Save units')" :loading="saving" :disabled="!changed.length" @click="saveUnits">
                <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
            </Button>
            <span class="text-xs text-gray-500">{{ ctrans("One unit of the blend's artefact is 1 kg of the blend.") }}</span>
        </div>
        <p v-if="generalError" class="text-xs text-red-600">{{ generalError }}</p>

        <label v-if="withBatch" class="flex flex-wrap items-center gap-2 text-xs text-gray-600">
            <span class="whitespace-nowrap">{{ ctrans("Work out a batch of") }}</span>
            <InputNumber v-model="batchKg" :min="0" :maxFractionDigits="3" :useGrouping="false" size="small" inputClass="w-24" placeholder="100" />
            <span>kg</span>
            <span v-if="batchKg" class="text-gray-400">{{ ctrans("Only a preview: the formulation keeps its shares, and a job order for :kg of the blend's artefact makes this batch.", { kg: format(batchKg, 3) }) }}</span>
        </label>
    </div>
</template>
