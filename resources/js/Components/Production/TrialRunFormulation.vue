<!--
  - Author Louis Perez
  - Created on 09-11-2026-12h-00m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import InputNumber from "primevue/inputnumber"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFlask, faPlus, faTimes, faCheck, faTrashAlt } from "@fal"
library.add(faFlask, faPlus, faTimes, faCheck, faTrashAlt)

export interface TrialRunFormulationLine {
    raw_material_id: number | null
    code: string | null
    description: string | null
    unit: string | null
    quantity_per_unit: number | null
}

export interface TrialRunShare {
    raw_material_id: number | null
    code: string | null
    description: string | null
    unit: string | null
    percent: number | null
}

export interface TrialRunFormulationForm {
    steps: { id: number, name: string, raw_materials: TrialRunFormulationLine[] }[]
    percentages?: { recipe_step_id: number | null, sample_size: number | null, sample_unit: string, lines: TrialRunShare[] } | null
    notes: string | null
    raw_material_options: routeType
    save_route: routeType
}

const props = defineProps<{
    form: TrialRunFormulationForm
    sku: string
    cancellable?: boolean
    changing?: boolean
}>()

const emit = defineEmits<{
    saved: []
    cancel: []
}>()

const steps = ref(props.form.steps.map((step) => ({
    ...step,
    raw_materials: step.raw_materials.map((line) => ({ ...line, key: `${line.raw_material_id}` })),
})))
const notes = ref(props.form.notes ?? "")
const saving = ref(false)
const errors = ref<Record<string, string>>({})
let nextKey = 0

function addLine(stepIndex: number) {
    steps.value[stepIndex].raw_materials.push({ key: `new-${nextKey++}`, raw_material_id: null, code: null, description: null, unit: null, quantity_per_unit: null })
}

function removeLine(stepIndex: number, lineIndex: number) {
    steps.value[stepIndex].raw_materials.splice(lineIndex, 1)
}

function picked(stepIndex: number, lineIndex: number, option: any) {
    const line = steps.value[stepIndex].raw_materials[lineIndex]
    line.code = option?.code ?? null
    line.description = option?.description ?? null
    line.unit = option?.unit ?? null
}

const proportional = !!props.form.percentages
const sampleUnits = [{ label: "g", value: "g" }, { label: "ml", value: "ml" }]
const sampleSize = ref<number | null>(props.form.percentages?.sample_size ?? null)
const sampleUnit = ref<string>(props.form.percentages?.sample_unit ?? "g")
const roundTo = (value: number, digits: number) => Number(value.toFixed(digits))
const shares = ref((props.form.percentages?.lines ?? []).map((line) => ({
    ...line,
    key: `${line.raw_material_id}`,
    amount: sampleSize.value && line.percent !== null ? roundTo(line.percent * sampleSize.value / 100, 3) : null as number | null,
})))

function addShare() {
    shares.value.push({ key: `new-${nextKey++}`, raw_material_id: null, code: null, description: null, unit: null, percent: null, amount: null })
}

function amountTyped(index: number, value: number | null) {
    const share = shares.value[index]
    share.amount = value
    if (sampleSize.value && value !== null) {
        share.percent = roundTo(value / sampleSize.value * 100, 4)
    }
}

function percentTyped(index: number, value: number | null) {
    const share = shares.value[index]
    share.percent = value
    if (sampleSize.value && value !== null) {
        share.amount = roundTo(value * sampleSize.value / 100, 3)
    }
}

function sizeTyped(value: number | null) {
    sampleSize.value = value
    shares.value.forEach((share) => share.amount = value && share.percent !== null ? roundTo(share.percent * value / 100, 3) : null)
}

function sharePicked(index: number, option: any) {
    const share = shares.value[index]
    share.code = option?.code ?? null
    share.description = option?.description ?? null
    share.unit = option?.unit ?? null
}

const totalPercent = computed(() => shares.value.reduce((sum, share) => sum + (share.raw_material_id ? share.percent ?? 0 : 0), 0))
const totalAmount = computed(() => shares.value.reduce((sum, share) => sum + (share.raw_material_id ? share.amount ?? 0 : 0), 0))
const postedShares = computed(() => shares.value.filter((share) => share.raw_material_id))

function shareError(key: string): string | null {
    const index = postedShares.value.findIndex((share) => share.key === key)
    if (index < 0) {
        return null
    }

    return errors.value[`lines.${index}.raw_material_id`] ?? errors.value[`lines.${index}.percent`] ?? null
}

const hasLines = computed(() => proportional
    ? postedShares.value.length > 0
    : steps.value.some((step) => step.raw_materials.some((line) => line.raw_material_id)))

function lineError(stepIndex: number, lineIndex: number): string | null {
    return errors.value[`steps.${stepIndex}.raw_materials.${lineIndex}.raw_material_id`]
        ?? errors.value[`steps.${stepIndex}.raw_materials.${lineIndex}.quantity_per_unit`]
        ?? null
}

