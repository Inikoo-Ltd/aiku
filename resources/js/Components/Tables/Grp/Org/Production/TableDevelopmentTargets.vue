<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 10 Nov 2026 23:40:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import { computed, onMounted, ref } from "vue"
import axios from "axios"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import MultiSelect from "primevue/multiselect"
import Textarea from "primevue/textarea"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes, faCog, faStopwatch, faPause, faChevronDown, faChevronUp } from "@fal"

library.add(faCheck, faTimes, faCog, faStopwatch, faPause, faChevronDown, faChevronUp)

type StepSummary = {
    id: number
    name: string
    target: number | null
    is_piece_rate: boolean
    sessions_count: number
    artisans_count: number
    hours: number
    proposed_target: number | null
    is_ready: boolean
}

type Row = {
    id: number
    slug: string
    code: string
    name: string | null
    family: string | null
    development_started_at: string | null
    recipe_changed_at: string | null
    last_run_at: string | null
    status: "overdue" | "ready_idle" | "collecting" | "kept"
    kept: { reason: string, user: string | null, at: string } | null
    steps: StepSummary[]
}

type Session = {
    id: number
    artisan: string | null
    artefact_code: string
    started_at: string
    quantity: number
    hours: number
    rate: number | null
    excluded: "first_run" | "too_short" | "outlier" | null
}

type StepDetail = StepSummary & {
    units_per_artefact: number
    excluded_count: number
    min_rate: number | null
    max_rate: number | null
    q1_rate: number | null
    q3_rate: number | null
    sessions: Session[]
    similar: { code: string, label: string | null, units_per_artefact: number, target: number }[]
}

type Settings = {
    min_sessions: number
    min_artisans: number
    min_hours: number
    min_session_minutes: number
    idle_weeks: number
    reviewer_ids: number[]
}

const props = defineProps<{
    data: { data?: Row[] }
    tab: string
    artefactRoute: routeType
    review: {
        can_decide: boolean
        review: string | null
        settings: Settings
        reviewer_options: { id: number, name: string }[]
        reviewers: string[]
        settings_route: routeType
        proposal_route: routeType
        decide_route: routeType
    }
}>()

const rows = computed<Row[]>(() => props.data?.data ?? [])
const counts = computed(() => ({
    overdue: rows.value.filter(row => row.status === "overdue").length,
    ready_idle: rows.value.filter(row => row.status === "ready_idle").length,
    collecting: rows.value.filter(row => row.status === "collecting").length,
    kept: rows.value.filter(row => row.status === "kept").length,
}))

const statusLabel = (status: Row["status"]) => ({
    overdue: ctrans("Overdue"),
    ready_idle: ctrans("Ready, not run lately"),
    collecting: ctrans("Collecting"),
    kept: ctrans("Kept on development"),
}[status])

const statusClass = (status: Row["status"]) => ({
    overdue: "bg-red-50 text-red-700 ring-red-200",
    ready_idle: "bg-amber-50 text-amber-700 ring-amber-200",
    collecting: "bg-gray-50 text-gray-600 ring-gray-200",
    kept: "bg-sky-50 text-sky-700 ring-sky-200",
}[status])

const formatDate = (date: string | null) => date ? useFormatTime(date, { formatTime: "aiku" }) : "—"

const excludedLabel = (reason: Session["excluded"]) => reason ? {
    first_run: ctrans("First run of this artisan"),
    too_short: ctrans("Too short"),
    outlier: ctrans("Outlier"),
}[reason] : ""

const reviewing = ref<Row | null>(null)
const isLoadingProposal = ref(false)
const steps = ref<StepDetail[]>([])
const targets = ref<Record<number, number | null>>({})
const keepReason = ref("")
const isKeeping = ref(false)
const isSaving = ref(false)
const reviewError = ref<string | null>(null)
const openSessions = ref<number | null>(null)

