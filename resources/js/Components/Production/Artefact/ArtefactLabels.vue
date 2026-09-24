<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBarcode, faCalendarAlt, faCheck, faExclamationTriangle, faFilePdf, faHashtag, faImage, faInfoCircle, faPlus, faTags, faTrashAlt } from "@fal"
import pdfWorkerUrl from "pdfjs-dist/legacy/build/pdf.worker.min.mjs?url"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ArtefactLabelSheetModal from "@/Components/Production/Artefact/ArtefactLabelSheetModal.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"

library.add(faBarcode, faCalendarAlt, faCheck, faExclamationTriangle, faFilePdf, faHashtag, faImage, faInfoCircle, faPlus, faTags, faTrashAlt)

interface ArtefactLabel {
    id: number
    name: string
    layout: Record<string, any>
    state: "raw" | "processed" | "published"
    state_label: string
    published_at: string | null
    pdf_url: string | null
    artwork: { name: string, size: number, mime_type: string, url: string } | null
    updated_at: string | null
    on_artwork: string[]
    missing_information: string[]
}

interface InformationOption {
    value: string
    label: string
    is_icon: boolean
    can_be_typed: boolean
}

const LABEL_STATE_CLASSES: Record<ArtefactLabel["state"], string> = {
    raw: "bg-gray-100 text-gray-600",
    processed: "bg-amber-50 text-amber-700",
    published: "bg-emerald-50 text-emerald-700",
}

interface ArtefactLabelSheet {
    route: { name: string, parameters: any }
    store_route: { name: string, parameters: any }
    update_route: { name: string, parameters: any }
    delete_route: { name: string, parameters: any }
    publish_route: { name: string, parameters: any }
    unpublish_route: { name: string, parameters: any }
    on_artwork_route: { name: string, parameters: any }
    mandatory_route: { name: string, parameters: any }
    information_options: InformationOption[]
    mandatory_information: string[]
    abilities: { edit: boolean, publish: boolean, set_mandatory: boolean }
    batch_code: string
    expiry_date: string
    barcode: string
    labels: ArtefactLabel[]
}

const props = defineProps<{
    data: ArtefactLabelSheet | null
}>()

const PDF_MIME_TYPE = "application/pdf"
const THUMBNAIL_EDGE = 120
const POINTS_TO_CENTIMETRES = 2.54 / 72

const SOURCE_ICONS: Record<string, string> = {
    batch_code: "fal fa-hashtag",
    expiry_date: "fal fa-calendar-alt",
    barcode: "fal fa-barcode",
}

const informationLabels = computed<Record<string, string>>(() =>
    Object.fromEntries((props.data?.information_options ?? []).map(option => [option.value, option.label]))
)

const mandatoryInformation = ref<string[]>([...(props.data?.mandatory_information ?? [])])
watch(() => props.data?.mandatory_information, value => { mandatoryInformation.value = [...(value ?? [])] })
const isSavingMandatory = ref(false)

const saveMandatoryInformation = () => {
    if (!props.data) return

    router.patch(
        route(props.data.mandatory_route.name, props.data.mandatory_route.parameters),
        { label_mandatory_information: mandatoryInformation.value },
        {
            preserveScroll: true,
            onStart: () => { isSavingMandatory.value = true },
            onFinish: () => { isSavingMandatory.value = false },
            onError: () => notify({ title: ctrans("Something went wrong"), text: ctrans("The mandatory information could not be saved"), type: "error" }),
        }
    )
}

const toggleOnArtwork = async (label: ArtefactLabel, information: string) => {
    if (!props.data) return

    const onArtwork = label.on_artwork.includes(information)
        ? label.on_artwork.filter(item => item !== information)
        : [...label.on_artwork, information]

    try {
        await axios.post(route(props.data.on_artwork_route.name, { ...props.data.on_artwork_route.parameters, label: label.id }), { on_artwork: onArtwork })
        router.reload()
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.message ?? ctrans("The label could not be updated"),
            type: "error",
        })
    }
}

const isPlaced = (label: ArtefactLabel, information: string) =>
    (label.layout.fields ?? []).some((field: Record<string, any>) => (field.source ?? "batch_code") === information)

