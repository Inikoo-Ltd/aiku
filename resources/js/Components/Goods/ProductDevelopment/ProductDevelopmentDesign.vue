<!--
  - Author Louis Perez
  - Created on 17-10-2026-16h-20m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import Dialog from "primevue/dialog"
import Popover from "primevue/popover"
import InputText from "primevue/inputtext"
import Checkbox from "primevue/checkbox"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import Image from "@common/Components/Image.vue"
import DesignArtworkViewer from "@/Components/Goods/ProductDevelopment/Design/DesignArtworkViewer.vue"
import DesignLabelContent from "@/Components/Goods/ProductDevelopment/Design/DesignLabelContent.vue"
import DesignArtworkPanel, { type Artwork, type ArtworkRow as Row, type ArtworkStatus as Status, type ArtworkVersion } from "@/Components/Goods/ProductDevelopment/Design/DesignArtworkPanel.vue"
import TicketAttachmentPreview, { type TicketAttachment } from "@/Components/Tickets/TicketAttachmentPreview.vue"
import { productDevelopmentColor } from "@/Components/Goods/ProductDevelopment/productDevelopmentColors"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLightbulbOn, faBoxOpen, faTag, faLock, faPlus, faPencil, faChevronDown, faEye, faDownload, faEllipsisH, faFilePdf, faCloudUpload, faPencilRuler, faCheck, faUndo, faTimes, faTrashAlt, faSpinner, faCircle, faClock, faExclamationCircle, faCheckCircle, faArrowRight, faImage, faShieldCheck, faComments } from "@fal"
library.add(faLightbulbOn, faBoxOpen, faTag, faLock, faPlus, faPencil, faChevronDown, faEye, faDownload, faEllipsisH, faFilePdf, faCloudUpload, faPencilRuler, faCheck, faUndo, faTimes, faTrashAlt, faSpinner, faCircle, faClock, faExclamationCircle, faCheckCircle, faArrowRight, faImage, faShieldCheck, faComments)

interface Section {
    id: number
    name: string
    description: string | null
    packaging_supplier: string | null
    editor_kind: "box" | "sheet"
    needs_compliance_text: boolean
    progress: { done: number, total: number }
    files: number
    can_delete: boolean
    rows: Row[]
    routes: Record<"update" | "delete" | "upload" | "aiku" | "zip", routeType>
}

const props = defineProps<{
    data: {
        concept: {
            latest: { version: number, status: Status, submitted_by: string | null, submitted_at: string | null, upload: { kind: "pdf" | "image", name: string, url: string, preview_url: string } | null } | null
            route: routeType
        }
        sections: Section[]
        can_manage: boolean
        locked: boolean
        max_mb: number
        routes: { section_store: routeType }
    }
}>()

