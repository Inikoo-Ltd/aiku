<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPlus, faTrashAlt, faTimes, faCheck } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { routeType } from '@/types/route'
import { ctrans } from '@/Composables/useTrans'
import { useLocaleStore } from '@/Stores/locale'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import ConfirmRecipeOpenJobs from '@/Components/Production/ConfirmRecipeOpenJobs.vue'
import RecipeStepTiers from '@/Components/Production/RecipeStepTiers.vue'
import { useFormatTime } from '@/Composables/useFormatTime'

library.add(faPlus, faTrashAlt, faTimes, faCheck)

interface RecipeStepRawMaterial {
    raw_material_id: number
    code: string
    description: string
    unit: string
    quantity_per_unit: number | string
    line_cost: number | string
}

interface TargetChange {
    standard_rate: number | null
    effective_from: string | null
    set_by: string | null
    set_at: string | null
    sessions: number
}

interface RecipeRow {
    id: number
    step_id: number
    slug: string
    code: string
    name: string
    label: string | null
    unit_name: string | null
    step_name: string
    waits_for: number[] | null
    task_standard_rate: number | string | null
    position: number
    units_per_artefact: number | string
    is_recorded_in_kg: boolean
    mix_batch_kg: number | null
    blocks_later_steps: boolean
    standard_rate: number | string | null
    targets: { code: string, name: string, hourly_rate: number, units_per_hour: number, calculated_units_per_hour: number, is_edited: boolean }[]
    tiers: { source: 'artefact' | 'family' | null, artefact: Record<string, number> | null, family: Record<string, number> | null, history: { applies_to: 'artefact' | 'family', thresholds: Record<string, number> | null, previous_thresholds: Record<string, number> | null, set_by: string | null, set_at: string | null }[] }
    target_history: TargetChange[]
    raw_materials: RecipeStepRawMaterial[]
}

const props = defineProps<{
    data: {
        artefact_id: number
        artefact_name: string
        currency_code: string
        recipe: RecipeRow[]
        mix_batch_sizes_kg: number[]
        first_open_day: string
        family_code: string | null
        can_edit_tiers: boolean
        routes: {
            task_options: routeType
            raw_material_options: routeType
            attach: { name: string, parameters: object }
            detach: { name: string, parameters: object }
            tiers: { name: string, parameters: object }
            raw_material_attach: { name: string }
            raw_material_detach: { name: string }
        }
    }
    tab?: string
}>()

const newTaskId = ref<number | null>(null)
const newPosition = ref(props.data.recipe.length + 1)
const newUnits = ref(1)
const newLabel = ref('')
const processing = ref(false)
const attachErrors = ref<Record<string, string>>({})
const failedSaves = ref(0)

const newRawMaterialId = ref<Record<number, number | null>>({})
const newRawMaterialQuantity = ref<Record<number, number>>({})
const rawMaterialProcessing = ref(false)

const positionDraft = ref<Record<number, number>>({})
const unitsDraft = ref<Record<number, number>>({})
const rateDraft = ref<Record<number, number | null>>({})
const fromDraft = ref<Record<number, Date | null>>({})
const labelDraft = ref<Record<number, string>>({})
const unitNameDraft = ref<Record<number, string>>({})
const chosenWaitsFor = ref<Record<number, number[]>>({})
const quantityDraft = ref<Record<string, number>>({})

const locale = useLocaleStore()

const asMoney = (amount: number | string) => locale.currencyFormat(props.data.currency_code, amount)

const materialKey = (stepId: number, rawMaterialId: number) => `${stepId}-${rawMaterialId}`

const isSameNumber = (left: unknown, right: unknown) => Number(left) === Number(right)

function syncDraftsWithRecipe() {
    props.data.recipe.forEach(row => {
        positionDraft.value[row.step_id] = Number(row.position)
        unitsDraft.value[row.step_id] = Number(row.units_per_artefact)
        rateDraft.value[row.step_id] = row.standard_rate === null ? null : Number(row.standard_rate)
        fromDraft.value[row.step_id] = null
        labelDraft.value[row.step_id] = row.label ?? ''
        unitNameDraft.value[row.step_id] = row.unit_name ?? ''
        newRawMaterialQuantity.value[row.step_id] ??= 1
        row.raw_materials.forEach(material => {
            quantityDraft.value[materialKey(row.step_id, material.raw_material_id)] = Number(material.quantity_per_unit)
        })
    })
    newPosition.value = props.data.recipe.length + 1
    chosenWaitsFor.value = {}
}

