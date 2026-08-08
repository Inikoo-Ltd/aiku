<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 28 Nov 2024 16:45:01 Central Indonesia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, onMounted, onUnmounted, ref } from "vue";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import FlatTreeMap from "@/Components/Navigation/FlatTreeMap.vue";
import ManufactureWorkingCard from "@/Components/ManufactureWorkingCard.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faSeedling, faThumbsDown, faUserHardHat, faTasks } from "@fal";
import { faCheckCircle, faTimesCircle, faPauseCircle } from "@fas";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { trans } from "laravel-vue-i18n";

import { capitalize } from "@/Composables/capitalize";

import { PageHeadingTypes } from "@/types/PageHeading";

library.add(faSeedling, faThumbsDown, faTimesCircle, faPauseCircle, faCheckCircle, faUserHardHat, faTasks);

interface QueueTask {
    id: number
    state: string
    task_name: string
    artefact_code: string
    artefact_name: string
    job_order_reference: string
    quantity_made: number
    quantity_required: number
    start_route: { name: string, parameters: object }
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    flatTreeMaps: {}
    command_control?: {
        payroll_export_route: { name: string, parameters: object }
        floor_route: { name: string, parameters: object }
        open_session: null | {
            id: number
            started_at: string
            task: {
                task_name: string
                artefact_code: string
                artefact_name: string
                job_order_reference: string
                quantity_made: number
                quantity_required: number
            }
            close_route: { name: string, parameters: object }
        }
        working_now: {
            id: number
            worker: string
            task_name: string
            artefact_code: string
            job_order_reference: string
            started_at: string
            quantity_made: number
            quantity_required: number
        }[]
        queue: QueueTask[]
    }
}>();

const processing = ref(false)

function startTask(task: QueueTask) {
    processing.value = true
    router.post(
        route(task.start_route.name, task.start_route.parameters),
        {},
        { preserveScroll: true, onFinish: () => processing.value = false }
    )
}

const now = ref(Date.now())
let timer: ReturnType<typeof setInterval>
onMounted(() => timer = setInterval(() => now.value = Date.now(), 1000))
onUnmounted(() => clearInterval(timer))

function previousMonday(weeksBack: number) {
    const date = new Date()
    const day = (date.getDay() + 6) % 7
    date.setDate(date.getDate() - day - weeksBack * 7)
    return date.toISOString().slice(0, 10)
}
const payrollFrom = ref(previousMonday(1))
const payrollTo = ref((() => {
    const date = new Date(previousMonday(1))
    date.setDate(date.getDate() + 6)
    return date.toISOString().slice(0, 10)
})())

function payrollExportUrl() {
    if (!props.command_control) return '#'
    return route(props.command_control.payroll_export_route.name, {
        ...props.command_control.payroll_export_route.parameters,
        from: payrollFrom.value,
        to: payrollTo.value,
    })
}

function elapsedSince(startedAt: string) {
    const seconds = Math.max(0, Math.floor((now.value - new Date(startedAt).getTime()) / 1000))
    const h = Math.floor(seconds / 3600)
    const m = Math.floor((seconds % 3600) / 60)
    return h ? `${h}h ${String(m).padStart(2, '0')}m` : `${m}m`
}
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <FlatTreeMap class="mx-4" v-for="(treeMap, idx) in flatTreeMaps" :key="idx" :nodes="treeMap" />

    <div v-if="command_control" class="mx-4 mt-6 grid gap-6 lg:grid-cols-2">
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold">
                    <FontAwesomeIcon :icon="['fal', 'user-hard-hat']" fixed-width class="text-gray-400 mr-1" />
                    {{ trans('Working now') }}
                </h2>
                <Link
                    :href="route(command_control.floor_route.name, command_control.floor_route.parameters)"
                    class="rounded bg-indigo-600 text-white text-sm px-3 py-1.5"
                >
                    {{ trans('Open manufacture floor') }}
                </Link>
            </div>

            <ManufactureWorkingCard
                v-if="command_control.open_session"
                :session="command_control.open_session"
                class="mb-4"
            />

            <div v-if="!command_control.working_now.length" class="text-gray-400 text-sm py-6 text-center border border-dashed border-gray-200 rounded-lg">
                {{ trans('Nobody is working on a task right now') }}
            </div>
            <div v-for="session in command_control.working_now" :key="session.id"
                class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-3 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-medium truncate">{{ session.worker }}</div>
                    <div class="text-sm text-gray-600 truncate">
                        {{ session.task_name }} · {{ session.artefact_code }}
                        · {{ trans('Job order') }} {{ session.job_order_reference }}
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="font-mono tabular-nums text-indigo-700">{{ elapsedSince(session.started_at) }}</div>
                    <div class="text-xs text-gray-500 tabular-nums">{{ session.quantity_made }} / {{ session.quantity_required }}</div>
                </div>
            </div>
        </div>

        <div>
            <h2 class="text-lg font-semibold mb-3">
                <FontAwesomeIcon :icon="['fal', 'tasks']" fixed-width class="text-gray-400 mr-1" />
                {{ trans('Task queue') }}
            </h2>
            <div v-if="!command_control.queue.length" class="text-gray-400 text-sm py-6 text-center border border-dashed border-gray-200 rounded-lg">
                {{ trans('The queue is empty') }}
            </div>
            <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 flex items-end gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ trans('Payroll from') }}</label>
                    <input type="date" v-model="payrollFrom" class="rounded border-gray-300 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ trans('To') }}</label>
                    <input type="date" v-model="payrollTo" class="rounded border-gray-300 text-sm" />
                </div>
                <a
                    :href="payrollExportUrl()"
                    class="rounded bg-gray-700 text-white text-sm px-3 py-2"
                >
                    {{ trans('Export payroll CSV') }}
                </a>
            </div>
            <div v-for="task in command_control.queue" :key="task.id"
                class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-3 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-medium truncate">{{ task.task_name }}</div>
                    <div class="text-sm text-gray-600 truncate">
                        {{ task.artefact_code }} — {{ task.artefact_name }}
                        · {{ trans('Job order') }} {{ task.job_order_reference }}
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5 tabular-nums">
                        {{ task.quantity_made }} / {{ task.quantity_required }}
                        <span v-if="task.state == 'in_progress'" class="ml-1 text-amber-600 font-medium">{{ trans('In progress') }}</span>
                    </div>
                </div>
                <button
                    v-if="!command_control.open_session"
                    type="button"
                    class="shrink-0 rounded bg-indigo-600 text-white text-sm font-semibold px-4 py-2 disabled:opacity-40"
                    :disabled="processing"
                    @click="startTask(task)"
                >
                    {{ trans('START') }}
                </button>
            </div>
        </div>
    </div>

</template>
