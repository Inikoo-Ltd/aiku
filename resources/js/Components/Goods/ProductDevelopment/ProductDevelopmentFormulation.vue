<!--
  - Author Louis Perez
  - Created on 26-10-2026-15h-50m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import Checkbox from "primevue/checkbox"
import Button from "@/Components/Elements/Buttons/Button.vue"
import RecipeIngredients, { type Ingredient } from "@/Components/Goods/ProductDevelopment/Formulation/RecipeIngredients.vue"
import FormulationPercentages, { type Percentages } from "@/Components/Goods/ProductDevelopment/Formulation/FormulationPercentages.vue"
import SampleThread from "@/Components/Goods/ProductDevelopment/Formulation/SampleThread.vue"
import ProductDevelopmentPhotoStrip, { type ProductDevelopmentPhoto } from "@/Components/Goods/ProductDevelopment/ProductDevelopmentPhotoStrip.vue"
import ProductDevelopmentSkuList from "@/Components/Goods/ProductDevelopment/ProductDevelopmentSkuList.vue"
import ProductDevelopmentRemoteSelect from "@/Components/Goods/ProductDevelopment/ProductDevelopmentRemoteSelect.vue"
import { productDevelopmentColor } from "@/Components/Goods/ProductDevelopment/productDevelopmentColors"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFlask, faExternalLink, faPlus, faPencil, faCheck, faUndo, faTimes, faPaperPlane, faComments, faChevronDown, faExclamationTriangle, faImage, faTasks, faCheckCircle, faTrashAlt, faBoxOpen, faFileAlt, faCube, faHourglassHalf, faLink, faCalendarAlt, faIndustry } from "@fal"
library.add(faFlask, faExternalLink, faPlus, faPencil, faCheck, faUndo, faTimes, faPaperPlane, faComments, faChevronDown, faExclamationTriangle, faImage, faTasks, faCheckCircle, faTrashAlt, faBoxOpen, faFileAlt, faCube, faHourglassHalf, faLink, faCalendarAlt, faIndustry)

interface Status {
    value: string
    label: string
    color: string
    icon: string
}

interface Recipe {
    steps: { position: number, name: string, raw_materials: Ingredient[] }[]
    ingredients: Ingredient[]
    formulation?: Percentages
}

interface ArtefactLink {
    code: string
    name: string
    state: string
    is_cancelled?: boolean
    route: routeType
}

type FormulationType = "base_blend" | "intermediate" | "finished_product"

interface Formulation {
    id: number
    position: number
    name: string
    type: { value: FormulationType, label: string }
    brief: string | null
    artisan_id: number | null
    artisan: string | null
    approved: string | null
    approved_at: string | null
    approved_by: string | null
    approved_recipe: Recipe | null
    blends_made_on_sku: { stage: string, number: string }[]
    is_current: boolean
    status: Status | null
    assignment: { value: string, label: string, color: string } | null
    artefact: ArtefactLink | null
    recipe: Recipe | null
    in_finished_recipe: boolean | null
    percentages: Percentages | null
    trial_runs: { total: number, reviewed: number }
    can_edit: boolean
    can_change_type: boolean
    can_create_artefact: boolean
    can_delete: boolean
    routes: Record<"update" | "delete" | "artefact", routeType>
}

interface Progress {
    stages: number
    current: string | null
    approved: number
    trial_runs: number
    awaiting: number
    longest_wait: number | null
    artefacts: number
    final: string | null
    final_status: { label: string, color: string }
}

interface Sample {
    id: number
    formulation_id: number | null
    number: string
    status: Status
    recipe: Recipe | null
    batch_size: number | null
    batch_unit: string | null
    notes: string | null
    artefact: string | null
    waiting_days: number | null
    made_by: string | null
    created_at: string | null
    ready_at: string | null
    review_comment: string | null
    reviewed_by: string | null
    reviewed_at: string | null
    comments: number
    job: { reference: string, status: string, waiting: boolean, assignee: string | null, quantity: number, received: number, route: routeType | null } | null
    photos: ProductDevelopmentPhoto[]
    can_edit: boolean
    can_review: boolean
    routes: Record<"update" | "submit" | "review" | "comments" | "photos", routeType>
}

interface Component {
    id: number
    trade_unit_id: number
    code: string
    name: string
    route: routeType
    quantity: number
    note: string | null
    status: Status
    approved_by: string | null
    approved_at: string | null
    can_edit: boolean
    can_approve: boolean
    routes: Record<"update" | "delete" | "approval", routeType>
}

interface Sku {
    id: number
    code: string
    name: string
    image: (Record<string, string> & { original: string }) | null
    brief: string | null
    artefact: { code: string, name: string, state: string, branched_from: string | null, route: routeType } | null
    recipe: Recipe | null
    status: Status | null
    final: string | null
    recipe_changed: boolean
    can_start_sample: boolean
    no_artefact_reason: string | null
    formulations: Formulation[]
    has_finished_stage: boolean
    progress: Progress
    can_add_formulation: boolean
    samples: Sample[]
    components: Component[]
    can_add_component: boolean
    routes: { sample_store: routeType, component_store: routeType, formulation_store: routeType }
}

const props = defineProps<{
    data: {
        production: { name: string, organisation: string | null } | null
        skus: Sku[]
        progress: { done: number, total: number }
        can_manage: boolean
        can_review: boolean
        can_create_job: boolean
        artisan_options: { id: number, name: string }[]
        can_approve_components: boolean
        component_search: routeType
        routes: { sample_job: routeType, comments: routeType }
    }
}>()

const only = ["formulation", "recentActivity"]
const formatDate = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM yyyy" }) : ""
const firstError = (errors: Record<string, string>) => Object.values(errors)[0]

/* SKU list */

const selectedId = ref<number | null>((props.data?.skus.find((sku) => sku.status?.value !== "approved") ?? props.data?.skus[0])?.id ?? null)
const selected = computed(() => props.data?.skus.find((sku) => sku.id === selectedId.value) ?? props.data?.skus[0] ?? null)

const entries = computed(() => (props.data?.skus ?? []).map((sku) => ({
    id: sku.id,
    code: sku.code,
    name: sku.name,
    image: sku.image,
    done: sku.formulations.filter((formulation) => formulation.approved).length,
    total: Math.max(sku.formulations.length, 1),
    badge: sku.final
        ? { label: ctrans("Trial run :number approved", { number: sku.final }), class: productDevelopmentColor("green").active }
        : sku.status
            ? { label: sku.status.label, class: productDevelopmentColor(sku.status.color).active }
            : { label: ctrans("No trial run yet"), class: productDevelopmentColor("gray").active },
})))

/* Formulation stages */

const typeOptions: { value: FormulationType, label: string }[] = [
    { value: "base_blend", label: ctrans("Base blend") },
    { value: "intermediate", label: ctrans("Intermediate formulation") },
    { value: "finished_product", label: ctrans("Finished product") },
]

const expanded = ref<Record<number, boolean>>({})
const isExpanded = (formulation: Formulation) => expanded.value[formulation.id] ?? formulation.is_current
const toggleStage = (formulation: Formulation) => expanded.value[formulation.id] = !isExpanded(formulation)

const samplesOf = (sku: Sku, formulation: Formulation) => sku.samples.filter((sample) => sample.formulation_id === formulation.id || (sku.formulations.length < 2 && sample.formulation_id === null))
const openSample = (sku: Sku) => sku.samples.find((sample) => ["in_progress", "ready_for_review"].includes(sample.status.value)) ?? null
const stageOf = (sku: Sku, sample: Sample) => sku.formulations.find((formulation) => formulation.id === sample.formulation_id) ?? null
const canRaiseIn = (sku: Sku, formulation: Formulation) => formulation.is_current && !openSample(sku) && !!sku.artefact
const blendsOf = (sku: Sku) => sku.formulations.filter((formulation) => formulation.type.value !== "finished_product")

