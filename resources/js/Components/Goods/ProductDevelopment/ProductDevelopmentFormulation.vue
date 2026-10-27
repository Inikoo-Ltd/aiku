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
import DatePicker from "primevue/datepicker"
import Checkbox from "primevue/checkbox"
import Button from "@/Components/Elements/Buttons/Button.vue"
import RecipeIngredients, { type Ingredient } from "@/Components/Goods/ProductDevelopment/Formulation/RecipeIngredients.vue"
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
import { faFlask, faExternalLink, faPlus, faPencil, faCheck, faUndo, faTimes, faPaperPlane, faComments, faChevronDown, faExclamationTriangle, faImage, faTasks, faCheckCircle, faTrashAlt, faBoxOpen } from "@fal"
library.add(faFlask, faExternalLink, faPlus, faPencil, faCheck, faUndo, faTimes, faPaperPlane, faComments, faChevronDown, faExclamationTriangle, faImage, faTasks, faCheckCircle, faTrashAlt, faBoxOpen)

interface Status {
    value: string
    label: string
    color: string
    icon: string
}

interface Recipe {
    steps: { position: number, name: string, raw_materials: Ingredient[] }[]
    ingredients: Ingredient[]
}

interface Sample {
    id: number
    number: string
    status: Status
    recipe: Recipe | null
    batch_size: number | null
    batch_unit: string | null
    notes: string | null
    made_by: string | null
    created_at: string | null
    ready_at: string | null
    review_comment: string | null
    reviewed_by: string | null
    reviewed_at: string | null
    comments: number
    job: { reference: string, status: string, assignee: string | null, due_at: string | null, route: routeType } | null
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
    artefact: { code: string, name: string, state: string, route: routeType } | null
    recipe: Recipe | null
    status: Status | null
    final: string | null
    recipe_changed: boolean
    can_start_sample: boolean
    samples: Sample[]
    components: Component[]
    can_add_component: boolean
    routes: { sample_store: routeType, component_store: routeType }
}

const props = defineProps<{
    data: {
        production: { name: string, organisation: string | null } | null
        skus: Sku[]
        progress: { done: number, total: number }
        can_manage: boolean
        can_review: boolean
        can_create_job: boolean
        can_approve_components: boolean
        component_search: routeType
        people: { value: number, label: string }[]
        priorities: { value: string, label: string }[]
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
    done: sku.final ? 1 : 0,
    total: 1,
    badge: sku.final
        ? { label: ctrans("Sample :number approved", { number: sku.final }), class: productDevelopmentColor("green").active }
        : sku.status
            ? { label: sku.status.label, class: productDevelopmentColor(sku.status.color).active }
            : { label: ctrans("No sample yet"), class: productDevelopmentColor("gray").active },
})))

const previousRecipe = (sku: Sku, sample: Sample) => {
    const index = sku.samples.findIndex((candidate) => candidate.id === sample.id)

    return sku.samples.slice(index + 1).find((candidate) => candidate.recipe)?.recipe?.ingredients ?? null
}

/* Start a sample */

const starting = ref(false)

function startSample(sku: Sku) {
    router.post(route(sku.routes.sample_store.name, sku.routes.sample_store.parameters), {}, {
        preserveScroll: true,
        only,
        onStart: () => starting.value = true,
        onFinish: () => starting.value = false,
    })
}

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
const openThread = ref<Record<number, boolean>>({})

/* Sample job */

const jobDialog = ref<{ item_ids: number[], assignee_id: number | null, due_at: Date | null, priority: string, instructions: string } | null>(null)
const jobErrors = ref<Record<string, string>>({})
const sendingJob = ref(false)

function openJob() {
    jobErrors.value = {}
    jobDialog.value = { item_ids: selected.value ? [selected.value.id] : [], assignee_id: null, due_at: null, priority: "normal", instructions: "" }
}

