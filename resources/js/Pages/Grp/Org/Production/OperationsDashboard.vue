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
import { faSeedling, faThumbsDown, faUserHardHat, faTasks, faHandshake, faInventory } from "@fal";
import { faCheckCircle, faTimesCircle, faPauseCircle } from "@fas";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { trans } from "laravel-vue-i18n";

import { capitalize } from "@/Composables/capitalize";
import { useLocaleStore } from "@/Stores/locale";

import { PageHeadingTypes } from "@/types/PageHeading";

library.add(faSeedling, faThumbsDown, faTimesCircle, faPauseCircle, faCheckCircle, faUserHardHat, faTasks, faHandshake, faInventory);

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

interface Lane { quantity: number, amount: number }
interface PartnerOrder {
    org_partner_id: number
    partner_code: string
    partner_name: string
    location_code: string
    location_slug: string
    requested: Lane
    being_made: Lane
    on_the_shelves: Lane
    in_the_bay: Lane
    ready: Lane
    ordered: Lane
    order_references: string[]
    quantity_in_the_bay: number
    quantity_in_the_bay_not_requested: number
    job_orders: { reference: string, slug: string }[]
    items: { code: string, name: string, in_the_bay: number, on_the_shelves: number, waiting: number, amount: number }[]
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
    partner_orders?: {
        can_create: boolean
        currency_code: string
        orders: PartnerOrder[]
    }
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
const openPartnerOrder = ref<number | null>(null)
const partnerOrderLanes: { key: 'requested' | 'being_made' | 'on_the_shelves' | 'in_the_bay' | 'ready', label: string }[] = [
    { key: 'requested', label: trans('Requested, no stock yet') },
    { key: 'being_made', label: trans('Being made') },
    { key: 'on_the_shelves', label: trans('On the shelves') },
    { key: 'in_the_bay', label: trans('In the bay') },
    { key: 'ready', label: trans('Ready to order') },
]

function createPartnerOrder(order: PartnerOrder) {
    const amount = locale.currencyFormat(props.partner_orders!.currency_code, order.ready.amount)
    if (!window.confirm(`${trans('Create the order and send it to the warehouse?')} ${order.partner_name} · ${locale.number(order.ready.quantity)} SKOs · ${amount}`)) return
    processing.value = true
    router.post(
        route('grp.org.productions.show.operations.partner_orders.store', [route().params['organisation'], route().params['production'], order.org_partner_id]),
        {},
        {
            preserveScroll: true,
            onError: errors => window.alert(Object.values(errors).join(' ')),
            onFinish: () => processing.value = false,
        }
    )
}
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

    <div v-if="partner_orders?.orders.length" class="mx-4 mt-6">
        <h2 class="text-lg font-semibold mb-3">
            <FontAwesomeIcon :icon="['fal', 'handshake']" fixed-width class="text-gray-400 mr-1" />
            {{ trans('Partner orders') }}
        </h2>
        <div class="grid gap-4 lg:grid-cols-3">
            <div v-for="order in partner_orders.orders" :key="order.org_partner_id" class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                <div class="flex items-baseline justify-between gap-3">
                    <div class="font-medium truncate">{{ order.partner_name }}</div>
                    <div class="text-sm text-gray-500 shrink-0">
                        <FontAwesomeIcon :icon="['fal', 'inventory']" fixed-width class="text-gray-400" />
                        {{ order.location_code }}
                    </div>
                </div>

                <dl class="mt-2 text-sm">
                    <div v-for="lane in partnerOrderLanes" :key="lane.key" class="flex justify-between gap-3 py-0.5" :class="lane.key == 'ready' ? 'font-semibold text-gray-800 border-t border-gray-100 mt-1 pt-1.5' : 'text-gray-600'">
                        <dt>{{ lane.label }}</dt>
                        <dd class="tabular-nums">
                            {{ locale.number(order[lane.key].quantity) }}
                            <span class="ml-2 inline-block min-w-20 text-right">{{ locale.currencyFormat(partner_orders.currency_code, order[lane.key].amount) }}</span>
                        </dd>
                    </div>
                </dl>

                <div v-if="order.ordered.quantity > 0" class="mt-1 flex justify-between gap-3 text-sm text-gray-600">
                    <span>{{ trans('Ordered, waiting to be picked') }} · {{ order.order_references.join(', ') }}</span>
                    <span class="tabular-nums shrink-0">
                        {{ locale.number(order.ordered.quantity) }}
                        <span class="ml-2 inline-block min-w-20 text-right">{{ locale.currencyFormat(partner_orders.currency_code, order.ordered.amount) }}</span>
                    </span>
                </div>

