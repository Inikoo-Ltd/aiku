<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { useFormatTime } from '@/Composables/useFormatTime'
import { ctrans } from '@/Composables/useTrans'
import { routeType } from '@/types/route'

interface Segment {
    key: string
    label: string
    done_at: string | null
    target_at: string | null
    state: 'done' | 'on_track' | 'at_risk' | 'overdue' | 'future'
    days_overdue: number | null
}

interface Ribbon {
    slug: string
    reference: string
    organisation_code: string
    parent_type: 'agent' | 'supplier'
    parent_code: string
    parent_name: string
    country_code: string
    buyer_name: string
    amount: number
    currency_code: string
    created_at: string
    current_stage: string
    status: 'on_track' | 'at_risk' | 'overdue'
    days_overdue: number | null
    route: routeType
    segments: Segment[]
}

defineProps<{ ribbon: Ribbon }>()

const stateClass: Record<Segment['state'], string> = {
    done: 'bg-emerald-500',
    on_track: 'bg-blue-500 ring-2 ring-blue-300 ring-offset-1 animate-pulse',
    at_risk: 'bg-amber-400',
    overdue: 'bg-red-600',
    future: 'bg-gray-200'
}

function segmentTitle(segment: Segment): string {
    const parts = [segment.label]
    if (segment.done_at) {
        parts.push(`${ctrans('Done')}: ${segment.done_at}`)
    }
    if (segment.target_at) {
        parts.push(`${ctrans('Target')}: ${segment.target_at}`)
    }
    if (segment.days_overdue) {
        parts.push(`${ctrans('Days overdue')}: ${segment.days_overdue}`)
    }
    return parts.join(' · ')
}
</script>

<template>
    <div class="flex items-center gap-4 py-2">
        <div class="w-56 shrink-0">
            <Link :href="route(ribbon.route.name, ribbon.route.parameters)" class="text-sm font-semibold text-blue-600">
                {{ ribbon.reference }}
            </Link>
            <div class="text-xs text-gray-500">{{ ribbon.organisation_code }} · {{ ribbon.parent_code }}</div>
            <div class="text-xs text-gray-500">{{ ribbon.amount }} {{ ribbon.currency_code }}</div>
            <div class="text-xs text-gray-400">{{ useFormatTime(ribbon.created_at) }}</div>
        </div>
        <div class="flex flex-1 gap-0.5">
            <div
                v-for="segment in ribbon.segments"
                :key="segment.key"
                :title="segmentTitle(segment)"
                class="flex h-9 flex-1 flex-col items-center justify-center rounded-sm text-white"
                :class="stateClass[segment.state]"
            >
                <span class="hidden truncate px-1 text-xs md:block">{{ segment.label }}</span>
                <span class="hidden text-xs font-semibold md:block">
                    <template v-if="segment.state === 'overdue'">+{{ segment.days_overdue }}d</template>
                    <template v-else-if="segment.done_at">{{ useFormatTime(segment.done_at) }}</template>
                </span>
            </div>
        </div>
    </div>
</template>