const generalError = computed(() => errors.value.formulation
    ?? errors.value.lines ?? errors.value.sample_size ?? errors.value.sample_unit ?? errors.value.recipe_step_id
    ?? Object.entries(errors.value).find(([key]) => /^steps\.\d+\.(raw_materials|recipe_step_id)$/.test(key))?.[1]
    ?? null)

function save() {
    router.post(route(props.form.save_route.name, props.form.save_route.parameters), proportional ? {
        sample_size: sampleSize.value,
        sample_unit: sampleUnit.value,
        recipe_step_id: props.form.percentages?.recipe_step_id,
        lines: postedShares.value.map((share) => ({ raw_material_id: share.raw_material_id, percent: share.percent })),
        notes: notes.value || null,
    } : {
        steps: steps.value.map((step) => ({
            recipe_step_id: step.id,
            raw_materials: step.raw_materials
                .filter((line) => line.raw_material_id)
                .map((line) => ({ raw_material_id: line.raw_material_id, quantity_per_unit: line.quantity_per_unit })),
        })),
        notes: notes.value || null,
    }, {
        preserveScroll: true,
        onStart: () => { saving.value = true; errors.value = {} },
        onFinish: () => saving.value = false,
        onError: (validationErrors) => errors.value = validationErrors,
        onSuccess: () => emit("saved"),
    })
}
</script>

