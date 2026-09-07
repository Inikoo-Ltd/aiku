<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { nextTick, ref, watch } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPlus, faTrashAlt, faSave } from '@fal'
import InputNumber from 'primevue/inputnumber'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { routeType } from '@/types/route'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'

library.add(faPlus, faTrashAlt, faSave)

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
    task_work_cost: number | string
    position: number
    units_per_artefact: number | string
    raw_materials: RecipeStepRawMaterial[]
}

const props = defineProps<{
    data: {
        artefact_id: number
        artefact_name: string
        recipe: RecipeRow[]
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

const newRawMaterialId = ref<Record<number, number | null>>({})
const newRawMaterialQuantity = ref<Record<number, number>>({})
const rawMaterialProcessing = ref(false)

const positionDraft = ref<Record<number, number>>({})
const unitsDraft = ref<Record<number, number>>({})
const quantityDraft = ref<Record<string, number>>({})

const materialKey = (stepId: number, rawMaterialId: number) => `${stepId}-${rawMaterialId}`

const isSameNumber = (left: unknown, right: unknown) => Number(left) === Number(right)

function syncDraftsWithRecipe() {
    props.data.recipe.forEach(row => {
        positionDraft.value[row.id] = Number(row.position)
        unitsDraft.value[row.id] = Number(row.units_per_artefact)
        newRawMaterialQuantity.value[row.step_id] ??= 1
        row.raw_materials.forEach(material => {
            quantityDraft.value[materialKey(row.step_id, material.raw_material_id)] = Number(material.quantity_per_unit)
        })
    })
    newPosition.value = props.data.recipe.length + 1
}

syncDraftsWithRecipe()
watch(() => props.data.recipe, syncDraftsWithRecipe, { deep: true })

function attach(taskId: number, position: number, units: number) {
    processing.value = true
    router.post(
        route(props.data.routes.attach.name, props.data.routes.attach.parameters),
        {
            manufacture_task_id: taskId,
            position: position,
            units_per_artefact: units,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false
                newTaskId.value = null
            },
        }
    )
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

    if (!position || !units) return
    if (isSameNumber(position, row.position) && isSameNumber(units, row.units_per_artefact)) return

    attach(row.id, position, units)
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
    <div class="mt-5 px-4 max-w-3xl">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b border-gray-200 text-gray-500">
                    <th class="py-2 pr-4 w-20">{{ ctrans('Step') }}</th>
                    <th class="py-2 pr-4">{{ ctrans('Task') }}</th>
                    <th class="py-2 pr-4 w-36">{{ ctrans('Units per artefact') }}</th>
                    <th class="py-2 pr-4 w-28">{{ ctrans('Pay per unit') }}</th>
                    <th class="py-2 w-10"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in data.recipe" :key="row.id" class="border-b border-gray-100">
                    <td class="py-2 pr-4">
                        <InputNumber
                            v-model="positionDraft[row.id]"
                            :min="1"
                            :useGrouping="false"
                            size="small"
                            inputClass="w-16"
                            @blur="commitStep(row)"
                            @keyup.enter="commitStep(row)" />
                    </td>
                    <td class="py-2 pr-4">
                        <span class="font-medium">{{ row.code }}</span>
                        <span class="text-gray-500 ml-2">{{ row.name }}</span>
                    </td>
                    <td class="py-2 pr-4">
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
                    </td>
                    <td class="py-2 pr-4 tabular-nums" v-tooltip="ctrans('Task work cost')">{{ row.task_work_cost }}</td>
                    <td class="py-2 text-right">
                        
                        <ModalConfirmationDelete
                            @onYes="detach(row.id)"
                            :title="ctrans('Are you sure you want to remove task from recipe of :artifact?', { artifact: data.artefact_name })"
                            :description="ctrans('This will remove the task from the recipe of the artefact. This action cannot be undone.')"
                            isFullLoading
                            :loadingSubmit="processing"
                        >
                            <template #default="{ isOpenModal, changeModel }">
                                <Button
                                    type="transparent"
                                    size="xs"
                                    icon="fal fa-trash-alt"
                                    :disabled="processing"
                                    :tooltip="ctrans('Remove task from recipe')"
                                    @click="changeModel"
                                />
                            </template>
                        </ModalConfirmationDelete>
                    </td>
                </tr>
                <tr v-for="row in data.recipe" :key="`materials-${row.id}`" class="border-b border-gray-100 bg-gray-50">
                    <td></td>
                    <td colspan="4" class="py-2 pr-4">
                        <ul class="pl-4 space-y-1">
                            <li v-for="material in row.raw_materials" :key="material.raw_material_id" class="flex items-center gap-3 text-xs text-gray-600">
                                <span class="font-medium">{{ material.code }}</span>
                                <span>{{ material.description }}</span>
                                <InputNumber
                                    v-model="quantityDraft[materialKey(row.step_id, material.raw_material_id)]"
                                    :min="0.0001"
                                    :minFractionDigits="0"
                                    :maxFractionDigits="4"
                                    :useGrouping="false"
                                    size="small"
                                    inputClass="w-24 text-xs"
                                    @blur="commitRawMaterial(row, material)"
                                    @keyup.enter="commitRawMaterial(row, material)" />
                                <span>{{ material.unit }}</span>
                                <span v-tooltip="ctrans('Line cost')" class="tabular-nums">{{ material.line_cost }}</span>
                                <button
                                    type="button"
                                    class="text-gray-400 hover:text-red-600 disabled:opacity-50"
                                    :disabled="rawMaterialProcessing"
                                    :title="ctrans('Remove raw material from step')"
                                    @click="detachRawMaterial(row.step_id, material.raw_material_id)"
                                >
                                    <FontAwesomeIcon :icon="['fal', 'trash-alt']" fixed-width />
                                </button>
                            </li>
                        </ul>
                        <div class="pl-4 mt-2 flex items-center gap-2">
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
                            <InputNumber
                                v-model="newRawMaterialQuantity[row.step_id]"
                                :min="0.0001"
                                :minFractionDigits="0"
                                :maxFractionDigits="4"
                                :useGrouping="false"
                                size="small"
                                inputClass="w-24 text-xs" />
                            <Button
                                type="secondary"
                                size="xs"
                                icon="fal fa-plus"
                                :label="ctrans('Add material')"
                                :loading="rawMaterialProcessing"
                                :disabled="!newRawMaterialId[row.step_id]"
                                @click="attachRawMaterial(row.step_id, newRawMaterialId[row.step_id]!, newRawMaterialQuantity[row.step_id] || 1)" />
                            <span class="text-xs text-gray-500 ml-auto">{{ ctrans('Materials cost') }}: {{ stepMaterialsCost(row) }}</span>
                        </div>
                    </td>
                </tr>
                <tr v-if="!data.recipe.length">
                    <td colspan="5" class="py-6 text-center text-gray-400">
                        {{ ctrans('No manufacture tasks yet. Add the steps needed to make this artefact.') }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="mt-4 flex items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Task') }}</label>
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
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Step') }}</label>
                <InputNumber
                    v-model="newPosition"
                    :min="1"
                    :useGrouping="false"
                    size="small"
                    inputClass="w-16" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Units per artefact') }}</label>
                <InputNumber
                    v-model="newUnits"
                    :min="0.001"
                    :minFractionDigits="0"
                    :maxFractionDigits="3"
                    :useGrouping="false"
                    size="small"
                    inputClass="w-24" />
            </div>
            <Button
                type="create"
                icon="fal fa-plus"
                :label="ctrans('Add step')"
                :loading="processing"
                :disabled="!newTaskId"
                @click="attach(newTaskId!, newPosition, newUnits)" />
        </div>
    </div>
</template>