const formulationDialog = ref<{ formulation: Formulation | null, name: string, artisan_id: number | null, type: FormulationType, brief: string } | null>(null)
const formulationErrors = ref<Record<string, string>>({})

function openFormulation(sku: Sku, formulation: Formulation | null) {
    formulationErrors.value = {}
    formulationDialog.value = {
        formulation,
        name: formulation?.name ?? "",
        artisan_id: formulation?.artisan_id ?? null,
        type: formulation?.type.value ?? (sku.has_finished_stage ? "intermediate" : "finished_product"),
        brief: formulation?.brief ?? "",
    }
}

function saveFormulation(sku: Sku) {
    const dialog = formulationDialog.value
    if (!dialog) {
        return
    }
    const payload: Record<string, unknown> = { name: dialog.name, artisan_id: dialog.artisan_id, brief: dialog.brief || null }
    if (!dialog.formulation || dialog.formulation.type.value !== dialog.type) {
        payload.type = dialog.type
    }
    const options = {
        preserveScroll: true,
        only: [...only, "tabs"],
        onStart: () => saving.value = true,
        onFinish: () => saving.value = false,
        onSuccess: () => formulationDialog.value = null,
        onError: (errors: Record<string, string>) => formulationErrors.value = errors,
    }
    if (dialog.formulation) {
        router.patch(route(dialog.formulation.routes.update.name, dialog.formulation.routes.update.parameters), payload, options)
    } else {
        router.post(route(sku.routes.formulation_store.name, sku.routes.formulation_store.parameters), payload, options)
    }
}

const artefactErrors = ref<Record<number, string>>({})
const creatingArtefact = ref<number | null>(null)

function createArtefact(formulation: Formulation) {
    router.post(route(formulation.routes.artefact.name, formulation.routes.artefact.parameters), {}, {
        preserveScroll: true,
        only,
        onStart: () => creatingArtefact.value = formulation.id,
        onFinish: () => creatingArtefact.value = null,
        onSuccess: () => delete artefactErrors.value[formulation.id],
        onError: (errors) => artefactErrors.value[formulation.id] = firstError(errors),
    })
}

function removeFormulation(formulation: Formulation) {
    router.delete(route(formulation.routes.delete.name, formulation.routes.delete.parameters), {
        preserveScroll: true,
        only,
        onStart: () => saving.value = true,
        onFinish: () => saving.value = false,
        onSuccess: () => formulationDialog.value = null,
        onError: (errors) => formulationErrors.value = errors,
    })
}

const previousRecipe = (sku: Sku, sample: Sample) => {
    const samples = sku.samples.filter((candidate) => candidate.formulation_id === sample.formulation_id)
    const index = samples.findIndex((candidate) => candidate.id === sample.id)

    return samples.slice(index + 1).find((candidate) => candidate.recipe)?.recipe?.ingredients ?? null
}

/* Start a sample */

const starting = ref<number | null>(null)

function startSample(sku: Sku, formulation: Formulation) {
    router.post(route(sku.routes.sample_store.name, sku.routes.sample_store.parameters), { product_development_formulation_id: formulation.id }, {
        preserveScroll: true,
        only,
        onStart: () => starting.value = formulation.id,
        onFinish: () => starting.value = null,
        onError: (errors) => formulationErrors.value = errors,
    })
}

const waitingLabel = (days: number | null) => days === null ? "—" : days === 1 ? ctrans("1 day") : ctrans(":count days", { count: String(days) })

/* Edit batch and notes */

const batchUnits = [
    { value: "kg", label: "kg" },
    { value: "g", label: "g" },
    { value: "L", label: "L" },
    { value: "ml", label: "ml" },
    { value: "units", label: ctrans("units") },
]
const batchLabel = (sample: Sample) => sample.batch_size === null ? ctrans("Not set") : [sample.batch_size.toLocaleString(), sample.batch_unit].filter(Boolean).join(" ")

const editDialog = ref<{ sample: Sample, batch_size: number | null, batch_unit: string, notes: string } | null>(null)
const editErrors = ref<Record<string, string>>({})
const saving = ref(false)

function openEdit(sample: Sample) {
    editErrors.value = {}
    editDialog.value = { sample, batch_size: sample.batch_size, batch_unit: batchUnits.some((unit) => unit.value === sample.batch_unit) ? sample.batch_unit! : "kg", notes: sample.notes ?? "" }
}

function saveEdit() {
    const dialog = editDialog.value
    if (!dialog) {
        return
    }
    router.patch(route(dialog.sample.routes.update.name, dialog.sample.routes.update.parameters), { batch_size: dialog.batch_size, batch_unit: dialog.batch_unit || null, notes: dialog.notes || null }, {
        preserveScroll: true,
        only,
        onStart: () => saving.value = true,
        onFinish: () => saving.value = false,
        onSuccess: () => editDialog.value = null,
        onError: (errors) => editErrors.value = errors,
    })
}

/* Ready for review */

const actionErrors = ref<Record<number, string>>({})
const working = ref<string | null>(null)

function submit(sample: Sample) {
    router.patch(route(sample.routes.submit.name, sample.routes.submit.parameters), {}, {
        preserveScroll: true,
        only,
        onStart: () => working.value = `submit-${sample.id}`,
        onFinish: () => working.value = null,
        onError: (errors) => actionErrors.value[sample.id] = firstError(errors),
    })
}

/* Review */

const reviewComments = ref<Record<number, string>>({})

function review(sample: Sample, operation: "approve" | "request_changes") {
    router.patch(route(sample.routes.review.name, sample.routes.review.parameters), { operation, comment: reviewComments.value[sample.id] || null }, {
        preserveScroll: true,
        only,
        onStart: () => working.value = `${operation}-${sample.id}`,
        onFinish: () => working.value = null,
        onSuccess: () => { reviewComments.value[sample.id] = ""; delete actionErrors.value[sample.id] },
        onError: (errors) => actionErrors.value[sample.id] = firstError(errors),
    })
}

/* Folding parts of a sample */

const openRecipe = ref<Record<number, boolean>>({})
const openRun = ref<Record<number, boolean>>({})
const isRunOpen = (sample: Sample) => openRun.value[sample.id] ?? (sample.can_edit || sample.can_review)
const toggleRun = (sample: Sample) => openRun.value[sample.id] = !isRunOpen(sample)
const openThread = ref<Record<number, boolean>>({})

/* Trial runs */

const jobDialog = ref<{ item_ids: number[], quantity: number | null, instructions: string } | null>(null)
const jobErrors = ref<Record<string, string>>({})
const sendingJob = ref(false)

function openJob(sku: Sku | null = null) {
    jobErrors.value = {}
    const target = sku ?? selected.value
    jobDialog.value = { item_ids: target ? [target.id] : [], quantity: null, instructions: "" }
}

function sendJob() {
    const dialog = jobDialog.value
    if (!dialog) {
        return
    }
    router.post(route(props.data.routes.sample_job.name, props.data.routes.sample_job.parameters), { ...dialog, instructions: dialog.instructions || null }, {
        preserveScroll: true,
        only: [...only, "tasks", "tabs"],
        onStart: () => sendingJob.value = true,
        onFinish: () => sendingJob.value = false,
        onSuccess: () => jobDialog.value = null,
        onError: (errors) => jobErrors.value = errors,
    })
}

/* Packaging & components */

const newComponent = ref<{ trade_unit_id: number | null, quantity: number | null, note: string }>({ trade_unit_id: null, quantity: null, note: "" })
const componentErrors = ref<Record<string, string>>({})
const componentDialog = ref<{ component: Component, trade_unit_id: number | null, quantity: number | null, note: string } | null>(null)

