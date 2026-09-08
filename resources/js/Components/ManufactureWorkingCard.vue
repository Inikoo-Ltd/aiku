<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 23:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    session: {
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
        band_feedback: null | {
            band0_hourly_rate: number
            bands: { code: string, name: string | null, hourly_rate: number, target_units_per_hour: number }[]
            session: { started_at: string, break_minutes: number, quantity_made: number }
        }
    }
}>()

const processing = ref(false)
const quantityMade = ref<number | null>(null)
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
    const bandFeedback = props.session.band_feedback
    const breakMinutes = bandFeedback?.session.break_minutes ?? 0
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

function closeSession() {
    if (quantityMade.value === null) return
    processing.value = true
    router.patch(
        route(props.session.close_route.name, props.session.close_route.parameters),
        {
            quantity_made: quantityMade.value,
            quantity_rejected: quantityRejected.value || 0,
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
    <div class="rounded-xl border-2 border-indigo-500 bg-indigo-50 p-6">
        <div class="flex items-baseline justify-between">
            <div>
                <div class="text-xs uppercase tracking-wide text-indigo-600">{{ trans('Working on') }}</div>
                <div class="text-2xl font-semibold mt-1">{{ session.task.task_name }}</div>
                <div class="text-gray-600 mt-1">
                    {{ session.task.artefact_code }} — {{ session.task.artefact_name }}
                </div>
                <div class="text-sm text-gray-500 mt-1">
                    {{ trans('Job order') }} {{ session.task.job_order_reference }}
                    · {{ session.task.quantity_made }} / {{ session.task.quantity_required }}
                </div>
            </div>
            <div class="text-4xl font-mono tabular-nums text-indigo-700">{{ elapsed }}</div>
        </div>

        <div class="mt-6 flex items-end gap-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">{{ trans('Quantity made') }}</label>
                <input
                    type="number" min="0" inputmode="numeric"
                    v-model.number="quantityMade"
                    class="w-36 rounded-lg border-gray-300 text-3xl text-center py-3 tabular-nums"
                />
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">{{ trans('Rejected') }}</label>
                <input
                    type="number" min="0" inputmode="numeric"
                    v-model.number="quantityRejected"
                    class="w-24 rounded-lg border-gray-300 text-xl text-center py-4 tabular-nums"
                />
            </div>
            <button
                type="button"
                class="flex-1 rounded-lg bg-green-600 text-white text-2xl font-semibold py-4 disabled:opacity-40"
                :disabled="processing || quantityMade === null"
                @click="closeSession"
            >
                {{ trans('DONE') }}
            </button>
        </div>

        <div v-if="session.band_feedback" class="mt-6 border-t border-indigo-200 pt-4">
            <div class="text-center text-3xl font-semibold tabular-nums">
                {{ currentRate.toFixed(1) }} <span class="text-base font-normal text-gray-600">{{ trans('units/hour') }}</span>
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
                {{ trans('Next') }}: {{ trans('band') }} {{ nextBand.code }} {{ trans('at') }} {{ nextBand.target_units_per_hour }} {{ trans('units/h') }}
                — +£{{ (nextBand.hourly_rate - currentBandRate).toFixed(2) }}/{{ trans('hour') }}
            </div>
        </div>
    </div>
</template>