<template>
    <section class="rounded-xl border-2 border-[var(--app-accent-muted)] bg-white p-4 text-left">
        <div class="flex items-start gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--app-accent-soft)] text-[var(--app-accent-strong)]">
                <FontAwesomeIcon icon="fal fa-flask" fixed-width aria-hidden="true" />
            </span>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ changing ? ctrans("Change the formulation") : ctrans("Step 1 · Formulation") }}</h3>
                <p v-if="proportional" class="text-sm text-gray-500">{{ ctrans("Enter the raw materials of the sample and each one's share of it. The recipe in Production is worked out per kg of the blend.") }}</p>
                <p v-else class="text-sm text-gray-500">{{ changing ? ctrans("Correct the raw materials and how much of each goes into one :sku.", { sku }) : ctrans("Enter the raw materials and how much of each goes into one :sku, then save to start making the sample.", { sku }) }}</p>
            </div>
        </div>

        <div v-if="proportional" class="mt-4">
            <div class="flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="mb-1 block text-xs text-gray-500">{{ ctrans("Sample size") }}</span>
                    <InputNumber :modelValue="sampleSize" :min="0" :maxFractionDigits="3" :useGrouping="false" size="small" inputClass="w-28" placeholder="100" @update:modelValue="sizeTyped" />
                </label>
                <SegmentedToggle v-model="sampleUnit" :options="sampleUnits" :ariaLabel="ctrans('Sample measured in')" />
                <p class="text-xs text-gray-500">{{ ctrans("Enter each share in %, or its amount once the sample size is in: the other is worked out. The shares must make 100%.") }}</p>
            </div>
            <table class="mt-3 w-full text-sm">
                <thead class="whitespace-nowrap text-xs text-gray-500">
                    <tr>
                        <th class="pb-1 text-left font-medium">{{ ctrans("Raw material") }}</th>
                        <th class="w-36 pb-1 text-left font-medium">{{ ctrans("Amount") }}</th>
                        <th class="w-32 pb-1 text-left font-medium">%</th>
                        <th class="w-10 pb-1" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(share, index) in shares" :key="share.key" class="border-t border-gray-100 align-top">
                        <td class="py-2 pr-3">
                            <PureMultiselectInfiniteScroll
                                v-if="share.key.startsWith('new-')"
                                v-model="share.raw_material_id"
                                :fetchRoute="form.raw_material_options"
                                :placeholder="ctrans('Search raw materials')"
                                :noOptionsText="ctrans('No raw materials found')"
                                valueProp="id"
                                labelProp="description"
                                labelAdditionalProp="code"
                                fetchOnOpen
                                @selectedObject="(option) => sharePicked(index, option)" />
                            <div v-else>
                                <div class="font-medium text-gray-900">{{ share.description }}</div>
                                <div class="whitespace-nowrap text-xs text-gray-500">{{ share.code }}</div>
                            </div>
                            <p v-if="shareError(share.key)" class="mt-1 text-xs text-red-600">{{ shareError(share.key) }}</p>
                        </td>
                        <td class="py-2 pr-3">
                            <div class="flex items-center gap-1.5">
                                <InputNumber :modelValue="share.amount" :min="0" :maxFractionDigits="3" :useGrouping="false" size="small" inputClass="w-20" :disabled="!sampleSize" @update:modelValue="(value) => amountTyped(index, value)" />
                                <span class="text-xs text-gray-500">{{ sampleUnit }}</span>
                            </div>
                        </td>
                        <td class="py-2 pr-3">
                            <div class="flex items-center gap-1.5">
                                <InputNumber :modelValue="share.percent" :min="0" :max="100" :maxFractionDigits="4" :useGrouping="false" size="small" inputClass="w-20" @update:modelValue="(value) => percentTyped(index, value)" />
                                <span class="text-xs text-gray-500">%</span>
                            </div>
                        </td>
                        <td class="py-2 text-right">
                            <button type="button" class="rounded p-1 text-gray-400 hover:text-red-500" v-tooltip="ctrans('Remove')" :aria-label="ctrans('Remove')" @click="shares.splice(index, 1)">
                                <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                            </button>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t border-gray-200 text-xs">
                    <tr>
                        <td class="py-1.5 font-medium text-gray-700">{{ ctrans("Total") }}</td>
                        <td class="whitespace-nowrap py-1.5 tabular-nums text-gray-700">{{ sampleSize ? `${Number(totalAmount.toFixed(3))} / ${sampleSize} ${sampleUnit}` : "—" }}</td>
                        <td class="whitespace-nowrap py-1.5 tabular-nums font-medium" :class="Math.abs(totalPercent - 100) > 0.05 ? 'text-red-600' : 'text-green-700'">{{ Number(totalPercent.toFixed(2)) }}%</td>
                        <td />
                    </tr>
                </tfoot>
            </table>
            <Button type="tertiary" size="xs" :label="ctrans('Add raw material')" class="mt-2" @click="addShare">
                <template #icon><FontAwesomeIcon icon="fal fa-plus" class="text-gray-500" fixed-width aria-hidden="true" /></template>
            </Button>
        </div>

        <div v-for="(step, stepIndex) in proportional ? [] : steps" :key="step.id" class="mt-4">
            <h4 v-if="steps.length > 1" class="mb-1 text-sm font-semibold text-gray-700">{{ step.name }}</h4>
            <table class="w-full text-sm">
                <thead class="whitespace-nowrap text-xs text-gray-500">
                    <tr>
                        <th class="pb-1 text-left font-medium">{{ ctrans("Raw material") }}</th>
                        <th class="w-40 pb-1 text-left font-medium">{{ ctrans("Per unit") }}</th>
                        <th class="w-10 pb-1" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(line, lineIndex) in step.raw_materials" :key="line.key" class="border-t border-gray-100 align-top">
                        <td class="py-2 pr-3">
                            <template v-if="line.key.startsWith('new-')">
                                <PureMultiselectInfiniteScroll
                                    v-model="line.raw_material_id"
                                    :fetchRoute="form.raw_material_options"
                                    :placeholder="ctrans('Search raw materials')"
                                    :noOptionsText="ctrans('No raw materials found')"
                                    valueProp="id"
                                    labelProp="description"
                                    labelAdditionalProp="code"
                                    fetchOnOpen
                                    @selectedObject="(option) => picked(stepIndex, lineIndex, option)" />
                            </template>
                            <div v-else>
                                <div class="font-medium text-gray-900">{{ line.description }}</div>
                                <div class="whitespace-nowrap text-xs text-gray-500">{{ line.code }}</div>
                            </div>
                            <p v-if="lineError(stepIndex, lineIndex)" class="mt-1 text-xs text-red-600">{{ lineError(stepIndex, lineIndex) }}</p>
                        </td>
                        <td class="py-2 pr-3">
                            <div class="flex items-center gap-1.5">
                                <InputNumber v-model="line.quantity_per_unit" :min="0" :minFractionDigits="0" :maxFractionDigits="8" :useGrouping="false" size="small" inputClass="w-24" :invalid="!!errors[`steps.${stepIndex}.raw_materials.${lineIndex}.quantity_per_unit`]" />
                                <span class="whitespace-nowrap text-xs text-gray-500">{{ line.unit }}</span>
                            </div>
                        </td>
                        <td class="py-2 text-right">
                            <button type="button" class="rounded p-1 text-gray-400 hover:text-red-500" v-tooltip="ctrans('Remove')" :aria-label="ctrans('Remove')" @click="removeLine(stepIndex, lineIndex)">
                                <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <Button type="tertiary" size="xs" :label="ctrans('Add raw material')" class="mt-2" @click="addLine(stepIndex)">
                <template #icon><FontAwesomeIcon icon="fal fa-plus" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
            </Button>
        </div>
        <p class="mt-2 text-xs text-gray-500">{{ ctrans("Can't find a raw material? Write it in the notes and ask the production manager to add it, then change the formulation.") }}</p>

        <label class="mt-4 block">
            <span class="mb-1 block text-xs text-gray-500">{{ ctrans("Notes (optional)") }}</span>
            <Textarea v-model="notes" rows="2" autoResize class="w-full text-sm" :placeholder="ctrans('Anything the development office should know about this formulation')" />
        </label>

        <p v-if="generalError" class="mt-2 text-sm text-red-600">{{ generalError }}</p>

        <div class="mt-4 flex w-full gap-2">
            <Button v-if="cancellable" type="tertiary" :label="ctrans('Cancel')" class="shrink-0" @click="emit('cancel')">
                <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
            </Button>
            <Button type="primary" full :label="ctrans('Save formulation')" :loading="saving" :disabled="!hasLines" @click="save">
                <template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" /></template>
            </Button>
        </div>
    </section>
</template>