function addComponent(sku: Sku) {
    router.post(route(sku.routes.component_store.name, sku.routes.component_store.parameters), { ...newComponent.value, note: newComponent.value.note || null }, {
        preserveScroll: true,
        only,
        onStart: () => working.value = `component-${sku.id}`,
        onFinish: () => working.value = null,
        onSuccess: () => { newComponent.value = { trade_unit_id: null, quantity: null, note: "" }; componentErrors.value = {} },
        onError: (errors) => componentErrors.value = errors,
    })
}

function openComponent(component: Component) {
    componentErrors.value = {}
    componentDialog.value = { component, trade_unit_id: component.trade_unit_id, quantity: component.quantity, note: component.note ?? "" }
}

function saveComponent() {
    const dialog = componentDialog.value
    if (!dialog) {
        return
    }
    router.patch(route(dialog.component.routes.update.name, dialog.component.routes.update.parameters), { trade_unit_id: dialog.trade_unit_id, quantity: dialog.quantity, note: dialog.note || null }, {
        preserveScroll: true,
        only,
        onStart: () => saving.value = true,
        onFinish: () => saving.value = false,
        onSuccess: () => componentDialog.value = null,
        onError: (errors) => componentErrors.value = errors,
    })
}

function removeComponent(component: Component) {
    router.delete(route(component.routes.delete.name, component.routes.delete.parameters), {
        preserveScroll: true,
        only,
        onStart: () => working.value = `remove-${component.id}`,
        onFinish: () => working.value = null,
    })
}

function setApproval(component: Component, approved: boolean) {
    router.patch(route(component.routes.approval.name, component.routes.approval.parameters), { approved }, {
        preserveScroll: true,
        only,
        onStart: () => working.value = `approval-${component.id}`,
        onFinish: () => working.value = null,
    })
}

const formatQuantity = (quantity: number) => Number(quantity.toFixed(3)).toString()

const skusWithRecipe = computed(() => (props.data?.skus ?? []).filter((sku) => sku.artefact))
</script>

