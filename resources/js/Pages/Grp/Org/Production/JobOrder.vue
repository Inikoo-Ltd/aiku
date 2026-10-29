<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 09 Aug 2026 10:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { ref, computed, watch } from "vue"
import axios from "axios"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { mixBatchesLabel } from "@/Composables/mixBatches"
import Modal from "@/Components/Utils/Modal.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSortShapesDown, faPlus, faTrashAlt, faUsers, faLink } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { PageHeadingTypes } from "@/types/PageHeading"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import { faPauseCircle, faPlayCircle, faCheck, faTimes, faUndo, faBan } from "@fal"

library.add(faSortShapesDown, faPlus, faTrashAlt, faUsers, faLink, faPauseCircle, faPlayCircle, faCheck, faTimes, faUndo, faBan)

interface Hold {
    reason: string
    kind: string
    kind_label: string | null
    by: string | null
    at: string
    cleared_by?: string | null
    cleared_at?: string
}

interface Cancellation {
    mode: "backlog" | "cancel"
    kind: string
    kind_label: string | null
    reason: string | null
    by: string | null
    at: string
    units_kept: number
    units_returned: number
}

interface ItemTask {
    id: number
    position: number
    task_name: string
    state: string
    quantity_required: number
    quantity_made: number
    quantity_rejected: number
    unit: string | null
    mix_batch_kg: number | null
    combined_with: string | null
    owners: { id: number, sub_job: string | null, owner_id: number | null, owner: string | null, only_for: number[], can_assign: boolean }[]
}

interface SubJob {
    id: number
    reference: string
    employee_id: number | null
    artisan: string | null
    quantity: number
    quantity_made: number
    quantity_target: number
    state: "assigned" | "in_progress" | "complete"
    seconds: number
    reward: string | null
    can_remove: boolean
}

interface Item {
    id: number
    artefact_code: string
    artefact_name: string
    quantity: number
    demand_skos: number | null
    suggested_quantity: number | null
    update_route: null | { name: string, parameters: object }
    split_route: null | { name: string, parameters: object }
    produced_quantity: number
    waiting_for: { code: string, needed: number, on_hand: number }[]
    tasks: ItemTask[]
    sub_jobs: SubJob[]
}

const selectPt = { label: { class: "!py-0.5 !text-xs" } }

function assignStep(stepId: number, employeeId: number | null) {
    router.patch(route("grp.models.job-order-item-task.assign", { jobOrderItemTask: stepId }), { employee_id: employeeId ?? null }, { preserveScroll: true })
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    job_order: {
        id: number
        reference: string
        state: string
        state_label: string
        date: string | null
        public_notes: string | null
        employee_id: number | null
        artisan: string | null
        overproductions: { step: string, artefact_code: string, quantity: number, pack_size?: number | null, made_by: string, manager: string, method: 'qr' | 'pin', at: string }[]
        corrections: { step: string, artefact_code: string, pack_size: number | null, from: number, to: number, by: string | null, at: string }[]
        non_productive: { activity: string, hours: number, pay: string | null }[]
        hold: Hold | null
        holds: Hold[]
        cancellation: Cancellation | null
    }
    can_see_pay: boolean
    hold_route: null | { name: string, parameters: object }
    hold_kind_options: { value: string, label: string }[]
    cancel_route: null | { name: string, parameters: object }
    cancel_kind_options: { value: string, label: string }[]
    artisan_options: { id: number, name: string }[]
    update_route: null | { name: string, parameters: object }
    items: Item[]
    artefact_options: { id: number, code: string, name: string, has_recipe: boolean, recommended_batch_size: number | null }[]
    add_item_route: null | { name: string, parameters: object }
    confirm_route: null | { name: string, parameters: object }
    pdf_route: { name: string, parameters: object }
    receive_route: null | { name: string, parameters: object }
    is_trial_run: boolean
    locations_fetch_route: null | { name: string, parameters: object }
}>()

function setArtisan(value: string) {
    if (!props.update_route) return
    router.patch(route(props.update_route.name, props.update_route.parameters), { employee_id: value === '' ? null : Number(value) }, { preserveScroll: true })
}

function confirmJobOrder() {
    if (!props.confirm_route) return
    processing.value = true
    router.patch(
        route(props.confirm_route.name, props.confirm_route.parameters),
        {},
        { preserveScroll: true, onFinish: () => processing.value = false }
    )
}

const newArtefactId = ref<number | null>(null)
const newQuantity = ref<number | null>(null)
const processing = ref(false)

const selectedArtefactOption = computed(() =>
    props.artefact_options.find(option => option.id === newArtefactId.value) ?? null
)

