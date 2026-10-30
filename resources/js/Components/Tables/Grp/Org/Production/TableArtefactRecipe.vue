<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, nextTick, ref, watch } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPlus, faTrashAlt } from '@fal'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { routeType } from '@/types/route'
import { ctrans } from '@/Composables/useTrans'
import { useLocaleStore } from '@/Stores/locale'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import ConfirmRecipeOpenJobs from '@/Components/Production/ConfirmRecipeOpenJobs.vue'

library.add(faPlus, faTrashAlt)

interface RecipeStepRawMaterial {
    raw_material_id: number
    code: string
    description: string
    unit: string
    quantity_per_unit: number | string
    line_cost: number | string
}

interface RecipeRow {
    id: number
    step_id: number
    slug: string
    code: string
    name: string
    task_standard_rate: number | string | null
    position: number
    units_per_artefact: number | string
    is_recorded_in_kg: boolean
    mix_batch_kg: number | null
    blocks_later_steps: boolean
    standard_rate: number | string | null
    targets: { code: string, name: string, hourly_rate: number, units_per_hour: number }[]
    raw_materials: RecipeStepRawMaterial[]
}

const props = defineProps<{
    data: {
        artefact_id: number
        artefact_name: string
        currency_code: string
        recipe: RecipeRow[]
        mix_batch_sizes_kg: number[]
        routes: {
            task_options: routeType
            raw_material_options: routeType
            attach: { name: string, parameters: object }
            detach: { name: string, parameters: object }
            raw_material_attach: { name: string }
            raw_material_detach: { name: string }
        }
    }
    tab?: string
}>()

const newTaskId = ref<number | null>(null)
const newPosition = ref(props.data.recipe.length + 1)
const newUnits = ref(1)
const processing = ref(false)
const attachErrors = ref<Record<number, string>>({})
const failedSaves = ref(0)

const newRawMaterialId = ref<Record<number, number | null>>({})
const newRawMaterialQuantity = ref<Record<number, number>>({})
const rawMaterialProcessing = ref(false)

const positionDraft = ref<Record<number, number>>({})
const unitsDraft = ref<Record<number, number>>({})
const rateDraft = ref<Record<number, number | null>>({})
const quantityDraft = ref<Record<string, number>>({})

const locale = useLocaleStore()

const asMoney = (amount: number | string) => locale.currencyFormat(props.data.currency_code, amount)

const materialKey = (stepId: number, rawMaterialId: number) => `${stepId}-${rawMaterialId}`

const isSameNumber = (left: unknown, right: unknown) => Number(left) === Number(right)

function syncDraftsWithRecipe() {
    props.data.recipe.forEach(row => {
        positionDraft.value[row.id] = Number(row.position)
        unitsDraft.value[row.id] = Number(row.units_per_artefact)
        rateDraft.value[row.id] = row.standard_rate === null ? null : Number(row.standard_rate)
        newRawMaterialQuantity.value[row.step_id] ??= 1
        row.raw_materials.forEach(material => {
            quantityDraft.value[materialKey(row.step_id, material.raw_material_id)] = Number(material.quantity_per_unit)
        })
    })
    newPosition.value = props.data.recipe.length + 1
}

syncDraftsWithRecipe()
watch(() => props.data.recipe, syncDraftsWithRecipe, { deep: true })

const openJobs = ref<string | null>(null)
let pendingAttach: Record<string, unknown> = {}