const openReview = async (row: Row) => {
    reviewing.value = row
    steps.value = []
    targets.value = {}
    keepReason.value = ""
    isKeeping.value = false
    reviewError.value = null
    openSessions.value = null
    isLoadingProposal.value = true
    try {
        const response = await axios.get(route(props.review.proposal_route.name, { artefact: row.id }))
        steps.value = response.data.steps
        steps.value
            .filter(step => step.is_piece_rate && step.target === null)
            .forEach(step => targets.value[step.id] = step.is_ready ? step.proposed_target : null)
    } catch {
        reviewError.value = ctrans("The sessions could not be loaded, try again")
    } finally {
        isLoadingProposal.value = false
    }
}

const editableSteps = computed(() => steps.value.filter(step => step.is_piece_rate && step.target === null))
const filledTargets = computed(() => Object.fromEntries(Object.entries(targets.value).filter(([, rate]) => rate !== null && rate > 0)))

const decide = (decision: "accepted" | "adjusted" | "kept") => {
    if (!reviewing.value) {
        return
    }
    isSaving.value = true
    reviewError.value = null
    router.post(
        route(props.review.decide_route.name, { artefact: reviewing.value.id }),
        decision === "kept" ? { decision, reason: keepReason.value } : { decision, targets: filledTargets.value },
        {
            preserveScroll: true,
            onSuccess: () => reviewing.value = null,
            onError: (errors) => reviewError.value = Object.values(errors).join(" "),
            onFinish: () => isSaving.value = false,
        }
    )
}

const saveTargets = () => decide(Object.keys(filledTargets.value).every(stepId => {
    const step = editableSteps.value.find(step => step.id === Number(stepId))

    return step?.is_ready && targets.value[step.id] === step.proposed_target
}) ? "accepted" : "adjusted")

const isSettingsOpen = ref(false)
const settingsForm = ref<Settings>({ ...props.review.settings })
const settingsError = ref<string | null>(null)
const isSavingSettings = ref(false)

const openSettings = () => {
    settingsForm.value = { ...props.review.settings, reviewer_ids: [...props.review.settings.reviewer_ids] }
    settingsError.value = null
    isSettingsOpen.value = true
}

const saveSettings = () => {
    isSavingSettings.value = true
    router.patch(route(props.review.settings_route.name, props.review.settings_route.parameters), settingsForm.value, {
        preserveScroll: true,
        onSuccess: () => isSettingsOpen.value = false,
        onError: (errors) => settingsError.value = Object.values(errors).join(" "),
        onFinish: () => isSavingSettings.value = false,
    })
}

onMounted(() => {
    const row = props.review.review ? rows.value.find(row => row.slug === props.review.review) : null
    if (row && props.review.can_decide) {
        openReview(row)
    }
})
</script>

