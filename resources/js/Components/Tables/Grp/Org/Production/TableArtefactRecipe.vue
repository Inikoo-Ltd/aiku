<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPlus, faTrashAlt, faSave } from '@fal'

library.add(faPlus, faTrashAlt, faSave)

interface RecipeStepRawMaterial {
    raw_material_id: number
    code: string
    description: string
    unit: string
    quantity_per_unit: number
    line_cost: number
}

interface RecipeRow {
    id: number
    step_id: number
    slug: string
    code: string
    name: string
    task_work_cost: number
    position: number
    units_per_artefact: number
    raw_materials: RecipeStepRawMaterial[]
}

const props = defineProps<{
    data: {
        artefact_id: number
        recipe: RecipeRow[]
        task_options: { id: number, code: string, name: string }[]
        raw_material_options: { id: number, code: string, description: string, unit: string }[]
        routes: {
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

function stepMaterialsCost(row: RecipeRow): number {
    return row.raw_materials.reduce((sum, material) => sum + material.line_cost, 0)
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
                    <th class="py-2 pr-4 w-14">{{ trans('Step') }}</th>
                    <th class="py-2 pr-4">{{ trans('Task') }}</th>
                    <th class="py-2 pr-4 w-32">{{ trans('Units per artefact') }}</th>
                    <th class="py-2 pr-4 w-28">{{ trans('Pay per unit') }}</th>
                    <th class="py-2 w-10"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in data.recipe" :key="row.id" class="border-b border-gray-100">
                    <td class="py-2 pr-4">
                        <input
                            type="number" min="1"
                            class="w-14 rounded border-gray-300 text-sm"
                            :value="row.position"
                            @change="attach(row.id, Number(($event.target as HTMLInputElement).value), row.units_per_artefact)"
                        />
                    </td>
                    <td class="py-2 pr-4">
                        <span class="font-medium">{{ row.code }}</span>
                        <span class="text-gray-500 ml-2">{{ row.name }}</span>
                    </td>
                    <td class="py-2 pr-4">
                        <input
                            type="number" min="0.001" step="any"
                            class="w-24 rounded border-gray-300 text-sm"
                            :value="row.units_per_artefact"
                            @change="attach(row.id, row.position, Number(($event.target as HTMLInputElement).value))"
                        />
                    </td>
                    <td class="py-2 pr-4 tabular-nums">{{ row.task_work_cost }}</td>
                    <td class="py-2 text-right">
                        <button
                            type="button"
                            class="text-gray-400 hover:text-red-600 disabled:opacity-50"
                            :disabled="processing"
                            :title="trans('Remove task from recipe')"
                            @click="detach(row.id)"
                        >
                            <FontAwesomeIcon :icon="['fal', 'trash-alt']" fixed-width />
                        </button>
                    </td>
                </tr>
                <tr v-for="row in data.recipe" :key="`materials-${row.id}`" class="border-b border-gray-100 bg-gray-50">
                    <td></td>
                    <td colspan="4" class="py-2 pr-4">
                        <ul class="pl-4 space-y-1">
                            <li v-for="material in row.raw_materials" :key="material.raw_material_id" class="flex items-center gap-3 text-xs text-gray-600">
                                <span class="font-medium">{{ material.code }}</span>
                                <span>{{ material.description }}</span>
                                <input
                                    type="number" min="0.0001" step="any"
                                    class="w-24 rounded border-gray-300 text-xs"
                                    :value="material.quantity_per_unit"
                                    @change="attachRawMaterial(row.step_id, material.raw_material_id, Number(($event.target as HTMLInputElement).value))"
                                />
                                <span>{{ material.unit }}</span>
                                <span class="tabular-nums">{{ material.line_cost }}</span>
                                <button
                                    type="button"
                                    class="text-gray-400 hover:text-red-600 disabled:opacity-50"
                                    :disabled="rawMaterialProcessing"
                                    :title="trans('Remove raw material from step')"
                                    @click="detachRawMaterial(row.step_id, material.raw_material_id)"
                                >
                                    <FontAwesomeIcon :icon="['fal', 'trash-alt']" fixed-width />
                                </button>
                            </li>
                        </ul>
                        <div class="pl-4 mt-2 flex items-center gap-2">
                            <select v-model="newRawMaterialId[row.step_id]" class="rounded border-gray-300 text-xs min-w-40">
                                <option :value="null" disabled>{{ trans('Select raw material') }}</option>
                                <option v-for="option in data.raw_material_options" :key="option.id" :value="option.id">
                                    {{ option.code }} — {{ option.description }}
                                </option>
                            </select>
                            <input
                                type="number" min="0.0001" step="any"
                                class="w-24 rounded border-gray-300 text-xs"
                                v-model.number="newRawMaterialQuantity[row.step_id]"
                            />
                            <button
                                type="button"
                                class="rounded bg-indigo-50 text-indigo-600 text-xs px-2 py-1 disabled:opacity-50"
                                :disabled="!newRawMaterialId[row.step_id] || rawMaterialProcessing"
                                @click="attachRawMaterial(row.step_id, newRawMaterialId[row.step_id]!, newRawMaterialQuantity[row.step_id] || 1)"
                            >
                                <FontAwesomeIcon :icon="['fal', 'plus']" fixed-width class="mr-1" />
                                {{ trans('Add material') }}
                            </button>
                            <span class="text-xs text-gray-500 ml-auto">{{ trans('Materials cost') }}: {{ stepMaterialsCost(row) }}</span>
                        </div>
                    </td>
                </tr>
                <tr v-if="!data.recipe.length">
                    <td colspan="5" class="py-6 text-center text-gray-400">
                        {{ trans('No manufacture tasks yet. Add the steps needed to make this artefact.') }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="mt-4 flex items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ trans('Task') }}</label>
                <select v-model="newTaskId" class="rounded border-gray-300 text-sm min-w-48">
                    <option :value="null" disabled>{{ trans('Select task') }}</option>
                    <option v-for="option in data.task_options" :key="option.id" :value="option.id">
                        {{ option.code }} — {{ option.name }}
                    </option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ trans('Step') }}</label>
                <input type="number" min="1" v-model.number="newPosition" class="w-16 rounded border-gray-300 text-sm" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ trans('Units per artefact') }}</label>
                <input type="number" min="0.001" step="any" v-model.number="newUnits" class="w-24 rounded border-gray-300 text-sm" />
            </div>
            <button
                type="button"
                class="rounded bg-indigo-600 text-white text-sm px-3 py-2 disabled:opacity-50"
                :disabled="!newTaskId || processing"
                @click="attach(newTaskId!, newPosition, newUnits)"
            >
                <FontAwesomeIcon :icon="['fal', 'plus']" fixed-width class="mr-1" />
                {{ trans('Add step') }}
            </button>
        </div>
    </div>
</template>
