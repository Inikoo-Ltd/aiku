<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import ManufactureWorkingCard from '@/Components/ManufactureWorkingCard.vue'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'

interface FloorStep {
    id: number
    task_name: string
    state: string
    quantity_made: number
    quantity_required: number
    blocked_by_step: string | null
    worked_by: string[]
    working_on_by: string[]
    seconds: number
}

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
    blocked_by_step: string | null
    can_start: boolean
    steps: FloorStep[]
    quantity_required: number
    quantity_made: number
    start_route: { name: string, parameters: object }
}

const props = defineProps<{
    title: string
    production_id: number
    pageHead: PageHeadingTypes
    break_options: number[]
    break_route: { name: string, parameters: object }
    open_break: null | {
        id: number
        planned_minutes: number
        started_at: string
        end_route: { name: string, parameters: object }
    }
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
        break_minutes: number
    }
    artisan: string | null
    can_pick_open_jobs: boolean
    tasks: FloorTask[]
    finished_today: {
        id: number
        ended_at: string
        seconds: number
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
const breakError = computed(() => (page.props.errors as Record<string, string> | undefined)?.break)

const now = ref(Date.now())
let clock: ReturnType<typeof setInterval>
onMounted(() => clock = setInterval(() => now.value = Date.now(), 1000))
onUnmounted(() => clearInterval(clock))

const breakSecondsLeft = computed(() => {
    if (!props.open_break) return 0
    const elapsed = Math.floor((now.value - new Date(props.open_break.started_at).getTime()) / 1000)
    return Math.max(0, props.open_break.planned_minutes * 60 - elapsed)
})
const breakCountdown = computed(() => {
    const m = Math.floor(breakSecondsLeft.value / 60)
    const s = breakSecondsLeft.value % 60
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

const pendingBreak = ref<number | null>(null)
const breakProcessing = ref(false)

function startBreak() {
    if (pendingBreak.value === null) return
    breakProcessing.value = true
    router.post(
        route(props.break_route.name, props.break_route.parameters),
        { planned_minutes: pendingBreak.value },
        { preserveScroll: true, onFinish: () => { breakProcessing.value = false; pendingBreak.value = null } }
    )
}

function endBreak() {
    if (!props.open_break || breakProcessing.value) return
    breakProcessing.value = true
    router.patch(
        route(props.open_break.end_route.name, props.open_break.end_route.parameters),
        {},
        { preserveScroll: true, onFinish: () => breakProcessing.value = false }
    )
}

watch(breakSecondsLeft, left => {
    if (props.open_break && left <= 0) endBreak()
})

const selectedTaskId = ref<number | null>(props.open_session?.task.id ?? props.tasks.find(task => task.is_mine)?.id ?? props.tasks[0]?.id ?? null)
const selectedTask = computed(() => props.tasks.find(task => task.id == selectedTaskId.value) ?? null)
watch(() => props.tasks, tasks => {
    if (!tasks.some(task => task.id == selectedTaskId.value)) {
        selectedTaskId.value = tasks.find(task => task.is_mine)?.id ?? tasks[0]?.id ?? null
    }
})

const sections = computed(() => {
    const mine = props.tasks.filter(task => task.is_mine)
    const open = props.tasks.filter(task => !task.is_mine)
    if (!props.can_pick_open_jobs) {
        return [{ key: 'mine', title: ctrans('Your jobs'), tasks: mine, empty: ctrans('No jobs addressed to you yet') }]
    }
    const list = [{ key: 'open', title: ctrans('Open jobs'), tasks: open, empty: ctrans('No open jobs right now') }]
    if (props.artisan) {
        list.unshift({ key: 'mine', title: ctrans('Your jobs'), tasks: mine, empty: ctrans('No jobs addressed to you, pick one from the open jobs') })
    }
    return list
})

const floorChannel = `grp.production.${props.production_id}.floor`
let reloadTimer: ReturnType<typeof setTimeout> | null = null
onMounted(() => {
    window.Echo.private(floorChannel).listen('.floor-changed', () => {
        if (reloadTimer) clearTimeout(reloadTimer)
        reloadTimer = setTimeout(() => router.reload({ preserveScroll: true }), 300)
    })
})
onUnmounted(() => window.Echo.private(floorChannel).stopListening('.floor-changed'))

function formatTime(datetime: string) {
    return new Date(datetime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function formatDuration(seconds: number) {
    const m = Math.floor(seconds / 60)
    const s = seconds % 60
    return `${m}:${String(s).padStart(2, '0')}`
}

function formatWorked(seconds: number) {
    const h = Math.floor(seconds / 3600)
    const m = Math.round((seconds % 3600) / 60)
    return h ? `${h}h ${m}m` : `${m}m`
}

function stepStatus(step: FloorStep) {
    const units = `${step.quantity_made}/${step.quantity_required} ${ctrans('units')}`
    if (step.state == 'done') {
        return [ctrans('Done'), units, formatWorked(step.seconds), step.worked_by.join(', ')].filter(Boolean).join(' · ')
    }
    if (step.working_on_by.length) {
        return `${ctrans('In progress')} · ${units} · ${step.working_on_by.join(', ')}`
    }
    if (step.blocked_by_step) {
        return `${ctrans('Waiting for')} ${step.blocked_by_step}`
    }
    return `${ctrans('Ready')} · ${units}`
}

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

    <div class="flex h-[calc(100vh-8rem)] min-h-[32rem] border-t border-gray-200">
        <aside class="w-80 shrink-0 border-r border-gray-200 bg-gray-50 overflow-y-auto">
            <div class="grid grid-cols-2 divide-x divide-gray-200 border-b border-gray-200 bg-white text-center">
                <div class="py-2">
                    <div class="text-xl font-semibold tabular-nums">{{ today.quantity_made }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans('Units today') }}</div>
                </div>
                <div class="py-2">
                    <div class="text-xl font-semibold tabular-nums">{{ today.sessions }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans('Tasks finished') }}</div>
                </div>
            </div>

            <div v-for="section in sections" :key="section.key">
                <h2 class="px-3 pt-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ section.title }}</h2>
                <div v-if="!section.tasks.length" class="px-3 py-3 text-sm text-gray-400">{{ section.empty }}</div>
                <button v-for="task in section.tasks" :key="task.id" type="button"
                    class="w-full text-left px-3 py-2.5 border-b border-gray-200 hover:bg-white"
                    :class="[
                        selectedTaskId == task.id ? 'bg-indigo-50 ring-1 ring-inset ring-indigo-200' : '',
                        open_session?.task.id == task.id ? 'bg-amber-50 ring-1 ring-inset ring-amber-200' : ''
                    ]"
                    @click="selectedTaskId = task.id">
                    <div class="font-semibold truncate flex items-center gap-2">
                        {{ task.artefact_code }}
                        <template v-if="open_session?.task.id == task.id">
                            <FontAwesomeIcon icon="fas fa-play" class="text-green-600 text-xs" fixed-width aria-hidden="true" />
                            <span class="text-xs font-normal text-gray-400 tabular-nums">{{ formatTime(open_session.started_at) }}</span>
                        </template>
                    </div>
                    <div class="text-sm text-gray-600 truncate">{{ task.artefact_name }}</div>
                    <div class="text-xs text-gray-500 mt-0.5 flex justify-between">
                        <span>{{ task.task_name }} · {{ task.job_order_reference }}</span>
                        <span class="tabular-nums">{{ task.quantity_made }}/{{ task.quantity_required }}</span>
                    </div>
                    <div v-if="task.blocked_by_step" class="text-xs text-gray-400 font-medium truncate flex items-center gap-1">
                        <FontAwesomeIcon icon="fas fa-lock" fixed-width aria-hidden="true" /> {{ ctrans('Waiting for') }} {{ task.blocked_by_step }}
                    </div>
                    <div v-else-if="task.working_on_by.length || task.waiting_for.length" class="text-xs text-amber-600 font-medium truncate">
                        <span v-if="task.waiting_for.length">{{ ctrans('Waiting for mix') }}: {{ task.waiting_for.join(', ') }}</span>
                        <span v-else>{{ ctrans('Working') }}: {{ task.working_on_by.join(', ') }}</span>
                    </div>
                </button>
            </div>

            <h2 class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans('Finished today') }}</h2>
            <div v-if="!finished_today.length" class="px-3 py-3 text-sm text-gray-400">{{ ctrans('Nothing finished yet today') }}</div>
            <div v-for="session in finished_today" :key="session.id"
                class="px-3 py-2 border-b border-gray-200 flex justify-between gap-2 text-sm">
                <div class="min-w-0">
                    <div class="font-semibold truncate">{{ session.artefact_code }}</div>
                    <div class="text-xs text-gray-500 truncate">
                        {{ session.task_name }} · {{ formatDuration(session.seconds) }}
                        <span v-if="session.quantity_rejected" class="text-red-600">· {{ session.quantity_rejected }} {{ ctrans('rejected') }}</span>
                    </div>
                </div>
                <div class="font-semibold tabular-nums text-green-700 shrink-0">{{ session.quantity_made }}</div>
            </div>
        </aside>

        <main class="flex-1 min-w-0 overflow-y-auto p-6 flex flex-col items-center gap-6">
            <div v-if="breakError" class="w-full max-w-5xl rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700">{{ breakError }}</div>

            <div v-if="open_break" class="w-full max-w-5xl rounded-2xl border-2 border-amber-400 bg-amber-50 p-8 flex items-center justify-between gap-6">
                <div>
                    <div class="text-xs uppercase tracking-wide text-amber-700">{{ ctrans('On break') }} · {{ open_break.planned_minutes }} {{ ctrans('min') }}</div>
                    <div class="text-7xl font-mono tabular-nums text-amber-700">{{ breakCountdown }}</div>
                </div>
                <button type="button"
                    class="rounded-xl bg-amber-600 text-white text-2xl font-semibold px-10 py-5 disabled:opacity-40"
                    :disabled="breakProcessing" @click="endBreak">
                    {{ ctrans('Finish break now') }}
                </button>
            </div>

            <div v-else-if="pendingBreak !== null" class="w-full max-w-5xl rounded-lg border border-amber-300 bg-amber-50 p-4 flex items-center justify-between gap-4">
                <div class="text-2xl">{{ ctrans('Start a :minutes minute break?', { minutes: pendingBreak }) }}</div>
                <div class="flex gap-3">
                    <button type="button" class="rounded-lg bg-amber-600 text-white text-xl font-semibold px-8 py-4 disabled:opacity-40"
                        :disabled="breakProcessing" @click="startBreak">
                        {{ ctrans('Yes, start break') }}
                    </button>
                    <button type="button" class="rounded-lg border border-gray-300 bg-white text-gray-700 text-xl font-semibold px-6 py-4"
                        @click="pendingBreak = null">
                        {{ ctrans('Cancel') }}
                    </button>
                </div>
            </div>

            <div v-else class="w-full max-w-5xl flex items-center gap-3">
                <span class="text-lg text-gray-600">{{ ctrans('Break') }}:</span>
                <button v-for="minutes in break_options" :key="minutes" type="button"
                    class="rounded-lg border-2 border-amber-300 bg-white text-amber-800 text-xl font-semibold px-6 py-3 hover:bg-amber-50"
                    @click="pendingBreak = minutes">
                    {{ minutes }}m
                </button>
            </div>

            <div v-if="open_break" class="flex-1 flex items-center text-gray-400 text-lg">{{ ctrans('Finish your break to continue working') }}</div>

            <ManufactureWorkingCard v-else-if="open_session" :session="open_session" class="w-full max-w-5xl" />

            <div v-else-if="selectedTask" class="flex-1 flex flex-col justify-center w-full max-w-2xl text-center">
                <div v-if="startError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700">{{ startError }}</div>
                <div class="text-sm text-gray-500">{{ selectedTask.task_name }} · {{ ctrans('Job order') }} {{ selectedTask.job_order_reference }}</div>
                <div class="text-3xl font-semibold mt-2">{{ selectedTask.artefact_code }}</div>
                <div class="text-xl text-gray-600">{{ selectedTask.artefact_name }}</div>
                <div class="text-5xl font-semibold tabular-nums my-6">
                    {{ selectedTask.quantity_made }} <span class="text-gray-400 text-3xl">/ {{ selectedTask.quantity_required }}</span>
                </div>
                <div v-if="selectedTask.artisan && !selectedTask.is_mine" class="text-gray-500 mb-2">{{ ctrans('For') }} {{ selectedTask.artisan }}</div>
                <div v-if="selectedTask.waiting_for.length" class="text-amber-600 font-medium mb-2">{{ ctrans('Waiting for mix') }}: {{ selectedTask.waiting_for.join(', ') }}</div>
                <div v-if="selectedTask.blocked_by_step" class="text-gray-500 font-medium mb-2 flex items-center justify-center gap-1">
                    <FontAwesomeIcon icon="fas fa-lock" fixed-width aria-hidden="true" /> {{ ctrans('Waiting for') }} {{ selectedTask.blocked_by_step }}
                </div>
                <div v-else-if="selectedTask.working_on_by.length" class="text-amber-600 font-medium mb-2">{{ ctrans('Working') }}: {{ selectedTask.working_on_by.join(', ') }}</div>
                <button v-if="selectedTask.can_start" type="button"
                    class="rounded-xl bg-indigo-600 text-white text-2xl font-semibold px-16 py-5 disabled:opacity-40"
                    :disabled="processing"
                    @click="startTask(selectedTask)">
                    {{ ctrans('START') }}
                </button>

                <div class="mt-8 rounded-xl border border-gray-200 bg-white text-left">
                    <div class="px-4 py-2 border-b border-gray-200 text-sm text-gray-600">
                        {{ ctrans('Steps for this product') }} ·
                        {{ ctrans(':done of :total complete', { done: selectedTask.steps.filter(step => step.state == 'done').length, total: selectedTask.steps.length }) }}
                    </div>
                    <button v-for="step in selectedTask.steps" :key="step.id" type="button"
                        class="w-full flex items-center gap-3 px-4 py-3 border-b border-gray-100 last:border-0 text-left disabled:cursor-default"
                        :class="step.id == selectedTask.id ? 'bg-indigo-50' : 'hover:bg-gray-50'"
                        :disabled="!tasks.some(task => task.id == step.id)"
                        @click="selectedTaskId = step.id">
                        <FontAwesomeIcon v-if="step.state == 'done'" icon="fas fa-check-circle" fixed-width class="text-green-600" aria-hidden="true" />
                        <FontAwesomeIcon v-else-if="step.blocked_by_step" icon="fas fa-lock" fixed-width class="text-gray-400" aria-hidden="true" />
                        <FontAwesomeIcon v-else-if="step.working_on_by.length" icon="fas fa-play" fixed-width class="text-amber-500" aria-hidden="true" />
                        <span v-else class="inline-block w-5" />
                        <div class="min-w-0">
                            <div class="font-medium" :class="step.state == 'done' ? 'text-gray-500' : ''">{{ step.task_name }}</div>
                            <div class="text-xs text-gray-500">{{ stepStatus(step) }}</div>
                        </div>
                    </button>
                    <div class="px-4 py-2 text-xs text-gray-400">
                        {{ ctrans('Only the steps in the recipe of :code are shown', { code: selectedTask.artefact_code }) }}
                    </div>
                </div>
            </div>

            <div v-else class="flex-1 flex items-center text-gray-400 text-lg">
                {{ tasks.length ? ctrans('Pick a job from the list') : ctrans('No tasks to do right now') }}
            </div>
        </main>
    </div>
</template>
