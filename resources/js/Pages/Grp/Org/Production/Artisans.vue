<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 09 Aug 2026 15:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserHardHat, faChevronDown, faChevronUp } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faUserHardHat, faChevronDown, faChevronUp)

interface ArtisanSession {
    id: number
    state: string
    task_name: string
    artefact_code: string
    job_order_reference: string
    started_at: string
    ended_at: string
    break_minutes: number
    quantity_made: number
    quantity_rejected: number
    earned: number
    units_per_hour: number | null
    standard_rate: number | null
    is_under_target: boolean
    under_target_review: null | { reason: string, note: string | null, reviewed_by: string | null, reviewed_at: string }
    review_route: null | { name: string, parameters: object }
    void_route: null | { name: string, parameters: object }
}

interface ArtisanJobStep {
    manufacture_task_id: number
    task_name: string
    hours: number
    quantity_made: number
    quantity_rejected: number
    earned: number
    sessions: ArtisanSession[]
}

interface ArtisanJob {
    job_order_item_id: number
    job_order_reference: string
    artefact_code: string
    hours: number
    quantity_made: number
    quantity_rejected: number
    earned: number
    steps: ArtisanJobStep[]
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    period: { from: string, to: string }
    manufacture_task_id: number | null
    manufacture_tasks: { id: number, name: string }[]
    under_target: boolean
    under_target_reasons: Record<string, string>
    artisans: {
        user_id: number
        worker: string
        number_sessions: number
        hours_worked: number
        quantity_made: number
        quantity_rejected: number
        earned: number
        under_target_open: number
        jobs: ArtisanJob[]
    }[]
}>()

const from = ref(props.period.from)
const to = ref(props.period.to)
const manufactureTaskId = ref<number | null>(props.manufacture_task_id)
const underTargetOnly = ref(props.under_target)
const reviewing = ref<number | null>(null)
const reviewReason = ref<string | null>(null)
const reviewNote = ref('')
const expanded = ref<Set<number>>(new Set())
const processing = ref(false)

function applyPeriod() {
    router.get(
        window.location.pathname,
        { from: from.value, to: to.value, manufacture_task_id: manufactureTaskId.value || undefined, under_target: underTargetOnly.value ? 1 : undefined },
        { preserveState: false }
    )
}

function toggle(userId: number) {
    if (expanded.value.has(userId)) {
        expanded.value.delete(userId)
    } else {
        expanded.value.add(userId)
    }
    expanded.value = new Set(expanded.value)
}

function voidSession(session: ArtisanSession) {
    if (!session.void_route) return
    if (!window.confirm(ctrans('Void this entry?') + ` ${session.quantity_made}`)) return
    processing.value = true
    router.patch(
        route(session.void_route.name, session.void_route.parameters),
        {},
        { preserveScroll: true, onFinish: () => processing.value = false }
    )
}

function openReview(session: ArtisanSession) {
    reviewing.value = session.id
    reviewReason.value = null
    reviewNote.value = ''
}

function saveReview(session: ArtisanSession) {
    if (!session.review_route || !reviewReason.value) return
    processing.value = true
    router.patch(
        route(session.review_route.name, session.review_route.parameters),
        { under_target_reason: reviewReason.value, under_target_note: reviewNote.value || null },
        {
            preserveScroll: true,
            onSuccess: () => reviewing.value = null,
            onFinish: () => processing.value = false,
        }
    )
}