interface ArtworkPreview {
    thumbnail: string | null
    width: number | null
    height: number | null
}

const isOpenLabelSheet = ref(false)
const labelToEdit = ref<ArtefactLabel | null>(null)
const deletingLabelId = ref<number | null>(null)

/**
 * Keyed by artwork url, so the same file shared by several labels is only fetched and drawn once.
 */
const artworkPreviews = reactive<Record<string, ArtworkPreview>>({})

let pdfjs: typeof import("pdfjs-dist/legacy/build/pdf.mjs") | null = null

const loadPdfjs = async () => {
    if (!pdfjs) {
        pdfjs = await import("pdfjs-dist/legacy/build/pdf.mjs")
        pdfjs.GlobalWorkerOptions.workerSrc = pdfWorkerUrl
    }

    return pdfjs
}

/**
 * Nothing on the server can rasterise a PDF, so the first page is drawn here to show what the label
 * is made of, and its page box is read in the same pass to say how big the artwork prints. The
 * artworks run to several megabytes, so only the ranges the first page needs are pulled over.
 */
const loadPdfArtwork = async (url: string) => {
    if (url in artworkPreviews) return

    artworkPreviews[url] = { thumbnail: null, width: null, height: null }

    try {
        const { getDocument } = await loadPdfjs()
        const loadingTask = getDocument({ url, disableAutoFetch: true })

        try {
            const page = await (await loadingTask.promise).getPage(1)
            const { width, height } = page.getViewport({ scale: 1 })
            const viewport = page.getViewport({ scale: Math.min(THUMBNAIL_EDGE / Math.max(width, height), 4) })

            const canvas = document.createElement("canvas")
            canvas.width = Math.round(viewport.width)
            canvas.height = Math.round(viewport.height)

            await page.render({ canvas, viewport }).promise

            artworkPreviews[url] = { thumbnail: canvas.toDataURL("image/png"), width, height }
        } finally {
            loadingTask.destroy()
        }
    } catch {
        artworkPreviews[url] = { thumbnail: null, width: null, height: null }
    }
}

const loadArtworkPreviews = async () => {
    for (const label of props.data?.labels ?? []) {
        if (label.artwork?.mime_type === PDF_MIME_TYPE) {
            await loadPdfArtwork(label.artwork.url)
        }
    }
}

onMounted(loadArtworkPreviews)
watch(() => props.data?.labels, loadArtworkPreviews)

const thumbnailOf = (label: ArtefactLabel) => {
    if (!label.artwork) return null

    return label.artwork.mime_type === PDF_MIME_TYPE
        ? artworkPreviews[label.artwork.url]?.thumbnail ?? null
        : label.artwork.url
}

const artworkSizeOf = (label: ArtefactLabel) => {
    if (label.artwork?.mime_type !== PDF_MIME_TYPE) return null

    const preview = artworkPreviews[label.artwork.url]

    if (!preview?.width || !preview?.height) return null

    const centimetres = (points: number) => (points * POINTS_TO_CENTIMETRES).toFixed(1)

    return `${centimetres(preview.width)} × ${centimetres(preview.height)} cm`
}

const usedSourcesOf = (label: ArtefactLabel) => {
    const sources = new Set<string>((label.layout.fields ?? []).map((field: Record<string, any>) => field.source ?? "batch_code"))

    return [...sources].map(source => ({
        source,
        icon: SOURCE_ICONS[source] ?? "fal fa-info-circle",
        label: informationLabels.value[source] ?? source,
    }))
}

const openLabel = (label: ArtefactLabel | null) => {
    labelToEdit.value = label
    isOpenLabelSheet.value = true
}

const onDeleteLabel = async (label: ArtefactLabel) => {
    deletingLabelId.value = label.id

    try {
        await axios.delete(route(props.data!.delete_route.name, {
            ...props.data!.delete_route.parameters,
            label: label.id,
        }))
        router.reload()
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.message ?? ctrans("The label could not be deleted"),
            type: "error",
        })
    } finally {
        deletingLabelId.value = null
    }
}

