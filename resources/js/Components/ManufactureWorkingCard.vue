<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 23:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { ctrans } from '@/Composables/useTrans'
import { packsLabel } from '@/Composables/packs'
import Dialog from 'primevue/dialog'
import Password from 'primevue/password'
import InputText from 'primevue/inputtext'
import Image from '@common/Components/Image.vue'
import { QrcodeStream } from 'vue-qrcode-reader'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPause, faShare, faCheck, faTimes, faExclamationTriangle, faPlay } from '@fal'
library.add(faPause, faShare, faCheck, faTimes, faExclamationTriangle, faPlay)

const props = defineProps<{
    session: {
        id: number
        started_at: string
        can_reject?: boolean
        activity?: { type: string, label: string }
        preparation_reasons?: { id: number, label: string, is_other: boolean }[] | null
        short_preparation_minutes?: number
        task: {
            task_name: string
            artefact_code: string | null
            artefact_name: string | null
            artefact_image?: object | null
            job_order_reference: string | null
            quantity_made: number
            quantity_required: number
            pack_size?: number | null
            unit?: string | null
            kg_per_unit?: number | null
            mix_batch_kg?: number | null
            continued_from?: string | null
            is_quantity_only?: boolean
        }
        carry?: null | { reference: string, units_per_artefact: number, quantity: number }
        close_route: { name: string, parameters: object }
        break_minutes: number
        left_open_after_shift_at?: string | null
        shift_end?: null | {
            paused_at: string
            worked_seconds: number
            answer: string | null
            is_resolved: boolean
            continue_route: { name: string, parameters: object }
        }
        band_feedback: null | {
            currency_symbol: string
            band0_hourly_rate: number
            bands: { code: string, name: string | null, hourly_rate: number, target_units_per_hour: number }[]
            session: { started_at: string, break_minutes: number, quantity_made: number }
        }
    }
    serverTime?: string
}>()

const page = usePage()
const closeError = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined
    return errors?.quantity_made ?? errors?.state ?? errors?.quantity_rejected ?? errors?.non_productive_reason ?? errors?.preparation_reason_id ?? errors?.preparation_reason_note
})
const managerError = computed(() => (page.props.errors as Record<string, string> | undefined)?.manager_code)

const processing = ref(false)
const quantityMade = ref<number | null>(null)
const remaining = computed(() => Math.max(0, props.session.task.quantity_required - props.session.task.quantity_made))
const quantityRejected = ref(0)
const packSize = computed(() => props.session.task.pack_size ?? null)
const unit = computed(() => props.session.task.unit ?? null)
const kgInUnits = computed(() => unit.value && props.session.task.kg_per_unit && quantityMade.value !== null ? Math.floor(quantityMade.value / props.session.task.kg_per_unit + 1e-6).toLocaleString() : null)
const mixBatch = computed(() => props.session.task.mix_batch_kg ?? null)
const remainingBatches = computed(() => mixBatch.value ? Math.ceil(remaining.value / mixBatch.value - 1e-9) : null)
const batchesMade = computed({
    get: () => quantityMade.value === null || !mixBatch.value ? null : Math.round(quantityMade.value / mixBatch.value * 1000) / 1000,
    set: (batches: number | string | null) => quantityMade.value = batches === null || batches === '' ? null : Math.round(Math.max(0, Math.floor(Number(batches))) * (mixBatch.value ?? 1) * 1000) / 1000,
})
const inPieces = (packs: number) => Math.round(packs * (packSize.value ?? 1)).toLocaleString()
const notWholePacks = computed(() => packSize.value !== null && quantityMade.value !== null && !Number.isInteger(quantityMade.value) && quantityMade.value !== remaining.value)

const deviceClockOffset = computed(() => props.serverTime ? Date.parse(props.serverTime) - Date.now() : 0)
const now = ref(Date.now() + deviceClockOffset.value)
let timer: ReturnType<typeof setInterval>
onMounted(() => timer = setInterval(() => now.value = Date.now() + deviceClockOffset.value, 1000))
onUnmounted(() => clearInterval(timer))