watch(newArtefactId, () => {
    if (!newQuantity.value && selectedArtefactOption.value?.recommended_batch_size) {
        newQuantity.value = selectedArtefactOption.value.recommended_batch_size
    }
})

function addItem() {
    if (!props.add_item_route || !newArtefactId.value || !newQuantity.value) return
    processing.value = true
    router.post(
        route(props.add_item_route.name, props.add_item_route.parameters),
        {
            artefact_id: newArtefactId.value,
            quantity: newQuantity.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false
                newArtefactId.value = null
                newQuantity.value = null
            },
        }
    )
}

const editingItemId = ref<number | null>(null)
const editedQuantity = ref<number | null>(null)

function startEditingQuantity(item: { id: number, quantity: number }) {
    editingItemId.value = item.id
    editedQuantity.value = item.quantity
}

const quantityErrors = ref<Record<number, string>>({})

function saveQuantity(item: { id: number, update_route: null | { name: string, parameters: object } }, quantity: number | null) {
    if (!item.update_route || !quantity || quantity < 1) return
    delete quantityErrors.value[item.id]
    router.patch(
        route(item.update_route.name, item.update_route.parameters),
        { quantity: quantity },
        {
            preserveScroll: true,
            onSuccess: () => editingItemId.value = null,
            onError: (errors) => quantityErrors.value[item.id] = Object.values(errors).join(' '),
        }
    )
}

function progressPercent(task: ItemTask) {
    if (!task.quantity_required) return 0
    return Math.min(100, Math.round(task.quantity_made / task.quantity_required * 100))
}

interface Assignment {
    id: number | null
    employee_id: number | null
    quantity: number | null
    can_remove: boolean
}

const splittingItem = ref<Item | null>(null)
const assignments = ref<Assignment[]>([])
const splitErrors = ref<string[]>([])

const allocatedQuantity = computed(() => assignments.value.reduce((total, assignment) => total + (assignment.quantity || 0), 0))
const isBalanced = computed(() => splittingItem.value !== null && allocatedQuantity.value === splittingItem.value.quantity)
const canSaveAssignments = computed(() =>
    isBalanced.value
    && assignments.value.every(assignment => assignment.employee_id && assignment.quantity && assignment.quantity > 0)
    && new Set(assignments.value.map(assignment => assignment.employee_id)).size === assignments.value.length
)

function openSplit(item: Item) {
    splittingItem.value = item
    splitErrors.value = []
    assignments.value = item.sub_jobs.length
        ? item.sub_jobs.map(subJob => ({ id: subJob.id, employee_id: subJob.employee_id, quantity: subJob.quantity, can_remove: subJob.can_remove }))
        : [{ id: item.id, employee_id: props.job_order.employee_id, quantity: item.quantity, can_remove: false }]
}

function addAssignment() {
    const missing = splittingItem.value ? splittingItem.value.quantity - allocatedQuantity.value : 0
    assignments.value.push({ id: null, employee_id: null, quantity: missing > 0 ? missing : null, can_remove: true })
}

function saveAssignments() {
    if (!splittingItem.value?.split_route || !canSaveAssignments.value) return
    processing.value = true
    router.patch(
        route(splittingItem.value.split_route.name, splittingItem.value.split_route.parameters),
        { assignments: assignments.value.map(({ id, employee_id, quantity }) => ({ id, employee_id, quantity })) },
        {
            preserveScroll: true,
            onSuccess: () => splittingItem.value = null,
            onError: (errors) => splitErrors.value = Object.values(errors),
            onFinish: () => processing.value = false,
        }
    )
}

function lineProgress(item: Item) {
    const lastTask = item.tasks[item.tasks.length - 1]
    if (!lastTask?.quantity_required) return 0
    return Math.min(100, Math.round(lastTask.quantity_made / lastTask.quantity_required * 100))
}

function subJobProgress(subJob: SubJob) {
    if (!subJob.quantity_target) return 0
    return Math.min(100, Math.round(subJob.quantity_made / subJob.quantity_target * 100))
}

function formatDuration(seconds: number) {
    const hours = Math.floor(seconds / 3600)
    const minutes = Math.floor(seconds % 3600 / 60)
    return hours ? `${hours}h ${minutes}m` : `${minutes}m`
}

const subJobStateLabels: Record<SubJob["state"], string> = {
    assigned: ctrans("Assigned"),
    in_progress: ctrans("In progress"),
    complete: ctrans("Completed"),
}

const subJobStateClasses: Record<SubJob["state"], string> = {
    assigned: "bg-[--app-accent-soft] text-[--app-accent-strong]",
    in_progress: "bg-amber-50 text-amber-700",
    complete: "bg-green-50 text-green-700",
}

const locationQuery = ref("")
const locationResults = ref<{ id: number, code: string }[]>([])
const selectedLocation = ref<{ id: number, code: string } | null>(null)
const searchingLocations = ref(false)

