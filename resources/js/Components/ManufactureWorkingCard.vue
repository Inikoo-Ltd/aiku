<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 23:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { ctrans } from '@/Composables/useTrans'

const props = defineProps<{
    session: {
        id: number
        started_at: string
        can_reject?: boolean
        task: {
            task_name: string
            artefact_code: string
            artefact_name: string
            job_order_reference: string
            quantity_made: number
            quantity_required: number
        }
        close_route: { name: string, parameters: object }
        break_minutes: number
        band_feedback: null | {
            currency_symbol: string
            band0_hourly_rate: number
            bands: { code: string, name: string | null, hourly_rate: number, target_units_per_hour: number }[]
            session: { started_at: string, break_minutes: number, quantity_made: number }
        }
    }
}>()

const page = usePage()
const closeError = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined
    return errors?.quantity_made ?? errors?.state ?? errors?.quantity_rejected ?? errors?.non_productive_reason
})

const processing = ref(false)
const quantityMade = ref<number | null>(null)
const remaining = computed(() => Math.max(0, props.session.task.quantity_required - props.session.task.quantity_made))
const quantityRejected = ref(0)

const now = ref(Date.now())
let timer: ReturnType<typeof setInterval>
onMounted(() => timer = setInterval(() => now.value = Date.now(), 1000))
onUnmounted(() => clearInterval(timer))

