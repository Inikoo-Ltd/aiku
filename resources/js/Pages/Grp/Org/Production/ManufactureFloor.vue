<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import ManufactureWorkingCard from '@/Components/ManufactureWorkingCard.vue'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'

interface FloorTask {
    id: number
    state: string
    position: number
    task_code: string
    task_name: string
    artefact_code: string
    artefact_name: string
    job_order_reference: string
    quantity_required: number
    quantity_made: number
    start_route: { name: string, parameters: object }
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    open_session: null | {
        id: number
        started_at: string
        task: FloorTask
        close_route: { name: string, parameters: object }
        band_feedback: null | {
            band0_hourly_rate: number
            bands: { code: string, name: string | null, hourly_rate: number, target_units_per_hour: number }[]
            session: { started_at: string, break_minutes: number, quantity_made: number }
        }
    }
    tasks: FloorTask[]
    today: {
        sessions: number
        quantity_made: number
        earned: number
    }
}>()

const processing = ref(false)

function startTask(task: FloorTask) {
    processing.value = true
    router.post(
        route(task.start_route.name, task.start_route.parameters),
        {},
        { preserveScroll: true, onFinish: () => processing.value = false }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-4 max-w-3xl mx-auto">
        <div class="mb-6 grid grid-cols-3 gap-3 text-center">
            <div class="rounded-lg bg-gray-50 border border-gray-200 py-3">
                <div class="text-2xl font-semibold tabular-nums">{{ today.quantity_made }}</div>
                <div class="text-xs text-gray-500">{{ trans('Units today') }}</div>
            </div>
            <div class="rounded-lg bg-gray-50 border border-gray-200 py-3">
                <div class="text-2xl font-semibold tabular-nums">{{ today.sessions }}</div>
                <div class="text-xs text-gray-500">{{ trans('Tasks finished') }}</div>
            </div>
            <div class="rounded-lg bg-gray-50 border border-gray-200 py-3">
                <div class="text-2xl font-semibold tabular-nums">{{ today.earned.toFixed(2) }}</div>
                <div class="text-xs text-gray-500">{{ trans('Earned today') }}</div>
            </div>
        </div>

        <ManufactureWorkingCard v-if="open_session" :session="open_session" />

        <div v-else>
            <div v-if="!tasks.length" class="text-center text-gray-400 py-16 text-lg">
                {{ trans('No tasks to do right now') }}
            </div>
            <div v-for="task in tasks" :key="task.id"
                class="mb-3 rounded-xl border border-gray-200 bg-white p-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-lg font-semibold truncate">{{ task.task_name }}</div>
                    <div class="text-gray-600 truncate">{{ task.artefact_code }} — {{ task.artefact_name }}</div>
                    <div class="text-sm text-gray-500 mt-0.5">
                        {{ trans('Job order') }} {{ task.job_order_reference }}
                        · {{ task.quantity_made }} / {{ task.quantity_required }}
                        <span v-if="task.state == 'in_progress'" class="ml-2 text-amber-600 font-medium">
                            {{ trans('In progress') }}
                        </span>
                    </div>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-lg bg-indigo-600 text-white text-xl font-semibold px-8 py-4 disabled:opacity-40"
                    :disabled="processing"
                    @click="startTask(task)"
                >
                    {{ trans('START') }}
                </button>
            </div>
        </div>
    </div>
</template>