const describeLabel = (label: ArtefactLabel) => {
    const parts = [
        `${label.layout.columns ?? '?'} × ${label.layout.rows ?? '?'}`,
        label.layout.orientation === 'landscape' ? ctrans('Horizontal') : ctrans('Vertical'),
    ]

    if (label.artwork) {
        parts.push(label.artwork.mime_type === 'application/pdf' ? ctrans('PDF artwork') : ctrans('Image artwork'))
    }

    if (label.updated_at) {
        parts.push(useFormatTime(label.updated_at, { formatTime: 'aiku' }))
    }

    return parts.join(' · ')
}
</script>

<template>
    <div v-if="!data" class="p-4">
        <div class="rounded border border-dashed border-gray-300 px-3 py-4 text-center text-xs text-gray-500" role="status">
            {{ ctrans('This artefact has no SKO yet. Labels belong to the SKO, so they can be designed once it has one.') }}
        </div>
    </div>
    <div v-else class="p-4 space-y-4">
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" aria-labelledby="label-mandatory-information-title">
            <h2 id="label-mandatory-information-title" class="text-sm font-semibold">{{ ctrans('Mandatory information') }}</h2>
            <p class="mt-1 text-xs text-gray-500">
                {{ ctrans('Every label of this SKO must show these, placed on the label or already printed on the artwork. A label missing any of them cannot be published.') }}
            </p>

            <fieldset v-if="data.abilities?.set_mandatory" class="mt-3">
                <legend class="sr-only">{{ ctrans('Mandatory information') }}</legend>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <label
                        v-for="option in data.information_options"
                        :key="option.value"
                        class="flex items-center gap-2 text-sm">
                        <input v-model="mandatoryInformation" type="checkbox" :value="option.value" class="rounded border-gray-300" />
                        {{ option.label }}
                    </label>
                </div>
                <div class="mt-3">
                    <Button
                        type="primary"
                        size="xs"
                        :label="ctrans('Save')"
                        :loading="isSavingMandatory"
                        @click="saveMandatoryInformation" />
                </div>
            </fieldset>

            <ul v-else-if="data.mandatory_information.length" class="mt-3 flex flex-wrap gap-1.5" :aria-label="ctrans('Mandatory information')">
                <li
                    v-for="information in data.mandatory_information"
                    :key="information"
                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                    {{ informationLabels[information] ?? information }}
                </li>
            </ul>
            <div v-else class="mt-3 text-xs text-gray-500" role="status">
                {{ ctrans('Nothing set yet. The compliance manager decides what every label of this SKO must show.') }}
            </div>
        </section>

        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" aria-labelledby="artefact-labels-title">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 id="artefact-labels-title" class="text-sm font-semibold">{{ ctrans('Labels') }}</h2>
                <Button
                    v-if="data.abilities?.edit !== false"
                    type="tertiary"
                    size="xs"
                    icon="fal fa-plus"
                    :label="ctrans('New label')"
                    :aria-label="ctrans('Design a new label')"
                    aria-haspopup="dialog"
                    :aria-expanded="isOpenLabelSheet && !labelToEdit"
                    @click="openLabel(null)" />
            </div>

            <div
                v-if="!data.labels.length"
                class="rounded border border-dashed border-gray-300 px-3 py-4 text-center text-xs text-gray-500"
                role="status">
                {{ ctrans('No label designed yet. A saved label keeps its artwork, so it can be printed again any time.') }}
            </div>

            <ul v-else class="grid grid-cols-1 md:grid-cols-2 gap-2" :aria-label="ctrans('Saved labels')">
                <li
                    v-for="label in data.labels"
                    :key="label.id"
                    class="flex items-center gap-3 rounded border border-gray-200 px-3 py-2 cursor-pointer hover:bg-gray-50"
                    role="button"
                    tabindex="0"
                    aria-haspopup="dialog"
                    :aria-label="ctrans('Edit label :name, :state, :details', { name: label.name, state: label.state_label, details: describeLabel(label) })"
                    :aria-busy="deletingLabelId === label.id"
                    :data-label-id="label.id"
                    :data-label-state="label.state"
                    @click="openLabel(label)"
                    @keydown.enter.self.prevent="openLabel(label)"
                    @keydown.space.self.prevent="openLabel(label)">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded border border-gray-200 bg-gray-50"
                        aria-hidden="true">
                        <img
                            v-if="thumbnailOf(label)"
                            :src="thumbnailOf(label) ?? undefined"
                            class="h-full w-full object-contain"
                            draggable="false"
                            alt="" />
                        <FontAwesomeIcon
                            v-else
                            :icon="label.artwork
                                ? (label.artwork.mime_type === 'application/pdf' ? 'fal fa-file-pdf' : 'fal fa-image')
                                : 'fal fa-tags'"
                            class="text-gray-400"
                            fixed-width />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="truncate text-sm">{{ label.name }}</span>
                            <span
                                class="shrink-0 rounded-full px-2 py-px text-[10px] uppercase tracking-wide"
                                :class="LABEL_STATE_CLASSES[label.state]"
                                :aria-label="ctrans('State: :state', { state: label.state_label })">{{ label.state_label }}</span>
                        </div>
                        <div class="truncate text-xs text-gray-500">
                            {{ describeLabel(label) }}
                            <template v-if="artworkSizeOf(label)"> &middot; {{ artworkSizeOf(label) }}</template>
                        </div>
                        <ul
                            v-if="usedSourcesOf(label).length"
                            class="mt-1 flex items-center gap-1.5"
                            :aria-label="ctrans('Texts printed on this label')">
                            <li
                                v-for="badge in usedSourcesOf(label)"
                                :key="badge.source"
                                class="flex h-5 w-5 items-center justify-center rounded bg-indigo-50 text-[10px] text-indigo-600"
                                :title="badge.label"
                                :aria-label="badge.label">
                                <FontAwesomeIcon :icon="badge.icon" fixed-width aria-hidden="true" />
                            </li>
                        </ul>
                        <ul
                            v-if="data.mandatory_information.length"
                            class="mt-2 flex flex-wrap gap-1.5"
                            :aria-label="ctrans('Mandatory information on this label')"
                            @click.stop
                            @keydown.stop>
                            <li
                                v-for="information in data.mandatory_information"
                                :key="information"
                                class="flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px]"
                                :class="label.missing_information.includes(information) ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'">
                                <FontAwesomeIcon
                                    :icon="label.missing_information.includes(information) ? 'fal fa-exclamation-triangle' : 'fal fa-check'"
                                    fixed-width
                                    aria-hidden="true" />
                                <span>{{ informationLabels[information] ?? information }}</span>
                                <label
                                    v-if="!isPlaced(label, information) && data.abilities?.edit !== false"
                                    class="ml-1 flex items-center gap-1 text-gray-600">
                                    <input
                                        type="checkbox"
                                        class="h-3 w-3 rounded border-gray-300"
                                        :checked="label.on_artwork.includes(information)"
                                        @change="toggleOnArtwork(label, information)" />
                                    {{ ctrans('on artwork') }}
                                </label>
                            </li>
                        </ul>
                    </div>
                    <div v-if="data.abilities?.edit !== false" @click.stop @keydown.stop>
                        <ModalConfirmationDelete
                            :title="ctrans('Delete label :name?', { name: label.name })"
                            :description="ctrans('The label and its layout will no longer be available to print.')"
                            @onYes="onDeleteLabel(label)">
                            <template #default="{ changeModel }">
                                <button
                                    type="button"
                                    class="text-gray-400 hover:text-red-600 disabled:opacity-40"
                                    :aria-label="ctrans('Delete label :name', { name: label.name })"
                                    :title="ctrans('Delete label :name', { name: label.name })"
                                    aria-haspopup="dialog"
                                    :aria-busy="deletingLabelId === label.id"
                                    :disabled="deletingLabelId === label.id"
                                    @click="changeModel">
                                    <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                                </button>
                            </template>
                        </ModalConfirmationDelete>
                    </div>
                </li>
            </ul>
        </section>

        <ArtefactLabelSheetModal
            :isOpen="isOpenLabelSheet"
            :labelSheet="data"
            :labelToEdit="labelToEdit"
            @onClose="isOpenLabelSheet = false"
            @onSaved="router.reload()" />
    </div>
</template>