function sessionDuration(session: ArtisanSession) {
    const seconds = Math.max(0, (new Date(session.ended_at).getTime() - new Date(session.started_at).getTime()) / 1000 - session.break_minutes * 60)
    const h = Math.floor(seconds / 3600)
    const m = Math.round((seconds % 3600) / 60)
    return h ? `${h}h ${m}m` : `${m}m`
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-4 max-w-4xl">
        <div class="mb-6 flex items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('From') }}</label>
                <input type="date" v-model="from" class="rounded border-gray-300 text-sm" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('To') }}</label>
                <input type="date" v-model="to" class="rounded border-gray-300 text-sm" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Step') }}</label>
                <select v-model="manufactureTaskId" class="rounded border-gray-300 text-sm">
                    <option :value="null">{{ ctrans('All steps') }}</option>
                    <option v-for="task in manufacture_tasks" :key="task.id" :value="task.id">{{ task.name }}</option>
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm text-gray-600">
                <input type="checkbox" v-model="underTargetOnly" class="rounded border-gray-300" />
                {{ ctrans('Under target only') }}
            </label>
            <button type="button" class="rounded bg-indigo-600 text-white text-sm px-3 py-2" @click="applyPeriod">
                {{ ctrans('Apply') }}
            </button>
        </div>

        <div v-if="!artisans.length" class="text-gray-400 text-center py-16 border border-dashed border-gray-200 rounded-lg">
            {{ ctrans('No finished work in this period') }}
        </div>

        <div v-for="artisan in artisans" :key="artisan.user_id" class="mb-3 rounded-xl border border-gray-200 bg-white">
            <button type="button" class="w-full px-4 py-3 flex items-center justify-between gap-4 text-left" @click="toggle(artisan.user_id)">
                <div class="font-semibold">{{ artisan.worker }}</div>
                <div class="flex items-center gap-6 text-sm text-gray-600 tabular-nums">
                    <span>{{ artisan.number_sessions }} {{ ctrans('tasks') }}</span>
                    <span>{{ artisan.hours_worked }} h</span>
                    <span>{{ artisan.quantity_made }} {{ ctrans('units') }}</span>
                    <span v-if="artisan.quantity_rejected" class="text-red-500">{{ artisan.quantity_rejected }} {{ ctrans('rejected') }}</span>
                    <span v-if="artisan.under_target_open" class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">
                        {{ artisan.under_target_open }} {{ ctrans('under target') }}
                    </span>
                    <span class="font-semibold text-gray-800">{{ artisan.earned.toFixed(2) }}</span>
                    <FontAwesomeIcon :icon="['fal', expanded.has(artisan.user_id) ? 'chevron-up' : 'chevron-down']" fixed-width class="text-gray-400" />
                </div>
            </button>

            <div v-if="expanded.has(artisan.user_id)" class="border-t border-gray-100 px-4 py-2">
                <div v-for="job in artisan.jobs" :key="job.job_order_item_id" class="py-2 border-b border-gray-100 last:border-0">
                    <div class="flex items-center justify-between gap-3 text-sm font-medium text-gray-700">
                        <div class="truncate">{{ job.artefact_code }} · {{ job.job_order_reference }}</div>
                        <div class="flex items-center gap-4 shrink-0 tabular-nums">
                            <span class="text-gray-400">{{ job.hours }} h</span>
                            <span>{{ job.quantity_made }}</span>
                            <span v-if="job.quantity_rejected" class="text-red-500">-{{ job.quantity_rejected }}</span>
                            <span class="w-16 text-right">{{ job.earned.toFixed(2) }}</span>
                        </div>
                    </div>

                    <div v-for="step in job.steps" :key="step.manufacture_task_id" class="mt-1 pl-4 border-l-2 border-gray-100">
                        <div class="flex items-center justify-between gap-3 text-xs text-gray-500">
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-medium text-gray-600">{{ step.task_name }}</span>
                            <div class="flex items-center gap-4 shrink-0 tabular-nums">
                                <span>{{ step.hours }} h</span>
                                <span>{{ step.quantity_made }}</span>
                                <span v-if="step.quantity_rejected" class="text-red-500">-{{ step.quantity_rejected }}</span>
                                <span class="w-16 text-right">{{ step.earned.toFixed(2) }}</span>
                            </div>
                        </div>

                        <div v-for="session in step.sessions" :key="session.id">
                        <div
                            class="py-1 flex items-center justify-between gap-3 text-sm"
                            :class="session.state == 'voided' ? 'opacity-40 line-through' : ''">
                            <span class="flex items-center gap-2 text-gray-400">
                                {{ useFormatTime(session.ended_at) }}
                                <span v-if="session.is_under_target"
                                    class="rounded px-1.5 py-0.5 text-xs font-medium"
                                    :class="session.under_target_review ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-800'">
                                    {{ session.under_target_review ? session.under_target_review.reason : ctrans('Under target / action required') }}
                                </span>
                            </span>
                            <div class="flex items-center gap-4 shrink-0 tabular-nums text-gray-700">
                                <span class="text-gray-400">
                                    {{ sessionDuration(session) }}
                                    <span v-if="session.break_minutes">({{ session.break_minutes }}m {{ ctrans('break') }})</span>
                                </span>
                                <span>{{ session.quantity_made }}</span>
                                <span v-if="session.quantity_rejected" class="text-red-500">-{{ session.quantity_rejected }}</span>
                                <span v-if="session.standard_rate !== null" :class="session.is_under_target ? 'text-amber-700' : 'text-gray-400'">
                                    {{ session.units_per_hour ?? 0 }}/{{ session.standard_rate }} {{ ctrans('per hr') }}
                                </span>
                                <span class="w-16 text-right">{{ session.earned.toFixed(2) }}</span>
                                <button
                                    v-if="session.review_route && reviewing !== session.id"
                                    type="button"
                                    class="text-xs text-indigo-600 hover:underline"
                                    @click="openReview(session)"
                                >
                                    {{ session.under_target_review ? ctrans('Change reason') : ctrans('Log reason') }}
                                </button>
                                <button
                                    v-if="session.void_route"
                                    type="button"
                                    class="text-xs text-red-600 hover:underline disabled:opacity-40"
                                    :disabled="processing"
                                    @click="voidSession(session)"
                                >
                                    {{ ctrans('Void') }}
                                </button>
                            </div>
                        </div>
                        <div v-if="session.under_target_review?.note && reviewing !== session.id" class="pb-1 pl-2 text-xs text-gray-500">
                            {{ session.under_target_review.note }}
                            <span v-if="session.under_target_review.reviewed_by">— {{ session.under_target_review.reviewed_by }}</span>
                        </div>
                        <div v-if="reviewing === session.id" class="mb-2 flex flex-wrap items-end gap-2 rounded border border-amber-200 bg-amber-50 p-2 text-sm">
                            <select v-model="reviewReason" class="rounded border-gray-300 text-sm">
                                <option :value="null" disabled>{{ ctrans('Reason') }}</option>
                                <option v-for="(label, value) in under_target_reasons" :key="value" :value="value">{{ label }}</option>
                            </select>
                            <input v-model="reviewNote" type="text" maxlength="1000" :placeholder="ctrans('Notes (optional)')" class="min-w-0 flex-1 rounded border-gray-300 text-sm" />
                            <button type="button" class="rounded bg-indigo-600 px-3 py-1.5 text-white disabled:opacity-40" :disabled="!reviewReason || processing" @click="saveReview(session)">
                                {{ ctrans('Save') }}
                            </button>
                            <button type="button" class="text-xs text-gray-500 hover:underline" @click="reviewing = null">
                                {{ ctrans('Cancel') }}
                            </button>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