const formatDate = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM yyyy" }) : ""
const formatSize = (bytes: number | null) => !bytes ? "" : bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`
const sectionIcon = (section: Section) => section.editor_kind === "box" ? "fal fa-box-open" : "fal fa-tag"
const firstError = (errors: Record<string, string>) => Object.values(errors)[0]
const uploadedTooltip = (artwork: Artwork) => artwork.updated_at ? ctrans("Uploaded :date by :name", { date: formatDate(artwork.updated_at), name: artwork.updated_by ?? "" }) : artwork.file_name ?? undefined

/* Expand and collapse */

const collapsed = ref<Record<number, boolean>>({})
const toggle = (section: Section) => collapsed.value[section.id] = !collapsed.value[section.id]

/* Preview: the same full-screen viewer as the Files tab */

const preview = ref<{ files: TicketAttachment[], index: number | null }>({ files: [], index: null })
const pdfUrl = (artwork: Artwork, inline = false) => artwork.pdf_route ? route(artwork.pdf_route.name, { ...(artwork.pdf_route.parameters as Record<string, unknown>), ...(inline ? { inline: 1 } : {}) }) : ""

function previewArtwork(section: Section, row: Row) {
    const withFiles = section.rows.filter((candidate) => candidate.artwork?.pdf_route)
    preview.value = {
        files: withFiles.map((candidate) => ({ name: candidate.artwork!.file_name ?? `${candidate.code}.pdf`, url: pdfUrl(candidate.artwork!, true), mime: "application/pdf" })),
        index: withFiles.findIndex((candidate) => candidate.item_id === row.item_id),
    }
}

function previewConcept() {
    const upload = props.data.concept.latest?.upload
    if (upload) {
        preview.value = { files: [{ name: upload.name, url: upload.preview_url, mime: upload.kind === "pdf" ? "application/pdf" : "image/jpeg" }], index: 0 }
    }
}

/* Section add / edit / remove */

const sectionDialog = ref<{ section: Section | null, name: string, description: string, packaging_supplier: string, editor_kind: "box" | "sheet", needs_compliance_text: boolean } | null>(null)
const sectionErrors = ref<Record<string, string>>({})
const savingSection = ref(false)
const editorKinds = computed(() => [
    { value: "box" as const, label: ctrans("Box"), icon: "fal fa-box-open" },
    { value: "sheet" as const, label: ctrans("Flat page"), icon: "fal fa-tag" },
])

function openSection(section: Section | null) {
    sectionErrors.value = {}
    sectionDialog.value = {
        section,
        name: section?.name ?? "",
        description: section?.description ?? "",
        packaging_supplier: section?.packaging_supplier ?? "",
        editor_kind: section?.editor_kind ?? "sheet",
        needs_compliance_text: section?.needs_compliance_text ?? false,
    }
}

function saveSection() {
    const dialog = sectionDialog.value
    if (!dialog) {
        return
    }
    const payload = { name: dialog.name, description: dialog.description || null, packaging_supplier: dialog.packaging_supplier || null, needs_compliance_text: dialog.needs_compliance_text }
    const options = {
        preserveScroll: true,
        onStart: () => savingSection.value = true,
        onFinish: () => savingSection.value = false,
        onSuccess: () => sectionDialog.value = null,
        onError: (errors: Record<string, string>) => sectionErrors.value = errors,
    }
    if (dialog.section) {
        router.patch(route(dialog.section.routes.update.name, dialog.section.routes.update.parameters), payload, options)
    } else {
        router.post(route(props.data.routes.section_store.name, props.data.routes.section_store.parameters), { ...payload, editor_kind: dialog.editor_kind }, options)
    }
}

function removeSection() {
    const section = sectionDialog.value?.section
    if (!section) {
        return
    }
    router.delete(route(section.routes.delete.name, section.routes.delete.parameters), {
        preserveScroll: true,
        onStart: () => savingSection.value = true,
        onFinish: () => savingSection.value = false,
        onSuccess: () => sectionDialog.value = null,
        onError: (errors) => sectionErrors.value = errors,
    })
}

/* Row actions menu */

const rowMenu = ref<InstanceType<typeof Popover> | null>(null)
const menuFor = ref<{ section: Section, row: Row } | null>(null)

function openMenu(event: Event, section: Section, row: Row) {
    menuFor.value = { section, row }
    rowMenu.value?.toggle(event)
}

/* Side panel: the SKU's design, its review and discussion */

const panel = ref<{ sectionId: number, itemId: number } | null>(null)
const panelSection = computed(() => props.data.sections.find((section) => section.id === panel.value?.sectionId) ?? null)
const panelRow = computed(() => panelSection.value?.rows.find((row) => row.item_id === panel.value?.itemId) ?? null)
const panelVisible = computed({
    get: () => !!panelRow.value,
    set: (visible: boolean) => !visible && (panel.value = null),
})

function openPanel(section: Section, row: Row) {
    rowMenu.value?.hide()
    panel.value = { sectionId: section.id, itemId: row.item_id }
}

/* Versions: every version sent for review, newest first */

const versionsFor = ref<{ section: Section, row: Row } | null>(null)

function openVersions(section: Section, row: Row) {
    if (!row.artwork?.versions.length) {
        openPanel(section, row)
        return
    }
    versionsFor.value = { section, row }
}

const versionUrl = (version: ArtworkVersion, inline = false) => version.pdf_route ? route(version.pdf_route.name, { ...(version.pdf_route.parameters as Record<string, unknown>), ...(inline ? { inline: 1 } : {}) }) : ""

function previewVersion(version: ArtworkVersion) {
    const dialog = versionsFor.value
    if (!dialog?.row.artwork) {
        return
    }
    const withFiles = dialog.row.artwork.versions.filter((candidate) => candidate.pdf_route)
    preview.value = {
        files: withFiles.map((candidate) => ({ name: `${dialog.row.code}_${dialog.section.name}_V${candidate.version}.pdf`, url: versionUrl(candidate, true), mime: "application/pdf" })),
        index: withFiles.findIndex((candidate) => candidate.version === version.version),
    }
}

/* Upload or replace a PDF */

const uploadDialog = ref<{ section: Section, row: Row, file: File | null, note: string } | null>(null)
const uploadErrors = ref<Record<string, string>>({})
const uploadProgress = ref<number | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

function startUpload(section: Section, row: Row) {
    rowMenu.value?.hide()
    uploadErrors.value = {}
    uploadDialog.value = { section, row, file: null, note: "" }
}

function onFilePicked(event: Event) {
    const input = event.target as HTMLInputElement
    if (uploadDialog.value) {
        uploadDialog.value.file = input.files?.[0] ?? null
    }
    input.value = ""
}

function sendUpload() {
    const dialog = uploadDialog.value
    if (!dialog?.file) {
        return
    }
    router.post(route(dialog.section.routes.upload.name, dialog.section.routes.upload.parameters), {
        product_development_item_id: dialog.row.item_id,
        file: dialog.file,
        update_note: dialog.note || null,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => uploadProgress.value = 0,
        onProgress: (event) => uploadProgress.value = Math.round(event?.percentage ?? 0),
        onFinish: () => uploadProgress.value = null,
        onSuccess: () => uploadDialog.value = null,
        onError: (errors) => uploadErrors.value = errors,
    })
}

/* Build in Aiku */

function openInAiku(section: Section, row: Row) {
    rowMenu.value?.hide()
    if (row.artwork?.editor_route && (row.artwork.can_edit_layout || !row.can_upload)) {
        router.visit(route(row.artwork.editor_route.name, row.artwork.editor_route.parameters))
        return
    }
    router.post(route(section.routes.aiku.name, section.routes.aiku.parameters), { product_development_item_id: row.item_id }, {
        onError: (errors) => notify({ title: ctrans("Editor not opened"), text: firstError(errors), type: "error" }),
    })
}

/* Cancel a design still being drawn in Aiku, so a PDF can be uploaded instead */

const cancelAikuFor = ref<{ section: Section, row: Row } | null>(null)
const cancellingAiku = ref(false)

function askCancelAiku(section: Section, row: Row) {
    rowMenu.value?.hide()
    cancelAikuFor.value = { section, row }
}

function cancelAiku() {
    const artwork = cancelAikuFor.value?.row.artwork
    if (!artwork) {
        return
    }
    router.delete(route(artwork.cancel_aiku_route.name, artwork.cancel_aiku_route.parameters), {
        preserveScroll: true,
        onStart: () => cancellingAiku.value = true,
        onFinish: () => cancellingAiku.value = false,
        onSuccess: () => cancelAikuFor.value = null,
        onError: (errors) => notify({ title: ctrans("Design not cancelled"), text: firstError(errors), type: "error" }),
    })
}

/* Review: the PDF beside the SKU's approved label and pack information */

const reviewDialog = ref<{ section: Section, row: Row, artwork: Artwork, comment: string } | null>(null)
const reviewErrors = ref<Record<string, string>>({})
const reviewing = ref<string | null>(null)
const labelContent = ref<InstanceType<typeof DesignLabelContent>["$props"]["data"] | null>(null)

async function startReview(section: Section, row: Row) {
    rowMenu.value?.hide()
    if (!row.artwork) {
        return
    }
    reviewErrors.value = {}
    labelContent.value = null
    reviewDialog.value = { section, row, artwork: row.artwork, comment: "" }
    try {
        const { data } = await axios.get(route(row.label_content_route.name, row.label_content_route.parameters))
        labelContent.value = data
    } catch {
        labelContent.value = null
    }
}

function sendReview(operation: "approve" | "request_changes") {
    const dialog = reviewDialog.value
    if (!dialog) {
        return
    }
    router.patch(route(dialog.artwork.review_route.name, dialog.artwork.review_route.parameters), { operation, comment: dialog.comment || null }, {
        preserveScroll: true,
        onStart: () => reviewing.value = operation,
        onFinish: () => reviewing.value = null,
        onSuccess: () => reviewDialog.value = null,
        onError: (errors) => reviewErrors.value = errors,
    })
}
</script>

<template>
    <div v-if="data" id="pd-design" class="space-y-3">
        <section class="rounded-lg border border-gray-200 bg-white">
            <header class="flex flex-wrap items-start gap-3 px-4 py-3">
                <FontAwesomeIcon icon="fal fa-lightbulb-on" class="mt-0.5 text-lg text-[--app-accent]" fixed-width aria-hidden="true" />
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-semibold text-gray-900">{{ ctrans("Concept") }}</h3>
                    <p class="text-xs text-gray-500">{{ ctrans("Range concept and design direction.") }}</p>
                </div>
            </header>
            <div class="px-4 pb-3">
                <div v-if="data.concept.latest?.upload" class="flex items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5">
                    <button type="button" class="h-14 w-14 shrink-0 overflow-hidden rounded-md border border-gray-200 bg-white" :aria-label="ctrans('Preview')" @click="previewConcept">
                        <img v-if="data.concept.latest.upload.kind === 'image'" :src="data.concept.latest.upload.preview_url" :alt="data.concept.latest.upload.name" class="h-full w-full object-cover" loading="lazy" />
                        <div v-else class="pointer-events-none h-full w-full overflow-hidden">
                            <DesignArtworkViewer :upload="data.concept.latest.upload" :maxPages="1" />
                        </div>
                    </button>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-gray-900">{{ data.concept.latest.upload.name }}</div>
                        <div class="text-xs text-gray-500">
                            v{{ data.concept.latest.version }} · {{ data.concept.latest.status.label }}
                            <template v-if="data.concept.latest.submitted_at"> · {{ ctrans("Uploaded :date by :name", { date: formatDate(data.concept.latest.submitted_at), name: data.concept.latest.submitted_by ?? "" }) }}</template>
                        </div>
                    </div>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800" v-tooltip="ctrans('Preview')" :aria-label="ctrans('Preview')" @click="previewConcept">
                        <FontAwesomeIcon icon="fal fa-eye" fixed-width aria-hidden="true" />
                    </button>
                    <a :href="data.concept.latest.upload.url" class="flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800" v-tooltip="ctrans('Download')" :aria-label="ctrans('Download')">
                        <FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
                    </a>
                    <Link :href="route(data.concept.route.name, data.concept.route.parameters)" class="flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800" v-tooltip="ctrans('Open the concept: versions, comments and review')" :aria-label="ctrans('Open the concept')">
                        <FontAwesomeIcon icon="fal fa-ellipsis-h" fixed-width aria-hidden="true" />
                    </Link>
                </div>
                <Link v-else :href="route(data.concept.route.name, data.concept.route.parameters)" class="flex items-center justify-between rounded-md border border-dashed border-gray-300 px-3 py-3 text-sm text-gray-500 hover:border-[--app-accent] hover:text-[--app-accent]">
                    {{ data.concept.latest ? ctrans("Concept v:version has no file yet", { version: String(data.concept.latest.version) }) : ctrans("No concept yet") }}
                    <span class="flex items-center gap-1.5 text-xs">{{ ctrans("Open the concept") }} <FontAwesomeIcon icon="fal fa-arrow-right" fixed-width aria-hidden="true" /></span>
                </Link>
            </div>
        </section>

        <div v-if="data.can_manage" class="flex justify-end">
            <Button type="tertiary" size="xs" :label="ctrans('Add design section')" @click="openSection(null)">
                <template #icon><FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" /></template>
            </Button>
        </div>

        <div v-if="data.locked" class="rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800">
            <FontAwesomeIcon icon="fal fa-lock" fixed-width aria-hidden="true" /> {{ ctrans("The artwork is signed off and locked") }}
        </div>

        <section v-for="section in data.sections" :key="section.id" class="rounded-lg border border-gray-200 bg-white">
            <header class="flex flex-wrap items-center gap-3 px-4 py-3">
                <FontAwesomeIcon :icon="sectionIcon(section)" class="text-xl text-gray-500" fixed-width aria-hidden="true" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-semibold text-gray-900">{{ section.name }}</h3>
                        <button v-if="data.can_manage" type="button" class="flex h-6 w-6 items-center justify-center rounded text-[--app-accent] hover:bg-[--app-accent-soft]" :aria-label="ctrans('Edit section')" @click="openSection(section)">
                            <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                        </button>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium tabular-nums" :class="section.progress.total && section.progress.done === section.progress.total ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">
                            {{ ctrans(":done of :total approved", { done: String(section.progress.done), total: String(section.progress.total) }) }}
                        </span>
                        <span v-if="section.needs_compliance_text" class="flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-800" v-tooltip="ctrans('Artwork is only approved once the SKU\'s compliance text is approved')">
                            <FontAwesomeIcon icon="fal fa-shield-check" fixed-width aria-hidden="true" />
                            {{ ctrans("Needs compliance text") }}
                        </span>
                    </div>
                    <p v-if="section.description" class="text-xs text-gray-500">{{ section.description }}</p>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <span class="text-xs text-gray-500">{{ ctrans("Packaging supplier") }}</span>
                    <button
                        type="button"
                        class="flex min-w-[10rem] items-center justify-between gap-2 rounded-md border border-gray-200 px-2.5 py-1.5 text-left"
                        :class="data.can_manage ? 'hover:border-[--app-accent]' : 'cursor-default'"
                        :disabled="!data.can_manage"
                        @click="openSection(section)"
                    >
                        <span :class="section.packaging_supplier ? 'text-gray-800' : 'text-gray-400'">{{ section.packaging_supplier || "—" }}</span>
                        <FontAwesomeIcon v-if="data.can_manage" icon="fal fa-pencil" class="text-xs text-[--app-accent]" fixed-width aria-hidden="true" />
                    </button>
                </div>
                <button type="button" class="flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100" :aria-expanded="!collapsed[section.id]" :aria-label="collapsed[section.id] ? ctrans('Expand') : ctrans('Collapse')" @click="toggle(section)">
                    <FontAwesomeIcon icon="fal fa-chevron-down" class="transition-transform" :class="collapsed[section.id] ? '-rotate-90' : 'rotate-180'" fixed-width aria-hidden="true" />
                </button>
            </header>

            <div v-show="!collapsed[section.id]">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[38rem] text-sm">
                        <thead class="whitespace-nowrap bg-gray-50 text-left text-xs font-medium text-gray-500">
                            <tr>
                                <th class="px-4 py-2">{{ ctrans("SKU") }}</th>
                                <th class="px-2 py-2">{{ ctrans("Artwork file") }}</th>
                                <th class="px-2 py-2">{{ ctrans("Design status") }}</th>
                                <th class="px-4 py-2 text-right">{{ ctrans("Actions") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in section.rows" :key="row.item_id" class="align-middle">
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-3">
                                        <div class="h-11 w-11 shrink-0 overflow-hidden rounded-md bg-gray-100">
                                            <Image v-if="row.image" :src="row.image" :alt="row.code" class="h-full w-full object-cover" />
                                            <div v-else class="flex h-full w-full items-center justify-center text-gray-300" v-tooltip="ctrans('No product image yet')">
                                                <FontAwesomeIcon icon="fal fa-image" fixed-width aria-hidden="true" />
                                            </div>
                                        </div>
                                        <button type="button" class="min-w-0 text-left" @click="openPanel(section, row)">
                                            <span class="block whitespace-nowrap font-medium text-gray-900 hover:text-[--app-accent]">{{ row.code }}</span>
                                            <span class="block max-w-[14rem] truncate text-xs text-gray-500" v-tooltip="row.name">{{ row.name }}</span>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-2 py-2">
                                    <button v-if="row.artwork?.pdf_route" type="button" class="flex items-center gap-2 text-left" @click="previewArtwork(section, row)">
                                        <FontAwesomeIcon icon="fal fa-file-pdf" class="text-xl text-red-500" fixed-width aria-hidden="true" />
                                        <span class="min-w-0">
                                            <span class="block max-w-[16rem] truncate font-medium text-gray-900 hover:text-[--app-accent]" v-tooltip="uploadedTooltip(row.artwork)">{{ row.artwork.file_name }}</span>
                                            <span v-if="row.artwork.update_note" class="block max-w-[16rem] truncate text-xs text-gray-600" v-tooltip="row.artwork.update_note">{{ row.artwork.update_note }}</span>
                                            <span v-else class="block text-xs text-gray-500">{{ row.artwork.source === "aiku" ? ctrans("Made in Aiku") : formatSize(row.artwork.size) }}</span>
                                        </span>
                                    </button>
                                    <Link
                                        v-else-if="row.artwork?.source === 'aiku' && row.artwork.editor_route"
                                        :href="route(row.artwork.editor_route.name, row.artwork.editor_route.parameters)"
                                        class="text-xs text-gray-600 underline decoration-dotted underline-offset-2 hover:text-[--app-accent]"
                                    >
                                        {{ ctrans("Being made in Aiku") }}
                                    </Link>
                                    <span v-else-if="row.artwork?.source === 'aiku'" class="text-xs text-gray-500">{{ ctrans("Being made in Aiku") }}</span>
                                    <button
                                        v-else-if="row.can_upload || row.artwork?.can_review || row.artwork?.editor_route"
                                        type="button"
                                        class="text-xs text-amber-700 underline decoration-dotted underline-offset-2 hover:text-amber-900"
                                        aria-haspopup="menu"
                                        @click="(event) => openMenu(event, section, row)"
                                    >
                                        {{ ctrans("Missing artwork") }}
                                    </button>
                                    <span v-else class="text-xs text-amber-700">{{ ctrans("Missing artwork") }}</span>
                                </td>
                                <td class="px-2 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-medium hover:opacity-80"
                                        :class="productDevelopmentColor(row.status.color).active"
                                        v-tooltip="row.artwork?.versions.length ? ctrans('See all versions') : undefined"
                                        @click="openVersions(section, row)"
                                    >
                                        <template v-if="row.artwork?.version">
                                            <span class="tabular-nums">V{{ row.artwork.version }}</span>
                                            <span class="h-3 w-px bg-current opacity-30" aria-hidden="true" />
                                        </template>
                                        <FontAwesomeIcon :icon="row.status.icon" fixed-width aria-hidden="true" />
                                        {{ row.status.label }}
                                    </button>
                                    <button v-if="row.artwork?.needs_recheck" type="button" class="mt-1 flex items-center gap-1 text-xs text-amber-700 hover:underline" v-tooltip="ctrans('The label information changed after approval')" @click="openPanel(section, row)">
                                        <FontAwesomeIcon icon="fal fa-exclamation-circle" fixed-width aria-hidden="true" />
                                        {{ ctrans("Recheck: label info changed") }}
                                    </button>
                                    <button
                                        v-if="row.artwork?.packaging_sample.status"
                                        type="button"
                                        class="mt-1 flex items-center gap-1 whitespace-nowrap text-xs hover:underline"
                                        :class="row.artwork.packaging_sample.status === 'approved' ? 'text-green-700' : 'text-orange-700'"
                                        v-tooltip="ctrans('Open the packaging sample')"
                                        @click="openPanel(section, row)"
                                    >
                                        <FontAwesomeIcon icon="fal fa-box-open" fixed-width aria-hidden="true" />
                                        {{ row.artwork.packaging_sample.status === "approved" ? ctrans("Packaging sample approved") : ctrans("Packaging sample: changes needed") }}
                                    </button>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center justify-end gap-1">
                                        <button v-if="row.artwork?.pdf_route" type="button" class="flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800" v-tooltip="ctrans('Preview')" :aria-label="ctrans('Preview')" @click="previewArtwork(section, row)">
                                            <FontAwesomeIcon icon="fal fa-eye" fixed-width aria-hidden="true" />
                                        </button>
                                        <a v-if="row.artwork?.pdf_route" :href="pdfUrl(row.artwork)" class="flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800" v-tooltip="ctrans('Download')" :aria-label="ctrans('Download')">
                                            <FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
                                        </a>
                                        <button type="button" class="relative flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800" v-tooltip="ctrans('Review and discussion')" :aria-label="ctrans('Review and discussion')" @click="openPanel(section, row)">
                                            <FontAwesomeIcon icon="fal fa-comments" fixed-width aria-hidden="true" />
                                            <span v-if="row.artwork?.comments" class="absolute -right-0.5 -top-0.5 min-w-[1rem] rounded-full bg-[--app-accent] px-1 text-[10px] leading-4 text-[--app-accent-text] tabular-nums">{{ row.artwork.comments }}</span>
                                        </button>
                                        <button
                                            v-if="row.can_upload || row.artwork?.can_review || row.artwork?.editor_route"
                                            type="button"
                                            class="flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                                            :aria-label="ctrans('More actions')"
                                            aria-haspopup="menu"
                                            @click="(event) => openMenu(event, section, row)"
                                        >
                                            <FontAwesomeIcon icon="fal fa-ellipsis-h" fixed-width aria-hidden="true" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!section.rows.length">
                                <td colspan="4" class="px-4 py-3 text-sm text-gray-400">{{ ctrans("This development has no SKUs yet") }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <footer v-if="section.files" class="flex justify-end border-t border-gray-100 px-4 py-2.5">
                    <a :href="route(section.routes.zip.name, section.routes.zip.parameters)">
                        <Button type="tertiary" size="xs" :label="ctrans('Download all (:count files)', { count: String(section.files) })">
                            <template #icon><FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </a>
                </footer>
            </div>
        </section>

        <p v-if="!data.sections.length" class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-400">{{ ctrans("No design sections. This development needs no printed artwork.") }}</p>

        <Popover ref="rowMenu">
            <div v-if="menuFor" class="w-60 text-sm" role="menu">
                <p class="px-2 pb-1.5 text-xs text-gray-500">{{ menuFor.section.name }} · {{ menuFor.row.code }}</p>
                <button v-if="menuFor.row.artwork?.can_review" type="button" role="menuitem" class="flex w-full items-center gap-2.5 rounded-md px-2 py-2 text-left text-gray-800 hover:bg-gray-50" @click="openPanel(menuFor.section, menuFor.row)">
                    <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                    {{ ctrans("Review") }}
                </button>
                <template v-if="menuFor.row.can_upload">
                    <button v-if="menuFor.row.artwork?.source !== 'aiku' || menuFor.row.status.value === 'in_review'" type="button" role="menuitem" class="flex w-full items-center gap-2.5 rounded-md px-2 py-2 text-left text-gray-800 hover:bg-gray-50" @click="startUpload(menuFor.section, menuFor.row)">
                        <FontAwesomeIcon icon="fal fa-cloud-upload" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                        {{ menuFor.row.artwork?.pdf_route ? ctrans("Replace with a PDF") : ctrans("Upload PDF") }}
                    </button>
                    <button v-if="menuFor.row.artwork?.source !== 'upload' || menuFor.row.status.value === 'in_review'" type="button" role="menuitem" class="flex w-full items-center gap-2.5 rounded-md px-2 py-2 text-left text-gray-800 hover:bg-gray-50" @click="openInAiku(menuFor.section, menuFor.row)">
                        <FontAwesomeIcon icon="fal fa-pencil-ruler" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                        {{ menuFor.row.artwork?.source === "aiku" ? (menuFor.row.artwork.can_edit_layout ? ctrans("Continue in Aiku") : ctrans("Make changes in Aiku")) : ctrans("Create in Aiku") }}
                    </button>
                </template>
                <button v-if="menuFor.row.artwork?.can_cancel_aiku" type="button" role="menuitem" class="flex w-full items-center gap-2.5 rounded-md px-2 py-2 text-left text-gray-800 hover:bg-gray-50" @click="askCancelAiku(menuFor.section, menuFor.row)">
                    <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    {{ ctrans("Cancel Aiku design") }}
                </button>
                <Link v-if="!menuFor.row.can_upload && menuFor.row.artwork?.editor_route" :href="route(menuFor.row.artwork.editor_route.name, menuFor.row.artwork.editor_route.parameters)" role="menuitem" class="flex w-full items-center gap-2.5 rounded-md px-2 py-2 text-left text-gray-800 hover:bg-gray-50">
                    <FontAwesomeIcon icon="fal fa-eye" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                    {{ ctrans("Open in Aiku") }}
                </Link>
            </div>
        </Popover>

        <Dialog :visible="!!sectionDialog" modal :header="sectionDialog?.section ? ctrans('Edit design section') : ctrans('Add design section')" :style="{ width: '28rem' }" :breakpoints="{ '640px': '94vw' }" @update:visible="(visible) => !visible && (sectionDialog = null)">
            <div v-if="sectionDialog" class="space-y-3 text-sm">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Name") }}</label>
                    <InputText v-model="sectionDialog.name" class="w-full" maxlength="64" :invalid="!!sectionErrors.name" :placeholder="ctrans('e.g. Sleeve')" />
                    <p v-if="sectionErrors.name" class="mt-1 text-xs text-red-600">{{ sectionErrors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Description") }}</label>
                    <Textarea v-model="sectionDialog.description" rows="2" autoResize class="w-full" maxlength="255" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Packaging supplier") }}</label>
                    <InputText v-model="sectionDialog.packaging_supplier" class="w-full" maxlength="255" :placeholder="ctrans('Who prints or makes it')" />
                </div>
                <label class="flex items-start gap-2">
                    <Checkbox v-model="sectionDialog.needs_compliance_text" binary inputId="section-needs-compliance" class="mt-0.5" />
                    <span>
                        <span class="block text-gray-800">{{ ctrans("Needs approved compliance text") }}</span>
                        <span class="block text-xs text-gray-500">{{ ctrans("Artwork can be uploaded any time, but only approved once the SKU's compliance text is approved. Use it for labels.") }}</span>
                    </span>
                </label>
                <div v-if="!sectionDialog.section">
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("When made in Aiku, it is a") }}</label>
                    <SegmentedToggle v-model="sectionDialog.editor_kind" :options="editorKinds" :ariaLabel="ctrans('Editor')" />
                </div>
                <p v-if="sectionErrors.section" class="text-xs text-red-600">{{ sectionErrors.section }}</p>
            </div>
            <template #footer>
                <Button v-if="sectionDialog?.section?.can_delete" type="tertiary" size="xs" :label="ctrans('Remove section')" class="mr-auto" :loading="savingSection" @click="removeSection">
                    <template #icon><FontAwesomeIcon icon="fal fa-trash-alt" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="sectionDialog = null">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" size="xs" :label="ctrans('Save')" :loading="savingSection" @click="saveSection">
                    <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
        </Dialog>

        <Dialog :visible="!!uploadDialog" modal :header="uploadDialog ? `${uploadDialog.section.name} · ${uploadDialog.row.code}` : ''" :style="{ width: '30rem' }" :breakpoints="{ '640px': '94vw' }" @update:visible="(visible) => !visible && (uploadDialog = null)">
            <div v-if="uploadDialog" class="space-y-3 text-sm">
                <button type="button" class="flex w-full flex-col items-center justify-center gap-1.5 rounded-md border-2 border-dashed border-gray-200 px-4 py-8 text-gray-500 hover:border-[--app-accent] hover:text-[--app-accent]" @click="fileInput?.click()">
                    <FontAwesomeIcon :icon="uploadDialog.file ? 'fal fa-file-pdf' : 'fal fa-cloud-upload'" class="text-2xl" fixed-width aria-hidden="true" />
                    <span>{{ uploadDialog.file ? uploadDialog.file.name : ctrans("Choose the artwork PDF") }}</span>
                    <span class="text-xs text-gray-400">{{ ctrans("PDF up to :size MB", { size: String(data.max_mb) }) }}</span>
                </button>
                <input ref="fileInput" type="file" accept="application/pdf" class="hidden" @change="onFilePicked" />
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ uploadDialog.row.artwork ? ctrans("What changed") : ctrans("Note (optional)") }}</label>
                    <InputText v-model="uploadDialog.note" class="w-full" maxlength="255" :placeholder="ctrans('e.g. Corrected barcode')" />
                </div>
                <p v-if="uploadDialog.row.status.value === 'approved'" class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ ctrans("This artwork is approved. Replacing it removes the approval and sends the new file for review.") }}</p>
                <div v-if="uploadProgress !== null" class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full bg-[--app-accent] transition-all" :style="{ width: uploadProgress + '%' }" />
                </div>
                <p v-if="Object.keys(uploadErrors).length" class="text-xs text-red-600">{{ firstError(uploadErrors) }}</p>
            </div>
            <template #footer>
                <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="uploadDialog = null">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" size="xs" :label="ctrans('Upload and send for review')" :disabled="!uploadDialog?.file" :loading="uploadProgress !== null" @click="sendUpload">
                    <template #icon><FontAwesomeIcon icon="fal fa-cloud-upload" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
        </Dialog>

        <Dialog :visible="!!reviewDialog" modal :header="reviewDialog ? ctrans('Review :section · :sku · V:version', { section: reviewDialog.section.name, sku: reviewDialog.row.code, version: String(reviewDialog.artwork.version) }) : ''" :style="{ width: '76rem' }" :breakpoints="{ '1024px': '96vw' }" @update:visible="(visible) => !visible && (reviewDialog = null)">
            <div v-if="reviewDialog" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="min-w-0 space-y-2">
                    <p v-if="reviewDialog.artwork.approval_blocked" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        <FontAwesomeIcon icon="fal fa-exclamation-circle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
                        {{ ctrans(":reason. You can still ask for changes.", { reason: reviewDialog.artwork.approval_blocked }) }}
                    </p>
                    <p v-if="reviewDialog.artwork.update_note" class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700"><span class="font-medium">{{ ctrans("What changed:") }}</span> {{ reviewDialog.artwork.update_note }}</p>
                    <div class="max-h-[65vh] overflow-auto rounded-md bg-gray-50 p-2">
                        <DesignArtworkViewer :upload="{ kind: 'pdf', preview_url: pdfUrl(reviewDialog.artwork, true), name: reviewDialog.artwork.file_name ?? '' }" />
                    </div>
                </div>
                <div class="min-w-0 space-y-3">
                    <DesignLabelContent v-if="labelContent" :data="labelContent" />
                    <p v-else class="flex items-center gap-2 text-sm text-gray-400"><FontAwesomeIcon icon="fal fa-spinner" spin fixed-width aria-hidden="true" /> {{ ctrans("Loading the label and pack information…") }}</p>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Comment (required to ask for changes)") }}</label>
                        <Textarea v-model="reviewDialog.comment" rows="3" autoResize class="w-full text-sm" :invalid="!!reviewErrors.comment" :placeholder="ctrans('What exactly needs fixing?')" />
                        <p v-if="Object.keys(reviewErrors).length" class="mt-1 text-xs text-red-600">{{ firstError(reviewErrors) }}</p>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button type="tertiary" size="xs" :label="ctrans('Changes required')" :loading="reviewing === 'request_changes'" @click="sendReview('request_changes')">
                    <template #icon><FontAwesomeIcon icon="fal fa-undo" class="text-orange-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" size="xs" :label="ctrans('Approve')" :loading="reviewing === 'approve'" @click="sendReview('approve')">
                    <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
        </Dialog>

        <DesignArtworkPanel
            v-model:visible="panelVisible"
            :sectionName="panelSection?.name ?? ''"
            :row="panelRow"
            :pdfUrl="panelRow?.artwork ? pdfUrl(panelRow.artwork) : ''"
            @preview="panelSection && panelRow && previewArtwork(panelSection, panelRow)"
            @compare="panelSection && panelRow && startReview(panelSection, panelRow)"
            @upload="panelSection && panelRow && startUpload(panelSection, panelRow)"
            @aiku="panelSection && panelRow && openInAiku(panelSection, panelRow)"
            @cancelAiku="panelSection && panelRow && askCancelAiku(panelSection, panelRow)"
        />

        <Dialog :visible="!!cancelAikuFor" modal :header="cancelAikuFor ? `${cancelAikuFor.section.name} · ${cancelAikuFor.row.code}` : ''" :style="{ width: '28rem' }" :breakpoints="{ '640px': '94vw' }" @update:visible="(visible) => !visible && (cancelAikuFor = null)">
            <p class="text-sm text-gray-700">{{ ctrans("Cancel the design being made in Aiku? Its layout is deleted and the artwork can be uploaded as a PDF instead. Versions already sent for review stay in the history.") }}</p>
            <template #footer>
                <Button type="tertiary" size="xs" :label="ctrans('Keep the design')" @click="cancelAikuFor = null">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" size="xs" :label="ctrans('Cancel Aiku design')" :loading="cancellingAiku" @click="cancelAiku">
                    <template #icon><FontAwesomeIcon icon="fal fa-exclamation-circle" class="text-amber-500" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
        </Dialog>

        <Dialog :visible="!!versionsFor" modal :header="versionsFor ? ctrans(':section · :sku · versions', { section: versionsFor.section.name, sku: versionsFor.row.code }) : ''" :style="{ width: '32rem' }" :breakpoints="{ '640px': '94vw' }" @update:visible="(visible) => !visible && (versionsFor = null)">
            <ol v-if="versionsFor?.row.artwork" class="divide-y divide-gray-100 text-sm">
                <li v-for="version in versionsFor.row.artwork.versions" :key="version.version" class="flex items-start gap-3 py-2.5">
                    <span class="w-8 shrink-0 pt-0.5 font-semibold tabular-nums text-gray-800">V{{ version.version }}</span>
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-medium" :class="productDevelopmentColor(version.status.color).active">
                                <FontAwesomeIcon :icon="version.status.icon" fixed-width aria-hidden="true" />
                                {{ version.status.value !== "in_review" ? version.status.label : !version.is_current ? ctrans("Replaced before review") : versionsFor.row.status.value !== "in_review" ? ctrans("Taken back before review") : version.status.label }}
                            </span>
                            <span v-if="version.is_current" class="text-xs text-gray-500">{{ ctrans("Current") }}</span>
                        </div>
                        <p v-if="version.submitted_at" class="text-xs text-gray-500">
                            {{ version.source === "aiku" ? ctrans("Made in Aiku, sent :date by :name", { date: formatDate(version.submitted_at), name: version.submitted_by ?? "" }) : ctrans("Uploaded :date by :name", { date: formatDate(version.submitted_at), name: version.submitted_by ?? "" }) }}
                        </p>
                        <p v-if="version.update_note" class="text-xs text-gray-700">{{ version.update_note }}</p>
                        <p v-if="version.reviewed_at" class="text-xs text-gray-500">
                            {{ version.status.value === "approved" ? ctrans("Approved by :name on :date", { name: version.reviewed_by ?? "", date: formatDate(version.reviewed_at) }) : ctrans("Changes asked by :name on :date", { name: version.reviewed_by ?? "", date: formatDate(version.reviewed_at) }) }}
                        </p>
                        <p v-if="version.review_comment" class="rounded bg-gray-50 px-2 py-1 text-xs text-gray-700">{{ version.review_comment }}</p>
                    </div>
                    <div v-if="version.pdf_route" class="flex shrink-0 items-center gap-0.5">
                        <button type="button" class="flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800" v-tooltip="ctrans('Preview')" :aria-label="ctrans('Preview')" @click="previewVersion(version)">
                            <FontAwesomeIcon icon="fal fa-eye" fixed-width aria-hidden="true" />
                        </button>
                        <a :href="versionUrl(version)" class="flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800" v-tooltip="ctrans('Download')" :aria-label="ctrans('Download')">
                            <FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
                        </a>
                    </div>
                </li>
            </ol>
        </Dialog>

        <TicketAttachmentPreview v-model:index="preview.index" :files="preview.files" />
    </div>
</template>
