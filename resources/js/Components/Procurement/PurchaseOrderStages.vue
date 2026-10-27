<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import DatePicker from "primevue/datepicker"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faCheckCircle, faPencil, faTimes, faTruck, faPaperclip, faUpload, faSpinnerThird, faBan, faUndo, faMinusCircle, faExclamationCircle } from "@fal"
library.add(faCheck, faCheckCircle, faPencil, faTimes, faTruck, faPaperclip, faUpload, faSpinnerThird, faBan, faUndo, faMinusCircle, faExclamationCircle)

interface Step {
    key: string
    label: string
    date: string | null
    by: string | null
    note?: string | null
}

interface Mark {
    field: string
    label: string
    date: string | null
    skipped: string[]
    planned?: boolean
    suggested?: string | null
    confirms_order?: boolean
}

interface Evidence {
    scope: string
    label: string
    count: number
}

interface Stage {
    key: string
    number: number
    label: string
    description: string
    reached: boolean
    current: boolean
    date: string | null
    by: string | null
    expected: string | null
    expected_note: string | null
    days_late: number
    deliveries: { reached: number, total: number } | null
    not_required?: boolean
    unpaid?: boolean
    note?: string | null
    steps: Step[]
    evidence: Evidence[]
    marks: Mark[]
}

const props = defineProps<{
    stages: Stage[]
    route: routeType
    uploadRoute?: routeType | null
    deposit?: { not_needed: boolean, route: routeType } | null
    sample?: { required: boolean, approved: boolean, override_reason: string | null, route: routeType | null } | null
}>()

const savingDeposit = ref(false)

async function setDepositNotNeeded(notNeeded: boolean) {
    if (!props.deposit) {
        return
    }
    savingDeposit.value = true
    try {
        await axios.patch(route(props.deposit.route.name, props.deposit.route.parameters), { deposit_amount: notNeeded ? 0 : null })
        router.reload({ only: ["stages"] })
    } catch (error: any) {
        notify({ title: ctrans("Something went wrong"), text: error?.response?.data?.message ?? "", type: "error" })
    } finally {
        savingDeposit.value = false
    }
}

/* Sample required: the goods may not ship before the sample is approved, unless the buyer records why */

const savingSample = ref(false)
const overrideDialog = ref(false)
const overrideReason = ref("")
const overrideError = ref<string | null>(null)

async function updateSample(data: { sample_required?: boolean, sample_override_reason?: string | null }) {
    if (!props.sample?.route) {
        return false
    }
    savingSample.value = true
    try {
        await axios.patch(route(props.sample.route.name, props.sample.route.parameters), data)
        router.reload({ only: ["stages", "sampleRequirement", "pageHead"] })
        return true
    } catch (error: any) {
        overrideError.value = error?.response?.data?.message ?? null
        notify({ title: ctrans("Something went wrong"), text: error?.response?.data?.message ?? "", type: "error" })
        return false
    } finally {
        savingSample.value = false
    }
}

function openOverride() {
    overrideReason.value = ""
    overrideError.value = null
    overrideDialog.value = true
}

async function saveOverride() {
    if (await updateSample({ sample_override_reason: overrideReason.value.trim() })) {
        overrideDialog.value = false
    }
}

const uploading = ref<string | null>(null)

async function upload(evidence: Evidence, event: Event) {
    const input = event.target as HTMLInputElement
    const files = Array.from(input.files ?? [])
    input.value = ""
    if (!files.length || !props.uploadRoute) {
        return
    }
    uploading.value = evidence.scope
    try {
        await axios.post(route(props.uploadRoute.name, props.uploadRoute.parameters), { attachments: files, scope: evidence.scope }, { headers: { "Content-Type": "multipart/form-data" } })
        router.reload({ only: ["stages"] })
    } catch (error: any) {
        notify({ title: ctrans("Something went wrong"), text: error?.response?.data?.message ?? "", type: "error" })
    } finally {
        uploading.value = null
    }
}

const formatDate = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM yyyy" }) : null