function sendJob() {
    const dialog = jobDialog.value
    if (!dialog) {
        return
    }
    const dueAt = dialog.due_at ? `${dialog.due_at.getFullYear()}-${String(dialog.due_at.getMonth() + 1).padStart(2, "0")}-${String(dialog.due_at.getDate()).padStart(2, "0")}` : null
    router.post(route(props.data.routes.sample_job.name, props.data.routes.sample_job.parameters), { ...dialog, due_at: dueAt, instructions: dialog.instructions || null }, {
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
    <div v-if="data" class="space-y-3">
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <header class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-gray-100 px-4 py-3">
                <FontAwesomeIcon icon="fal fa-flask" class="text-amber-600" fixed-width aria-hidden="true" />
                <h3 class="text-sm font-semibold text-gray-900">{{ ctrans("Formulation & samples") }}</h3>
                <span v-if="data.production" class="text-xs text-gray-500">{{ ctrans("Made by :production", { production: data.production.organisation ? `${data.production.name} · ${data.production.organisation}` : data.production.name }) }}</span>
                <span class="rounded-full px-2 py-0.5 text-xs font-medium tabular-nums" :class="data.progress.total && data.progress.done === data.progress.total ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">
                    {{ ctrans(":done of :total SKUs approved", { done: String(data.progress.done), total: String(data.progress.total) }) }}
                </span>
                <Button v-if="data.can_create_job && skusWithRecipe.length" type="tertiary" size="xs" :label="ctrans('Create sample job')" class="ml-auto" @click="openJob">
                    <template #icon><FontAwesomeIcon icon="fal fa-tasks" class="text-[--app-accent]" fixed-width aria-hidden="true" /></template>
                </Button>
            </header>

            <div v-if="data.skus.length" class="grid 2xl:grid-cols-[18rem_minmax(0,1fr)]">
                <ProductDevelopmentSkuList v-model="selectedId" :entries="entries" />

                <div v-if="selected" :key="selected.id" class="min-w-0 space-y-5 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-100 pb-3">
                        <div class="min-w-0">
                            <div class="whitespace-nowrap text-base font-semibold text-gray-900">{{ selected.code }}</div>
                            <div class="text-sm text-gray-600">{{ selected.name }}</div>
                        </div>
                        <a v-if="selected.artefact" :href="route(selected.artefact.route.name, selected.artefact.route.parameters)" target="_blank" rel="noopener">
                            <Button type="tertiary" size="xs" :label="ctrans('Open recipe in Production')">
                                <template #icon><FontAwesomeIcon icon="fal fa-external-link" class="text-[--app-accent]" fixed-width aria-hidden="true" /></template>
                            </Button>
                        </a>
                    </div>

                    <section v-if="selected.brief">
                        <h4 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Brief") }}</h4>
                        <p class="whitespace-pre-line text-sm text-gray-800">{{ selected.brief }}</p>
                    </section>

                    <section>
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Recipe in Production now") }}</h4>
                        <p v-if="!selected.artefact" class="rounded-md border border-dashed border-gray-300 px-3 py-2.5 text-xs text-gray-500">{{ ctrans("This SKU has no recipe in Production yet") }}</p>
                        <template v-else>
                            <p v-if="selected.recipe_changed" class="mb-2 flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
                                {{ ctrans("The recipe has changed since sample :number was approved. A new sample needs to be made and approved.", { number: selected.final ?? "" }) }}
                            </p>
                            <RecipeIngredients :ingredients="selected.recipe?.ingredients ?? []" />
                            <p class="mt-1 text-xs text-gray-400">{{ ctrans("Edited in Production, under :artefact", { artefact: selected.artefact.code }) }}</p>
                        </template>
                    </section>

                    <section>
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Packaging & components") }}</h4>
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
                                            <a :href="route(component.route.name, component.route.parameters)" class="whitespace-nowrap font-medium text-gray-900 hover:text-[--app-accent]">{{ component.code }}</a>
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
                                                <button v-if="component.can_edit" type="button" class="flex h-7 w-7 items-center justify-center rounded text-[--app-accent] hover:bg-[--app-accent-soft]" :aria-label="ctrans('Change component')" v-tooltip="ctrans('Change component')" @click="openComponent(component)">
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
                                <template #icon><FontAwesomeIcon icon="fal fa-plus" class="text-[--app-accent]" fixed-width aria-hidden="true" /></template>
                            </Button>
                        </div>
                        <p v-if="!componentDialog && Object.keys(componentErrors).length" class="mt-1 text-xs text-red-600">{{ firstError(componentErrors) }}</p>
                    </section>

                    <section class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Samples") }}</h4>
                            <Button v-if="selected.can_start_sample" type="tertiary" size="xs" :label="ctrans('Start a new sample')" :loading="starting" @click="startSample(selected)">
                                <template #icon><FontAwesomeIcon icon="fal fa-plus" class="text-[--app-accent]" fixed-width aria-hidden="true" /></template>
                            </Button>
                        </div>

                        <p v-if="!selected.samples.length" class="rounded-md border border-dashed border-gray-300 px-3 py-3 text-center text-xs text-gray-400">{{ ctrans("No samples yet") }}</p>

                        <article v-for="sample in selected.samples" :key="sample.id" class="rounded-lg border border-gray-200">
                            <header class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2.5">
                                <span class="text-sm font-semibold text-gray-900">{{ ctrans("Sample :number", { number: sample.number }) }}</span>
                                <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-medium" :class="productDevelopmentColor(sample.status.color).active">
                                    <FontAwesomeIcon :icon="sample.status.icon" fixed-width aria-hidden="true" />
                                    {{ sample.status.label }}
                                </span>
                                <span v-if="selected.final === sample.number" class="rounded-full bg-green-600 px-2 py-0.5 text-xs font-medium text-white">{{ ctrans("Final formulation") }}</span>
                                <span class="text-xs text-gray-500">
                                    {{ ctrans("Made by :name", { name: sample.made_by ?? "—" }) }}
                                    <template v-if="sample.ready_at"> · {{ ctrans("ready :date", { date: formatDate(sample.ready_at) }) }}</template>
                                </span>
                                <a v-if="sample.job" :href="route(sample.job.route.name, sample.job.route.parameters)" class="ml-auto text-xs text-gray-500 hover:text-[--app-accent]">
                                    <FontAwesomeIcon icon="fal fa-tasks" fixed-width aria-hidden="true" />
                                    {{ sample.job.reference }}<template v-if="sample.job.due_at"> · {{ ctrans("due :date", { date: formatDate(sample.job.due_at) }) }}</template>
                                </a>
                            </header>

                            <div class="space-y-2.5 border-t border-gray-100 px-3 py-2.5 text-sm">
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
                                    <button v-if="sample.can_edit" type="button" class="flex h-7 w-7 shrink-0 items-center justify-center rounded text-[--app-accent] hover:bg-[--app-accent-soft]" :aria-label="ctrans('Edit batch size and notes')" v-tooltip="ctrans('Edit batch size and notes')" @click="openEdit(sample)">
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

                                <p v-if="(sample.can_edit && !(selected.recipe?.ingredients ?? []).length) || (sample.can_review && !(sample.recipe?.ingredients ?? []).length)" class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
                                    {{ sample.can_edit ? ctrans("The recipe in Production has no ingredients yet. Add them before sending this sample for review.") : ctrans("This sample was made from a recipe with no ingredients.") }}
                                </p>

                                <div v-if="sample.can_edit" class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs text-gray-500">{{ ctrans("When the sample is made, send it for review. Its recipe is kept as it is then.") }}</span>
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
                                    <button v-if="sample.recipe" type="button" class="flex items-center gap-1.5 text-xs text-gray-600 hover:text-[--app-accent]" :aria-expanded="!!openRecipe[sample.id]" @click="openRecipe[sample.id] = !openRecipe[sample.id]">
                                        <FontAwesomeIcon icon="fal fa-chevron-down" class="transition-transform" :class="openRecipe[sample.id] ? '' : '-rotate-90'" fixed-width aria-hidden="true" />
                                        {{ ctrans("Recipe of this sample") }}
                                    </button>
                                    <button type="button" class="flex items-center gap-1.5 text-xs text-gray-600 hover:text-[--app-accent]" :aria-expanded="!!openThread[sample.id]" @click="openThread[sample.id] = !openThread[sample.id]">
                                        <FontAwesomeIcon icon="fal fa-comments" fixed-width aria-hidden="true" />
                                        {{ ctrans("Discussion (:count)", { count: String(sample.comments) }) }}
                                    </button>
                                </div>
                                <div v-if="sample.recipe && openRecipe[sample.id]">
                                    <RecipeIngredients :ingredients="sample.recipe.ingredients" :previous="previousRecipe(selected, sample)" />
                                    <p v-if="previousRecipe(selected, sample)" class="mt-1 text-xs text-gray-400">{{ ctrans("Compared with the previous sample: new in green, changed in amber, removed in red.") }}</p>
                                </div>
                                <SampleThread v-if="openThread[sample.id]" :commentsRoute="sample.routes.comments" :sampleId="sample.id" :count="sample.comments" />
                            </div>
                        </article>
                    </section>
                </div>
            </div>
            <p v-else class="px-4 py-6 text-center text-sm text-gray-400">{{ ctrans("No SKUs in this development yet") }}</p>
        </section>

        <Dialog :visible="!!editDialog" modal :header="editDialog ? ctrans('Sample :number', { number: editDialog.sample.number }) : ''" :style="{ width: '28rem' }" :breakpoints="{ '640px': '94vw' }" @update:visible="(visible) => !visible && (editDialog = null)">
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
                <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="editDialog = null">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" size="xs" :label="ctrans('Save')" :loading="saving" @click="saveEdit">
                    <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
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
                <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="componentDialog = null">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" size="xs" :label="ctrans('Save')" :loading="saving" @click="saveComponent">
                    <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
        </Dialog>

        <Dialog :visible="!!jobDialog" modal :header="ctrans('Create sample job')" :style="{ width: '32rem' }" :breakpoints="{ '640px': '94vw' }" @update:visible="(visible) => !visible && (jobDialog = null)">
            <div v-if="jobDialog" class="space-y-3 text-sm">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("SKUs to sample") }}</label>
                    <div class="max-h-48 space-y-1 overflow-y-auto rounded-md border border-gray-200 p-2 [scrollbar-width:thin]">
                        <label v-for="sku in skusWithRecipe" :key="sku.id" class="flex cursor-pointer items-center gap-2 rounded px-1 py-1 hover:bg-gray-50">
                            <Checkbox v-model="jobDialog.item_ids" :value="sku.id" :inputId="`job-sku-${sku.id}`" />
                            <span class="whitespace-nowrap font-medium text-gray-900">{{ sku.code }}</span>
                            <span class="truncate text-gray-500">{{ sku.name }}</span>
                        </label>
                    </div>
                    <p v-if="jobErrors.item_ids" class="mt-1 text-xs text-red-600">{{ jobErrors.item_ids }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Who makes them") }}</label>
                    <Select v-model="jobDialog.assignee_id" :options="data.people" optionLabel="label" optionValue="value" filter :placeholder="ctrans('Pick a person')" class="w-full" overlayClass="text-sm" :invalid="!!jobErrors.assignee_id" />
                    <p v-if="jobErrors.assignee_id" class="mt-1 text-xs text-red-600">{{ jobErrors.assignee_id }}</p>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Due") }}</label>
                        <DatePicker v-model="jobDialog.due_at" dateFormat="dd M yy" showIcon iconDisplay="input" class="w-full" panelClass="text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Priority") }}</label>
                        <Select v-model="jobDialog.priority" :options="data.priorities" optionLabel="label" optionValue="value" class="w-full" overlayClass="text-sm" />
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Instructions") }}</label>
                    <Textarea v-model="jobDialog.instructions" rows="3" autoResize class="w-full" :placeholder="ctrans('What should the sample show or test?')" />
                </div>
                <p class="text-xs text-gray-500">{{ ctrans("They get a task and join the development with the Production role. Each SKU gets its next sample.") }}</p>
                <p v-if="Object.keys(jobErrors).length && !jobErrors.item_ids && !jobErrors.assignee_id" class="text-xs text-red-600">{{ firstError(jobErrors) }}</p>
            </div>
            <template #footer>
                <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="jobDialog = null">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" size="xs" :label="ctrans('Create job')" :loading="sendingJob" @click="sendJob">
                    <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
        </Dialog>
    </div>
</template>