syncDraftsWithRecipe()
watch(() => props.data.recipe, syncDraftsWithRecipe, { deep: true })

const openJobs = ref<string | null>(null)
const recalculation = ref<string | null>(null)

const hasUnsavedChanges = () => processing.value || rawMaterialProcessing.value || openJobs.value !== null || recalculation.value !== null
    || newTaskId.value !== null
    || props.data.recipe.some(row => !isSameNumber(positionDraft.value[row.step_id], row.position)
        || !isSameNumber(unitsDraft.value[row.step_id], row.units_per_artefact)
        || !isSameNumber(rateDraft.value[row.step_id] || null, row.standard_rate)
        || fromDraft.value[row.step_id] !== null
        || (labelDraft.value[row.step_id] ?? '').trim() !== (row.label ?? '')
        || (unitNameDraft.value[row.step_id] ?? '').trim() !== (row.unit_name ?? '')
        || row.raw_materials.some(material => !isSameNumber(quantityDraft.value[materialKey(row.step_id, material.raw_material_id)], material.quantity_per_unit)))

const warnBeforeUnload = (event: BeforeUnloadEvent) => {
    if (hasUnsavedChanges()) event.preventDefault()
}
let removeNavigationGuard: (() => void) | null = null
let isLeaving = false

const isSaving = () => processing.value || rawMaterialProcessing.value

// Fields save on blur, so a click on another page (an artefact's previous / next) usually starts while its save is on its way:
// wait for the save, then go, and only ask when something is still not saved (a failed save, a pending confirmation, a draft not sent)
async function leaveOnceSaved(href: string) {
    await new Promise(resolve => setTimeout(resolve))
    if (isSaving()) {
        await new Promise<void>(resolve => {
            const stop = watch(isSaving, saving => {
                if (!saving) {
                    stop()
                    resolve()
                }
            })
        })
    }
    if (hasUnsavedChanges() && !window.confirm(ctrans('Changes to this recipe are not saved yet. Leave anyway?'))) {
        return
    }
    isLeaving = true
    router.visit(href)
}

onMounted(() => {
    removeNavigationGuard = router.on('before', (event) => {
        const visit = event.detail.visit
        if (isLeaving || visit.method?.toLowerCase() !== 'get' || visit.url.href === window.location.href) return
        // InputNumber hands its value over on blur, so a field still being typed in is saved like any other
        if (document.activeElement instanceof HTMLInputElement) document.activeElement.blur()
        if (!hasUnsavedChanges()) return

        leaveOnceSaved(visit.url.href)
        return false
    })
    window.addEventListener('beforeunload', warnBeforeUnload)
})

onUnmounted(() => {
    removeNavigationGuard?.()
    window.removeEventListener('beforeunload', warnBeforeUnload)
})
let pendingAttach: Record<string, unknown> = {}

function sendAttach(payload: Record<string, unknown>) {
    processing.value = true
    const errorKey = errorKeyOf(payload)
    delete attachErrors.value[errorKey]
    router.post(
        route(props.data.routes.attach.name, props.data.routes.attach.parameters),
        payload,
        {
            preserveScroll: true,
            onSuccess: () => {
                openJobs.value = null
                recalculation.value = null
                newTaskId.value = null
                newLabel.value = ''
            },
            onError: (errors) => {
                if (errors.confirm_recalculation) {
                    pendingAttach = payload
                    recalculation.value = errors.confirm_recalculation
                    return
                }
                recalculation.value = null
                if (errors.confirm_open_jobs) {
                    pendingAttach = payload
                    openJobs.value = errors.confirm_open_jobs
                    return
                }
                syncDraftsWithRecipe()
                failedSaves.value++
                attachErrors.value[errorKey] = Object.values(errors)[0] as string
            },
            onFinish: () => processing.value = false,
        }
    )
}

