<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
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
    artisan: string | null
    is_mine: boolean
    waiting_for: string[]
    working_on_by: string[]
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
    artisan: string | null
    can_pick_open_jobs: boolean
    tasks: FloorTask[]
    finished_today: {
        id: number
        ended_at: string
        minutes: number
        task_name: string
        artefact_code: string
        artefact_name: string
        job_order_reference: string
        quantity_made: number
        quantity_rejected: number
    }[]
    today: {
        sessions: number
        quantity_made: number
        earned: number
    }
}>()

const processing = ref(false)
const page = usePage()
const startError = computed(() => (page.props.errors as Record<string, string> | undefined)?.job_order_item_task_id)

const sections = computed(() => {
    const mine = props.tasks.filter(task => task.is_mine)
    const open = props.tasks.filter(task => !task.is_mine)
    if (!props.can_pick_open_jobs) {
        return [{ key: 'mine', title: trans('Your jobs'), tasks: mine, empty: trans('No jobs addressed to you yet') }]
    }
    const list = [{ key: 'open', title: trans('Open jobs'), tasks: open, empty: trans('No open jobs right now') }]
    if (props.artisan) {
        list.unshift({ key: 'mine', title: trans('Your jobs'), tasks: mine, empty: trans('No jobs addressed to you, pick one from the open jobs') })
    }
    return list
})

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

    <div class="px-4 py-4 max-w-6xl mx-auto grid gap-8 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
    <div>
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

        <div v-if="open_session" class="fixed inset-0 z-50 bg-white flex items-center justify-center p-6">
            <ManufactureWorkingCard :session="open_session" class="w-full max-w-5xl" />
        </div>

        <div v-else>
            <div v-if="startError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                {{ startError }}
            </div>
            <div v-if="!tasks.length" class="text-center text-gray-400 py-16 text-lg">
                {{ trans('No tasks to do right now') }}
            </div>
            <template v-else>
                <div v-for="section in sections" :key="section.key" class="mb-6">
                    <h2 class="text-sm font-semibold text-gray-500 mb-2">{{ section.title }}</h2>
                    <div v-if="!section.tasks.length"
                        class="rounded-xl border border-dashed border-gray-300 px-4 py-5 text-center text-gray-500">
                        {{ section.empty }}
                    </div>
                    <div v-for="task in section.tasks" :key="task.id"
                        class="mb-3 rounded-xl border bg-white p-4 flex items-center justify-between gap-4"
                        :class="task.is_mine ? 'border-indigo-300' : 'border-gray-200'">
                        <div class="min-w-0">
                            <div class="text-lg font-semibold truncate">{{ task.task_name }}</div>
                            <div class="text-gray-600 truncate">{{ task.artefact_code }} — {{ task.artefact_name }}</div>
                            <div class="text-sm text-gray-500 mt-0.5">
                                {{ trans('Job order') }} {{ task.job_order_reference }}
                                · {{ task.quantity_made }} / {{ task.quantity_required }}
                                <span v-if="task.artisan && !task.is_mine" class="ml-2">
                                    {{ trans('For') }} {{ task.artisan }}
                                </span>
                                <span v-if="task.waiting_for.length" class="ml-2 text-amber-600 font-medium">
                                    {{ trans('Waiting for mix') }}: {{ task.waiting_for.join(', ') }}
                                </span>
                                <span v-if="task.working_on_by.length" class="ml-2 text-amber-600 font-medium">
                                    {{ trans('Working') }}: {{ task.working_on_by.join(', ') }}
                                </span>
                                <span v-else-if="task.state == 'in_progress'" class="ml-2 text-amber-600 font-medium">
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
            </template>
        </div>
    </div>

    <div>
        <h2 class="text-sm font-semibold text-gray-500 mb-2">{{ trans('Finished today') }}</h2>
        <div v-if="!finished_today.length"
            class="rounded-xl border border-dashed border-gray-300 px-4 py-5 text-center text-gray-500">
            {{ trans('Nothing finished yet today') }}
        </div>
        <div v-for="session in finished_today" :key="session.id"
            class="mb-3 rounded-xl border border-green-200 bg-green-50/40 p-4 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <div class="font-semibold truncate">{{ session.task_name }}</div>
                <div class="text-gray-600 truncate">{{ session.artefact_code }} — {{ session.artefact_name }}</div>
                <div class="text-sm text-gray-500 mt-0.5">
                    {{ trans('Job order') }} {{ session.job_order_reference }}
                    · {{ session.minutes }} {{ trans('min') }}
                    <span v-if="session.quantity_rejected" class="ml-2 text-red-600">{{ session.quantity_rejected }} {{ trans('rejected') }}</span>
                </div>
            </div>
            <div class="text-3xl font-semibold tabular-nums text-green-700 shrink-0">{{ session.quantity_made }}</div>
        </div>
    </div>
    </div>
</template>