                <div v-if="order.quantity_in_the_bay_not_requested > 0" class="mt-1 text-sm text-amber-600">
                    {{ locale.number(order.quantity_in_the_bay_not_requested) }} {{ trans('in the bay that nobody asked for, it stays out of the order') }}
                </div>

                <div v-if="order.job_orders.length" class="mt-1 text-sm text-gray-600">
                    {{ trans('Job orders') }}:
                    <template v-for="(jobOrder, index) in order.job_orders" :key="jobOrder.slug">
                        <span v-if="index">, </span>
                        <Link :href="jobOrderHref(jobOrder.slug)" class="text-[--app-accent-strong] hover:underline">{{ jobOrder.reference }}</Link>
                    </template>
                </div>

                <div class="mt-3 flex items-center justify-between gap-3">
                    <button v-if="order.items.length" type="button" class="text-sm text-[--app-accent-strong] hover:underline"
                        @click="openPartnerOrder = openPartnerOrder == order.org_partner_id ? null : order.org_partner_id">
                        {{ locale.number(order.items.length) }} {{ trans('items') }}
                    </button>
                    <button
                        v-if="partner_orders.can_create"
                        type="button"
                        class="ml-auto rounded bg-[--app-accent] text-[--app-accent-text] text-sm font-semibold px-4 py-2 transition duration-200 hover:bg-[--app-accent-strong] disabled:opacity-40 disabled:hover:bg-[--app-accent]"
                        :disabled="processing || order.ready.quantity <= 0"
                        @click="createPartnerOrder(order)"
                    >
                        {{ trans('Create order') }}
                    </button>
                </div>

                <table v-if="openPartnerOrder == order.org_partner_id" class="mt-3 w-full text-sm">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="text-left font-medium py-1">{{ trans('Code') }}</th>
                            <th class="text-right font-medium">{{ trans('In the bay') }}</th>
                            <th class="text-right font-medium">{{ trans('On the shelves') }}</th>
                            <th class="text-right font-medium">{{ trans('Waiting') }}</th>
                            <th class="text-right font-medium">{{ trans('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in order.items" :key="item.code" class="border-t border-gray-100">
                            <td class="py-1 truncate" :title="item.name">{{ item.code }}</td>
                            <td class="text-right tabular-nums">{{ locale.number(item.in_the_bay) }}</td>
                            <td class="text-right tabular-nums">{{ locale.number(item.on_the_shelves) }}</td>
                            <td class="text-right tabular-nums text-gray-500">{{ locale.number(item.waiting) }}</td>
                            <td class="text-right tabular-nums">{{ locale.currencyFormat(partner_orders.currency_code, item.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div v-if="command_control" class="mx-4 mt-6 grid gap-6 lg:grid-cols-2">
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold">
                    <FontAwesomeIcon :icon="['fal', 'user-hard-hat']" fixed-width class="text-gray-400 mr-1" />
                    {{ trans('Working now') }}
                </h2>
                <Link
                    :href="route(command_control.floor_route.name, command_control.floor_route.parameters)"
                    class="rounded bg-[--app-accent] text-[--app-accent-text] text-sm px-3 py-1.5 transition duration-200 hover:bg-[--app-accent-strong]"
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
                        · {{ trans('Job order') }} <Link :href="jobOrderHref(session.job_order_slug)" class="text-[--app-accent-strong] hover:underline">{{ session.job_order_reference }}</Link>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="font-mono tabular-nums text-[--app-accent-strong]">{{ elapsedSince(session.started_at) }}</div>
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
                        <Link :href="jobOrderHref(session.job_order_slug)" class="text-[--app-accent-strong] hover:underline">{{ session.job_order_reference }}</Link>
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
                        · {{ trans('Job order') }} <Link :href="jobOrderHref(task.job_order_slug)" class="text-[--app-accent-strong] hover:underline">{{ task.job_order_reference }}</Link>
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5 tabular-nums">
                        {{ task.quantity_made }} / {{ task.quantity_required }}
                        <span v-if="task.state == 'in_progress'" class="ml-1 text-amber-600 font-medium">{{ trans('In progress') }}</span>
                    </div>
                </div>
                <button
                    v-if="!command_control.open_session"
                    type="button"
                    class="shrink-0 rounded bg-[--app-accent] text-[--app-accent-text] text-sm font-semibold px-4 py-2 transition duration-200 hover:bg-[--app-accent-strong] disabled:opacity-40 disabled:hover:bg-[--app-accent]"
                    :disabled="processing"
                    @click="startTask(task)"
                >
                    {{ trans('START') }}
                </button>
            </div>
        </div>
    </div>

</template>
