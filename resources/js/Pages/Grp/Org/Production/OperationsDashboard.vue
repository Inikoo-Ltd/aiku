<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 28 Nov 2024 16:45:01 Central Indonesia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, onMounted, onUnmounted, ref } from "vue";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import ManufactureWorkingCard from "@/Components/ManufactureWorkingCard.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faSeedling, faThumbsDown, faUserHardHat, faTasks } from "@fal";
import { faCheckCircle, faTimesCircle, faPauseCircle } from "@fas";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { trans } from "laravel-vue-i18n";

import { capitalize } from "@/Composables/capitalize";
import { useLocaleStore } from "@/Stores/locale";

import { PageHeadingTypes } from "@/types/PageHeading";

library.add(faSeedling, faThumbsDown, faTimesCircle, faPauseCircle, faCheckCircle, faUserHardHat, faTasks);

interface QueueTask {
    id: number
    state: string
    task_name: string
    artefact_code: string
    artefact_name: string
    job_order_reference: string
    job_order_slug: string
    quantity_made: number
    quantity_required: number
    start_route: { name: string, parameters: object }
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    stats: {
        name: string
        stat: number
        color: string
        icon: string[]
        route: { name: string, parameters: object }
    }[]
    command_control?: {
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
            job_order_slug: string
            started_at: string
            quantity_made: number
            quantity_required: number
        }[]
        today_sessions: {
            id: number
            worker: string
            task_name: string
            artefact_code: string
            job_order_reference: string
            job_order_slug: string
            ended_at: string
            quantity_made: number
            void_route: { name: string, parameters: object }
        }[]
        queue: QueueTask[]
    }
}>();

function voidSession(session: { id: number, worker: string, quantity_made: number, void_route: { name: string, parameters: object } }) {
    if (!window.confirm(trans('Void this entry?') + ` ${session.worker} · ${session.quantity_made}`)) return
    processing.value = true
    router.patch(
        route(session.void_route.name, session.void_route.parameters),
        {},
        { preserveScroll: true, onFinish: () => processing.value = false }
    )
}

const processing = ref(false)
function jobOrderHref(slug: string) {
    return route('grp.org.productions.show.operations.job-orders.show', [route().params['organisation'], route().params['production'], slug])
}
const locale = useLocaleStore()
const iconColors: Record<string, string> = {
    indigo: "text-indigo-500",
    teal: "text-teal-500",
    amber: "text-amber-500",
    green: "text-green-500",
    blue: "text-blue-500",
}

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
    <dl class="mx-4 mt-4 grid grid-cols-2 divide-x divide-gray-100 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100 md:grid-cols-5">
        <Link
            v-for="card in stats"
            :key="card.name"
            :href="route(card.route.name, card.route.parameters)"
            class="flex items-center gap-2 px-3 py-2.5 text-sm hover:bg-gray-50">
            <FontAwesomeIcon :icon="card.icon" :class="iconColors[card.color] ?? 'text-gray-400'" fixed-width />
            <dt class="truncate text-gray-500">{{ card.name }}</dt>
            <dd class="ml-auto font-semibold tabular-nums text-gray-800">{{ locale.number(card.stat) }}</dd>
        </Link>
    </dl>

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
                        · {{ trans('Job order') }} <Link :href="jobOrderHref(session.job_order_slug)" class="text-indigo-700 hover:underline">{{ session.job_order_reference }}</Link>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="font-mono tabular-nums text-indigo-700">{{ elapsedSince(session.started_at) }}</div>
                    <div class="text-xs text-gray-500 tabular-nums">{{ session.quantity_made }} / {{ session.quantity_required }}</div>
                </div>
            </div>

            <template v-if="command_control.today_sessions.length">
                <h3 class="text-sm font-semibold text-gray-500 mt-6 mb-2">{{ trans('Finished today') }}</h3>
                <div v-for="session in command_control.today_sessions" :key="session.id"
                    class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-2 flex items-center justify-between gap-3 text-sm">
                    <div class="min-w-0 truncate">
                        <span class="font-medium">{{ session.worker }}</span>
                        <span class="text-gray-600"> · {{ session.task_name }} · {{ session.artefact_code }} · </span>
                        <Link :href="jobOrderHref(session.job_order_slug)" class="text-indigo-700 hover:underline">{{ session.job_order_reference }}</Link>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="tabular-nums text-gray-700">{{ session.quantity_made }}</span>
                        <button
                            type="button"
                            class="text-xs text-red-600 hover:underline disabled:opacity-40"
                            :disabled="processing"
                            @click="voidSession(session)"
                        >
                            {{ trans('Void') }}
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <div>
            <h2 class="text-lg font-semibold mb-3">
                <FontAwesomeIcon :icon="['fal', 'tasks']" fixed-width class="text-gray-400 mr-1" />
                {{ trans('Task queue') }}
            </h2>
            <div v-if="!command_control.queue.length" class="text-gray-400 text-sm py-6 text-center border border-dashed border-gray-200 rounded-lg">
                {{ trans('The queue is empty') }}
            </div>
            <div v-for="task in command_control.queue" :key="task.id"
                class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-3 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-medium truncate">{{ task.task_name }}</div>
                    <div class="text-sm text-gray-600 truncate">
                        {{ task.artefact_code }} — {{ task.artefact_name }}
                        · {{ trans('Job order') }} <Link :href="jobOrderHref(task.job_order_slug)" class="text-indigo-700 hover:underline">{{ task.job_order_reference }}</Link>
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
