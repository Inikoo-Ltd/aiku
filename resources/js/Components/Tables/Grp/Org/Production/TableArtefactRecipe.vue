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

interface RecipeRow {
    id: number
    slug: string
    code: string
    name: string
    task_work_cost: number
    position: number
    units_per_artefact: number
}

const props = defineProps<{
    data: {
        artefact_id: number
        recipe: RecipeRow[]
        task_options: { id: number, code: string, name: string }[]
        routes: {
            attach: { name: string, parameters: object }
            detach: { name: string, parameters: object }
        }
    }
    tab?: string
}>()

const newTaskId = ref<number | null>(null)
const newPosition = ref(props.data.recipe.length + 1)
const newUnits = ref(1)
const processing = ref(false)

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