<template>
    <div v-if="data" id="pd-formulation" class="space-y-3">
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <header class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-gray-100 px-4 py-3">
                <FontAwesomeIcon icon="fal fa-flask" class="text-amber-600" fixed-width aria-hidden="true" />
                <h3 class="text-sm font-semibold text-gray-900">{{ ctrans("Formulation & trial runs") }}</h3>
                <span v-if="data.production" class="text-xs text-gray-500">{{ ctrans("Made by :production", { production: data.production.organisation ? `${data.production.name} · ${data.production.organisation}` : data.production.name }) }}</span>
                <span class="rounded-full px-2 py-0.5 text-xs font-medium tabular-nums" :class="data.progress.total && data.progress.done === data.progress.total ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">
                    {{ ctrans(":done of :total SKUs approved", { done: String(data.progress.done), total: String(data.progress.total) }) }}
                </span>
                <Button v-if="data.can_create_job && skusWithRecipe.length" type="tertiary" size="xs" :label="ctrans('Raise trial runs')" class="ml-auto" @click="openJob()">
                    <template #icon><FontAwesomeIcon icon="fal fa-tasks" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
                </Button>
            </header>

            <div v-if="data.skus.length" class="grid 2xl:grid-cols-[18rem_minmax(0,1fr)]">
                <ProductDevelopmentSkuList v-model="selectedId" :entries="entries" />

                <div v-if="selected" :key="selected.id" class="min-w-0 space-y-5 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-100 pb-3">
                        <div class="min-w-0">
                            <div class="whitespace-nowrap text-base font-semibold text-gray-900">{{ selected.code }}</div>
                            <div class="text-sm text-gray-600">{{ selected.name }}</div>
                            <div v-if="selected.artefact?.branched_from" class="whitespace-nowrap text-xs text-gray-400">{{ ctrans("branch of :code", { code: selected.artefact.branched_from }) }}</div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-medium" :class="productDevelopmentColor(selected.progress.final_status.color).active">{{ selected.progress.final_status.label }}</span>
                            <a v-if="selected.artefact" :href="route(selected.artefact.route.name, selected.artefact.route.parameters)" target="_blank" rel="noopener">
                                <Button type="tertiary" size="xs" :label="ctrans('Open recipe in Production')">
                                    <template #icon><FontAwesomeIcon icon="fal fa-external-link" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
                                </Button>
                            </a>
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-md border border-gray-200 bg-gray-200 text-sm sm:grid-cols-4 xl:grid-cols-8">
                        <div class="bg-white px-3 py-2">
                            <dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Stages") }}</dt>
                            <dd class="font-semibold tabular-nums text-gray-900">{{ selected.progress.stages }}</dd>
                        </div>
                        <div class="bg-white px-3 py-2">
                            <dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Current stage") }}</dt>
                            <dd class="truncate font-semibold text-gray-900">{{ selected.progress.current ?? "—" }}</dd>
                        </div>
                        <div class="bg-white px-3 py-2">
                            <dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Approved stages") }}</dt>
                            <dd class="font-semibold tabular-nums text-gray-900">{{ selected.progress.approved }} / {{ selected.progress.stages }}</dd>
                        </div>
                        <div class="bg-white px-3 py-2">
                            <dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Trial runs reviewed") }}</dt>
                            <dd class="font-semibold tabular-nums text-gray-900">{{ selected.progress.trial_runs }}</dd>
                        </div>
                        <div class="bg-white px-3 py-2">
                            <dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Samples being made") }}</dt>
                            <dd class="font-semibold tabular-nums text-gray-900">{{ selected.progress.awaiting }}</dd>
                        </div>
                        <div class="bg-white px-3 py-2">
                            <dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Longest wait") }}</dt>
                            <dd class="font-semibold tabular-nums" :class="(selected.progress.longest_wait ?? 0) > 7 ? 'text-amber-700' : 'text-gray-900'">{{ waitingLabel(selected.progress.longest_wait) }}</dd>
                        </div>
                        <div class="bg-white px-3 py-2">
                            <dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Artefacts") }}</dt>
                            <dd class="font-semibold tabular-nums text-gray-900">{{ selected.progress.artefacts }}</dd>
                        </div>
                        <div class="bg-white px-3 py-2">
                            <dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Production formula") }}</dt>
                            <dd class="truncate font-semibold" :class="selected.progress.final ? 'text-green-700' : 'text-gray-900'">{{ selected.progress.final ? ctrans("Trial run :number", { number: selected.progress.final }) : "—" }}</dd>
                        </div>
                    </dl>

                    <p v-if="!selected.artefact" class="rounded-md border border-dashed border-gray-300 px-3 py-2.5 text-xs text-gray-500">{{ selected.no_artefact_reason ?? ctrans("This SKU has no recipe in Production yet") }}</p>

                    <section v-if="selected.brief">
                        <h4 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("General brief") }}</h4>
                        <p class="whitespace-pre-line text-sm text-gray-800">{{ selected.brief }}</p>
                    </section>

                    <section class="space-y-4">
                        <div class="flex items-center justify-between gap-2">
                            <h4 class="flex items-center gap-1.5 text-sm font-semibold text-gray-900">
                                <FontAwesomeIcon icon="fal fa-flask" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Formulation stages (:count)", { count: String(selected.formulations.length) }) }}
                            </h4>
                            <Button v-if="selected.can_add_formulation" type="tertiary" size="xs" :label="ctrans('Add stage')" @click="openFormulation(selected, null)">
                                <template #icon><FontAwesomeIcon icon="fal fa-plus" class="text-gray-500" fixed-width aria-hidden="true" /></template>
                            </Button>
                        </div>

                        <p v-if="!selected.has_finished_stage" class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
                            {{ ctrans("No stage is the finished product yet. Set one stage's type to Finished product: its approved trial run becomes the production formula.") }}
                        </p>

                        <article v-for="formulation in selected.formulations" :key="formulation.id" class="overflow-hidden rounded-xl border border-gray-300 bg-white shadow-sm">
                            <header class="flex cursor-pointer flex-wrap items-center gap-x-3 gap-y-2 bg-gray-800 px-4 py-3 text-white" role="button" tabindex="0" :aria-expanded="isExpanded(formulation)" @click="toggleStage(formulation)" @keydown.enter="toggleStage(formulation)">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--app-accent)] text-[var(--app-accent-text)]">
                                    <FontAwesomeIcon icon="fal fa-flask" fixed-width aria-hidden="true" />
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="text-sm font-semibold">{{ ctrans("Stage :position – :name", { position: String(formulation.position), name: formulation.name }) }}</span>
                                        <span class="whitespace-nowrap rounded-full border border-white/30 px-2 py-0.5 text-xs text-gray-200">{{ formulation.type.label }}</span>
                                    </div>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-gray-300">
                                        <span class="whitespace-nowrap">{{ ctrans("Artisan: :name", { name: formulation.artisan ?? ctrans("not proposed") }) }}</span>
                                        <span class="text-gray-500">|</span>
                                        <span class="whitespace-nowrap">{{ ctrans("Artefact: :code", { code: formulation.artefact?.code ?? "—" }) }}<template v-if="formulation.artefact?.is_cancelled"> ({{ formulation.artefact.state }})</template></span>
                                    </div>
                                </div>
                                <div class="ml-auto flex flex-wrap items-center gap-1.5 text-xs">
                                    <span v-if="formulation.percentages?.pending" class="whitespace-nowrap rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-amber-800">{{ ctrans("Units to enter") }}</span>
                                    <span v-if="formulation.approved" class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-green-600 px-2.5 py-0.5 text-white">
                                        <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />{{ formulation.type.value === "finished_product" ? ctrans("Production formula") : ctrans("Approved formulation") }}
                                    </span>
                                    <span v-else-if="formulation.status" class="whitespace-nowrap rounded-full border px-2 py-0.5" :class="productDevelopmentColor(formulation.status.color).active">{{ formulation.status.label }}</span>
                                    <span v-else class="whitespace-nowrap rounded-full border border-white/40 px-2.5 py-0.5 text-gray-100">{{ ctrans("No trial run yet") }}</span>
                                    <span v-if="formulation.assignment && !formulation.approved" class="whitespace-nowrap rounded-full border px-2 py-0.5" :class="productDevelopmentColor(formulation.assignment.color).active">{{ formulation.assignment.label }}</span>
                                    <button v-if="formulation.can_edit" type="button" class="flex h-7 w-7 shrink-0 items-center justify-center rounded text-gray-300 hover:bg-white/10 hover:text-white" :aria-label="ctrans('Edit stage')" v-tooltip="ctrans('Edit stage')" @click.stop="openFormulation(selected, formulation)">
                                        <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                                    </button>
                                    <FontAwesomeIcon icon="fal fa-chevron-down" class="text-gray-300 transition-transform" :class="isExpanded(formulation) ? 'rotate-180' : ''" fixed-width aria-hidden="true" />
                                </div>
                            </header>

                            <div v-if="isExpanded(formulation)" class="space-y-4 bg-gray-50 p-4">
                                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.7fr)]">
                                    <div class="min-w-0 space-y-4">
                                        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                                            <h5 class="flex items-center gap-2 border-b border-gray-200 bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-800">
                                                <FontAwesomeIcon icon="fal fa-file-alt" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Formulation brief") }}
                                            </h5>
                                            <p class="whitespace-pre-line px-3 py-2.5 text-sm" :class="formulation.brief ? 'text-gray-800' : 'text-gray-400'">{{ formulation.brief || ctrans("No brief for this stage yet") }}</p>
                                        </section>

                                        <section v-if="formulation.type.value === 'finished_product' && blendsOf(selected).length" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                                            <h5 class="flex items-center gap-2 border-b border-gray-200 bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-800">
                                                <FontAwesomeIcon icon="fal fa-link" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Linked formulations / blends used") }}
                                            </h5>
                                            <ul class="space-y-2 p-3">
                                                <li v-for="blend in blendsOf(selected)" :key="blend.id" class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)] px-3 py-2">
                                                    <div class="min-w-0">
                                                        <div class="text-sm font-medium text-gray-900">{{ blend.name }} <span class="whitespace-nowrap text-xs text-gray-500">({{ ctrans("Stage :position", { position: String(blend.position) }) }})</span></div>
                                                        <a v-if="blend.artefact" :href="route(blend.artefact.route.name, blend.artefact.route.parameters)" target="_blank" rel="noopener" class="whitespace-nowrap text-xs text-gray-600 hover:text-[var(--app-accent)]">{{ blend.artefact.code }}</a>
                                                    </div>
                                                    <div class="flex flex-col items-end gap-1 text-xs">
                                                        <span v-if="blend.approved" class="inline-flex items-center gap-1 whitespace-nowrap rounded-full border border-green-300 bg-white px-2 py-0.5 text-green-700"><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />{{ ctrans("Approved") }}</span>
                                                        <span v-else class="whitespace-nowrap rounded-full border border-gray-300 bg-white px-2 py-0.5 text-gray-500">{{ ctrans("Not approved yet") }}</span>
                                                        <span v-if="blend.in_finished_recipe" class="whitespace-nowrap text-green-700">{{ ctrans("in the recipe") }}</span>
                                                        <span v-else-if="blend.approved" class="text-amber-700">{{ ctrans("add it to the recipe in Production") }}</span>
                                                    </div>
                                                </li>
                                            </ul>
                                        </section>

                                        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                                            <h5 class="flex items-center gap-2 border-b border-gray-200 bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-800">
                                                <FontAwesomeIcon icon="fal fa-check-circle" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Formulation status") }}
                                            </h5>
                                            <div class="px-3 py-2.5">
                                                <div v-if="formulation.approved" class="flex items-start gap-2">
                                                    <FontAwesomeIcon icon="fal fa-check-circle" class="mt-0.5 text-green-600" fixed-width aria-hidden="true" />
                                                    <div>
                                                        <div class="text-sm font-semibold text-gray-900">{{ ctrans("Approved") }}</div>
                                                        <div class="text-xs text-gray-600">{{ ctrans("Trial run :number, approved by :name on :date", { number: formulation.approved, name: formulation.approved_by ?? "—", date: formatDate(formulation.approved_at) }) }}</div>
                                                        <div v-if="formulation.percentages?.pending" class="mt-1 text-xs text-amber-700">{{ ctrans(":count ingredients wait for their units: the blend is a draft until they are entered", { count: String(formulation.percentages.pending) }) }}</div>
                                                    </div>
                                                </div>
                                                <div v-else class="flex flex-wrap items-center gap-2">
                                                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs" :class="formulation.status ? productDevelopmentColor(formulation.status.color).active : productDevelopmentColor('sky').active">
                                                        <FontAwesomeIcon icon="fal fa-hourglass-half" fixed-width aria-hidden="true" />{{ formulation.status?.label ?? ctrans("In process") }}
                                                    </span>
                                                    <span v-if="formulation.assignment" class="text-xs text-gray-600">{{ formulation.assignment.label }}</span>
                                                    <span v-if="!formulation.is_current" class="text-xs text-gray-500">{{ ctrans("Approve the earlier stages first") }}</span>
                                                </div>
                                            </div>
                                        </section>
                                    </div>

                                    <section class="min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white">
                                        <h5 class="flex flex-wrap items-center gap-x-2 border-b border-gray-200 bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-800">
                                            <FontAwesomeIcon icon="fal fa-cube" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Ingredients") }}
                                            <span class="text-xs font-normal text-gray-500">{{ ctrans("(recipe for this formulation)") }}</span>
                                        </h5>
                                        <div class="space-y-3 p-3">
                                            <p v-if="formulation.type.value === 'finished_product' && selected.recipe_changed" class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                                <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
                                                {{ ctrans("The recipe has changed since trial run :number was approved. A new trial run needs to be made and approved.", { number: selected.final ?? "" }) }}
                                            </p>
                                            <p v-for="blend in formulation.blends_made_on_sku" :key="blend.stage" class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                                <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
                                                {{ ctrans("Trial run :number of :stage was made on :code before that stage was a blend, so its ingredients may still be in :code's recipe below. Take them out of :code in Production and use the blend instead.", { number: blend.number, stage: blend.stage, code: formulation.artefact?.code ?? selected.code }) }}
                                            </p>

                                            <FormulationPercentages v-if="formulation.percentages" :key="formulation.percentages.trial_run" :percentages="formulation.percentages" withBatch />
                                            <div v-else-if="formulation.approved_recipe?.ingredients?.length">
                                                <p class="mb-1 text-xs text-gray-600">{{ ctrans("Approved in trial run :number", { number: formulation.approved ?? "" }) }}</p>
                                                <RecipeIngredients :ingredients="formulation.approved_recipe.ingredients" />
                                            </div>
                                            <RecipeIngredients v-else-if="formulation.recipe?.ingredients?.length" :ingredients="formulation.recipe.ingredients" />
                                            <div v-else class="flex flex-col items-center gap-1.5 rounded-md border border-dashed border-gray-300 px-3 py-5 text-center">
                                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500"><FontAwesomeIcon icon="fal fa-flask" fixed-width aria-hidden="true" /></span>
                                                <span class="text-sm font-medium text-gray-800">{{ ctrans("No ingredients added yet") }}</span>
                                                <span class="text-xs text-gray-500">{{ formulation.type.value === "finished_product" ? ctrans("Add the ingredients of the finished product (:name) to its recipe in Production.", { name: formulation.name }) : ctrans("They are entered with the first trial run's formulation.") }}</span>
                                            </div>

                                            <div v-if="formulation.artefact && !formulation.artefact.is_cancelled && (formulation.percentages || formulation.approved_recipe?.ingredients?.length)" class="border-t border-gray-100 pt-2">
                                                <p class="mb-1 text-xs font-medium text-gray-700">{{ formulation.percentages ? ctrans("Recipe in Production, per kg of blend") : ctrans("Recipe in Production now") }}</p>
                                                <RecipeIngredients :ingredients="formulation.recipe?.ingredients ?? []" />
                                            </div>
                                            <p v-if="formulation.artefact" class="text-xs text-gray-500">
                                                {{ ctrans("Edited in Production, under") }}
                                                <a :href="route(formulation.artefact.route.name, formulation.artefact.route.parameters)" target="_blank" rel="noopener" class="whitespace-nowrap font-medium text-gray-700 hover:text-[var(--app-accent)]">{{ formulation.artefact.code }}</a>
                                                · {{ formulation.artefact.state }}
                                            </p>
                                            <p v-if="formulation.type.value !== 'finished_product' && formulation.approved && formulation.artefact && !formulation.artefact.is_cancelled" class="text-xs" :class="formulation.in_finished_recipe ? 'text-green-700' : 'text-amber-700'">
                                                {{ formulation.in_finished_recipe ? ctrans("Used as an ingredient of the finished product") : formulation.percentages?.pending ? ctrans("Enter the missing units: then :code becomes an in-house ingredient for the finished product's recipe.", { code: formulation.artefact.code }) : ctrans("Approved: it is an in-house ingredient now. Add :code to the finished product's recipe in Production.", { code: formulation.artefact.code }) }}
                                            </p>
                                            <div v-if="!formulation.artefact || formulation.artefact.is_cancelled" class="space-y-2 text-xs text-gray-500">
                                                <p v-if="formulation.type.value === 'finished_product'">{{ selected.no_artefact_reason ?? ctrans("This SKU has no recipe in Production yet") }}</p>
                                                <p v-else-if="formulation.artefact">{{ ctrans(":code was cancelled. Create a new artefact for this stage and enter its recipe there.", { code: formulation.artefact.code }) }}</p>
                                                <p v-else-if="formulation.approved">{{ ctrans("Trial run :number was made on :code before this stage had its own artefact. Create its artefact and enter the approved recipe there; it is then an ingredient the finished product's recipe can use.", { number: formulation.approved, code: selected.artefact?.code ?? selected.code }) }}</p>
                                                <p v-else>{{ ctrans("This stage gets its own artefact, with its own recipe, when its first trial run is raised, or create it now to enter the recipe first.") }}</p>
                                                <p v-if="artefactErrors[formulation.id]" class="text-red-600">{{ artefactErrors[formulation.id] }}</p>
                                            </div>
                                            <Button v-if="formulation.can_create_artefact" type="tertiary" size="xs" :label="ctrans('Create artefact')" :loading="creatingArtefact === formulation.id" @click="createArtefact(formulation)">
                                                <template #icon><FontAwesomeIcon icon="fal fa-plus" class="text-gray-500" fixed-width aria-hidden="true" /></template>
                                            </Button>
                                        </div>
                                    </section>
                                </div>

                                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-[var(--app-accent-soft)] px-3 py-2">
                                        <h5 class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                                            <FontAwesomeIcon icon="fal fa-tasks" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Trial runs (:count)", { count: String(formulation.trial_runs.total) }) }}
                                        </h5>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span v-if="formulation.is_current && openSample(selected) && stageOf(selected, openSample(selected)!)?.id !== formulation.id" class="text-xs text-gray-600">{{ ctrans("A trial run of :stage is open; finish it first", { stage: stageOf(selected, openSample(selected)!)?.name ?? "" }) }}</span>
                                            <Button v-if="data.can_create_job && canRaiseIn(selected, formulation)" type="tertiary" size="xs" :label="ctrans('Raise trial run')" @click="openJob(selected)">
                                                <template #icon><FontAwesomeIcon icon="fal fa-tasks" class="text-gray-500" fixed-width aria-hidden="true" /></template>
                                            </Button>
                                            <Button v-if="selected.can_start_sample && canRaiseIn(selected, formulation)" type="tertiary" size="xs" :label="ctrans('Start a new trial run')" :loading="starting === formulation.id" @click="startSample(selected, formulation)">
                                                <template #icon><FontAwesomeIcon icon="fal fa-plus" class="text-gray-500" fixed-width aria-hidden="true" /></template>
                                            </Button>
                                        </div>
                                    </div>

                                    <div class="space-y-2 p-3">
                                        <p v-if="!samplesOf(selected, formulation).length" class="flex items-center justify-center gap-2 rounded-md border border-dashed border-gray-300 px-3 py-3 text-xs text-gray-500">
                                            <FontAwesomeIcon icon="fal fa-flask" fixed-width aria-hidden="true" />{{ ctrans("No trial runs yet") }}
                                        </p>

                                        <article v-for="sample in samplesOf(selected, formulation)" :key="sample.id" class="overflow-hidden rounded-lg border" :class="formulation.approved === sample.number ? 'border-green-200' : 'border-gray-200'">
                                            <header class="flex cursor-pointer flex-wrap items-center gap-x-4 gap-y-2 px-3 py-2.5" :class="formulation.approved === sample.number ? 'bg-green-50/60' : 'bg-gray-50'" role="button" tabindex="0" :aria-expanded="isRunOpen(sample)" @click="toggleRun(sample)" @keydown.enter="toggleRun(sample)">
                                                <span class="whitespace-nowrap text-sm font-semibold text-gray-900">{{ ctrans("Trial run :number", { number: sample.number }) }}</span>
                                                <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2 py-0.5 text-xs" :class="productDevelopmentColor(sample.status.color).active">
                                                    <FontAwesomeIcon :icon="sample.status.icon" fixed-width aria-hidden="true" />{{ sample.status.label }}
                                                </span>
                                                <span v-if="formulation.approved === sample.number" class="whitespace-nowrap rounded-full bg-green-600 px-2 py-0.5 text-xs text-white">{{ formulation.type.value === "finished_product" ? ctrans("Production formula") : ctrans("Approved formulation") }}</span>
                                                <dl class="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs">
                                                    <div class="flex items-center gap-1.5">
                                                        <FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" fixed-width aria-hidden="true" />
                                                        <dt class="text-gray-500">{{ ctrans("Requested") }}</dt>
                                                        <dd class="whitespace-nowrap text-gray-900">{{ formatDate(sample.created_at) || "—" }}</dd>
                                                    </div>
                                                    <div class="flex items-center gap-1.5">
                                                        <FontAwesomeIcon icon="fal fa-flask" class="text-gray-400" fixed-width aria-hidden="true" />
                                                        <dt class="text-gray-500">{{ ctrans("Sample done") }}</dt>
                                                        <dd class="whitespace-nowrap" :class="sample.ready_at ? 'text-gray-900' : 'text-gray-400'">{{ sample.ready_at ? formatDate(sample.ready_at) : ctrans("Not yet") }}</dd>
                                                    </div>
                                                    <div class="flex items-center gap-1.5">
                                                        <FontAwesomeIcon icon="fal fa-hourglass-half" class="text-gray-400" fixed-width aria-hidden="true" />
                                                        <dt class="text-gray-500">{{ sample.ready_at ? ctrans("Waited") : ctrans("Waiting") }}</dt>
                                                        <dd class="whitespace-nowrap tabular-nums" :class="!sample.ready_at && (sample.waiting_days ?? 0) > 7 ? 'text-amber-700' : 'text-gray-900'">{{ waitingLabel(sample.waiting_days) }}</dd>
                                                    </div>
                                                    <div class="flex items-center gap-1.5">
                                                        <FontAwesomeIcon icon="fal fa-industry" class="text-gray-400" fixed-width aria-hidden="true" />
                                                        <dt class="text-gray-500">{{ ctrans("Made on") }}</dt>
                                                        <dd class="whitespace-nowrap font-medium text-gray-900">{{ sample.artefact ?? "—" }}</dd>
                                                    </div>
                                                </dl>
                                                <FontAwesomeIcon icon="fal fa-chevron-down" class="ml-auto text-gray-500 transition-transform" :class="isRunOpen(sample) ? 'rotate-180' : ''" fixed-width aria-hidden="true" />
                                            </header>

                                            <div v-if="isRunOpen(sample)" class="space-y-2.5 border-t border-gray-100 px-3 py-2.5 text-sm">
                                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                                                    <span>{{ ctrans("Made by :name", { name: sample.made_by ?? "—" }) }}</span>
                                                    <component :is="sample.job.route ? 'a' : 'span'" v-if="sample.job" :href="sample.job.route ? route(sample.job.route.name, sample.job.route.parameters) : undefined" class="whitespace-nowrap" :class="{ 'hover:text-[var(--app-accent)]': sample.job.route }">
                                                        <FontAwesomeIcon icon="fal fa-tasks" fixed-width aria-hidden="true" />
                                                        {{ sample.job.reference }} · <span :class="{ 'text-amber-700': sample.job.waiting }">{{ sample.job.status }}</span> · {{ ctrans(":received of :quantity at the office", { received: String(sample.job.received), quantity: String(sample.job.quantity) }) }}<template v-if="sample.job.assignee"> · {{ sample.job.assignee }}</template>
                                                    </component>
                                                </div>
        <div class="flex items-start gap-3">
                                                    <dl class="grid min-w-0 flex-1 gap-x-6 gap-y-2 sm:grid-cols-[10rem_minmax(0,1fr)]">
                                                        <div>
                                                            <dt class="text-xs text-gray-500">{{ ctrans("Batch size") }}</dt>
                                                            <dd class="font-medium text-gray-900">{{ batchLabel(sample) }}</dd>
                                                        </div>
                                                        <div class="min-w-0">
                                                            <dt class="text-xs text-gray-500">{{ ctrans("Notes on the formulation") }}</dt>
                                                            <dd class="whitespace-pre-line text-gray-800" :class="sample.notes ? '' : 'text-gray-400'">{{ sample.notes || ctrans("No notes") }}</dd>
                                                        </div>
                                                    </dl>
                                                    <button v-if="sample.can_edit" type="button" class="flex h-7 w-7 shrink-0 items-center justify-center rounded text-[var(--app-accent)] hover:bg-[var(--app-accent-soft)]" :aria-label="ctrans('Edit batch size and notes')" v-tooltip="ctrans('Edit batch size and notes')" @click="openEdit(sample)">
                                                        <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                                                    </button>
                                                </div>

                                                <div v-if="sample.photos.length || sample.can_edit">
                                                    <p class="mb-1 text-xs text-gray-500">{{ ctrans("Photos") }}</p>
                                                    <ProductDevelopmentPhotoStrip :photos="sample.photos" :uploadRoute="sample.can_edit ? sample.routes.photos : null" />
                                                </div>

                                                <p v-if="sample.reviewed_at" class="text-xs text-gray-600">
                                                    {{ sample.status.value === "approved"
                                                        ? ctrans("Approved by :name on :date", { name: sample.reviewed_by ?? "", date: formatDate(sample.reviewed_at) })
                                                        : ctrans("Changes asked by :name on :date", { name: sample.reviewed_by ?? "", date: formatDate(sample.reviewed_at) }) }}<template v-if="sample.review_comment">: <span class="text-gray-800">{{ sample.review_comment }}</span></template>
                                                </p>

                                                <p v-if="(sample.can_edit && sample.artefact === formulation.artefact?.code && !(formulation.recipe?.ingredients ?? []).length) || (sample.can_review && !(sample.recipe?.ingredients ?? []).length)" class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
                                                    {{ sample.can_edit ? ctrans("The recipe in Production has no ingredients yet. Add them before sending this trial run for review.") : ctrans("This trial run was made from a recipe with no ingredients.") }}
                                                </p>

                                                <div v-if="sample.can_edit" class="flex flex-wrap items-center gap-2">
                                                    <span class="text-xs text-gray-500">{{ ctrans("When the trial run is made, send it for review. Its recipe is kept as it is then.") }}</span>
                                                    <Button type="tertiary" size="xs" :label="ctrans('Ready for review')" class="ml-auto" :loading="working === `submit-${sample.id}`" @click="submit(sample)">
                                                        <template #icon><FontAwesomeIcon icon="fal fa-paper-plane" class="text-green-600" fixed-width aria-hidden="true" /></template>
                                                    </Button>
                                                </div>

                                                <div v-if="sample.can_review" class="space-y-2 rounded-md bg-gray-50 p-2.5">
                                                    <Textarea v-model="reviewComments[sample.id]" rows="2" autoResize class="w-full text-sm" :placeholder="ctrans('Comment (required to ask for changes)')" />
                                                    <div class="flex justify-end gap-2">
                                                        <Button type="tertiary" size="xs" :label="ctrans('Changes required')" :loading="working === `request_changes-${sample.id}`" @click="review(sample, 'request_changes')">
                                                            <template #icon><FontAwesomeIcon icon="fal fa-undo" class="text-orange-500" fixed-width aria-hidden="true" /></template>
                                                        </Button>
                                                        <Button type="tertiary" size="xs" :label="ctrans('Approve')" :loading="working === `approve-${sample.id}`" @click="review(sample, 'approve')">
                                                            <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                                                        </Button>
                                                    </div>
                                                </div>
                                                <p v-if="actionErrors[sample.id]" class="text-xs text-red-600">{{ actionErrors[sample.id] }}</p>

                                                <div class="flex flex-wrap gap-3 border-t border-gray-100 pt-2">
                                                    <button v-if="sample.recipe" type="button" class="flex items-center gap-1.5 text-xs text-gray-600 hover:text-[var(--app-accent)]" :aria-expanded="!!openRecipe[sample.id]" @click="openRecipe[sample.id] = !openRecipe[sample.id]">
                                                        <FontAwesomeIcon icon="fal fa-chevron-down" class="transition-transform" :class="openRecipe[sample.id] ? '' : '-rotate-90'" fixed-width aria-hidden="true" />
                                                        {{ ctrans("Recipe of this trial run") }}
                                                    </button>
                                                    <button type="button" class="flex items-center gap-1.5 text-xs text-gray-600 hover:text-[var(--app-accent)]" :aria-expanded="!!openThread[sample.id]" @click="openThread[sample.id] = !openThread[sample.id]">
                                                        <FontAwesomeIcon icon="fal fa-comments" fixed-width aria-hidden="true" />
                                                        {{ ctrans("Discussion (:count)", { count: String(sample.comments) }) }}
                                                    </button>
                                                </div>
                                                <div v-if="sample.recipe && openRecipe[sample.id]">
                                                    <FormulationPercentages v-if="sample.recipe.formulation" :percentages="sample.recipe.formulation" class="mb-2" />
                                                    <RecipeIngredients :ingredients="sample.recipe.ingredients" :previous="previousRecipe(selected, sample)" />
                                                    <p v-if="previousRecipe(selected, sample)" class="mt-1 text-xs text-gray-400">{{ ctrans("Compared with the previous trial run: new in green, changed in amber, removed in red.") }}</p>
                                                </div>
                                                <SampleThread v-if="openThread[sample.id]" :commentsRoute="sample.routes.comments" :sampleId="sample.id" :count="sample.comments" />
                                            </div>
                                        </article>
                                    </div>
                                </section>
                            </div>
                        </article>
                    </section>

                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <h4 class="flex flex-wrap items-center gap-x-2 border-b border-gray-200 bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-800">
                            <FontAwesomeIcon icon="fal fa-box-open" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Packaging & components") }}
                            <span class="text-xs font-normal text-gray-500">{{ ctrans("(bought in, not part of the formulation)") }}</span>
                        </h4>
                        <div class="p-3">
                            <div class="overflow-x-auto rounded-md border border-gray-200">
                                <table class="w-full text-sm">
                                    <thead class="whitespace-nowrap bg-gray-50 text-left text-xs text-gray-500">
                                        <tr>
                                            <th class="px-3 py-1.5 font-medium">{{ ctrans("Component") }}</th>
                                            <th class="px-3 py-1.5 text-right font-medium">{{ ctrans("Needed to start") }}</th>
                                            <th class="px-3 py-1.5 font-medium">{{ ctrans("Status") }}</th>
                                            <th class="px-3 py-1.5" />
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="component in selected.components" :key="component.id">
                                            <td class="px-3 py-1.5">
                                                <a :href="route(component.route.name, component.route.parameters)" class="whitespace-nowrap font-medium text-gray-900 hover:text-[var(--app-accent)]">{{ component.code }}</a>
                                                <span class="ml-1.5 text-gray-600">{{ component.name }}</span>
                                                <div v-if="component.note" class="text-xs text-gray-500">{{ component.note }}</div>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums">{{ formatQuantity(component.quantity) }}</td>
                                            <td class="px-3 py-1.5">
                                                <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-xs" :class="productDevelopmentColor(component.status.color).active" v-tooltip="component.approved_by ? ctrans('Approved by :name on :date', { name: component.approved_by, date: formatDate(component.approved_at) }) : undefined">
                                                    <FontAwesomeIcon :icon="component.status.icon" fixed-width aria-hidden="true" />
                                                    {{ component.status.label }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-1.5">
                                                <div class="flex items-center justify-end gap-1">
                                                    <Button v-if="component.can_approve" type="tertiary" size="xs" :label="component.status.value === 'approved' ? ctrans('Take back') : ctrans('Approve')" :loading="working === `approval-${component.id}`" @click="setApproval(component, component.status.value !== 'approved')">
                                                        <template #icon><FontAwesomeIcon :icon="component.status.value === 'approved' ? 'fal fa-undo' : 'fal fa-check'" :class="component.status.value === 'approved' ? 'text-orange-500' : 'text-green-600'" fixed-width aria-hidden="true" /></template>
                                                    </Button>
                                                    <button v-if="component.can_edit" type="button" class="flex h-7 w-7 items-center justify-center rounded text-[var(--app-accent)] hover:bg-[var(--app-accent-soft)]" :aria-label="ctrans('Change component')" v-tooltip="ctrans('Change component')" @click="openComponent(component)">
                                                        <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                                                    </button>
                                                    <button v-if="component.can_edit" type="button" class="flex h-7 w-7 items-center justify-center rounded text-red-500 hover:bg-red-50" :aria-label="ctrans('Remove component')" v-tooltip="ctrans('Remove component')" :disabled="working === `remove-${component.id}`" @click="removeComponent(component)">
                                                        <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!selected.components.length">
                                            <td colspan="4" class="px-3 py-3 text-center text-xs text-gray-400">{{ ctrans("No components yet") }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="selected.can_add_component" class="mt-2 grid gap-2 sm:grid-cols-[minmax(0,1fr)_8rem_minmax(0,12rem)_auto]">
                                <ProductDevelopmentRemoteSelect v-model="newComponent.trade_unit_id" :searchRoute="data.component_search" :placeholder="ctrans('Search a component by code')" />
                                <InputNumber v-model="newComponent.quantity" :min="0" :maxFractionDigits="3" :placeholder="ctrans('Quantity')" class="w-full" inputClass="w-full" />
                                <InputText v-model="newComponent.note" maxlength="255" :placeholder="ctrans('Note (optional)')" class="w-full" />
                                <Button type="tertiary" :label="ctrans('Add')" :loading="working === `component-${selected.id}`" :disabled="!newComponent.trade_unit_id || !newComponent.quantity" @click="addComponent(selected)">
                                    <template #icon><FontAwesomeIcon icon="fal fa-plus" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
                                </Button>
                            </div>
                            <p v-if="!componentDialog && Object.keys(componentErrors).length" class="mt-1 text-xs text-red-600">{{ firstError(componentErrors) }}</p>
                        </div>
                    </section>
                </div>
            </div>
            <p v-else class="px-4 py-6 text-center text-sm text-gray-400">{{ ctrans("No SKUs in this development yet") }}</p>
        </section>

        <Dialog :visible="!!editDialog" modal :header="editDialog ? ctrans('Trial run :number', { number: editDialog.sample.number }) : ''" :style="{ width: '28rem' }" :breakpoints="{ '640px': '94vw' }" @update:visible="(visible) => !visible && (editDialog = null)">
            <div v-if="editDialog" class="space-y-3 text-sm">
                <div class="grid grid-cols-[1fr_7rem] gap-2">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Batch size") }}</label>
                        <InputNumber v-model="editDialog.batch_size" :minFractionDigits="0" :maxFractionDigits="3" :min="0" class="w-full" inputClass="w-full" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Unit") }}</label>
                        <Select v-model="editDialog.batch_unit" :options="batchUnits" optionLabel="label" optionValue="value" class="w-full" overlayClass="text-sm" />
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Notes on the formulation") }}</label>
                    <Textarea v-model="editDialog.notes" rows="3" autoResize class="w-full" :placeholder="ctrans('e.g. Fragrance raised from 2% to 3%')" />
                </div>
                <p v-if="Object.keys(editErrors).length" class="text-xs text-red-600">{{ firstError(editErrors) }}</p>
            </div>
            <template #footer>
                <div class="flex w-full gap-2">
                    <Button type="tertiary" :label="ctrans('Cancel')" class="shrink-0" @click="editDialog = null">
                        <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="primary" full :label="ctrans('Save')" :loading="saving" @click="saveEdit">
                        <template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </template>
        </Dialog>

        <Dialog :visible="!!componentDialog" modal :header="componentDialog ? componentDialog.component.code : ''" :style="{ width: '30rem' }" :breakpoints="{ '640px': '94vw' }" @update:visible="(visible) => !visible && (componentDialog = null)">
            <div v-if="componentDialog" class="space-y-3 text-sm">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Replace with another component") }}</label>
                    <ProductDevelopmentRemoteSelect v-model="componentDialog.trade_unit_id" :searchRoute="data.component_search" :placeholder="componentDialog.component.code" />
                    <p v-if="componentDialog.component.status.value === 'approved' && componentDialog.trade_unit_id !== componentDialog.component.trade_unit_id" class="mt-1 text-xs text-amber-700">{{ ctrans("The new component will need approval before it can be ordered.") }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Needed to start production") }}</label>
                    <InputNumber v-model="componentDialog.quantity" :min="0" :maxFractionDigits="3" class="w-full" inputClass="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Note") }}</label>
                    <InputText v-model="componentDialog.note" maxlength="255" class="w-full" />
                </div>
                <p v-if="Object.keys(componentErrors).length" class="text-xs text-red-600">{{ firstError(componentErrors) }}</p>
            </div>
            <template #footer>
                <div class="flex w-full gap-2">
                    <Button type="tertiary" :label="ctrans('Cancel')" class="shrink-0" @click="componentDialog = null">
                        <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="primary" full :label="ctrans('Save')" :loading="saving" @click="saveComponent">
                        <template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </template>
        </Dialog>

        <Dialog :visible="!!formulationDialog" modal :header="formulationDialog?.formulation ? ctrans('Edit stage') : ctrans('Add stage')" :style="{ width: '28rem' }" :breakpoints="{ '640px': '94vw' }" @update:visible="(visible) => !visible && (formulationDialog = null)">
            <div v-if="formulationDialog" class="space-y-3 text-sm">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Name") }} <span class="text-red-500">*</span></label>
                    <InputText v-model="formulationDialog.name" maxlength="120" class="w-full" :placeholder="ctrans('e.g. EO Blend – Citrus Kick')" :invalid="!!formulationErrors.name" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Formulation type") }} <span class="text-red-500">*</span></label>
                    <Select v-model="formulationDialog.type" :options="typeOptions" optionLabel="label" optionValue="value" class="w-full" overlayClass="text-sm" :disabled="!!formulationDialog.formulation && !formulationDialog.formulation.can_change_type" :invalid="!!formulationErrors.type" />
                    <p class="mt-1 text-xs text-gray-500">{{ formulationDialog.type === "finished_product"
                        ? ctrans("The SKU itself: its approved trial run becomes the production formula. A SKU has one finished product stage.")
                        : ctrans("Made on its own artefact with its own recipe. Once approved it is an in-house ingredient the finished product's recipe can use. It goes before the finished product stage, which is always the last.") }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Formulation brief") }}</label>
                    <Textarea v-model="formulationDialog.brief" rows="3" autoResize maxlength="5000" class="w-full" :placeholder="ctrans('What this stage has to achieve')" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Artisan for the trial runs") }}</label>
                    <Select v-model="formulationDialog.artisan_id" :options="data.artisan_options" optionLabel="name" optionValue="id" filter showClear class="w-full" overlayClass="text-sm" :placeholder="ctrans('Let the production manager choose')" />
                    <p class="mt-1 text-xs text-gray-500">{{ ctrans("The production manager approves the artisan when releasing the trial run.") }}</p>
                </div>
                <p v-if="Object.keys(formulationErrors).length" class="text-xs text-red-600">{{ firstError(formulationErrors) }}</p>
            </div>
            <template #footer>
                <div class="flex w-full gap-2">
                    <Button v-if="formulationDialog?.formulation?.can_delete" type="tertiary" :label="ctrans('Remove stage')" class="shrink-0" @click="removeFormulation(formulationDialog.formulation)">
                        <template #icon><FontAwesomeIcon icon="fal fa-trash-alt" class="text-amber-600" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="tertiary" :label="ctrans('Cancel')" class="shrink-0" @click="formulationDialog = null">
                        <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="primary" full :label="ctrans('Save')" :loading="saving" :disabled="!formulationDialog?.name.trim()" @click="selected && saveFormulation(selected)">
                        <template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </template>
        </Dialog>

        <Dialog :visible="!!jobDialog" modal :header="ctrans('Raise trial runs')" :style="{ width: '32rem' }" :breakpoints="{ '640px': '94vw' }" @update:visible="(visible) => !visible && (jobDialog = null)">
            <div v-if="jobDialog" class="space-y-3 text-sm">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("SKUs to make a trial run of") }} <span class="text-red-500">*</span></label>
                    <div class="max-h-48 space-y-1 overflow-y-auto rounded-md border border-gray-200 p-2 [scrollbar-width:thin]">
                        <label v-for="sku in skusWithRecipe" :key="sku.id" class="flex cursor-pointer items-center gap-2 rounded px-1 py-1 hover:bg-gray-50">
                            <Checkbox v-model="jobDialog.item_ids" :value="sku.id" :inputId="`job-sku-${sku.id}`" />
                            <span class="whitespace-nowrap font-medium text-gray-900">{{ sku.code }}</span>
                            <span class="truncate text-gray-500">{{ sku.name }}</span>
                        </label>
                    </div>
                    <p v-if="jobErrors.item_ids" class="mt-1 text-xs text-red-600">{{ jobErrors.item_ids }}</p>
                </div>
                <div class="sm:w-1/2">
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Units per SKU") }} <span class="text-red-500">*</span></label>
                    <InputNumber v-model="jobDialog.quantity" :min="1" :maxFractionDigits="0" class="w-full" inputClass="w-full" :invalid="!!jobErrors.quantity" />
                    <p v-if="jobErrors.quantity" class="mt-1 text-xs text-red-600">{{ jobErrors.quantity }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Instructions") }}</label>
                    <Textarea v-model="jobDialog.instructions" rows="2" autoResize maxlength="200" class="w-full" :placeholder="ctrans('What should the trial run show or test?')" />
                </div>
                <p class="text-xs text-gray-500">{{ ctrans("Each SKU gets its next trial run, in the stage being worked on, and a draft job order with the stage's proposed artisan. The production manager approves the artisan and releases it to the floor; what is made comes to the development office.") }}</p>
                <p v-if="Object.keys(jobErrors).length && !jobErrors.item_ids && !jobErrors.quantity" class="text-xs text-red-600">{{ firstError(jobErrors) }}</p>
            </div>
            <template #footer>
                <div class="flex w-full gap-2">
                    <Button type="tertiary" :label="ctrans('Cancel')" class="shrink-0" @click="jobDialog = null">
                        <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="primary" full :label="ctrans('Raise trial runs')" :loading="sendingJob" @click="sendJob">
                        <template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