async function searchLocations() {
    if (!props.locations_fetch_route || !locationQuery.value) {
        locationResults.value = []
        return
    }
    searchingLocations.value = true
    try {
        const response = await axios.get(route(props.locations_fetch_route.name, props.locations_fetch_route.parameters), {
            params: { "filter[global]": locationQuery.value },
        })
        locationResults.value = response.data.data ?? []
    } finally {
        searchingLocations.value = false
    }
}

function pickLocation(location: { id: number, code: string }) {
    selectedLocation.value = location
    locationResults.value = []
    locationQuery.value = location.code
}

const isHoldDialogOpen = ref(false)
const holdKind = ref("other")
const holdReason = ref("")
const holdErrors = ref<string[]>([])

function openHoldDialog() {
    holdKind.value = "other"
    holdReason.value = ""
    holdErrors.value = []
    isHoldDialogOpen.value = true
}

function setHold(onHold: boolean) {
    if (!props.hold_route) return
    processing.value = true
    router.patch(
        route(props.hold_route.name, props.hold_route.parameters),
        onHold ? { on_hold: true, kind: holdKind.value, reason: holdReason.value } : { on_hold: false },
        {
            preserveScroll: true,
            onSuccess: () => isHoldDialogOpen.value = false,
            onError: (errors) => holdErrors.value = Object.values(errors),
            onFinish: () => processing.value = false,
        }
    )
}

const cancelMode = ref<"backlog" | "cancel" | null>(null)
const cancelKind = ref("missing_label")
const cancelReason = ref("")
const cancelErrors = ref<string[]>([])
const unitsMade = computed(() => props.items.reduce((total, item) => total + item.produced_quantity, 0))

function openCancelDialog(mode: "backlog" | "cancel") {
    cancelMode.value = mode
    cancelKind.value = "missing_label"
    cancelReason.value = ""
    cancelErrors.value = []
}

function cancelJobOrder() {
    if (!props.cancel_route || !cancelMode.value) return
    processing.value = true
    router.patch(
        route(props.cancel_route.name, props.cancel_route.parameters),
        { mode: cancelMode.value, kind: cancelKind.value, reason: cancelReason.value },
        {
            preserveScroll: true,
            onSuccess: () => cancelMode.value = null,
            onError: (errors) => cancelErrors.value = Object.values(errors),
            onFinish: () => processing.value = false,
        }
    )
}

const receiveError = ref<string | null>(null)