function attach(taskId: number, position: number, units: number, standardRate?: number | null, extra: Record<string, unknown> = {}) {
    sendAttach({
        manufacture_task_id: taskId,
        position: position,
        units_per_artefact: units,
        ...(standardRate !== undefined ? { standard_rate: standardRate } : {}),
        ...extra,
    })
}

const errorKeyOf = (payload: Record<string, unknown>) => payload.step_id ? `step-${payload.step_id}` : `new-${payload.manufacture_task_id}`

function attachStep(row: RecipeRow, extra: Record<string, unknown> = {}, standardRate?: number | null) {
    attach(row.id, Number(row.position), Number(row.units_per_artefact), standardRate, { step_id: row.step_id, ...extra })
}

function cancelAttach() {
    openJobs.value = null
    recalculation.value = null
    syncDraftsWithRecipe()
}

function detach(stepId: number) {
    processing.value = true
    router.delete(
        route(props.data.routes.detach.name, { ...props.data.routes.detach.parameters, recipeStep: stepId }),
        {
            preserveScroll: true,
            onFinish: () => processing.value = false,
        }
    )
}

async function commitStep(row: RecipeRow) {
    await nextTick()

    const position = positionDraft.value[row.step_id]
    const units = unitsDraft.value[row.step_id]
    const standardRate = rateDraft.value[row.step_id] || null
    const effectiveFrom = fromDraft.value[row.step_id]

    if (!position || !units) return
    if (effectiveFrom && !standardRate && row.standard_rate === null) return
    if (!effectiveFrom && isSameNumber(position, row.position) && isSameNumber(units, row.units_per_artefact) && isSameNumber(standardRate, row.standard_rate)) return

    attach(row.id, position, units, standardRate, { step_id: row.step_id, ...(effectiveFrom ? { effective_from: toLocalIsoDate(effectiveFrom) } : {}) })
}

async function commitLabel(row: RecipeRow) {
    await nextTick()
    const label = (labelDraft.value[row.step_id] ?? '').trim()
    if (label === (row.label ?? '')) return

    attachStep(row, { label: label || null })
}

async function commitUnitName(row: RecipeRow) {
    await nextTick()
    const unitName = (unitNameDraft.value[row.step_id] ?? '').trim()
    if (unitName === (row.unit_name ?? '')) return

    attachStep(row, { unit_name: unitName || null })
}

type WaitsForMode = 'default' | 'chosen' | 'none'

const waitsForModes = [
    { label: ctrans('Earlier steps that hold up later ones'), value: 'default' },
    { label: ctrans('Only the steps chosen'), value: 'chosen' },
    { label: ctrans('Nothing, it can start straight away'), value: 'none' },
]

const waitsForMode = (row: RecipeRow): WaitsForMode => row.waits_for === null ? 'default' : (row.waits_for.length ? 'chosen' : 'none')

const earlierSteps = (row: RecipeRow) => props.data.recipe
    .filter(step => step.step_id !== row.step_id && Number(step.position) < Number(row.position))
    .map(step => ({ label: `${step.position}. ${step.step_name}`, value: step.step_id }))


function setWaitsForMode(row: RecipeRow, mode: WaitsForMode) {
    if (mode === 'chosen') {
        chosenWaitsFor.value[row.step_id] = row.waits_for ?? []
        return
    }
    delete chosenWaitsFor.value[row.step_id]
    attachStep(row, { waits_for: mode === 'none' ? [] : null })
}

function setWaitsFor(row: RecipeRow, stepIds: number[]) {
    chosenWaitsFor.value[row.step_id] = stepIds
    if (stepIds.length) attachStep(row, { waits_for: stepIds })
}

const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

const today = new Date()
const firstOpenDay = computed(() => {
    const [year, month, date] = props.data.first_open_day.split('-').map(Number)
    return new Date(year, month - 1, date)
})

const datePickerPt = { pcInputText: { root: { class: '!w-32 !py-1 !text-xs' } } }
const fieldFocusClass = '[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]'

const formatDay = (day: string) => {
    const [year, month, date] = day.split('-').map(Number)
    return useFormatTime(new Date(year, month - 1, date), { formatTime: 'mdy' })
}

const mixBatchSizes = computed(() => props.data.mix_batch_sizes_kg ?? [])