<template>
    <div class="mx-4 mt-4 flex flex-wrap items-start justify-between gap-3">
        <div class="flex flex-wrap gap-2 text-sm">
            <span class="rounded-md px-2.5 py-1 ring-1 ring-inset" :class="statusClass('overdue')">{{ ctrans("Overdue") }}: <b>{{ counts.overdue }}</b></span>
            <span class="rounded-md px-2.5 py-1 ring-1 ring-inset" :class="statusClass('ready_idle')">{{ ctrans("Ready, not run lately") }}: <b>{{ counts.ready_idle }}</b></span>
            <span class="rounded-md px-2.5 py-1 ring-1 ring-inset" :class="statusClass('collecting')">{{ ctrans("Collecting") }}: <b>{{ counts.collecting }}</b></span>
            <span class="rounded-md px-2.5 py-1 ring-1 ring-inset" :class="statusClass('kept')">{{ ctrans("Kept on development") }}: <b>{{ counts.kept }}</b></span>
        </div>
        <Button v-if="review.can_decide" type="tertiary" :label="ctrans('Settings')" @click="openSettings">
            <template #icon><FontAwesomeIcon :icon="['fal', 'cog']" fixed-width class="text-gray-500" aria-hidden="true" /></template>
        </Button>
    </div>
    <div class="mx-4 mt-2 text-xs text-gray-500">
        {{ ctrans("A step is ready for its target with :sessions sessions from :artisans artisans and :hours hours, after leaving out each artisan's first session, sessions under :minutes minutes and outliers. The proposed target is the median per hour. Products not run in the last :weeks weeks are not overdue.", {
            sessions: review.settings.min_sessions,
            artisans: review.settings.min_artisans,
            hours: review.settings.min_hours,
            minutes: review.settings.min_session_minutes,
            weeks: review.settings.idle_weeks,
        }) }}
        <span v-if="review.reviewers.length">{{ ctrans("Review tasks go to :names.", { names: review.reviewers.join(", ") }) }}</span>
        <span v-else class="text-amber-700">{{ ctrans("Nobody is chosen to review yet, so no review task is sent. Choose them in Settings.") }}</span>
    </div>

    <Table :resource="data" :name="tab" class="mt-3">
        <template #cell(status)="{ item }">
            <span class="whitespace-nowrap rounded-md px-2 py-0.5 text-xs ring-1 ring-inset" :class="statusClass(item.status)" v-tooltip="item.kept?.reason">{{ statusLabel(item.status) }}</span>
        </template>
        <template #cell(code)="{ item }">
            <Link :href="route(artefactRoute.name, { ...artefactRoute.parameters, artefact: item.slug })" class="primaryLink whitespace-nowrap">{{ item.code }}</Link>
            <div v-if="item.name" class="text-xs text-gray-500">{{ item.name }}</div>
        </template>
        <template #cell(family)="{ item }">
            <span class="whitespace-nowrap">{{ item.family ?? "—" }}</span>
        </template>
        <template #cell(development_started_at)="{ item }">
            <span class="whitespace-nowrap">{{ formatDate(item.development_started_at) }}</span>
        </template>
        <template #cell(recipe_changed_at)="{ item }">
            <span class="whitespace-nowrap">{{ formatDate(item.recipe_changed_at) }}</span>
        </template>
        <template #cell(last_run_at)="{ item }">
            <span class="whitespace-nowrap">{{ formatDate(item.last_run_at) }}</span>
        </template>
        <template #cell(steps)="{ item }">
            <ul class="space-y-0.5 text-xs">
                <li v-for="step in item.steps" :key="step.id" class="whitespace-nowrap">
                    <span class="font-medium">{{ step.name }}</span>
                    <template v-if="step.target !== null"> · <span class="text-green-700">{{ ctrans(":rate/h target", { rate: step.target }) }}</span></template>
                    <template v-else-if="!step.is_piece_rate"> · <span class="text-gray-500">{{ ctrans("paid by the hour") }}</span></template>
                    <template v-else>
                        · {{ ctrans(":sessions sessions, :artisans artisans, :hours h", { sessions: step.sessions_count, artisans: step.artisans_count, hours: step.hours }) }}
                        <template v-if="step.proposed_target !== null"> · {{ ctrans(":rate/h", { rate: step.proposed_target }) }}</template>
                        <FontAwesomeIcon v-if="step.is_ready" :icon="['fal', 'check']" class="ml-1 text-green-600" v-tooltip="ctrans('Ready for its target')" aria-hidden="true" />
                    </template>
                </li>
            </ul>
        </template>
        <template #cell(actions)="{ item }">
            <Button v-if="review.can_decide" type="tertiary" size="xs" :label="ctrans('Review')" @click="openReview(item)">
                <template #icon><FontAwesomeIcon :icon="['fal', 'stopwatch']" fixed-width aria-hidden="true" /></template>
            </Button>
        </template>
    </Table>

    <Dialog :visible="!!reviewing" modal :header="ctrans('Targets for :code', { code: reviewing?.code ?? '' })" :style="{ width: '56rem' }" @update:visible="(visible: boolean) => { if (!visible && !isSaving) reviewing = null }">
        <div v-if="isLoadingProposal" class="flex justify-center py-8"><LoadingIcon /></div>
        <div v-else class="space-y-4">
            <div v-if="reviewing?.kept" class="rounded-md bg-sky-50 px-3 py-2 text-xs text-sky-800">
                {{ ctrans("Kept on development by :user on :date: :reason", { user: reviewing.kept.user ?? "", date: formatDate(reviewing.kept.at), reason: reviewing.kept.reason }) }}
            </div>

            <div v-for="step in steps" :key="step.id" class="rounded-md border border-gray-200 p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="text-sm font-semibold">{{ step.name }} <span class="font-normal text-gray-500">· {{ ctrans(":units per artefact", { units: step.units_per_artefact }) }}</span></div>
                        <div class="text-xs text-gray-500">
                            {{ ctrans(":sessions sessions counted, :excluded left out, :artisans artisans, :hours h", { sessions: step.sessions_count, excluded: step.excluded_count, artisans: step.artisans_count, hours: step.hours }) }}
                        </div>
                    </div>
                    <div v-if="step.target !== null" class="text-sm text-green-700">{{ ctrans("Target :rate/h", { rate: step.target }) }}</div>
                    <div v-else-if="!step.is_piece_rate" class="text-sm text-gray-500">{{ ctrans("Paid by the hour, no target") }}</div>
                    <label v-else class="flex items-center gap-2 text-sm">
                        <span class="whitespace-nowrap">{{ ctrans("Target per hour") }}</span>
                        <InputNumber v-model="targets[step.id]" :min="0" :maxFractionDigits="1" inputClass="w-24 text-right" />
                    </label>
                </div>

                <div v-if="step.proposed_target !== null && step.target === null && step.is_piece_rate" class="mt-2 text-xs">
                    <span :class="step.is_ready ? 'text-green-700' : 'text-amber-700'">
                        {{ step.is_ready ? ctrans("Proposed :rate/h (median)", { rate: step.proposed_target }) : ctrans("Not enough data yet: :rate/h so far (median)", { rate: step.proposed_target }) }}
                    </span>
                    <span class="text-gray-500"> · {{ ctrans("middle half :q1–:q3/h, range :min–:max/h", { q1: step.q1_rate ?? "", q3: step.q3_rate ?? "", min: step.min_rate ?? "", max: step.max_rate ?? "" }) }}</span>
                </div>

                <div v-if="step.similar.length" class="mt-2 text-xs text-gray-600">
                    {{ ctrans("Similar steps") }}:
                    <span v-for="(similar, index) in step.similar" :key="similar.code + index" class="whitespace-nowrap">
                        {{ similar.code }}<template v-if="similar.label"> ({{ similar.label }})</template> {{ ctrans(":rate/h", { rate: similar.target }) }}<template v-if="similar.units_per_artefact !== step.units_per_artefact"> · {{ ctrans(":units per artefact", { units: similar.units_per_artefact }) }}</template><template v-if="index < step.similar.length - 1">; </template>
                    </span>
                </div>

                <Button v-if="step.sessions.length" type="tertiary" size="xs" class="mt-2" :label="openSessions === step.id ? ctrans('Hide sessions') : ctrans('Show the :count sessions', { count: step.sessions.length })" @click="openSessions = openSessions === step.id ? null : step.id">
                    <template #icon><FontAwesomeIcon :icon="['fal', openSessions === step.id ? 'chevron-up' : 'chevron-down']" fixed-width class="text-gray-500" aria-hidden="true" /></template>
                </Button>
                <table v-if="openSessions === step.id" class="mt-2 w-full text-xs">
                    <thead class="whitespace-nowrap text-left text-gray-500">
                        <tr>
                            <th class="py-1 pr-3 font-medium">{{ ctrans("Date") }}</th>
                            <th class="py-1 pr-3 font-medium">{{ ctrans("Artisan") }}</th>
                            <th class="py-1 pr-3 font-medium">{{ ctrans("Product") }}</th>
                            <th class="py-1 pr-3 text-right font-medium">{{ ctrans("Made") }}</th>
                            <th class="py-1 pr-3 text-right font-medium">{{ ctrans("Hours") }}</th>
                            <th class="py-1 pr-3 text-right font-medium">{{ ctrans("Per hour") }}</th>
                            <th class="py-1 font-medium">{{ ctrans("Left out") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="session in step.sessions" :key="session.id" :class="session.excluded ? 'text-gray-400' : ''">
                            <td class="whitespace-nowrap py-1 pr-3">{{ formatDate(session.started_at) }}</td>
                            <td class="py-1 pr-3">{{ session.artisan }}</td>
                            <td class="whitespace-nowrap py-1 pr-3">{{ session.artefact_code }}</td>
                            <td class="py-1 pr-3 text-right tabular-nums">{{ session.quantity }}</td>
                            <td class="py-1 pr-3 text-right tabular-nums">{{ session.hours }}</td>
                            <td class="py-1 pr-3 text-right tabular-nums">{{ session.rate ?? "—" }}</td>
                            <td class="py-1">{{ excludedLabel(session.excluded) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="isKeeping">
                <label for="keepReason" class="block text-sm font-medium">{{ ctrans("Why keep it on development?") }} <span class="text-red-500">*</span></label>
                <Textarea v-model="keepReason" inputId="keepReason" rows="2" autoResize fluid class="mt-1" />
            </div>

            <div v-if="reviewError" class="text-xs text-red-600">{{ reviewError }}</div>
        </div>

        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" :disabled="isSaving" @click="reviewing = null">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'times']" fixed-width class="text-red-500" aria-hidden="true" /></template>
                </Button>
                <template v-if="isKeeping">
                    <Button type="primary" full :label="ctrans('Keep on development')" :loading="isSaving" :disabled="!keepReason.trim()" @click="decide('kept')">
                        <template #icon><FontAwesomeIcon :icon="['fal', 'pause']" fixed-width aria-hidden="true" /></template>
                    </Button>
                </template>
                <template v-else>
                    <Button type="tertiary" class="shrink-0" :label="ctrans('Keep on development')" :disabled="isSaving || isLoadingProposal" @click="isKeeping = true">
                        <template #icon><FontAwesomeIcon :icon="['fal', 'pause']" fixed-width class="text-sky-600" aria-hidden="true" /></template>
                    </Button>
                    <Button type="primary" full :label="ctrans('Save targets')" :loading="isSaving" :disabled="isLoadingProposal || !Object.keys(filledTargets).length" @click="saveTargets">
                        <template #icon><FontAwesomeIcon :icon="['fal', 'check']" fixed-width aria-hidden="true" /></template>
                    </Button>
                </template>
            </div>
        </template>
    </Dialog>

    <Dialog :visible="isSettingsOpen" modal :header="ctrans('When a product is ready for its targets')" :style="{ width: '30rem' }" @update:visible="(visible: boolean) => { if (!visible && !isSavingSettings) isSettingsOpen = false }">
        <div class="grid grid-cols-2 gap-3 text-sm">
            <label class="flex flex-col gap-1"><span>{{ ctrans("Sessions per step") }} <span class="text-red-500">*</span></span><InputNumber v-model="settingsForm.min_sessions" :min="1" fluid /></label>
            <label class="flex flex-col gap-1"><span>{{ ctrans("Different artisans") }} <span class="text-red-500">*</span></span><InputNumber v-model="settingsForm.min_artisans" :min="1" fluid /></label>
            <label class="flex flex-col gap-1"><span>{{ ctrans("Hours in total") }} <span class="text-red-500">*</span></span><InputNumber v-model="settingsForm.min_hours" :min="0" :maxFractionDigits="1" fluid /></label>
            <label class="flex flex-col gap-1"><span>{{ ctrans("Shortest session (minutes)") }} <span class="text-red-500">*</span></span><InputNumber v-model="settingsForm.min_session_minutes" :min="0" fluid /></label>
            <label class="flex flex-col gap-1"><span>{{ ctrans("Not overdue when not run for (weeks)") }} <span class="text-red-500">*</span></span><InputNumber v-model="settingsForm.idle_weeks" :min="1" fluid /></label>
        </div>
        <label class="mt-3 flex flex-col gap-1 text-sm">
            {{ ctrans("Who reviews (Production Manager and accountant)") }}
            <MultiSelect v-model="settingsForm.reviewer_ids" :options="review.reviewer_options" optionLabel="name" optionValue="id" filter :selectionLimit="5" display="chip" fluid />
        </label>
        <div v-if="settingsError" class="mt-3 text-xs text-red-600">{{ settingsError }}</div>

        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" :disabled="isSavingSettings" @click="isSettingsOpen = false">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'times']" fixed-width class="text-red-500" aria-hidden="true" /></template>
                </Button>
                <Button type="primary" full :label="ctrans('Save')" :loading="isSavingSettings" @click="saveSettings">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'check']" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