function sendAttach(payload: Record<string, unknown>) {
    processing.value = true
    const taskId = Number(payload.manufacture_task_id)
    delete attachErrors.value[taskId]
    router.post(
        route(props.data.routes.attach.name, props.data.routes.attach.parameters),
        payload,
        {
            preserveScroll: true,
            onSuccess: () => {
                openJobs.value = null
                newTaskId.value = null
            },
            onError: (errors) => {
                if (errors.confirm_open_jobs) {
                    pendingAttach = payload
                    openJobs.value = errors.confirm_open_jobs
                    return
                }
                syncDraftsWithRecipe()
                failedSaves.value++
                attachErrors.value[taskId] = Object.values(errors)[0] as string
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

function cancelAttach() {
    openJobs.value = null
    syncDraftsWithRecipe()
}

function detach(taskId: number) {
    processing.value = true
    router.delete(
        route(props.data.routes.detach.name, { ...props.data.routes.detach.parameters, manufactureTask: taskId }),
        {
            preserveScroll: true,
            onFinish: () => processing.value = false,
        }
    )
}

async function commitStep(row: RecipeRow) {
    await nextTick()

    const position = positionDraft.value[row.id]
    const units = unitsDraft.value[row.id]
    const standardRate = rateDraft.value[row.id] || null

    if (!position || !units) return
    if (isSameNumber(position, row.position) && isSameNumber(units, row.units_per_artefact) && isSameNumber(standardRate, row.standard_rate)) return

    attach(row.id, position, units, standardRate)
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
    attach(row.id, Number(row.position), Number(row.units_per_artefact), undefined, { mix_batch_kg: mixBatchKg })
}

function setBlocksLaterSteps(row: RecipeRow, blocksLaterSteps: boolean) {
    attach(row.id, Number(row.position), Number(row.units_per_artefact), undefined, { blocks_later_steps: blocksLaterSteps })
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

        <article v-for="row in data.recipe" :key="row.id" class="xoverflow-hidden rounded-lg border border-gray-200 bg-white">
            <header class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-gray-100 px-4 py-3">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-[--app-accent-soft] text-xs font-semibold text-[--app-accent-strong]">
                    {{ row.position }}
                </span>
                <div class="mr-auto min-w-0">
                    <div class="truncate">
                        <span class="font-medium">{{ row.code }}</span>
                        <span class="ml-2 text-gray-500">{{ row.name }}</span>
                    </div>
                </div>

                <ModalConfirmationDelete
                    @onYes="detach(row.id)"
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
                        v-model="positionDraft[row.id]"
                        :min="1"
                        :useGrouping="false"
                        size="small"
                        inputClass="w-16"
                        @blur="commitStep(row)"
                        @keyup.enter="commitStep(row)" />
                </label>

                <label class="block">
                    <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                        {{ row.is_recorded_in_kg ? ctrans('Kg per artefact') : ctrans('Units per artefact') }}
                        <InformationIcon :information="row.is_recorded_in_kg
                            ? ctrans('How many kg of this step one artefact takes. A job order for 500 artefacts at 0.1 kg per artefact asks for 50 kg.')
                            : ctrans('How many units of this task one artefact needs. A job order for 10 artefacts at 2 units per artefact asks the artisan for 20 units of work.')" />
                    </span>
                    <InputNumber
                        v-model="unitsDraft[row.id]"
                        :min="0.001"
                        :minFractionDigits="0"
                        :maxFractionDigits="3"
                        :useGrouping="false"
                        size="small"
                        inputClass="w-24"
                        @blur="commitStep(row)"
                        @keyup.enter="commitStep(row)" />
                </label>

                <label v-if="row.is_recorded_in_kg" class="block">
                    <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                        {{ ctrans('Mix batch size') }}
                        <InformationIcon :information="ctrans('Mixes are made in whole batches of this size. The kg a job asks for is rounded up to whole batches, 94.1 kg in 50 kg batches is 2 batches = 100 kg, and the mixer records batches.')" />
                    </span>
                    <Select
                        :key="`${row.id}-${failedSaves}`"
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
                        :key="`${row.id}-${failedSaves}`"
                        :modelValue="row.blocks_later_steps"
                        :disabled="processing"
                        @update:modelValue="setBlocksLaterSteps(row, $event)" />
                </label>

                <label class="block">
                    <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                        {{ ctrans('Target units per hour') }}
                        <InformationIcon :information="ctrans('The lower target: units per hour an artisan makes on this step at the base pay rate. The higher pay tiers are worked out from it. Runs below it are flagged for a manager on the Performance tab.')" />
                    </span>
                    <InputNumber
                        v-model="rateDraft[row.id]"
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
            </div>

            <p v-if="attachErrors[row.id]" class="px-4 pb-3 text-xs text-red-600">{{ attachErrors[row.id] }}</p>

            <div v-if="row.targets.length" class="flex flex-wrap gap-2 px-4 pb-3 text-xs">
                <span
                    v-for="target in row.targets"
                    :key="target.code"
                    class="rounded border border-gray-200 bg-gray-50 px-2 py-1 tabular-nums text-gray-600">
                    {{ target.name }}: {{ target.units_per_hour }}/{{ ctrans('hr') }} · {{ asMoney(target.hourly_rate) }}/{{ ctrans('hr') }}
                </span>
            </div>

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
                    @click="attach(newTaskId!, newPosition, newUnits)" />
            </div>
            <p v-if="newTaskId && attachErrors[newTaskId]" class="mt-2 text-xs text-red-600">{{ attachErrors[newTaskId] }}</p>
        </section>

        <ConfirmRecipeOpenJobs
            :jobs="openJobs"
            :processing="processing"
            @confirm="sendAttach({ ...pendingAttach, confirm_open_jobs: true })"
            @cancel="cancelAttach" />
    </div>
</template>