const elapsed = computed(() => {
    const seconds = Math.max(0, Math.floor((now.value - new Date(props.session.started_at).getTime()) / 1000))
    const h = Math.floor(seconds / 3600)
    const m = Math.floor((seconds % 3600) / 60)
    const s = seconds % 60
    return `${h ? h + ':' : ''}${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

const elapsedHours = computed(() => {
    const breakMinutes = props.session.break_minutes
    const seconds = Math.max(0, Math.floor((now.value - new Date(props.session.started_at).getTime()) / 1000) - breakMinutes * 60)
    return seconds / 3600
})

const currentRate = computed(() => {
    if (!elapsedHours.value) return 0
    return (quantityMade.value ?? 0) / elapsedHours.value
})

const currentBandIndex = computed(() => {
    const bands = props.session.band_feedback?.bands ?? []
    const roundedRate = Math.round(currentRate.value)
    let index = -1
    bands.forEach((band, i) => {
        if (roundedRate >= band.target_units_per_hour) index = i
    })
    return index
})

const currentBandRate = computed(() => {
    const bandFeedback = props.session.band_feedback
    if (!bandFeedback) return 0
    return currentBandIndex.value === -1 ? bandFeedback.band0_hourly_rate : bandFeedback.bands[currentBandIndex.value].hourly_rate
})

const nextBand = computed(() => {
    const bands = props.session.band_feedback?.bands ?? []
    return bands[currentBandIndex.value + 1] ?? null
})

const askOutcome = ref(false)
const isShort = computed(() => quantityMade.value !== null && quantityMade.value < remaining.value)

function onDone() {
    if (isShort.value) {
        askOutcome.value = true
        return
    }
    closeSession()
}

function closeSession(outcome: 'complete' | 'carry_over' | null = null) {
    if (quantityMade.value === null) return
    askOutcome.value = false
    processing.value = true
    router.patch(
        route(props.session.close_route.name, props.session.close_route.parameters),
        {
            quantity_made: quantityMade.value,
            quantity_rejected: quantityRejected.value || 0,
            outcome,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false
                quantityMade.value = null
                quantityRejected.value = 0
            },
        }
    )
}
</script>

<template>
    <div class="rounded-2xl border-2 border-indigo-500 bg-indigo-50 p-10">
        <div class="flex items-baseline justify-between">
            <div>
                <div class="text-xs uppercase tracking-wide text-indigo-600">{{ ctrans('Working on') }}</div>
                <div class="text-4xl font-semibold mt-1">{{ session.task.task_name }}</div>
                <div class="text-2xl text-gray-600 mt-1">
                    {{ session.task.artefact_code }} — {{ session.task.artefact_name }}
                </div>
                <div class="text-lg text-gray-500 mt-1">
                    {{ ctrans('Job order') }} {{ session.task.job_order_reference }}
                </div>
                <button
                    type="button"
                    class="mt-3 rounded-lg border-2 border-dashed border-indigo-300 px-4 py-2 text-left hover:bg-indigo-100"
                    :title="ctrans('Tap to fill quantity made')"
                    @click="quantityMade = remaining"
                >
                    <span class="text-7xl font-semibold tabular-nums text-indigo-700">{{ remaining }}</span>
                    <span class="ml-3 text-xl text-gray-500">{{ ctrans('to do') }} · {{ session.task.quantity_made }} / {{ session.task.quantity_required }}</span>
                </button>
            </div>
            <div class="text-right">
                <div class="text-7xl font-mono tabular-nums text-indigo-700">{{ elapsed }}</div>
                <div v-if="session.break_minutes" class="text-sm text-gray-500">{{ session.break_minutes }} {{ ctrans('min on break') }}</div>
            </div>
        </div>

        <div v-if="closeError" class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-lg text-red-700">{{ closeError }}</div>

        <div v-if="!askOutcome" class="mt-6 flex items-end gap-4">
            <div>
                <label class="block text-lg text-gray-600 mb-1">{{ ctrans('Quantity made') }}</label>
                <input
                    type="number" min="0" inputmode="numeric"
                    v-model.number="quantityMade"
                    class="w-52 rounded-lg border-gray-300 text-5xl text-center py-4 tabular-nums"
                />
            </div>
            <div v-if="session.can_reject">
                <label class="block text-sm text-gray-600 mb-1">{{ ctrans('Rejected') }}</label>
                <input
                    type="number" min="0" inputmode="numeric"
                    v-model.number="quantityRejected"
                    class="w-24 rounded-lg border-gray-300 text-xl text-center py-4 tabular-nums"
                />
            </div>
            <button
                type="button"
                class="flex-1 rounded-lg bg-green-600 text-white text-4xl font-semibold py-6 disabled:opacity-40"
                :disabled="processing || quantityMade === null"
                @click="onDone"
            >
                {{ ctrans('DONE') }}
            </button>
        </div>

        <div v-if="askOutcome" class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4">
            <div class="flex items-baseline gap-6">
                <div><span class="text-4xl font-semibold tabular-nums text-green-700">{{ quantityMade }}</span> <span class="text-lg text-gray-600">{{ ctrans('done') }}</span></div>
                <div><span class="text-4xl font-semibold tabular-nums text-amber-700">{{ Math.max(0, remaining - (quantityMade ?? 0)) }}</span> <span class="text-lg text-gray-600">{{ ctrans('to do') }}</span></div>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <button type="button" class="rounded-lg bg-indigo-600 text-white text-xl font-semibold py-4 disabled:opacity-40"
                    :disabled="processing" @click="closeSession('carry_over')">
                    {{ ctrans('Continue later') }}
                    <div class="text-xs font-normal opacity-80">{{ ctrans('The rest keeps this job number') }}</div>
                </button>
                <button type="button" class="rounded-lg bg-green-600 text-white text-xl font-semibold py-4 disabled:opacity-40"
                    :disabled="processing" @click="closeSession('complete')">
                    {{ ctrans('Job finished') }}
                    <div class="text-xs font-normal opacity-80">{{ ctrans('Close with what was made') }}</div>
                </button>
                <button type="button" class="rounded-lg border border-gray-300 bg-white text-gray-700 text-xl font-semibold py-4"
                    @click="askOutcome = false">
                    {{ ctrans('Back') }}
                </button>
            </div>
        </div>

        <div v-if="session.band_feedback" class="mt-6 border-t border-indigo-200 pt-4">
            <div class="text-center text-3xl font-semibold tabular-nums">
                {{ currentRate.toFixed(1) }} <span class="text-base font-normal text-gray-600">{{ ctrans('units/hour') }}</span>
            </div>

            <div class="mt-3 flex gap-1">
                <div
                    class="flex-1 rounded-lg py-3 text-center"
                    :class="currentBandIndex === -1 ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-500'"
                >
                    <div class="text-lg font-semibold">0</div>
                    <div class="text-xs">—</div>
                </div>
                <div
                    v-for="(band, i) in session.band_feedback.bands"
                    :key="band.code"
                    class="flex-1 rounded-lg py-3 text-center"
                    :class="i === currentBandIndex ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-500'"
                >
                    <div class="text-lg font-semibold">{{ band.code }}</div>
                    <div class="text-xs">{{ band.target_units_per_hour }}/h</div>
                </div>
            </div>

            <div v-if="nextBand" class="mt-2 text-center text-sm text-gray-600">
                {{ ctrans('Next') }}: {{ ctrans('band') }} {{ nextBand.code }} {{ ctrans('at') }} {{ nextBand.target_units_per_hour }} {{ ctrans('units/h') }}
                — +{{ session.band_feedback.currency_symbol }}{{ (nextBand.hourly_rate - currentBandRate).toFixed(2) }}/{{ ctrans('hour') }}
            </div>
        </div>
    </div>
</template>
