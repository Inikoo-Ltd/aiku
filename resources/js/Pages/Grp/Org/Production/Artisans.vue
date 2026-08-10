<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 09 Aug 2026 15:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { ref } from "vue"
import { trans } from "laravel-vue-i18n"
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
    quantity_made: number
    quantity_rejected: number
    earned: number
    void_route: null | { name: string, parameters: object }
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    period: { from: string, to: string }
    artisans: {
        user_id: number
        worker: string
        number_sessions: number
        hours_worked: number
        quantity_made: number
        quantity_rejected: number
        earned: number
        sessions: ArtisanSession[]
    }[]
}>()

const from = ref(props.period.from)
const to = ref(props.period.to)
const expanded = ref<Set<number>>(new Set())
const processing = ref(false)

function applyPeriod() {
    router.get(window.location.pathname, { from: from.value, to: to.value }, { preserveState: false })
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
    if (!window.confirm(trans('Void this entry?') + ` ${session.quantity_made}`)) return
    processing.value = true
    router.patch(
        route(session.void_route.name, session.void_route.parameters),
        {},
        { preserveScroll: true, onFinish: () => processing.value = false }
    )
}

function sessionDuration(session: ArtisanSession) {
    const seconds = Math.max(0, (new Date(session.ended_at).getTime() - new Date(session.started_at).getTime()) / 1000)
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
                <label class="block text-xs text-gray-500 mb-1">{{ trans('From') }}</label>
                <input type="date" v-model="from" class="rounded border-gray-300 text-sm" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ trans('To') }}</label>
                <input type="date" v-model="to" class="rounded border-gray-300 text-sm" />
            </div>
            <button type="button" class="rounded bg-indigo-600 text-white text-sm px-3 py-2" @click="applyPeriod">
                {{ trans('Apply') }}
            </button>
        </div>

        <div v-if="!artisans.length" class="text-gray-400 text-center py-16 border border-dashed border-gray-200 rounded-lg">
            {{ trans('No finished work in this period') }}
        </div>

        <div v-for="artisan in artisans" :key="artisan.user_id" class="mb-3 rounded-xl border border-gray-200 bg-white">
            <button type="button" class="w-full px-4 py-3 flex items-center justify-between gap-4 text-left" @click="toggle(artisan.user_id)">
                <div class="font-semibold">{{ artisan.worker }}</div>
                <div class="flex items-center gap-6 text-sm text-gray-600 tabular-nums">
                    <span>{{ artisan.number_sessions }} {{ trans('tasks') }}</span>
                    <span>{{ artisan.hours_worked }} h</span>
                    <span>{{ artisan.quantity_made }} {{ trans('units') }}</span>
                    <span v-if="artisan.quantity_rejected" class="text-red-500">{{ artisan.quantity_rejected }} {{ trans('rejected') }}</span>
                    <span class="font-semibold text-gray-800">{{ artisan.earned.toFixed(2) }}</span>
                    <FontAwesomeIcon :icon="['fal', expanded.has(artisan.user_id) ? 'chevron-up' : 'chevron-down']" fixed-width class="text-gray-400" />
                </div>
            </button>

            <div v-if="expanded.has(artisan.user_id)" class="border-t border-gray-100 px-4 py-2">
                <div v-for="session in artisan.sessions" :key="session.id"
                    class="py-2 flex items-center justify-between gap-3 text-sm border-b border-gray-50 last:border-0"
                    :class="session.state == 'voided' ? 'opacity-40 line-through' : ''">
                    <div class="min-w-0 truncate">
                        {{ session.task_name }} · {{ session.artefact_code }}
                        · {{ session.job_order_reference }}
                        <span class="text-gray-400 ml-1">{{ useFormatTime(session.ended_at) }}</span>
                    </div>
                    <div class="flex items-center gap-4 shrink-0 tabular-nums text-gray-700">
                        <span class="text-gray-400">{{ sessionDuration(session) }}</span>
                        <span>{{ session.quantity_made }}</span>
                        <span v-if="session.quantity_rejected" class="text-red-500">-{{ session.quantity_rejected }}</span>
                        <span class="w-16 text-right">{{ session.earned.toFixed(2) }}</span>
                        <button
                            v-if="session.void_route"
                            type="button"
                            class="text-xs text-red-600 hover:underline disabled:opacity-40"
                            :disabled="processing"
                            @click="voidSession(session)"
                        >
                            {{ trans('Void') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