function mixBatchOptions(row: RecipeRow) {
    const sizes = row.mix_batch_kg && !mixBatchSizes.value.includes(row.mix_batch_kg) ? [...mixBatchSizes.value, row.mix_batch_kg] : mixBatchSizes.value

    return [
        { label: ctrans('Not in batches'), value: null },
        ...sizes.map((size) => ({ label: ctrans(':size kg', { size }), value: size })),
    ]
}

function setMixBatch(row: RecipeRow, mixBatchKg: number | null) {
    attachStep(row, { mix_batch_kg: mixBatchKg })
}

function setBlocksLaterSteps(row: RecipeRow, blocksLaterSteps: boolean) {
    attachStep(row, { blocks_later_steps: blocksLaterSteps })
}

function stepMaterialsCost(row: RecipeRow): number {
    return row.raw_materials.reduce((sum, material) => sum + Number(material.line_cost), 0)
}

function attachRawMaterial(stepId: number, rawMaterialId: number, quantityPerUnit: number) {
    rawMaterialProcessing.value = true
    router.post(
        route(props.data.routes.raw_material_attach.name, { recipeStep: stepId }),
        {
            raw_material_id: rawMaterialId,
            quantity_per_unit: quantityPerUnit,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                rawMaterialProcessing.value = false
                newRawMaterialId.value[stepId] = null
            },
        }
    )
}

async function commitRawMaterial(row: RecipeRow, material: RecipeStepRawMaterial) {
    await nextTick()

    const quantity = quantityDraft.value[materialKey(row.step_id, material.raw_material_id)]

    if (!quantity || isSameNumber(quantity, material.quantity_per_unit)) return

    attachRawMaterial(row.step_id, material.raw_material_id, quantity)
}

function detachRawMaterial(stepId: number, rawMaterialId: number) {
    rawMaterialProcessing.value = true
    router.delete(
        route(props.data.routes.raw_material_detach.name, { recipeStep: stepId, rawMaterial: rawMaterialId }),
        {
            preserveScroll: true,
            onFinish: () => rawMaterialProcessing.value = false,
        }
    )
}
</script>