const toIso = (date: Date | null) => date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}` : null

const editLabels: Record<string, string> = {
    production_started_at: "Edit production date",
    produced_at: "Edit production done",
}

const markLabel = (mark: Mark) => mark.date && editLabels[mark.field] ? ctrans(editLabels[mark.field]) : mark.label

const markTooltips: Record<string, { record: string, edit: string }> = {
    production_started_at: { record: "Click to record the date production started", edit: "Click to change the date production started" },
    produced_at: { record: "Click to record the date production finished", edit: "Click to change the date production finished" },
}

function markTooltip(mark: Mark) {
    if (mark.confirms_order && mark.field === "production_started_at") {
        return ctrans("Click once the supplier has accepted this order and started making it: it confirms the order and records the date production started")
    }

    const tooltip = markTooltips[mark.field]
    if (tooltip) {
        return ctrans(mark.date ? tooltip.edit : tooltip.record)
    }

    return mark.date
        ? ctrans("Click to change the date of :step", { step: mark.label })
        : ctrans("Click to record the date of :step", { step: mark.label })
}

const markDescriptions: Record<string, string> = {
    production_started_at: "Mark production started on the day the supplier began making the products of this order. It is set by itself when the first goods of this order arrive at the warehouse.",
    produced_at: "Mark production done once every product of this order is in the warehouse and nothing is left at the supplier.",
}

const editing = ref<Mark | null>(null)
const date = ref<Date | null>(null)
const reason = ref("")
const saving = ref(false)
const errors = ref<Record<string, string>>({})

function openMark(mark: Mark) {
    editing.value = mark
    const initial = mark.date ?? mark.suggested
    date.value = initial ? new Date(initial) : new Date()
    reason.value = ""
    errors.value = {}
}

const needsReason = computed(() => {
    if (!editing.value) {
        return false
    }
    if (!date.value) {
        return !!editing.value.date
    }
    const yesterday = new Date()
    yesterday.setDate(yesterday.getDate() - 1)
    const isPast = !editing.value.planned && (toIso(date.value) as string) < (toIso(yesterday) as string)
    return isPast || editing.value.skipped.length > 0
})

function save() {
    if (!editing.value) {
        return
    }
    router.patch(route(props.route.name, props.route.parameters), {
        field: editing.value.field,
        date: toIso(date.value),
        reason: reason.value.trim() || null,
    }, {
        preserveScroll: true,
        onStart: () => saving.value = true,
        onFinish: () => saving.value = false,
        onError: (error) => errors.value = error,
        onSuccess: () => editing.value = null,
    })
}
</script>

<template>
    <div>
    <div class="grid grid-cols-2 gap-px bg-gray-200 border-b border-gray-300 md:grid-cols-4" :class="stages.length > 7 ? 'xl:grid-cols-8' : 'xl:grid-cols-7'">
        <div
            v-for="stage in stages"
            :key="stage.key"
            class="p-3 text-sm"
            :class="[
                stage.current ? '' : '',
                stage.unpaid ? 'bg-amber-50' : stage.reached && !stage.not_required ? 'bg-green-50' : 'bg-white',
            ]"
        >
            <div class="flex items-center gap-2 font-medium" :class="stage.reached ? 'text-gray-800' : 'text-gray-400'" v-tooltip="stage.description">
                <FontAwesomeIcon v-if="stage.not_required" icon="fal fa-minus-circle" class="text-gray-400" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-else-if="stage.unpaid" icon="fal fa-exclamation-circle" class="text-amber-600" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-else-if="stage.reached" icon="fas fa-check-circle" class="text-green-600" fixed-width aria-hidden="true" />
                <span v-else class="w-4 text-center tabular-nums">{{ stage.number }}</span>
                {{ stage.label }}
            </div>

            <div class="mt-1 tabular-nums" :class="stage.date ? 'text-gray-700' : 'text-gray-400'">
                <template v-if="stage.not_required">{{ ctrans("Not required") }}</template>
                <template v-else-if="stage.unpaid"><span class="text-amber-700">{{ ctrans("Not paid") }}</span></template>
                <template v-else-if="stage.date">{{ formatDate(stage.date) }}</template>
                <template v-else-if="stage.reached">{{ ctrans("Not recorded") }}</template>
                <template v-else>-</template>
                <span v-if="stage.by" class="text-xs text-gray-500"> · {{ stage.by }}</span>
            </div>

            <div v-if="!stage.reached && stage.expected" class="mt-0.5 w-fit cursor-help text-xs tabular-nums" :class="stage.days_late ? 'text-red-600' : 'text-gray-500'" v-tooltip="stage.expected_note">
                {{ ctrans("Expected :date", { date: formatDate(stage.expected) as string }) }}
                <template v-if="stage.days_late"> · {{ ctrans(":days days late", { days: stage.days_late }) }}</template>
            </div>

            <div v-if="stage.note" class="mt-0.5 text-xs text-gray-500">{{ stage.note }}</div>

            <div v-if="stage.deliveries && stage.deliveries.total" class="mt-1 flex items-center gap-1 text-xs text-gray-500">
                <FontAwesomeIcon icon="fal fa-truck" fixed-width aria-hidden="true" />
                {{ ctrans(":reached of :total deliveries", { reached: stage.deliveries.reached, total: stage.deliveries.total }) }}
            </div>

            <dl v-if="stage.steps.length" class="mt-2 space-y-0.5 text-xs">
                <div v-for="step in stage.steps" :key="step.key" class="flex justify-between gap-2">
                    <dt class="text-gray-500">{{ step.label }}</dt>
                    <dd class="tabular-nums text-right" :class="step.date ? 'text-gray-700' : 'text-gray-400'" v-tooltip="step.note ?? step.by">
                        {{ formatDate(step.date) ?? "-" }}
                        <div v-if="step.note" class="text-gray-500">{{ step.note }}</div>
                    </dd>
                </div>
            </dl>

            <div v-if="stage.evidence.length" class="mt-2 space-y-0.5 text-xs">
                <div v-for="evidence in stage.evidence" :key="evidence.scope" class="flex items-center justify-between gap-2">
                    <span :class="evidence.count ? 'text-gray-700' : 'text-gray-400'">
                        <FontAwesomeIcon icon="fal fa-paperclip" fixed-width aria-hidden="true" />
                        {{ evidence.label }}<template v-if="evidence.count"> · {{ evidence.count }}</template>
                    </span>
                    <label v-if="uploadRoute" class="cursor-pointer text-gray-500 hover:text-gray-800" v-tooltip="ctrans('Upload :document', { document: evidence.label })">
                        <FontAwesomeIcon :icon="uploading === evidence.scope ? 'fal fa-spinner-third' : 'fal fa-upload'" :spin="uploading === evidence.scope" fixed-width aria-hidden="true" />
                        <input type="file" multiple accept="image/*,.pdf" class="hidden" :disabled="!!uploading" @change="(event) => upload(evidence, event)" />
                    </label>
                </div>
            </div>

            <div v-if="stage.marks.length" class="mt-2 flex flex-col gap-1">
                <Button
                    v-for="mark in stage.marks"
                    :key="mark.field"
                    type="tertiary"
                    size="xs"
                    full
                    :label="markLabel(mark)"
                    :tooltip="markTooltip(mark)"
                    @click="openMark(mark)"
                >
                    <template #icon>
                        <FontAwesomeIcon :icon="mark.date ? 'fal fa-pencil' : 'fal fa-check'" class="text-gray-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>

            <div v-if="stage.key === 'deposit_paid' && deposit" class="mt-2">
                <Button
                    type="tertiary"
                    size="xs"
                    full
                    :label="deposit.not_needed ? ctrans('Deposit needed after all') : ctrans('No deposit needed')"
                    :tooltip="deposit.not_needed ? ctrans('Click to ask for a deposit again, if the supplier wants one before production') : ctrans('Click to mark this order as needing no deposit, when the supplier starts production without one')"
                    :loading="savingDeposit"
                    @click="setDepositNotNeeded(!deposit.not_needed)"
                >
                    <template #icon>
                        <FontAwesomeIcon :icon="deposit.not_needed ? 'fal fa-undo' : 'fal fa-ban'" :class="deposit.not_needed ? 'text-gray-500' : 'text-amber-600'" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>

            <div v-if="stage.key === 'in_production' && sample?.required" class="mt-2 space-y-1 text-xs">
                <p v-if="sample.approved" class="text-green-700">{{ ctrans("Sample approved: the goods can ship") }}</p>
                <p v-else-if="sample.override_reason" class="text-amber-700">
                    {{ ctrans("Ships without an approved sample") }}: <span class="text-gray-700">{{ sample.override_reason }}</span>
                </p>
                <p v-else class="text-amber-700">{{ ctrans("Sample required: the goods can't ship until it is approved") }}</p>
                <template v-if="sample.route">
                    <Button
                        v-if="!sample.approved && !sample.override_reason"
                        type="tertiary"
                        size="xs"
                        full
                        :label="ctrans('Ship without approved sample')"
                        :tooltip="ctrans('Record why the goods ship before the sample is approved')"
                        @click="openOverride"
                    >
                        <template #icon><FontAwesomeIcon icon="fal fa-exclamation-circle" class="text-amber-600" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button
                        v-if="sample.override_reason && !sample.approved"
                        type="tertiary"
                        size="xs"
                        full
                        :label="ctrans('Wait for the sample after all')"
                        :loading="savingSample"
                        @click="updateSample({ sample_override_reason: null })"
                    >
                        <template #icon><FontAwesomeIcon icon="fal fa-undo" class="text-gray-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                </template>
            </div>

            <slot :name="`stage-${stage.key}`" :stage="stage" />
        </div>
    </div>

    <Dialog
        :visible="overrideDialog"
        modal
        :header="ctrans('Ship without approved sample')"
        :style="{ width: '28rem' }"
        @update:visible="(visible: boolean) => !visible && (overrideDialog = false)"
    >
        <div class="space-y-2 text-sm">
            <p class="text-gray-600">{{ ctrans("The goods of this order can then ship before its sample is approved. Say why; it is kept in the order's history.") }}</p>
            <Textarea v-model="overrideReason" rows="3" autoResize class="w-full" />
            <span v-if="overrideError" class="block text-xs text-red-600">{{ overrideError }}</span>
        </div>
        <template #footer>
            <Button type="tertiary" :label="ctrans('Save')" :loading="savingSample" :disabled="!overrideReason.trim()" full @click="saveOverride">
                <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
            </Button>
        </template>
    </Dialog>

    <Dialog
        :visible="!!editing"
        modal
        :header="editing ? markLabel(editing) : undefined"
        :style="{ width: '28rem' }"
        @update:visible="(visible: boolean) => !visible && (editing = null)"
    >
        <div class="space-y-4 text-sm">
            <p v-if="editing && markDescriptions[editing.field]" class="text-gray-600">
                {{ ctrans(markDescriptions[editing.field]) }}
            </p>

            <p v-if="editing?.confirms_order" class="text-gray-600">
                {{ ctrans("Saving also confirms this purchase order: work on it means the supplier accepted it.") }}
            </p>

            <label class="block space-y-1">
                <span class="block text-xs text-gray-500">{{ ctrans("Date") }}</span>
                <DatePicker v-model="date" dateFormat="yy-mm-dd" :maxDate="editing?.planned ? undefined : new Date()" showIcon showButtonBar class="w-full" />
                <span v-if="errors.date" class="block text-xs text-red-600">{{ errors.date }}</span>
            </label>

            <p v-if="editing?.skipped.length" class="text-xs text-amber-700">
                {{ ctrans("Not recorded yet: :stages", { stages: editing.skipped.join(", ") }) }}
            </p>

            <label v-if="needsReason" class="block space-y-1">
                <span class="block text-xs text-gray-500">{{ ctrans("Reason (kept in the order's history)") }}</span>
                <Textarea v-model="reason" rows="3" autoResize class="w-full" />
                <span v-if="errors.reason" class="block text-xs text-red-600">{{ errors.reason }}</span>
            </label>
            <span v-if="errors.field" class="block text-xs text-red-600">{{ errors.field }}</span>
        </div>

        <template #footer>
            <Button type="tertiary" :label="ctrans('Cancel')" @click="editing = null">
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                </template>
            </Button>
            <Button :type="editing && markDescriptions[editing.field] ? 'secondary' : 'tertiary'" :label="ctrans('Save')" :loading="saving" :disabled="needsReason && !reason.trim()" @click="save" full>
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </template>
    </Dialog>
    </div>
</template>