const elapsed = computed(() => {
    const seconds = Math.max(0, Math.floor((now.value - new Date(props.session.started_at).getTime()) / 1000))
    const h = Math.floor(seconds / 3600)
    const m = Math.floor((seconds % 3600) / 60)
    const s = seconds % 60
    return `${h ? h + ':' : ''}${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

const isQuantityOnly = computed(() => !!props.session.task.is_quantity_only)

const finishedBeforeShiftEnd = ref(false)
const shiftEndQuestion = computed(() => !!props.session.shift_end && !props.session.shift_end.answer && !finishedBeforeShiftEnd.value)
const pausedAtLabel = computed(() => props.session.shift_end ? new Date(props.session.shift_end.paused_at).toLocaleString([], { weekday: 'short', hour: '2-digit', minute: '2-digit' }) : '')
const timeLogged = computed(() => {
    if (!finishedBeforeShiftEnd.value || !props.session.shift_end) return elapsed.value
    const minutes = Math.floor(props.session.shift_end.worked_seconds / 60)
    return `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}`
})

function continueAfterShift() {
    if (!props.session.shift_end) return
    processing.value = true
    router.patch(
        route(props.session.shift_end.continue_route.name, props.session.shift_end.continue_route.parameters),
        {},
        { preserveScroll: true, onFinish: () => processing.value = false }
    )
}

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
const confirmingMove = ref(false)
const closedAt = computed(() => props.session.carry ? Math.floor((props.session.task.quantity_made + (quantityMade.value ?? 0)) / props.session.carry.units_per_artefact + 1e-6) : 0)
const movedOn = computed(() => props.session.carry ? Math.max(0, props.session.carry.quantity - closedAt.value) : 0)
const canMove = computed(() => !props.session.carry || (closedAt.value >= 1 && movedOn.value > 0))
const confirming = ref(false)
const isShort = computed(() => quantityMade.value !== null && quantityMade.value < remaining.value)
const surplus = computed(() => quantityMade.value === null || unit.value ? 0 : Math.max(0, quantityMade.value - remaining.value))

const askManager = ref(false)
const usePin = ref(false)
const pinField = ref<HTMLElement | null>(null)
watch(usePin, (showPin) => showPin && nextTick(() => pinField.value?.querySelector('input')?.focus()))
const managerPin = ref('')
const facingMode = ref<'environment' | 'user'>('environment')
const cameraError = ref<string | null>(null)
const rejectedBadge = ref<string | null>(null)
const rateWarning = ref<string | null>(null)
const pendingOutcome = ref<'complete' | 'carry_over' | null>(null)

function openManagerCheck() {
    askManager.value = true
    usePin.value = false
    managerPin.value = ''
    cameraError.value = null
    rejectedBadge.value = null
}

function onBadgeDetected(detectedCodes: { rawValue: string }[]) {
    const code = detectedCodes[0]?.rawValue
    if (code && code !== rejectedBadge.value && !processing.value) closeSession(pendingOutcome.value, { manager_code: code, manager_method: 'qr' })
}

function onCameraError(error: Error) {
    cameraError.value = error.message
    usePin.value = true
}

function onDone() {
    if (quantityMade.value === null || notWholePacks.value) return
    if (isQuantityOnly.value) {
        confirmDone()
        return
    }
    confirming.value = true
}

function confirmDone() {
    confirming.value = false
    if (surplus.value > 0) {
        pendingOutcome.value = null
        openManagerCheck()
        return
    }
    if (isShort.value) {
        askOutcome.value = true
        return
    }
    closeSession()
}

const preparationReasonId = ref<number | null>(null)
const preparationReasonNote = ref('')
const isOtherReason = computed(() => !!props.session.preparation_reasons?.find((reason) => reason.id === preparationReasonId.value)?.is_other)
const asksPreparationReason = computed(() => !!props.session.preparation_reasons?.length && now.value - new Date(props.session.started_at).getTime() >= (props.session.short_preparation_minutes ?? 0) * 60000)
const canFinishActivity = computed(() => !asksPreparationReason.value || (preparationReasonId.value !== null && (!isOtherReason.value || preparationReasonNote.value.trim() !== '')))

function finishActivity() {
    processing.value = true
    router.patch(
        route(props.session.close_route.name, props.session.close_route.parameters),
        asksPreparationReason.value ? { preparation_reason_id: preparationReasonId.value, preparation_reason_note: isOtherReason.value ? preparationReasonNote.value.trim() : null } : {},
        { preserveScroll: true, onFinish: () => { processing.value = false; confirming.value = false } }
    )
}

function closeSession(outcome: 'complete' | 'carry_over' | null = null, manager: { manager_code: string, manager_method: 'qr' | 'pin' } | null = null) {
    if (quantityMade.value === null) return
    if (!manager) pendingOutcome.value = outcome
    askOutcome.value = false
    confirmingMove.value = false
    processing.value = true
    router.patch(
        route(props.session.close_route.name, props.session.close_route.parameters),
        {
            quantity_made: quantityMade.value,
            quantity_rejected: quantityRejected.value || 0,
            outcome,
            ...(finishedBeforeShiftEnd.value ? { finished_before_shift_end: true } : {}),
            ...(manager ?? {}),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                askManager.value = false
                rateWarning.value = null
                pendingOutcome.value = null
                quantityMade.value = null
                quantityRejected.value = 0
            },
            onError: (errors) => {
                if (manager?.manager_method === 'qr') rejectedBadge.value = manager.manager_code
                managerPin.value = ''
                if (errors.rate) {
                    rateWarning.value = errors.rate
                    openManagerCheck()
                } else if (!errors.manager_code) {
                    askManager.value = false
                }
            },
            onFinish: () => {
                processing.value = false
            },
        }
    )
}
</script>

<template>
    <div v-if="session.activity" class="rounded-2xl border-2 border-gray-400 bg-gray-50 p-10">
        <div class="flex items-baseline justify-between">
            <div>
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ ctrans('Not production · paid at base rate') }}</div>
                <div class="text-5xl font-semibold mt-1">{{ session.activity.label }}</div>
                <div class="text-xl text-gray-600 mt-2">
                    {{ session.task.job_order_reference ? ctrans('Job order') + ' ' + session.task.job_order_reference : ctrans('General, no job order') }}
                </div>
            </div>
            <div class="text-right">
                <div class="text-7xl font-mono tabular-nums text-gray-800">{{ elapsed }}</div>
                <div v-if="session.break_minutes" class="text-sm text-gray-500">{{ session.break_minutes }} {{ ctrans('min on break') }}</div>
            </div>
        </div>

        <div v-if="closeError" class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-lg text-red-700">{{ closeError }}</div>

        <div v-if="confirming" class="mt-6 rounded-lg border border-green-300 bg-green-50 p-4">
            <div class="text-2xl font-semibold">{{ ctrans('Finish :activity?', { activity: session.activity.label }) }}</div>
            <div class="mt-2 text-lg text-gray-700">{{ ctrans('Time logged') }}: <b class="tabular-nums">{{ elapsed }}</b></div>
            <template v-if="asksPreparationReason">
                <div class="mt-4 text-xl font-semibold">{{ ctrans('Why did you need preparation?') }}</div>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <button v-for="reason in session.preparation_reasons" :key="reason.id" type="button"
                        class="min-h-20 rounded-lg border-2 px-4 py-3 text-left text-xl font-medium"
                        :class="preparationReasonId === reason.id ? 'border-[var(--app-accent)] bg-[var(--app-accent-soft)] text-gray-900' : 'border-gray-300 bg-white text-gray-700'"
                        :aria-pressed="preparationReasonId === reason.id"
                        @click="preparationReasonId = reason.id">
                        <FontAwesomeIcon v-if="preparationReasonId === reason.id" :icon="['fal', 'check']" fixed-width class="mr-1 text-[var(--app-accent)]" aria-hidden="true" />
                        {{ reason.label }}
                    </button>
                </div>
                <InputText v-if="isOtherReason" v-model="preparationReasonNote" :placeholder="ctrans('Type the reason')" :aria-label="ctrans('Type the reason')"
                    maxlength="500" class="mt-3 w-full !py-4 !text-xl" autofocus />
            </template>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <button type="button" class="rounded-lg border border-gray-300 bg-white text-gray-700 text-xl font-semibold py-4"
                    @click="confirming = false">
                    {{ ctrans('Cancel / Go back') }}
                </button>
                <button type="button" class="rounded-lg bg-green-600 text-white text-xl font-semibold py-4 disabled:opacity-40"
                    :disabled="processing || !canFinishActivity" @click="finishActivity">
                    {{ ctrans('Confirm & finish') }}
                </button>
            </div>
        </div>
        <button v-else type="button"
            class="mt-6 w-full rounded-lg bg-green-600 text-white text-4xl font-semibold py-6 disabled:opacity-40"
            :disabled="processing" @click="confirming = true">
            {{ ctrans('FINISH') }}
        </button>
    </div>

    <div v-else class="rounded-2xl border-2 border-[var(--app-accent)] bg-[var(--app-accent-soft)] p-10">
        <div class="flex items-baseline justify-between">
            <div>
                <div class="text-xs uppercase tracking-wide text-[var(--app-accent)]">{{ isQuantityOnly ? ctrans('Record quantity made') : ctrans('Working on') }}</div>
                <div class="text-4xl font-semibold mt-1">{{ session.task.task_name }}</div>
                <div class="flex items-center gap-4 mt-2">
                    <Image v-if="session.task.artefact_image" :src="session.task.artefact_image" class="aspect-square w-24 shrink-0 overflow-hidden rounded-xl shadow" />
                    <div>
                        <div class="text-4xl font-bold">{{ session.task.artefact_code }}</div>
                        <div class="text-2xl font-semibold text-gray-800">{{ session.task.artefact_name }}</div>
                    </div>
                </div>
                <div class="text-lg text-gray-500 mt-1">
                    {{ ctrans('Job order') }} {{ session.task.job_order_reference }}
                    <span v-if="session.task.continued_from"> · {{ ctrans('Continued from :reference', { reference: session.task.continued_from }) }}</span>
                </div>
                <button
                    type="button"
                    class="mt-3 rounded-lg border-2 border-dashed border-[var(--app-accent-muted)] px-4 py-2 text-left hover:bg-[var(--app-accent-soft)]"
                    :title="ctrans('Tap to fill quantity made')"
                    @click="quantityMade = remainingBatches !== null && mixBatch ? Math.round(remainingBatches * mixBatch * 1000) / 1000 : remaining"
                >
                    <span v-if="remainingBatches !== null" class="text-7xl font-semibold tabular-nums text-[var(--app-accent-strong)]">{{ ctrans(':batches × :size kg', { batches: remainingBatches, size: mixBatch ?? 0 }) }}</span>
                    <span v-else class="text-7xl font-semibold tabular-nums text-[var(--app-accent-strong)]">{{ packsLabel(remaining, packSize) }}</span>
                    <span class="ml-3 text-xl text-gray-500"><template v-if="unit">{{ unit }} </template>{{ ctrans('to do') }} · {{ packsLabel(session.task.quantity_made, packSize) }} / {{ packsLabel(session.task.quantity_required, packSize) }}</span>
                </button>
                <div v-if="packSize" class="mt-2 text-2xl font-semibold text-amber-700">
                    {{ ctrans('Counted in PACKS of :size (1 pack = :size × :code)', { size: packSize, code: session.task.artefact_code ?? '' }) }}
                </div>
            </div>
            <div v-if="!isQuantityOnly" class="text-right">
                <div class="text-7xl font-mono tabular-nums text-[var(--app-accent-strong)]">{{ elapsed }}</div>
                <div v-if="session.break_minutes" class="text-sm text-gray-500">{{ session.break_minutes }} {{ ctrans('min on break') }}</div>
            </div>
        </div>

        <div v-if="shiftEndQuestion" class="mt-6 rounded-lg border border-gray-300 bg-white p-4">
            <div class="text-2xl font-semibold">{{ ctrans('Did you finish this before the end of your shift?') }}</div>
            <div class="mt-1 text-lg text-gray-600">{{ ctrans('Work stopped :time', { time: pausedAtLabel }) }}</div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <button type="button" class="flex items-center justify-center gap-3 rounded-lg border border-gray-300 bg-white py-4 text-xl font-semibold text-gray-800 hover:bg-gray-50 disabled:opacity-40"
                    :disabled="processing" @click="finishedBeforeShiftEnd = true">
                    <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                    {{ ctrans('Yes, record quantity and close') }}
                </button>
                <button type="button" class="flex items-center justify-center gap-3 rounded-lg border border-gray-300 bg-white py-4 text-xl font-semibold text-gray-800 hover:bg-gray-50 disabled:opacity-40"
                    :disabled="processing" @click="continueAfterShift">
                    <FontAwesomeIcon :icon="faPlay" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
                    {{ ctrans('No, continue working') }}
                </button>
            </div>
        </div>

        <div v-else-if="finishedBeforeShiftEnd" class="mt-6 flex items-center justify-between gap-3 rounded-lg border border-gray-300 bg-white px-4 py-3 text-lg text-gray-700">
            <span>{{ ctrans('Enter what was made. The job closes at :time, when work stopped.', { time: pausedAtLabel }) }}</span>
            <button type="button" class="shrink-0 text-gray-500 underline" @click="finishedBeforeShiftEnd = false">{{ ctrans('Go back') }}</button>
        </div>

        <div v-else-if="session.left_open_after_shift_at && !session.shift_end?.is_resolved" class="mt-6 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-lg text-amber-800">
            <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-1" fixed-width aria-hidden="true" />
            <span>{{ ctrans('This job was left running after the end of the shift (:time). Record what was made and tell your supervisor, who will set the real times.', { time: new Date(session.left_open_after_shift_at).toLocaleString([], { weekday: 'short', hour: '2-digit', minute: '2-digit' }) }) }}</span>
        </div>

        <div v-if="closeError" class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-lg text-red-700">{{ closeError }}</div>

        <div v-if="confirming" class="mt-6 rounded-lg border border-green-300 bg-green-50 p-4">
            <div class="text-2xl font-semibold">{{ ctrans('Are you sure you want to finish :step?', { step: session.task.task_name }) }}</div>
            <div class="mt-2 flex flex-wrap gap-x-6 text-lg text-gray-700">
                <span>{{ ctrans('Step') }}: <b>{{ session.task.task_name }}</b></span>
                <span v-if="!isQuantityOnly">{{ ctrans('Time logged') }}: <b class="tabular-nums">{{ timeLogged }}</b></span>
                <span>{{ ctrans('Quantity made') }}: <b class="tabular-nums">{{ quantityMade }}<template v-if="unit"> {{ unit }}</template></b>
                    <b v-if="packSize" class="text-amber-700"> {{ ctrans('packs of :size = :pieces × :code', { size: packSize, pieces: inPieces(quantityMade ?? 0), code: session.task.artefact_code ?? '' }) }}</b>
                </span>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <button type="button" class="rounded-lg border border-gray-300 bg-white text-gray-700 text-xl font-semibold py-4"
                    @click="confirming = false">
                    {{ ctrans('Cancel / Go back') }}
                </button>
                <button type="button" class="rounded-lg bg-green-600 text-white text-xl font-semibold py-4 disabled:opacity-40"
                    :disabled="processing" @click="confirmDone">
                    {{ ctrans('Confirm & finish') }}
                </button>
            </div>
        </div>

        <div v-else-if="!askOutcome && !shiftEndQuestion" class="mt-6 flex items-end gap-4">
            <div>
                <label class="block text-lg text-gray-600 mb-1">{{ mixBatch ? ctrans('Batches of :size kg made', { size: mixBatch }) : packSize ? ctrans('Packs of :size made', { size: packSize }) : unit ? ctrans('Kg made') : ctrans('Quantity made') }}</label>
                <input
                    v-if="mixBatch"
                    type="number" min="0" inputmode="numeric" step="1"
                    v-model.number="batchesMade"
                    class="w-52 rounded-lg border-gray-300 text-5xl text-center py-4 tabular-nums"
                />
                <input
                    v-else
                    type="number" min="0" inputmode="numeric" :step="packSize ? 1 : 'any'"
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
                :disabled="processing || quantityMade === null || notWholePacks"
                @click="onDone"
            >
                {{ ctrans('DONE') }}
            </button>
        </div>

        <div v-if="packSize && quantityMade !== null && !confirming && !askOutcome" class="mt-4 text-2xl" :class="notWholePacks ? 'text-red-700' : 'text-gray-700'">
            {{ notWholePacks
                ? ctrans('Whole packs only: enter how many full packs of :size, not single pieces', { size: packSize })
                : ctrans(':packs packs of :size = :pieces × :code', { packs: quantityMade, size: packSize, pieces: inPieces(quantityMade), code: session.task.artefact_code ?? '' }) }}
        </div>

        <div v-if="kgInUnits !== null && !confirming && !askOutcome" class="mt-4 text-2xl text-gray-700">
            {{ ctrans(':kg kg of mix = :units × :code', { kg: quantityMade ?? 0, units: kgInUnits, code: session.task.artefact_code ?? '' }) }}
        </div>

        <div v-if="surplus > 0 && !askManager" class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-lg text-amber-800">
            {{ ctrans(':surplus above target, a manager must authorise it', { surplus }) }}
        </div>

        <Dialog v-model:visible="askManager" modal :closable="!processing" :header="rateWarning && !surplus ? ctrans('Manager authorisation required') : ctrans('Manager authorisation required for overproduction')" class="w-[32rem] max-w-[95vw]" @hide="rateWarning = null">
            <div v-if="rateWarning" class="mb-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-center text-xl font-semibold text-amber-800">{{ rateWarning }}</div>
            <div v-if="surplus" class="text-center text-lg text-gray-600">
                {{ ctrans('Target') }} <b>{{ remaining }}</b> · {{ ctrans('Made') }} <b>{{ quantityMade }}</b> · {{ ctrans('Surplus') }} <b class="text-amber-600">+{{ surplus }}</b>
            </div>
            <div v-if="packSize && surplus" class="mt-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-center text-xl font-semibold text-amber-800">
                {{ ctrans('+:surplus packs of :size = :pieces × :code extra, approve?', { surplus, size: packSize, pieces: inPieces(surplus), code: session.task.artefact_code ?? '' }) }}
            </div>

            <div v-if="managerError" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-red-700">{{ managerError }}</div>

            <div v-if="!usePin" class="mt-4">
                <div class="overflow-hidden rounded-lg bg-black aspect-[4/3]">
                    <QrcodeStream :key="facingMode" :constraints="{ facingMode }" :formats="['qr_code']" :paused="processing" @detect="onBadgeDetected" @error="onCameraError" />
                </div>
                <div class="mt-2 text-center text-gray-600">{{ ctrans('Scan the manager or supervisor badge') }}</div>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <button type="button" class="rounded-lg border border-gray-300 bg-white py-3 text-lg text-gray-700" @click="facingMode = facingMode === 'environment' ? 'user' : 'environment'">
                        {{ ctrans('Switch camera') }}
                    </button>
                    <button type="button" class="rounded-lg border border-[var(--app-accent-muted)] bg-white py-3 text-lg text-[var(--app-accent)]" @click="usePin = true">
                        {{ ctrans('Use manager PIN') }}
                    </button>
                </div>
            </div>

            <form v-else ref="pinField" class="mt-4" @submit.prevent="managerPin && closeSession(pendingOutcome, { manager_code: managerPin, manager_method: 'pin' })">
                <div v-if="cameraError" class="mb-2 text-sm text-gray-500">{{ ctrans('Camera not available') }}</div>
                <Password v-model="managerPin" :feedback="false" toggleMask fluid
                    :inputProps="{ autocapitalize: 'characters', autocomplete: 'off' }"
                    inputClass="text-center text-3xl tracking-widest" />
                <button type="submit" class="mt-3 w-full rounded-lg bg-[var(--app-accent)] py-4 text-xl font-semibold text-[var(--app-accent-text)] disabled:opacity-40"
                    :disabled="processing || !managerPin">
                    {{ ctrans('Verify PIN') }}
                </button>
                <button type="button" class="mt-2 w-full rounded-lg border border-gray-300 bg-white py-3 text-lg text-gray-700" @click="usePin = false; cameraError = null">
                    {{ ctrans('Back to QR scan') }}
                </button>
            </form>
        </Dialog>

        <div v-if="askOutcome" class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4">
            <div class="flex items-baseline gap-6">
                <div><span class="text-4xl font-semibold tabular-nums text-green-700">{{ quantityMade }}</span> <span class="text-lg text-gray-600">{{ ctrans('done') }}</span></div>
                <div><span class="text-4xl font-semibold tabular-nums text-amber-700">{{ Math.max(0, remaining - (quantityMade ?? 0)) }}</span> <span class="text-lg text-gray-600">{{ ctrans('to do') }}</span></div>
            </div>
            <div v-if="confirmingMove" class="mt-3">
                <div class="text-2xl font-semibold">
                    {{ session.carry
                        ? ctrans('Close job :reference at :made and move the remaining :left to new job :new?', { reference: session.task.job_order_reference, made: closedAt, left: movedOn, new: session.carry.reference })
                        : ctrans('Close these jobs at what was made and move what is left of each to a new job?') }}
                </div>
                <div class="mt-1 text-lg text-gray-700">{{ ctrans('This job is closed and cannot be continued. The new job gets its own batch code. If it is the same mix, go back and pause instead.') }}</div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <button type="button" class="rounded-lg border border-gray-300 bg-white text-gray-700 text-xl font-semibold py-4"
                        @click="confirmingMove = false">
                        <FontAwesomeIcon :icon="['fal', 'times']" fixed-width class="text-red-500" aria-hidden="true" />
                        {{ ctrans('Back') }}
                    </button>
                    <button type="button" class="rounded-lg border border-gray-300 bg-white text-gray-700 text-xl font-semibold py-4 disabled:opacity-40"
                        :disabled="processing" @click="closeSession('carry_over')">
                        <FontAwesomeIcon :icon="['fal', 'exclamation-triangle']" fixed-width class="text-amber-500" aria-hidden="true" />
                        {{ ctrans('Yes, close and move') }}
                    </button>
                </div>
            </div>
            <div v-else class="mt-3 grid gap-3 sm:grid-cols-2">
                <button type="button" class="rounded-lg border border-gray-300 bg-white text-gray-700 text-xl font-semibold py-4 px-3 disabled:opacity-40"
                    :disabled="processing" @click="closeSession()">
                    <FontAwesomeIcon :icon="['fal', 'pause']" fixed-width class="text-amber-500" aria-hidden="true" />
                    {{ ctrans('Pause') }}
                    <div class="text-sm font-normal text-gray-500">{{ ctrans('Same mix: carry on later with job :reference, same batch code', { reference: session.task.job_order_reference }) }}</div>
                </button>
                <button type="button" class="rounded-lg border border-gray-300 bg-white text-gray-700 text-xl font-semibold py-4 px-3 disabled:opacity-40"
                    :disabled="processing || !canMove" @click="confirmingMove = true">
                    <FontAwesomeIcon :icon="['fal', 'share']" fixed-width class="text-[var(--app-accent)]" aria-hidden="true" />
                    {{ session.carry
                        ? ctrans('Close at :made and move remaining :left to new job :new', { made: closedAt, left: movedOn, new: session.carry.reference })
                        : ctrans('Close and move what is left to new jobs') }}
                    <div class="text-sm font-normal text-gray-500">{{ canMove ? ctrans('New mix: the rest gets a new batch code') : ctrans('Nothing finished yet to close this job with, pause instead') }}</div>
                </button>
                <button type="button" class="rounded-lg border border-gray-300 bg-white text-gray-700 text-xl font-semibold py-4 px-3 disabled:opacity-40"
                    :disabled="processing" @click="closeSession('complete')">
                    <FontAwesomeIcon :icon="['fal', 'check']" fixed-width class="text-green-600" aria-hidden="true" />
                    {{ ctrans('Job finished') }}
                    <div class="text-sm font-normal text-gray-500">{{ ctrans('Close with what was made, nothing more to make') }}</div>
                </button>
                <button type="button" class="rounded-lg border border-gray-300 bg-white text-gray-700 text-xl font-semibold py-4 px-3"
                    @click="askOutcome = false">
                    <FontAwesomeIcon :icon="['fal', 'times']" fixed-width class="text-red-500" aria-hidden="true" />
                    {{ ctrans('Back') }}
                </button>
            </div>
        </div>

        <div v-if="session.band_feedback && !isQuantityOnly" class="mt-6 border-t border-[var(--app-accent-muted)] pt-4">
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