<template>
    <div class="mt-5 px-4 max-w-3xl space-y-4">
        <p class="text-sm text-gray-500">
            {{ ctrans('The steps an artisan follows to make one artefact, in the order they are done.') }}
        </p>

        <div v-if="!data.recipe.length" class="rounded-lg border border-dashed border-gray-300 px-4 py-10 text-center text-gray-400">
            {{ ctrans('No steps yet. Add the manufacture tasks needed to make this artefact.') }}
        </div>

        <article v-for="row in data.recipe" :key="row.step_id" class="xoverflow-hidden rounded-lg border border-gray-200 bg-white">
            <header class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-gray-100 px-4 py-3">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-[--app-accent-soft] text-xs font-semibold text-[--app-accent-strong]">
                    {{ row.position }}
                </span>
                <div class="mr-auto min-w-0">
                    <div class="truncate">
                        <span class="font-medium">{{ row.code }}</span>
                        <span class="ml-2 text-gray-500">{{ row.step_name }}</span>
                    </div>
                </div>

                <ModalConfirmationDelete
                    @onYes="detach(row.step_id)"
                    :title="ctrans('Are you sure you want to remove task from recipe of :artifact?', { artifact: data.artefact_name })"
                    :description="ctrans('This will remove the task from the recipe of the artefact. This action cannot be undone.')"
                    isFullLoading
                    :loadingSubmit="processing">
                    <template #default="{ changeModel }">
                        <Button
                            type="transparent"
                            size="xs"
                            icon="fal fa-trash-alt"
                            :disabled="processing"
                            :tooltip="ctrans('Remove step from recipe')"
                            @click="changeModel" />
                    </template>
                </ModalConfirmationDelete>
            </header>

            <div class="flex flex-wrap items-end gap-x-6 gap-y-3 px-4 py-3">
                <label class="block">
                    <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                        {{ ctrans('Step') }}
                        <InformationIcon :information="ctrans('The order the steps are done in: step 1 first. Artisans see the steps of a job order in this order on the manufacture floor.')" />
                    </span>
                    <InputNumber
                        v-model="positionDraft[row.step_id]"
                        :min="1"
                        :useGrouping="false"
                        size="small"
                        inputClass="w-16"
                        @blur="commitStep(row)"
                        @keyup.enter="commitStep(row)" />
                </label>

                <label class="block">
                    <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                        {{ ctrans('Label') }}
                        <InformationIcon :information="ctrans('Tells apart the same task used more than once in this recipe, e.g. Red for Mixing – Red. Artisans see it on the tablet after the task name.')" />
                    </span>
                    <InputText
                        v-model="labelDraft[row.step_id]"
                        size="small"
                        class="w-28"
                        :placeholder="ctrans('e.g. Red')"
                        :disabled="processing"
                        @blur="commitLabel(row)"
                        @keyup.enter="commitLabel(row)" />
                </label>

                <label class="block">
                    <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                        {{ row.is_recorded_in_kg ? ctrans('Kg per artefact') : ctrans('Units per artefact') }}
                        <InformationIcon :information="row.is_recorded_in_kg
                            ? ctrans('How many kg of this step one artefact takes. A job order for 500 artefacts at 0.1 kg per artefact asks for 50 kg.')
                            : ctrans('How many units of this task one artefact needs. A job order for 10 artefacts at 2 units per artefact asks the artisan for 20 units of work.')" />
                    </span>
                    <InputNumber
                        v-model="unitsDraft[row.step_id]"
                        :min="0.001"
                        :minFractionDigits="0"
                        :maxFractionDigits="3"
                        :useGrouping="false"
                        size="small"
                        inputClass="w-24"
                        @blur="commitStep(row)"
                        @keyup.enter="commitStep(row)" />
                </label>

                <label v-if="!row.is_recorded_in_kg" class="block">
                    <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                        {{ ctrans('Counted in') }}
                        <InformationIcon :information="Number(row.units_per_artefact) < 1
                            ? ctrans('What one unit of this step is called on the tablet, e.g. boxes: artisans see 40 boxes of 3 (120 bags). Empty uses the pack name of the family, else packs.')
                            : ctrans('What this step counts, shown after its quantities on the tablet, e.g. bags. Empty uses the unit name of the artefact or its family.')" />
                    </span>
                    <InputText
                        v-model="unitNameDraft[row.step_id]"
                        size="small"
                        class="w-28"
                        maxlength="32"
                        :placeholder="Number(row.units_per_artefact) < 1 ? ctrans('e.g. boxes') : ctrans('e.g. bags')"
                        :disabled="processing"
                        @blur="commitUnitName(row)"
                        @keyup.enter="commitUnitName(row)" />
                </label>

                <label v-if="row.is_recorded_in_kg" class="block">
                    <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                        {{ ctrans('Mix batch size') }}
                        <InformationIcon :information="ctrans('Mixes are made in whole batches of this size. The kg a job asks for is rounded up to whole batches, 94.1 kg in 50 kg batches is 2 batches = 100 kg, and the mixer records batches.')" />
                    </span>
                    <Select
                        :key="`${row.step_id}-${failedSaves}`"
                        :modelValue="row.mix_batch_kg"
                        :options="mixBatchOptions(row)"
                        optionLabel="label"
                        optionValue="value"
                        size="small"
                        :disabled="processing"
                        @update:modelValue="setMixBatch(row, $event)" />
                </label>

                <label class="block">
                    <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                        {{ ctrans('Later steps wait for it') }}
                        <InformationIcon :information="ctrans('On: no step after this one can start until it is done (for a kg step, until its kg is entered). Off: every later step, other mixes included, can start while this one is still to do, e.g. a second mix made while the maker already works with the first one.')" />
                    </span>
                    <ToggleSwitch
                        :key="`${row.step_id}-${failedSaves}`"
                        :modelValue="row.blocks_later_steps"
                        :disabled="processing"
                        @update:modelValue="setBlocksLaterSteps(row, $event)" />
                </label>

                <div v-if="Number(row.position) > 1" class="block">
                    <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                        {{ ctrans('Waits for') }}
                        <InformationIcon :information="ctrans('What must be done before this step can start on the tablet. Earlier steps that hold up later ones: the usual order, set with the switch above. Only the steps chosen: e.g. Making – Red waits only for Mixing – Red, and Packing waits for every Shrinking. Nothing: it can start straight away, e.g. the mix of each colour.')" />
                    </span>
                    <div class="flex flex-wrap items-center gap-2">
                        <Select
                            :key="`${row.step_id}-waits-${failedSaves}`"
                            :modelValue="chosenWaitsFor[row.step_id] ? 'chosen' : waitsForMode(row)"
                            :options="waitsForModes"
                            optionLabel="label"
                            optionValue="value"
                            size="small"
                            :disabled="processing"
                            @update:modelValue="setWaitsForMode(row, $event)" />
                        <MultiSelect
                            v-if="chosenWaitsFor[row.step_id] || waitsForMode(row) === 'chosen'"
                            :modelValue="chosenWaitsFor[row.step_id] ?? row.waits_for ?? []"
                            :options="earlierSteps(row)"
                            optionLabel="label"
                            optionValue="value"
                            display="chip"
                            size="small"
                            class="w-72"
                            :placeholder="ctrans('Pick the steps')"
                            :disabled="processing"
                            @update:modelValue="setWaitsFor(row, $event)" />
                    </div>
                </div>

                <label class="block">
                    <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                        {{ ctrans('Target units per hour') }}
                        <InformationIcon :information="ctrans('The lower target: units per hour an artisan makes on this step at the base pay rate. The higher pay tiers are worked out from it. Runs below it are flagged for a manager on the Performance tab.')" />
                    </span>
                    <InputNumber
                        v-model="rateDraft[row.step_id]"
                        :min="0.01"
                        :minFractionDigits="0"
                        :maxFractionDigits="2"
                        :useGrouping="false"
                        :placeholder="row.task_standard_rate ? String(Number(row.task_standard_rate)) : ''"
                        size="small"
                        inputClass="w-24"
                        @blur="commitStep(row)"
                        @keyup.enter="commitStep(row)" />
                </label>

                <label class="block">
                    <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                        {{ ctrans('Target applies from') }}
                        <InformationIcon :information="ctrans('Leave empty for the target to apply from today (a step that had no target gets it from the start of this pay week). Pick an earlier day of a pay week not yet closed for payroll for it to apply from the start of that day: sessions already worked on this step since then are paid again against it, and you are shown which before it is saved. Closed weeks stay as they were paid; if someone was underpaid then, tell the accountant. To change a target from an earlier day, type the new target first, then pick the day.')" />
                    </span>
                    <DatePicker
                        v-model="fromDraft[row.step_id]"
                        :minDate="firstOpenDay"
                        :maxDate="today"
                        dateFormat="d M yy"
                        :manualInput="false"
                        showIcon
                        iconDisplay="input"
                        showButtonBar
                        :placeholder="ctrans('Today')"
                        :class="fieldFocusClass"
                        :pt="datePickerPt"
                        :disabled="processing"
                        @update:modelValue="$event && commitStep(row)" />
                </label>
            </div>

            <p v-if="attachErrors[`step-${row.step_id}`]" class="px-4 pb-3 text-xs text-red-600">{{ attachErrors[`step-${row.step_id}`] }}</p>

            <div v-if="row.targets.length" class="flex flex-wrap gap-2 px-4 pb-3 text-xs">
                <span
                    v-for="target in row.targets"
                    :key="target.code"
                    class="rounded border border-gray-200 bg-gray-50 px-2 py-1 tabular-nums text-gray-600">
                    {{ target.name }}: {{ target.units_per_hour }}/{{ ctrans('hr') }}<span v-if="target.is_edited" v-tooltip="ctrans('Edited, calculated: :rate', { rate: target.calculated_units_per_hour })">*</span> · {{ asMoney(target.hourly_rate) }}/{{ ctrans('hr') }}
                </span>
            </div>

            <RecipeStepTiers
                :stepId="row.id"
                :stepName="row.step_name"
                :targets="row.targets"
                :tiers="row.tiers"
                :familyCode="data.family_code"
                :canEdit="data.can_edit_tiers"
                :route="data.routes.tiers" />

            <details v-if="row.target_history.length" class="px-4 pb-3 text-xs text-gray-600">
                <summary class="cursor-pointer text-gray-500">{{ ctrans('Target history') }}</summary>
                <ul class="mt-1 divide-y divide-gray-100">
                    <li v-for="(change, index) in row.target_history" :key="index" class="py-1">
                        <span class="font-medium tabular-nums">{{ change.standard_rate === null ? ctrans('No target') : ctrans(':rate per hour', { rate: change.standard_rate }) }}</span>
                        · {{ change.effective_from ? ctrans('from :date', { date: formatDay(change.effective_from) }) : ctrans('from before targets had dates') }}
                        <span v-if="change.set_by || change.set_at" class="text-gray-400">· {{ ctrans('set by :name on :date', { name: change.set_by ?? '-', date: change.set_at ? useFormatTime(change.set_at, { formatTime: 'hm' }) : '-' }) }}</span>
                        <span v-if="change.sessions" class="text-gray-400">· {{ ctrans(':count sessions paid again', { count: change.sessions }) }}</span>
                    </li>
                </ul>
            </details>

            <section class="border-t border-gray-100 bg-gray-50 px-4 py-3">
                <div class="mb-2 flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-gray-500">
                    {{ ctrans('Raw materials') }}
                    <InformationIcon :information="ctrans('What this step consumes to produce one unit of work. Quantities feed the mixes board and the cost of the artefact.')" />
                </div>

                <p v-if="!row.raw_materials.length" class="text-xs text-gray-400">
                    {{ ctrans('No raw materials for this step yet.') }}
                </p>

                <ul v-else class="divide-y divide-gray-200 border-y border-gray-200">
                    <li class="flex items-center gap-x-3 py-1.5 text-[11px] xuppercase tracking-wide text-gray-400">
                        <!-- <span class="w-24 shrink-0">{{ ctrans('Code') }}</span> -->
                        <span class="min-w-0 flex-1">{{ ctrans('Material') }}</span>
                        <span class="w-24 shrink-0">{{ ctrans('Quantity') }}</span>
                        <span class="w-10 shrink-0">{{ ctrans('Unit') }}</span>
                        <span class="w-20 shrink-0 text-right">{{ ctrans('Line cost') }}</span>
                        <span class="w-8 shrink-0"></span>
                    </li>
                    <li
                        v-for="material in row.raw_materials"
                        :key="material.raw_material_id"
                        class="flex items-center gap-x-3 py-1.5 text-xs text-gray-600">
                        <!-- <span class="w-24 shrink-0 truncate font-medium" :title="material.code"></span> -->
                        <div class="min-w-0 flex-1 truncate" :title="material.description">
                            <div class="font-bold">{{ material.code }}</div>
                            <div class="opacity-70">{{ material.description }}</div>
                        </div>
                        <InputNumber
                            v-model="quantityDraft[materialKey(row.step_id, material.raw_material_id)]"
                            :min="0.00000001"
                            :minFractionDigits="0"
                            :maxFractionDigits="8"
                            :useGrouping="false"
                            size="small"
                            inputClass="w-24 text-xs"
                            @blur="commitRawMaterial(row, material)"
                            @keyup.enter="commitRawMaterial(row, material)" />
                        <span class="w-10 shrink-0 text-gray-500">{{ material.unit }}</span>
                        <span class="w-20 shrink-0 text-right tabular-nums">{{ asMoney(material.line_cost) }}</span>
                        <ModalConfirmationDelete
                            @onYes="detachRawMaterial(row.step_id, material.raw_material_id)"
                            :title="ctrans('Are you sure you want to remove :material from this step?', { material: material.code })"
                            :description="ctrans('This will remove the raw material from the step. This action cannot be undone.')"
                            isFullLoading
                            :loadingSubmit="rawMaterialProcessing">
                            <template #default="{ changeModel }">
                                <Button
                                    type="transparent"
                                    size="xxs"
                                    icon="fal fa-trash-alt"
                                    :disabled="rawMaterialProcessing"
                                    :tooltip="ctrans('Remove raw material from step')"
                                    @click="changeModel" />
                            </template>
                        </ModalConfirmationDelete>
                    </li>
                </ul>

                <div class="mt-3 flex flex-wrap items-end gap-x-3 gap-y-2">
                    <label class="block">
                        <span class="mb-1 block text-xs text-gray-500">{{ ctrans('Raw material') }}</span>
                        <div class="w-64">
                            <PureMultiselectInfiniteScroll
                                v-model="newRawMaterialId[row.step_id]"
                                :fetchRoute="data.routes.raw_material_options"
                                :placeholder="ctrans('Select raw material')"
                                :noOptionsText="ctrans('No raw materials yet')"
                                valueProp="id"
                                labelProp="description"
                                labelAdditionalProp="code"
                                fetchOnOpen />
                        </div>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs text-gray-500">{{ ctrans('Quantity') }}</span>
                        <InputNumber
                            v-model="newRawMaterialQuantity[row.step_id]"
                            :min="0.00000001"
                            :minFractionDigits="0"
                            :maxFractionDigits="8"
                            :useGrouping="false"
                            size="small"
                            inputClass="w-24 text-xs" />
                    </label>

                    <Button
                        type="secondary"
                        size="xs"
                        icon="fal fa-plus"
                        :label="ctrans('Add material')"
                        :loading="rawMaterialProcessing"
                        :disabled="!newRawMaterialId[row.step_id]"
                        @click="attachRawMaterial(row.step_id, newRawMaterialId[row.step_id]!, newRawMaterialQuantity[row.step_id] || 1)" />

                    <span class="ml-auto self-center text-xs text-gray-500">
                        {{ ctrans('Materials cost') }}: <span class="tabular-nums">{{ asMoney(stepMaterialsCost(row)) }}</span>
                    </span>
                </div>
            </section>
        </article>

        <section class="rounded-lg border border-dashed border-gray-300 px-4 py-3">
            <div class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans('Add step') }}</div>
            <div class="flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                        {{ ctrans('Task') }}
                        <InformationIcon :information="ctrans('The piece of work an artisan does, such as pouring or wrapping. Tasks are set up once for the factory and reused by every artefact that needs them.')" />
                    </span>
                    <div class="w-72">
                        <PureMultiselectInfiniteScroll
                            v-model="newTaskId"
                            :fetchRoute="data.routes.task_options"
                            :placeholder="ctrans('Select task')"
                            :noOptionsText="ctrans('No manufacture tasks yet')"
                            valueProp="id"
                            labelProp="name"
                            labelAdditionalProp="code"
                            fetchOnOpen />
                    </div>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs text-gray-500">{{ ctrans('Label') }}</span>
                    <InputText v-model="newLabel" size="small" class="w-28" :placeholder="ctrans('e.g. Red')" />
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs text-gray-500">{{ ctrans('Step') }}</span>
                    <InputNumber
                        v-model="newPosition"
                        :min="1"
                        :useGrouping="false"
                        size="small"
                        inputClass="w-16" />
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs text-gray-500">{{ ctrans('Units per artefact') }}</span>
                    <InputNumber
                        v-model="newUnits"
                        :min="0.001"
                        :minFractionDigits="0"
                        :maxFractionDigits="3"
                        :useGrouping="false"
                        size="small"
                        inputClass="w-24" />
                </label>

                <Button
                    type="create"
                    icon="fal fa-plus"
                    :label="ctrans('Add step')"
                    :loading="processing"
                    :disabled="!newTaskId"
                    @click="attach(newTaskId!, newPosition, newUnits, undefined, { is_new_step: true, label: newLabel.trim() || null })" />
            </div>
            <p v-if="newTaskId && attachErrors[`new-${newTaskId}`]" class="mt-2 text-xs text-red-600">{{ attachErrors[`new-${newTaskId}`] }}</p>
        </section>

        <ConfirmRecipeOpenJobs
            :jobs="openJobs"
            :processing="processing"
            @confirm="sendAttach({ ...pendingAttach, confirm_open_jobs: true })"
            @cancel="cancelAttach" />

        <Dialog
            :visible="!!recalculation"
            modal
            :header="ctrans('Pay sessions already worked again?')"
            :style="{ width: '34rem' }"
            :breakpoints="{ '640px': '95vw' }"
            @update:visible="visible => !visible && cancelAttach()">
            <p class="whitespace-pre-line rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ recalculation }}</p>
            <template #footer>
                <Button type="tertiary" :label="ctrans('Cancel')" @click="cancelAttach">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button
                    type="tertiary"
                    :label="ctrans('Save and pay them again')"
                    :loading="processing"
                    @click="sendAttach({ ...pendingAttach, confirm_recalculation: true })">
                    <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
        </Dialog>
    </div>
</template>