function receiveIntoStock() {
    if (!props.receive_route || (!props.is_trial_run && !selectedLocation.value)) return
    processing.value = true
    receiveError.value = null
    router.patch(
        route(props.receive_route.name, props.receive_route.parameters),
        props.is_trial_run ? {} : { location_id: selectedLocation.value?.id },
        {
            preserveScroll: true,
            onError: (errors) => receiveError.value = Object.values(errors).join(' '),
            onFinish: () => processing.value = false,
        }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-4 max-w-4xl">
        <div class="flex items-center gap-4 text-sm text-gray-600 mb-6">
            <span class="rounded-full bg-gray-100 border border-gray-200 px-3 py-1 font-medium">{{ job_order.state_label }}</span>
            <span v-if="job_order.date">{{ useFormatTime(job_order.date) }}</span>
            <label class="flex items-center gap-1.5">
                <span class="text-gray-400">{{ ctrans('For') }}</span>
                <select v-if="update_route" :value="job_order.employee_id ?? ''" class="rounded border-gray-300 py-0.5 text-sm" @change="setArtisan(($event.target as HTMLSelectElement).value)">
                    <option value="">{{ ctrans('Anyone') }}</option>
                    <option v-for="option in artisan_options" :key="option.id" :value="option.id">{{ option.name }}</option>
                </select>
                <span v-else class="font-medium">{{ job_order.artisan ?? ctrans('Anyone') }}</span>
            </label>
            <span v-if="job_order.public_notes" class="truncate">{{ job_order.public_notes }}</span>
            <a
                :href="route(pdf_route.name, pdf_route.parameters)"
                target="_blank"
                class="ml-auto rounded border border-gray-300 bg-white text-gray-700 text-sm font-semibold px-4 py-2 hover:bg-gray-50"
            >
                {{ ctrans('Print job list') }}
            </a>
            <Button
                v-if="hold_route && !job_order.hold"
                type="tertiary"
                :label="ctrans('Put on hold')"
                :disabled="processing"
                @click="openHoldDialog">
                <template #icon><FontAwesomeIcon icon="fal fa-pause-circle" class="text-amber-500" fixed-width aria-hidden="true" /></template>
            </Button>
            <Button
                v-if="cancel_route"
                type="tertiary"
                :label="ctrans('Back to backlog')"
                :disabled="processing"
                @click="openCancelDialog('backlog')">
                <template #icon><FontAwesomeIcon icon="fal fa-undo" class="text-amber-500" fixed-width aria-hidden="true" /></template>
            </Button>
            <Button
                v-if="cancel_route"
                type="tertiary"
                :label="ctrans('Cancel job')"
                :disabled="processing"
                @click="openCancelDialog('cancel')">
                <template #icon><FontAwesomeIcon icon="fal fa-ban" class="text-red-500" fixed-width aria-hidden="true" /></template>
            </Button>
            <button
                v-if="confirm_route"
                type="button"
                class="rounded bg-green-600 text-white text-sm font-semibold px-4 py-2 disabled:opacity-40"
                :disabled="processing || !items.length"
                @click="confirmJobOrder"
            >
                {{ ctrans('Release to floor') }}
            </button>
        </div>
        <div v-if="job_order.cancellation" class="mb-6 flex items-start gap-3 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-gray-700" role="status">
            <FontAwesomeIcon :icon="job_order.cancellation.mode === 'backlog' ? 'fal fa-undo' : 'fal fa-ban'" class="mt-0.5 text-red-500" fixed-width aria-hidden="true" />
            <div class="flex-1">
                <div class="font-semibold text-red-800">
                    {{ job_order.cancellation.mode === 'backlog' ? ctrans('Sent back to the backlog') : ctrans('Cancelled') }}<template v-if="job_order.cancellation.kind_label && job_order.cancellation.kind !== 'other'">: {{ job_order.cancellation.kind_label }}</template>
                </div>
                <div v-if="job_order.cancellation.reason">{{ job_order.cancellation.reason }}</div>
                <div v-if="job_order.cancellation.units_kept">{{ ctrans(':kept made units stay on this job, :returned went back to To Produce', { kept: job_order.cancellation.units_kept, returned: job_order.cancellation.units_returned }) }}</div>
                <div class="text-xs text-gray-500">{{ job_order.cancellation.by ?? '—' }} · {{ useFormatTime(job_order.cancellation.at, { formatTime: 'hm' }) }}</div>
            </div>
        </div>
        <div v-if="job_order.hold" class="mb-6 flex items-start gap-3 rounded border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-gray-700" role="status">
            <FontAwesomeIcon icon="fal fa-pause-circle" class="mt-0.5 text-yellow-600" fixed-width aria-hidden="true" />
            <div class="flex-1">
                <div class="font-semibold text-yellow-800">{{ ctrans('On hold') }}<template v-if="job_order.hold.kind_label && job_order.hold.kind !== 'other'">: {{ job_order.hold.kind_label }}</template></div>
                <div>{{ job_order.hold.reason }}</div>
                <div class="text-xs text-gray-500">{{ job_order.hold.by ?? '—' }} · {{ useFormatTime(job_order.hold.at, { formatTime: 'hm' }) }} · {{ ctrans('Receiving into stock is blocked until the hold is cleared') }}</div>
            </div>
            <Button v-if="hold_route" type="tertiary" size="xs" :label="ctrans('Clear hold')" :disabled="processing" @click="setHold(false)">
                <template #icon><FontAwesomeIcon icon="fal fa-play-circle" class="text-green-600" fixed-width aria-hidden="true" /></template>
            </Button>
        </div>
        <div v-if="job_order.holds?.length" class="mb-6 rounded border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
            <div class="font-semibold mb-1">{{ ctrans('Hold history') }}</div>
            <div v-for="(entry, index) in job_order.holds" :key="index">
                {{ ctrans(':reason, put on hold by :by, cleared by :cleared_by', { reason: entry.reason, by: entry.by ?? '—', cleared_by: entry.cleared_by ?? '—' }) }}
                <span class="text-gray-500">· {{ useFormatTime(entry.at, { formatTime: 'hm' }) }} → {{ useFormatTime(entry.cleared_at, { formatTime: 'hm' }) }}</span>
            </div>
        </div>
        <div v-if="job_order.overproductions?.length" class="mb-6 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-gray-700">
            <div class="font-semibold text-amber-800 mb-1">{{ ctrans('Overproduction') }}</div>
            <div v-for="(entry, index) in job_order.overproductions" :key="index">
                {{ ctrans('+:quantity :code on :step by :maker, authorised by :manager via :method', {
                    quantity: entry.pack_size ? ctrans(':packs packs of :size (:pieces) of', { packs: entry.quantity, size: entry.pack_size, pieces: Math.round(entry.quantity * entry.pack_size) }) : entry.quantity,
                    code: entry.artefact_code,
                    step: entry.step,
                    maker: entry.made_by,
                    manager: entry.manager,
                    method: entry.method === 'qr' ? ctrans('QR scan') : ctrans('PIN'),
                }) }}
                <span class="text-gray-500">· {{ useFormatTime(entry.at, { formatTime: 'hm' }) }}</span>
            </div>
        </div>
        <div v-if="job_order.corrections?.length" class="mb-6 rounded border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
            <div class="font-semibold mb-1">{{ ctrans('Corrected entries') }}</div>
            <div v-for="(entry, index) in job_order.corrections" :key="index">
                {{ ctrans(':code on :step corrected from :from to :to by :by', { code: entry.artefact_code, step: entry.step, from: entry.from, to: entry.pack_size ? ctrans(':packs packs of :size', { packs: entry.to, size: entry.pack_size }) : entry.to, by: entry.by ?? '—' }) }}
                <span class="text-gray-500">· {{ useFormatTime(entry.at, { formatTime: 'hm' }) }}</span>
            </div>
        </div>
        <div v-if="job_order.non_productive?.length" class="mb-6 rounded border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
            <div class="font-semibold mb-1">{{ ctrans('Preparation and cleaning for this job') }}</div>
            <div v-for="entry in job_order.non_productive" :key="entry.activity" class="flex gap-6 tabular-nums">
                <span class="w-32">{{ entry.activity }}</span>
                <span>{{ entry.hours }} h</span>
                <span v-if="can_see_pay">{{ entry.pay ?? '—' }}</span>
            </div>
        </div>
        <div v-if="confirm_route" class="mb-6 -mt-3 text-sm text-amber-600">
            {{ ctrans('Draft — workers cannot see these tasks until the order is released to the floor') }}
        </div>

        <div v-if="!items.length" class="text-gray-400 text-center py-10 border border-dashed border-gray-200 rounded-lg mb-6">
            {{ ctrans('No items yet. Add an artefact to manufacture.') }}
        </div>

        <div v-for="item in items" :key="item.id" class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex items-baseline justify-between">
                <div class="font-semibold">
                    {{ item.artefact_code }}
                    <span class="text-gray-500 font-normal ml-2">{{ item.artefact_name }}</span>
                </div>
                <form v-if="editingItemId === item.id" class="flex items-center gap-2" @submit.prevent="saveQuantity(item, editedQuantity)">
                    <input v-model.number="editedQuantity" type="number" min="1" step="1" class="w-24 rounded border-gray-300 text-sm tabular-nums" :aria-label="ctrans('Quantity')" />
                    <button type="submit" class="text-sm font-medium text-[--app-accent]">{{ ctrans('Save') }}</button>
                    <button type="button" class="text-sm text-gray-500" @click="editingItemId = null">{{ ctrans('Cancel') }}</button>
                </form>
                <div v-else class="tabular-nums text-gray-700">
                    × {{ item.quantity }}
                    <button v-if="item.update_route" type="button" class="ml-2 text-sm text-[--app-accent]" @click="startEditingQuantity(item)">{{ ctrans('Change') }}</button>
                    <button v-if="item.split_route" type="button" class="ml-3 text-sm text-[--app-accent]" @click="openSplit(item)">
                        <FontAwesomeIcon :icon="['fal', 'users']" fixed-width class="mr-0.5" />
                        {{ item.sub_jobs.length ? ctrans('Change artisans') : ctrans('Split / Assign multiple artisans') }}
                    </button>
                </div>
            </div>

            <div v-if="item.update_route && item.suggested_quantity && item.suggested_quantity !== item.quantity" class="mt-2 text-sm text-amber-600">
                {{ ctrans('Raised with a different batch size') }}: {{ item.demand_skos }} {{ ctrans('SKOs asked for') }} &rarr; {{ item.suggested_quantity }}
                <button type="button" class="ml-2 font-medium underline" @click="saveQuantity(item, item.suggested_quantity)">{{ ctrans('Set to') }} {{ item.suggested_quantity }}</button>
            </div>
            <div v-if="quantityErrors[item.id]" class="mt-1 text-right text-xs text-red-600">{{ quantityErrors[item.id] }}</div>

            <div v-if="item.waiting_for.length" class="mt-2 text-sm text-amber-600">
                {{ ctrans('Waiting for mix') }}: <span v-for="mix in item.waiting_for" :key="mix.code" class="mr-2">{{ mix.code }} ({{ mix.on_hand }} / {{ mix.needed }})</span>
            </div>
            <div v-if="!item.tasks.length" class="mt-2 text-sm text-amber-600">
                {{ ctrans('This artefact has no recipe, no tasks were generated') }}
            </div>
            <div v-for="task in item.tasks" :key="task.id" class="mt-3">
                <div class="flex items-baseline justify-between text-sm">
                    <div>
                        <span class="text-gray-400 mr-1">{{ task.position }}.</span>
                        {{ task.task_name }}
                        <span v-if="task.state == 'done'" class="ml-2 text-green-600 font-medium">{{ ctrans('Done') }}</span>
                        <span v-else-if="task.state == 'in_progress'" class="ml-2 text-amber-600 font-medium">{{ ctrans('In progress') }}</span>
                        <span v-if="task.combined_with" class="ml-2 text-xs text-gray-500">
                            <FontAwesomeIcon :icon="['fal', 'link']" fixed-width aria-hidden="true" />
                            {{ ctrans('One batch with :lines', { lines: task.combined_with }) }}
                        </span>
                    </div>
                    <div class="tabular-nums text-gray-600">
                        {{ task.quantity_made }} / {{ task.quantity_required }}<template v-if="task.unit"> {{ task.unit }}</template>
                        <span v-if="mixBatchesLabel(task.quantity_required, task.mix_batch_kg)" class="ml-1 text-gray-500">· {{ mixBatchesLabel(task.quantity_required, task.mix_batch_kg) }}</span>
                        <span v-if="task.quantity_rejected" class="text-red-500 ml-1">({{ task.quantity_rejected }} {{ ctrans('rejected') }})</span>
                    </div>
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
                    <div v-for="owner in task.owners" :key="owner.id" class="flex items-center gap-2">
                        <span v-if="owner.sub_job" class="whitespace-nowrap">{{ owner.sub_job }}</span>
                        <Select
                            v-if="owner.can_assign"
                            :modelValue="owner.owner_id"
                            :options="owner.only_for.length ? artisan_options.filter((option) => owner.only_for.includes(option.id)) : artisan_options"
                            optionLabel="name"
                            optionValue="id"
                            filter
                            showClear
                            :placeholder="owner.owner ?? ctrans('Nobody')"
                            class="w-44"
                            :class="{ '!border-amber-400': !owner.owner }"
                            :pt="selectPt"
                            :aria-label="ctrans('Operator of :step', { step: task.task_name })"
                            @update:modelValue="(employeeId: number | null) => assignStep(owner.id, employeeId)" />
                        <span v-else :class="owner.owner ? 'text-gray-700' : 'text-gray-400'">{{ owner.owner ?? ctrans('Nobody') }}</span>
                    </div>
                </div>
                <div class="mt-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div
                        class="h-full rounded-full"
                        :class="task.state == 'done' ? 'bg-green-500' : 'bg-[--app-accent]'"
                        :style="{ width: progressPercent(task) + '%' }"
                    />
                </div>
            </div>

            <div v-if="item.sub_jobs.length" class="mt-4">
                <div class="flex items-baseline justify-between text-sm font-semibold mb-2">
                    <span>{{ ctrans('Assigned sub-jobs') }}</span>
                    <span class="tabular-nums font-normal text-gray-600">{{ item.produced_quantity }} / {{ item.quantity }} ({{ lineProgress(item) }}%)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs text-gray-500">
                            <tr>
                                <th class="py-1 pr-3 font-medium">{{ ctrans('Sub-job') }}</th>
                                <th class="py-1 pr-3 font-medium">{{ ctrans('Artisan') }}</th>
                                <th class="py-1 pr-3 font-medium text-right">{{ ctrans('Target') }}</th>
                                <th class="py-1 pr-3 font-medium">{{ ctrans('Completed') }}</th>
                                <th class="py-1 pr-3 font-medium">{{ ctrans('Status') }}</th>
                                <th class="py-1 pr-3 font-medium text-right">{{ ctrans('Time') }}</th>
                                <th v-if="can_see_pay" class="py-1 font-medium text-right">{{ ctrans('Reward') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="subJob in item.sub_jobs" :key="subJob.id">
                                <td class="py-2 pr-3 whitespace-nowrap">{{ subJob.reference }}</td>
                                <td class="py-2 pr-3">{{ subJob.artisan ?? ctrans('Anyone') }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ subJob.quantity_target }}</td>
                                <td class="py-2 pr-3 min-w-32">
                                    <div class="tabular-nums">{{ subJob.quantity_made }} / {{ subJob.quantity_target }}</div>
                                    <div class="mt-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                        <div class="h-full rounded-full bg-green-500" :style="{ width: subJobProgress(subJob) + '%' }" />
                                    </div>
                                </td>
                                <td class="py-2 pr-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap" :class="subJobStateClasses[subJob.state]">{{ subJobStateLabels[subJob.state] }}</span>
                                </td>
                                <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ formatDuration(subJob.seconds) }}</td>
                                <td v-if="can_see_pay" class="py-2 text-right tabular-nums">{{ subJob.reward }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :isOpen="splittingItem !== null" width="w-full max-w-lg" closeButton @onClose="splittingItem = null">
            <div v-if="splittingItem">
                <div class="text-lg font-semibold">{{ ctrans('Split / Assign multiple artisans') }}</div>
                <div class="text-sm text-gray-500 mt-1">{{ splittingItem.artefact_code }} — {{ ctrans('Total target for this line') }}</div>
                <div class="text-2xl font-semibold tabular-nums">{{ ctransChoice(':count unit|:count units', splittingItem.quantity) }}</div>

                <div class="mt-4 grid grid-cols-[1fr_7rem_2rem] gap-2 items-center text-sm">
                    <div class="text-xs text-gray-500">{{ ctrans('Artisan') }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans('Target (units)') }}</div>
                    <div />
                    <template v-for="(assignment, index) in assignments" :key="index">
                        <select v-model="assignment.employee_id" class="rounded border-gray-300 text-sm" :aria-label="ctrans('Artisan')">
                            <option :value="null" disabled>{{ ctrans('Select artisan') }}</option>
                            <option v-for="option in artisan_options" :key="option.id" :value="option.id">{{ option.name }}</option>
                        </select>
                        <input v-model.number="assignment.quantity" type="number" min="1" step="1" class="rounded border-gray-300 text-sm tabular-nums" :aria-label="ctrans('Target (units)')" />
                        <button
                            v-if="assignment.can_remove"
                            type="button"
                            class="text-gray-400 hover:text-red-500"
                            :aria-label="ctrans('Remove')"
                            @click="assignments.splice(index, 1)"
                        >
                            <FontAwesomeIcon :icon="['fal', 'trash-alt']" fixed-width />
                        </button>
                        <div v-else />
                    </template>
                </div>

                <button type="button" class="mt-3 rounded border border-[--app-accent-muted] text-[--app-accent] text-sm px-3 py-1.5" @click="addAssignment">
                    <FontAwesomeIcon :icon="['fal', 'plus']" fixed-width class="mr-1" />
                    {{ ctrans('Add artisan') }}
                </button>

                <div class="mt-4 rounded px-3 py-2 text-sm tabular-nums" :class="isBalanced ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'">
                    {{ ctrans('Allocated') }}: {{ allocatedQuantity }} / {{ splittingItem.quantity }}
                    — {{ isBalanced ? ctrans('balanced') : ctrans('must add up to the line total') }}
                </div>
                <div v-for="error in splitErrors" :key="error" class="mt-2 text-sm text-red-600">{{ error }}</div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded border border-gray-300 text-sm px-4 py-2" @click="splittingItem = null">{{ ctrans('Cancel') }}</button>
                    <button
                        type="button"
                        class="rounded bg-[--app-accent] text-[--app-accent-text] text-sm font-semibold px-4 py-2 disabled:opacity-40"
                        :disabled="!canSaveAssignments || processing"
                        @click="saveAssignments"
                    >
                        {{ ctrans('Save assignments') }}
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :isOpen="isHoldDialogOpen" width="w-full max-w-md" closeButton @onClose="isHoldDialogOpen = false">
            <form class="space-y-3" @submit.prevent="setHold(true)">
                <div class="text-lg font-semibold">{{ ctrans('Put :reference on hold', { reference: job_order.reference }) }}</div>
                <div class="text-sm text-gray-500">{{ ctrans('Work carries on as normal. Only receiving into stock is blocked until the hold is cleared.') }}</div>
                <SegmentedToggle v-model="holdKind" :options="hold_kind_options" :ariaLabel="ctrans('Why')" />
                <InputText v-model="holdReason" class="w-full" maxlength="255" autofocus :placeholder="ctrans('e.g. Labels out of stock, 468 to label when delivered')" :aria-label="ctrans('Reason')" />
                <div v-for="error in holdErrors" :key="error" class="text-sm text-red-600">{{ error }}</div>
                <div class="flex justify-end gap-2">
                    <Button type="tertiary" :label="ctrans('Cancel')" @click="isHoldDialogOpen = false">
                        <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="tertiary" :label="ctrans('Put on hold')" :disabled="!holdReason.trim() || processing" @click="setHold(true)">
                        <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </form>
        </Modal>

        <Modal :isOpen="!!cancelMode" width="w-full max-w-md" closeButton @onClose="cancelMode = null">
            <form class="space-y-3" @submit.prevent="cancelJobOrder">
                <div class="text-lg font-semibold">
                    {{ cancelMode === 'backlog' ? ctrans('Send :reference back to the backlog', { reference: job_order.reference }) : ctrans('Cancel :reference', { reference: job_order.reference }) }}
                </div>
                <div class="text-sm text-gray-500">
                    <template v-if="unitsMade">{{ ctrans('The :count units already made stay on this job to be received. The rest is taken off the artisan and the Manufacture Floor and goes back to To Produce.', { count: unitsMade }) }}</template>
                    <template v-else>{{ ctrans('The job is taken off the artisan and the Manufacture Floor and closed. What it was making goes back to To Produce, to be planned again.') }}</template>
                </div>
                <SegmentedToggle v-model="cancelKind" :options="cancel_kind_options" :ariaLabel="ctrans('Why')" />
                <InputText v-model="cancelReason" class="w-full" maxlength="255" autofocus :placeholder="ctrans('e.g. Labels out of stock, coming from China')" :aria-label="ctrans('Reason')" />
                <div v-for="error in cancelErrors" :key="error" class="text-sm text-red-600">{{ error }}</div>
                <div class="flex justify-end gap-2">
                    <Button type="tertiary" :label="ctrans('Keep the job')" @click="cancelMode = null">
                        <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="tertiary" :label="cancelMode === 'backlog' ? ctrans('Back to backlog') : ctrans('Cancel job')" :disabled="(cancelKind === 'other' && !cancelReason.trim()) || processing" @click="cancelJobOrder">
                        <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </form>
        </Modal>

        <div v-if="receive_route" class="mt-6 rounded-xl border border-gray-200 bg-white p-4">
            <div class="font-semibold mb-3">{{ is_trial_run ? ctrans('Trial run: hand to the development office') : ctrans('Receive into stock') }}</div>
            <ul class="text-sm text-gray-600 mb-3">
                <li v-for="item in items" :key="item.id">
                    {{ item.artefact_code }} — {{ ctransChoice('will receive :count unit|will receive :count units', item.produced_quantity) }}
                </li>
            </ul>
            <div v-if="is_trial_run" class="text-xs text-gray-500">
                {{ ctrans('Nothing goes into stock: the units go to the development team, and the raw materials used are taken out of stock.') }}
            </div>
            <div v-else class="relative max-w-xs">
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Location') }}</label>
                <InputText
                    v-model="locationQuery"
                    size="small"
                    class="w-full"
                    :placeholder="ctrans('Search location')"
                    @input="selectedLocation = null; searchLocations()"
                />
                <ul v-if="locationResults.length" class="absolute z-10 mt-1 w-full rounded border border-gray-200 bg-white shadow-lg max-h-48 overflow-auto">
                    <li
                        v-for="location in locationResults"
                        :key="location.id"
                        class="px-3 py-1.5 text-sm cursor-pointer hover:bg-gray-100"
                        @click="pickLocation(location)"
                    >
                        {{ location.code }}
                    </li>
                </ul>
            </div>
            <div v-if="job_order.hold" class="mt-3 text-sm text-yellow-800">
                {{ ctrans('On hold: :reason. Clear the hold before receiving.', { reason: job_order.hold.reason }) }}
            </div>
            <Button
                class="mt-3"
                type="tertiary"
                :label="is_trial_run ? ctrans('Received at the development office') : ctrans('Receive into stock')"
                :disabled="(!is_trial_run && !selectedLocation) || processing || !!job_order.hold"
                :loading="processing"
                @click="receiveIntoStock"
            >
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
            <div v-if="receiveError" class="mt-1 text-xs text-red-600">{{ receiveError }}</div>
        </div>

        <div v-if="add_item_route" class="mt-6 flex items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Artefact') }}</label>
                <select v-model="newArtefactId" class="rounded border-gray-300 text-sm min-w-64">
                    <option :value="null" disabled>{{ ctrans('Select artefact') }}</option>
                    <option v-for="option in artefact_options" :key="option.id" :value="option.id">
                        {{ option.code }} — {{ option.name }}{{ option.has_recipe ? '' : ' (' + ctrans('no recipe') + ')' }}
                    </option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Quantity') }}</label>
                <input type="number" min="1" v-model.number="newQuantity" class="w-24 rounded border-gray-300 text-sm" />
                <div
                    v-if="selectedArtefactOption?.recommended_batch_size && newQuantity !== selectedArtefactOption.recommended_batch_size"
                    class="text-xs text-gray-400 mt-1"
                >
                    {{ ctrans('recommended batch') }}: {{ selectedArtefactOption.recommended_batch_size }}
                </div>
            </div>
            <button
                type="button"
                class="rounded bg-[--app-accent] text-[--app-accent-text] text-sm px-3 py-2 disabled:opacity-50"
                :disabled="!newArtefactId || !newQuantity || processing"
                @click="addItem"
            >
                <FontAwesomeIcon :icon="['fal', 'plus']" fixed-width class="mr-1" />
                {{ ctrans('Add item') }}
            </button>
        </div>
    </div>
</template>
