<script setup lang="ts">
import { computed, nextTick, reactive, ref } from "vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCopy, faFilePdf, faImage, faPlus, faTags, faTrashAlt } from "@fal"
import pdfWorkerUrl from "pdfjs-dist/build/pdf.worker.min.mjs?url"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import PingIcon from "@/Components/Utils/PingIcon.vue"

library.add(faCopy, faFilePdf, faImage, faPlus, faTags, faTrashAlt)

const props = defineProps<{
    isOpen: boolean
    labelSheet: {
        route: routeType
        batch_code: string
        expiry_date: string
    }
}>()

const emits = defineEmits<{ (e: "onClose"): void }>()

type ItemSource = "batch_code" | "expiry_date"

interface LabelItem {
    id: number
    source: ItemSource
    text: string
    x: number
    y: number
    fontSize: number
    color: string
    bold: boolean
    rotation: Rotation
}

type Rotation = 0 | 90 | 180 | 270

const ROTATIONS: Rotation[] = [0, 90, 180, 270]

const PAGE_SIZES = {
    portrait: { width: 210, height: 297 },
    landscape: { width: 297, height: 210 },
}

const PREVIEW_BOX = { width: 460, height: 600 }
const LINE_HEIGHT = 1.1
const ZOOM_LIMITS = { min: 0.5, max: 8 }
const ZOOM_STEP = 1.25

/**
 * Web servers commonly cap an upload at one megabyte, and an A4 background at 300 dpi is all the
 * detail a printer can use, so the artwork is shrunk here rather than being refused in transit.
 */
const MAX_UPLOAD_BYTES = 700 * 1024
const A4_300DPI_EDGE = 3508

const IMAGE_VARIANTS = [
    { maxEdge: A4_300DPI_EDGE, type: "image/png", quality: undefined },
    { maxEdge: A4_300DPI_EDGE, type: "image/jpeg", quality: 0.9 },
    { maxEdge: A4_300DPI_EDGE, type: "image/jpeg", quality: 0.75 },
    { maxEdge: A4_300DPI_EDGE / 2, type: "image/jpeg", quality: 0.75 },
]
const A4_ASPECT_RATIO = 210 / 297
const A4_ASPECT_TOLERANCE = 0.06

/**
 * A PDF artwork is uploaded byte for byte so its text and vectors reach the sheet intact, the
 * picture drawn here is only ever the on screen preview.
 */
const PDF_MIME_TYPE = "application/pdf"
const PDF_PREVIEW_EDGE = 1400

const orientation = ref<"portrait" | "landscape">("portrait")
const columns = ref(3)
const rows = ref(8)
const pageMargin = ref(8)
const gap = ref(3)
const cutGuides = ref(true)
const isSheetArtwork = ref(false)
const canvasRotation = ref<Rotation>(0)
const isGenerating = ref(false)

const backgroundFile = ref<File | null>(null)
const backgroundPreview = ref<string | null>(null)
const isVectorArtwork = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const isPreparingArtwork = ref(false)

const gridBeforeSheetArtwork = {
    columns: columns.value,
    rows: rows.value,
    pageMargin: pageMargin.value,
    gap: gap.value,
    cutGuides: cutGuides.value,
}

let nextItemId = 1

const createItem = (source: ItemSource, overrides: Partial<LabelItem> = {}): LabelItem => ({
    id: nextItemId++,
    source,
    text: source === "batch_code" ? props.labelSheet.batch_code : props.labelSheet.expiry_date,
    x: 0.06,
    y: source === "batch_code" ? 0.08 : 0.28,
    fontSize: 8,
    color: "#111827",
    bold: true,
    rotation: 0,
    ...overrides,
})

const items = ref<LabelItem[]>([createItem("batch_code"), createItem("expiry_date")])
const selectedItemId = ref<number | null>(items.value[0]?.id ?? null)

const selectedItem = computed(() => items.value.find(item => item.id === selectedItemId.value) ?? null)

const sourceLabels: Record<ItemSource, string> = {
    batch_code: ctrans("Batch code"),
    expiry_date: ctrans("Expiry date"),
}

const addItem = (source: ItemSource) => {
    const item = createItem(source, { x: 0.1, y: 0.1 })
    items.value.push(item)
    selectedItemId.value = item.id
}

const duplicateItem = (item: LabelItem) => {
    const copy = createItem(item.source, {
        text: item.text,
        x: Math.min(item.x + 0.05, 0.95),
        y: Math.min(item.y + 0.05, 0.95),
        fontSize: item.fontSize,
        color: item.color,
        bold: item.bold,
        rotation: item.rotation,
    })
    items.value.push(copy)
    selectedItemId.value = copy.id
}

const removeItem = (item: LabelItem) => {
    items.value = items.value.filter(candidate => candidate.id !== item.id)
    if (selectedItemId.value === item.id) {
        selectedItemId.value = items.value[0]?.id ?? null
    }
}

const pageWidth = computed(() => PAGE_SIZES[orientation.value].width)
const pageHeight = computed(() => PAGE_SIZES[orientation.value].height)

const labelWidth = computed(
    () => (pageWidth.value - 2 * pageMargin.value - gap.value * (columns.value - 1)) / columns.value
)
const labelHeight = computed(
    () => (pageHeight.value - 2 * pageMargin.value - gap.value * (rows.value - 1)) / rows.value
)

const isGridValid = computed(() => labelWidth.value > 2 && labelHeight.value > 2)

const zoom = ref(1)
const previewViewport = ref<HTMLElement | null>(null)

const fitScale = computed(() =>
    Math.min(PREVIEW_BOX.width / pageWidth.value, PREVIEW_BOX.height / pageHeight.value)
)

const scale = computed(() => fitScale.value * zoom.value)

const toPx = (millimeters: number) => millimeters * scale.value

const setZoom = (value: number) => {
    zoom.value = Math.min(Math.max(value, ZOOM_LIMITS.min), ZOOM_LIMITS.max)
}

const zoomBy = (factor: number) => setZoom(zoom.value * factor)

const showWholePage = () => setZoom(1)

const showEditedLabel = async () => {
    if (!isGridValid.value) return

    setZoom(
        Math.min(
            PREVIEW_BOX.width / (labelWidth.value * fitScale.value),
            PREVIEW_BOX.height / (labelHeight.value * fitScale.value)
        )
    )

    await nextTick()
    centreEditedLabel()
}

/**
 * Scrolls the preview pane itself rather than letting the browser drag the whole modal around.
 */
const centreEditedLabel = () => {
    const viewport = previewViewport.value
    const cell = editorCell.value
    if (!viewport || !cell) return

    const cellRect = cell.getBoundingClientRect()
    const viewportRect = viewport.getBoundingClientRect()

    viewport.scrollLeft += cellRect.left - viewportRect.left - (viewportRect.width - cellRect.width) / 2
    viewport.scrollTop += cellRect.top - viewportRect.top - (viewportRect.height - cellRect.height) / 2
}

const onPreviewWheel = (event: WheelEvent) => {
    if (!event.ctrlKey && !event.metaKey) return

    event.preventDefault()
    zoomBy(event.deltaY < 0 ? ZOOM_STEP : 1 / ZOOM_STEP)
}

const cells = computed(() => {
    if (!isGridValid.value) return []

    const placed: { left: number; top: number; index: number }[] = []

    for (let row = 0; row < rows.value; row++) {
        for (let column = 0; column < columns.value; column++) {
            placed.push({
                index: row * columns.value + column,
                left: pageMargin.value + column * (labelWidth.value + gap.value),
                top: pageMargin.value + row * (labelHeight.value + gap.value),
            })
        }
    }

    return placed
})

const printableItems = computed(() => items.value.filter(item => item.text.trim()))

const fontSizePx = (item: LabelItem) => item.fontSize * (25.4 / 72) * scale.value

const backgroundStyle = computed(() => {
    const width = toPx(labelWidth.value)
    const height = toPx(labelHeight.value)
    const runsSideways = canvasRotation.value === 90 || canvasRotation.value === 270

    return {
        left: "50%",
        top: "50%",
        width: `${runsSideways ? height : width}px`,
        height: `${runsSideways ? width : height}px`,
        transform: `translate(-50%, -50%) rotate(${canvasRotation.value}deg)`,
        objectFit: "fill",
    }
})

/**
 * Keeps the top left corner of the turned text where it was before the turn, which is where mPDF
 * anchors a rotated block too.
 */
const itemTransform = (item: LabelItem) => {
    switch (item.rotation) {
        case 90:
            return "rotate(90deg) translateY(-100%)"
        case 180:
            return "rotate(180deg) translate(-100%, -100%)"
        case 270:
            return "rotate(270deg) translateX(-100%)"
        default:
            return "none"
    }
}

const itemStyle = (item: LabelItem) => ({
    left: `${item.x * toPx(labelWidth.value)}px`,
    top: `${item.y * toPx(labelHeight.value)}px`,
    fontSize: `${fontSizePx(item)}px`,
    lineHeight: String(LINE_HEIGHT),
    color: item.color,
    fontWeight: item.bold ? 700 : 400,
    fontFamily: "Arial, sans-serif",
    transform: itemTransform(item),
    transformOrigin: "0 0",
})

/**
 * mPDF needs the length of the run to lay a turned block out, and the browser is the only side that
 * knows how wide the text actually draws.
 */
const textLengthInMillimeters = (item: LabelItem) => {
    const chip = chipElements[item.id]

    return chip ? chip.offsetWidth / scale.value : null
}

const applySheetArtwork = () => {
    columns.value = 1
    rows.value = 1
    pageMargin.value = 0
    gap.value = 0
    cutGuides.value = false
}

const toggleSheetArtwork = (enabled: boolean) => {
    isSheetArtwork.value = enabled

    if (enabled) {
        gridBeforeSheetArtwork.columns = columns.value
        gridBeforeSheetArtwork.rows = rows.value
        gridBeforeSheetArtwork.pageMargin = pageMargin.value
        gridBeforeSheetArtwork.gap = gap.value
        gridBeforeSheetArtwork.cutGuides = cutGuides.value
        applySheetArtwork()
        return
    }

    columns.value = gridBeforeSheetArtwork.columns
    rows.value = gridBeforeSheetArtwork.rows
    pageMargin.value = gridBeforeSheetArtwork.pageMargin
    gap.value = gridBeforeSheetArtwork.gap
    cutGuides.value = gridBeforeSheetArtwork.cutGuides
}

const detectSheetArtwork = (width: number, height: number) => {
    const ratio = width / height
    const looksPortrait = Math.abs(ratio - A4_ASPECT_RATIO) < A4_ASPECT_TOLERANCE
    const looksLandscape = Math.abs(ratio - 1 / A4_ASPECT_RATIO) < A4_ASPECT_TOLERANCE

    if (!looksPortrait && !looksLandscape) return

    orientation.value = looksPortrait ? "portrait" : "landscape"
    toggleSheetArtwork(true)

    notify({
        title: ctrans("Full sheet artwork detected"),
        text: ctrans("The artwork has A4 proportions, the grid is set to 1 × 1 so it covers the whole page."),
        type: "success",
    })
}

const isPdf = (file: File) => file.type === PDF_MIME_TYPE || /\.pdf$/i.test(file.name)

let pdfjs: typeof import("pdfjs-dist") | null = null

const loadPdfjs = async () => {
    if (!pdfjs) {
        pdfjs = await import("pdfjs-dist")
        pdfjs.GlobalWorkerOptions.workerSrc = pdfWorkerUrl
    }

    return pdfjs
}

const renderPdfPreview = async (file: File): Promise<{ preview: string; width: number; height: number }> => {
    const { getDocument } = await loadPdfjs()
    const loadingTask = getDocument({ data: new Uint8Array(await file.arrayBuffer()) })

    try {
        const page = await (await loadingTask.promise).getPage(1)
        const { width, height } = page.getViewport({ scale: 1 })
        const viewport = page.getViewport({ scale: Math.min(PDF_PREVIEW_EDGE / Math.max(width, height), 4) })

        const canvas = document.createElement("canvas")
        canvas.width = Math.round(viewport.width)
        canvas.height = Math.round(viewport.height)

        await page.render({ canvas, viewport }).promise

        return { preview: canvas.toDataURL("image/png"), width, height }
    } finally {
        loadingTask.destroy()
    }
}

const loadImage = (source: string) =>
    new Promise<HTMLImageElement>((resolve, reject) => {
        const image = new Image()
        image.onload = () => resolve(image)
        image.onerror = () => reject(new Error("The image could not be read"))
        image.src = source
    })

const drawScaled = (image: HTMLImageElement, maxEdge: number) => {
    const ratio = Math.min(1, maxEdge / Math.max(image.width, image.height))
    const canvas = document.createElement("canvas")
    canvas.width = Math.max(Math.round(image.width * ratio), 1)
    canvas.height = Math.max(Math.round(image.height * ratio), 1)
    canvas.getContext("2d")?.drawImage(image, 0, 0, canvas.width, canvas.height)

    return canvas
}

const toBlob = (canvas: HTMLCanvasElement, type: string, quality?: number) =>
    new Promise<Blob | null>(resolve => canvas.toBlob(resolve, type, quality))

const shrinkForUpload = async (file: File, image: HTMLImageElement): Promise<File> => {
    const alreadySmall = file.size <= MAX_UPLOAD_BYTES && Math.max(image.width, image.height) <= A4_300DPI_EDGE

    if (alreadySmall) {
        return file
    }

    const variants = IMAGE_VARIANTS.filter(variant => variant.type !== "image/png" || file.type === "image/png")
    let smallest: { blob: Blob; type: string } | null = null

    for (const variant of variants) {
        const blob = await toBlob(drawScaled(image, variant.maxEdge), variant.type, variant.quality)
        if (!blob) continue

        if (!smallest || blob.size < smallest.blob.size) {
            smallest = { blob, type: variant.type }
        }

        if (blob.size <= MAX_UPLOAD_BYTES) break
    }

    if (!smallest) {
        return file
    }

    const extension = smallest.type === "image/png" ? "png" : "jpg"
    const name = file.name.replace(/\.[^.]+$/, "")

    return new File([smallest.blob], `${name}.${extension}`, { type: smallest.type })
}

const formatBytes = (bytes: number) =>
    bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`

const chipElements = reactive<Record<number, HTMLElement | null>>({})
const editorCell = ref<HTMLElement | null>(null)
const draggingId = ref<number | null>(null)
const dragOffset = reactive({ x: 0, y: 0 })
const resizing = ref<{ id: number; startX: number; startY: number; startLength: number; startFontSize: number } | null>(null)

const clamp = (value: number, min: number, max: number) => Math.min(Math.max(value, min), max)

const startDrag = (item: LabelItem, event: PointerEvent) => {
    selectedItemId.value = item.id

    const chip = event.currentTarget as HTMLElement
    const chipRect = chip.getBoundingClientRect()
    dragOffset.x = event.clientX - chipRect.left
    dragOffset.y = event.clientY - chipRect.top
    draggingId.value = item.id
    chip.setPointerCapture(event.pointerId)
}

const onDrag = (item: LabelItem, event: PointerEvent) => {
    if (draggingId.value !== item.id || !editorCell.value) return

    const cellRect = editorCell.value.getBoundingClientRect()
    const chipRect = (event.currentTarget as HTMLElement).getBoundingClientRect()
    const maxLeft = Math.max(cellRect.width - chipRect.width, 0)
    const maxTop = Math.max(cellRect.height - chipRect.height, 0)

    const left = clamp(event.clientX - cellRect.left - dragOffset.x, 0, maxLeft)
    const top = clamp(event.clientY - cellRect.top - dragOffset.y, 0, maxTop)

    item.x = cellRect.width ? left / cellRect.width : 0
    item.y = cellRect.height ? top / cellRect.height : 0
}

const stopDrag = (event: PointerEvent) => {
    const chip = event.currentTarget as HTMLElement
    if (chip.hasPointerCapture(event.pointerId)) {
        chip.releasePointerCapture(event.pointerId)
    }
    draggingId.value = null
}

const startResize = (item: LabelItem, event: PointerEvent) => {
    event.stopPropagation()

    const chip = chipElements[item.id]
    if (!chip) return

    resizing.value = {
        id: item.id,
        startX: event.clientX,
        startY: event.clientY,
        startLength: chip.offsetWidth,
        startFontSize: item.fontSize,
    }

    const handle = event.currentTarget as HTMLElement
    handle.setPointerCapture(event.pointerId)
}

const onResize = (item: LabelItem, event: PointerEvent) => {
    if (resizing.value?.id !== item.id) return

    const { startX, startY, startLength, startFontSize } = resizing.value
    const alongText = {
        0: event.clientX - startX,
        90: event.clientY - startY,
        180: startX - event.clientX,
        270: startY - event.clientY,
    }[item.rotation]

    const nextLength = Math.max(startLength + alongText, 8)

    item.fontSize = clamp(Number(((startFontSize * nextLength) / startLength).toFixed(1)), 3, 72)
}

const stopResize = (event: PointerEvent) => {
    const handle = event.currentTarget as HTMLElement
    if (handle.hasPointerCapture(event.pointerId)) {
        handle.releasePointerCapture(event.pointerId)
    }
    resizing.value = null
}

const anchorTo = (item: LabelItem, horizontal: number, vertical: number) => {
    const chip = chipElements[item.id]
    const cellRect = editorCell.value?.getBoundingClientRect()
    if (!chip || !cellRect || !cellRect.width || !cellRect.height) return

    const chipRect = chip.getBoundingClientRect()

    item.x = clamp(horizontal * (1 - chipRect.width / cellRect.width), 0, 1)
    item.y = clamp(vertical * (1 - chipRect.height / cellRect.height), 0, 1)
}

const onFileChange = async (event: Event) => {
    const target = event.target as HTMLInputElement
    const file = target.files?.[0]
    target.value = ""
    if (!file) return

    isPreparingArtwork.value = true

    try {
        if (isPdf(file)) {
            const { preview, width, height } = await renderPdfPreview(file)

            replaceBackground(file, preview, true)
            detectSheetArtwork(width, height)
            warnAboutPdfSize(file)
        } else {
            const sourceUrl = URL.createObjectURL(file)

            try {
                const image = await loadImage(sourceUrl)
                const prepared = await shrinkForUpload(file, image)

                replaceBackground(prepared, URL.createObjectURL(prepared), false)
                detectSheetArtwork(image.width, image.height)
            } finally {
                URL.revokeObjectURL(sourceUrl)
            }
        }
    } catch (error: any) {
        console.log('eeeeeeeeeee', error)
        notify({
            title: ctrans("Something went wrong"),
            text: isPdf(file) ? ctrans("The PDF could not be read") : ctrans("The image could not be read"),
            type: "error",
        })
    } finally {
        isPreparingArtwork.value = false
    }
}

const warnAboutPdfSize = (file: File) => {
    if (file.size <= MAX_UPLOAD_BYTES) return

    notify({
        title: ctrans("Large PDF"),
        text: ctrans("Shrinking it would flatten the text, so it is sent as it is and the server may refuse it."),
        type: "warn",
    })
}

const replaceBackground = (file: File, preview: string, vector: boolean) => {
    releasePreview()

    backgroundFile.value = file
    backgroundPreview.value = preview
    isVectorArtwork.value = vector
}

const releasePreview = () => {
    if (backgroundPreview.value?.startsWith("blob:")) {
        URL.revokeObjectURL(backgroundPreview.value)
    }
}

const removeBackground = () => {
    releasePreview()

    backgroundFile.value = null
    backgroundPreview.value = null
    isVectorArtwork.value = false
}

const generatePdf = async () => {
    if (!isGridValid.value) return

    isGenerating.value = true

    try {
        const formData = new FormData()
        formData.append("orientation", orientation.value)
        formData.append("columns", String(columns.value))
        formData.append("rows", String(rows.value))
        formData.append("page_margin", String(pageMargin.value))
        formData.append("gap", String(gap.value))
        formData.append("cut_guides", cutGuides.value ? "1" : "0")
        formData.append("canvas_rotation", String(canvasRotation.value))

        if (backgroundFile.value) {
            formData.append("background_artwork", backgroundFile.value)
        }

        printableItems.value.forEach((item, index) => {
            formData.append(`fields[${index}][text]`, item.text)
            formData.append(`fields[${index}][x]`, String(item.x))
            formData.append(`fields[${index}][y]`, String(item.y))
            formData.append(`fields[${index}][font_size]`, String(item.fontSize))
            formData.append(`fields[${index}][color]`, item.color)
            formData.append(`fields[${index}][bold]`, item.bold ? "1" : "0")
            formData.append(`fields[${index}][rotation]`, String(item.rotation))

            const length = textLengthInMillimeters(item)
            if (length) {
                formData.append(`fields[${index}][length]`, length.toFixed(3))
            }
        })

        const response = await axios.post(
            route(props.labelSheet.route.name, props.labelSheet.route.parameters),
            formData,
            { responseType: "blob" }
        )

        const pdfUrl = URL.createObjectURL(new Blob([response.data], { type: "application/pdf" }))
        window.open(pdfUrl, "_blank")
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: await describeFailure(error),
            type: "error",
        })
    } finally {
        isGenerating.value = false
    }
}

/**
 * The response arrives as a blob because a sheet is expected, so an error body has to be read back
 * as text before it can say anything useful.
 */
const describeFailure = async (error: any): Promise<string> => {
    const status = error?.response?.status

    if (status === 413) {
        return ctrans("The background artwork is too large for the server to accept, use a smaller one.")
    }

    try {
        const body = await (error?.response?.data as Blob)?.text()
        const message = JSON.parse(body ?? "")?.message

        if (message) {
            return message
        }
    } catch {
        return ctrans("The label sheet could not be generated")
    }

    return ctrans("The label sheet could not be generated")
}
</script>

<template>
    <Modal :isOpen="isOpen" closeButton :isClosableInBackground="false" @onClose="emits('onClose')" width="w-full max-w-6xl">
        <div class="flex items-center gap-2 mb-4">
            <FontAwesomeIcon icon="fal fa-tags" class="text-gray-400" fixed-width aria-hidden="true" />
            <h2 class="text-lg font-semibold">{{ ctrans("Label sheet") }}</h2>
        </div>

        <div class="flex flex-col lg:flex-row gap-6">
            <div class="w-full lg:w-80 shrink-0 space-y-4">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">{{ ctrans("Orientation") }}</div>
                    <div class="flex gap-2">
                        <button
                            v-for="option in (['portrait', 'landscape'] as const)"
                            :key="option"
                            class="flex-1 rounded border px-2 py-1.5 text-sm"
                            :class="orientation === option ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                            @click="orientation = option">
                            {{ option === 'portrait' ? ctrans("Vertical") : ctrans("Horizontal") }}
                        </button>
                    </div>
                </div>

                <hr class="border-t border-gray-400 border-dashed" />

                <!-- Field: Background artwork -->
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">
                        {{ ctrans("Background artwork") }}
                        <PingIcon v-if="!backgroundFile" class="text-[6px] text-orange-500" />
                    </div>
                    <input ref="fileInput" type="file" accept="image/*,application/pdf" class="hidden" @change="onFileChange" />
                    <div class="flex gap-2">
                        <Button
                            type="tertiary"
                            size="xs"
                            :icon="isVectorArtwork ? 'fal fa-file-pdf' : 'fal fa-image'"
                            :loading="isPreparingArtwork"
                            :label="backgroundFile ? ctrans('Replace artwork') : ctrans('Upload image or PDF')"
                            @click="() => fileInput?.click()" />
                        <Button
                            v-if="backgroundFile"
                            type="negative"
                            size="xs"
                            icon="fal fa-trash-alt"
                            @click="removeBackground" />
                    </div>
                    <div v-if="backgroundFile" class="mt-1 truncate text-xs text-gray-500">
                        {{ backgroundFile.name }} • {{ formatBytes(backgroundFile.size) }}
                    </div>
                    <div v-if="isVectorArtwork" class="mt-1 text-xs text-emerald-600">
                        {{ ctrans("Placed as vector, the text inside the PDF stays selectable.") }}
                    </div>
                </div>

                <!-- <hr class="border-t border-gray-400 border-dashed" /> -->

                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">{{ ctrans("Canvas rotation") }}</div>
                    <div class="flex gap-1">
                        <button
                            v-for="angle in ROTATIONS"
                            :key="angle"
                            class="flex-1 rounded border px-2 py-1.5 text-sm"
                            :class="canvasRotation === angle ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                            @click="canvasRotation = angle">
                            {{ angle }}°
                        </button>
                    </div>
                </div>

                <hr class="border-t border-gray-400 border-dashed" />

                <label class="flex items-start gap-2 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        class="mt-0.5 rounded border-gray-300"
                        :checked="isSheetArtwork"
                        @change="toggleSheetArtwork(($event.target as HTMLInputElement).checked)" />
                    <span>
                        {{ ctrans("Artwork already contains the grid") }}
                        <span class="block text-xs text-gray-500">
                            {{ ctrans("The whole A4 is one label, drop the texts straight onto the artwork.") }}
                        </span>
                    </span>
                </label>

                <div class="grid grid-cols-2 gap-3" :class="isSheetArtwork ? 'opacity-50' : ''">
                    <label class="block">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans("Columns") }}</span>
                        <input v-model.number="columns" type="number" min="1" max="20" :disabled="isSheetArtwork"
                            class="mt-1 w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                    </label>
                    <label class="block">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans("Rows") }}</span>
                        <input v-model.number="rows" type="number" min="1" max="30" :disabled="isSheetArtwork"
                            class="mt-1 w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                    </label>
                    <label class="block">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans("Page margin (mm)") }}</span>
                        <input v-model.number="pageMargin" type="number" min="0" max="40" step="0.5" :disabled="isSheetArtwork"
                            class="mt-1 w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                    </label>
                    <label class="block">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans("Gap (mm)") }}</span>
                        <input v-model.number="gap" type="number" min="0" max="30" step="0.5" :disabled="isSheetArtwork"
                            class="mt-1 w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                    </label>
                </div>

                <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                    <span v-if="isGridValid">
                        {{ columns * rows }} {{ ctrans("labels") }} ·
                        {{ labelWidth.toFixed(1) }} × {{ labelHeight.toFixed(1) }} mm
                    </span>
                    <span v-else class="text-red-600">
                        {{ ctrans("The grid does not fit on the page, reduce the labels, the margin or the gap.") }}
                    </span>
                </div>

                <hr class="border-t border-gray-400 border-dashed" />

                <label v-if="!isSheetArtwork" class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="cutGuides" type="checkbox" class="rounded border-gray-300" />
                    {{ ctrans("Show cutting guides") }}
                </label>

                <div class="space-y-2">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans("Texts") }}</div>
                    <div class="flex gap-2">
                        <Button type="tertiary" size="xs" icon="fal fa-plus"
                            :label="sourceLabels.batch_code" @click="addItem('batch_code')" />
                        <Button type="tertiary" size="xs" icon="fal fa-plus"
                            :label="sourceLabels.expiry_date" @click="addItem('expiry_date')" />
                    </div>

                    <div v-if="!items.length" class="rounded border border-dashed border-gray-300 px-3 py-2 text-xs text-gray-500">
                        {{ ctrans("No text on the label yet.") }}
                    </div>

                    <div
                        v-for="item in items"
                        :key="item.id"
                        class="flex items-center gap-2 rounded border px-2 py-1.5 cursor-pointer"
                        :class="item.id === selectedItemId ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'"
                        @click="selectedItemId = item.id">
                        <span class="min-w-0 flex-1 truncate text-sm" :style="{ color: item.color }">{{ item.text || sourceLabels[item.source] }}</span>
                        <span class="text-xs text-gray-400">{{ item.fontSize }}pt</span>
                        <span v-if="item.rotation" class="text-xs text-gray-400">{{ item.rotation }}°</span>
                        <button class="text-gray-400 hover:text-indigo-600" @click.stop="duplicateItem(item)">
                            <FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" />
                        </button>
                        <button class="text-gray-400 hover:text-red-600" @click.stop="removeItem(item)">
                            <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                </div>

                <div v-if="selectedItem" class="rounded border border-gray-200 p-3 space-y-2">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ sourceLabels[selectedItem.source] }}</div>

                    <input v-model="selectedItem.text" type="text"
                        class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />

                    <div class="flex items-center gap-2">
                        <label class="flex items-center gap-1 text-xs text-gray-500">
                            {{ ctrans("Size") }}
                            <input v-model.number="selectedItem.fontSize" type="number" min="3" max="72" step="0.5"
                                class="w-16 rounded border border-gray-300 px-1.5 py-1 text-sm" />
                        </label>
                        <input v-model="selectedItem.color" type="color" class="h-7 w-8 rounded border border-gray-300" />
                        <label class="flex items-center gap-1 text-xs text-gray-500">
                            <input v-model="selectedItem.bold" type="checkbox" class="rounded border-gray-300" />
                            {{ ctrans("Bold") }}
                        </label>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400">{{ ctrans("Rotation") }}</span>
                        <div class="flex gap-1">
                            <button
                                v-for="angle in ROTATIONS"
                                :key="angle"
                                class="rounded border px-1.5 py-0.5 text-xs"
                                :class="selectedItem.rotation === angle ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                                @click="selectedItem.rotation = angle">
                                {{ angle }}°
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400">{{ ctrans("Snap to") }}</span>
                        <div class="grid grid-cols-3 gap-0.5">
                            <button
                                v-for="anchor in [
                                    { h: 0, v: 0 }, { h: 0.5, v: 0 }, { h: 1, v: 0 },
                                    { h: 0, v: 0.5 }, { h: 0.5, v: 0.5 }, { h: 1, v: 0.5 },
                                    { h: 0, v: 1 }, { h: 0.5, v: 1 }, { h: 1, v: 1 },
                                ]"
                                :key="`${anchor.h}-${anchor.v}`"
                                class="h-5 w-5 rounded-sm border border-gray-300 hover:bg-indigo-100"
                                @click="anchorTo(selectedItem, anchor.h, anchor.v)" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-1 min-w-0">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-gray-500">
                        <template v-if="isSheetArtwork">
                            {{ ctrans("Drag each text onto the artwork, duplicate it to cover every label the image already has.") }}
                        </template>
                        <template v-else>
                            {{ ctrans("Drag the texts inside the highlighted label, the positions are applied to every label on the sheet.") }}
                        </template>
                    </p>

                    <div class="flex items-center gap-1">
                        <button
                            class="h-6 w-6 rounded border border-gray-300 text-gray-600 hover:bg-gray-50"
                            :title="ctrans('Zoom out')"
                            @click="zoomBy(1 / ZOOM_STEP)">−</button>
                        <span class="w-12 text-center text-xs tabular-nums text-gray-500">{{ Math.round(zoom * 100) }}%</span>
                        <button
                            class="h-6 w-6 rounded border border-gray-300 text-gray-600 hover:bg-gray-50"
                            :title="ctrans('Zoom in')"
                            @click="zoomBy(ZOOM_STEP)">+</button>
                        <button
                            class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50"
                            @click="showWholePage">{{ ctrans("Whole page") }}</button>
                        <button
                            class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50"
                            @click="showEditedLabel">{{ ctrans("Edited label") }}</button>
                    </div>

                    

                    <div class="flex gap-2">
                        <Button
                            type="primary"
                            full
                            icon="fas fa-download"
                            :label="ctrans('Download PDF')"
                            :loading="isGenerating"
                            :disabled="!isGridValid"
                            @click="generatePdf" />
                    </div>
                </div>

                <div
                    ref="previewViewport"
                    class="max-h-[70vh] overflow-auto rounded border border-gray-200 bg-gray-100 p-4"
                    @wheel="onPreviewWheel">
                    <div
                        class="relative mx-auto bg-white shadow-sm"
                        :style="{ width: `${toPx(pageWidth)}px`, height: `${toPx(pageHeight)}px` }">
                        <div
                            v-for="cell in cells"
                            :key="cell.index"
                            :ref="element => { if (cell.index === 0) editorCell = element as HTMLElement }"
                            class="absolute overflow-hidden"
                            :class="cell.index === 0 ? 'ring-1 ring-indigo-500' : cutGuides ? 'border border-dashed border-gray-300' : ''"
                            :style="{
                                left: `${toPx(cell.left)}px`,
                                top: `${toPx(cell.top)}px`,
                                width: `${toPx(labelWidth)}px`,
                                height: `${toPx(labelHeight)}px`,
                            }">
                            <img
                                v-if="backgroundPreview"
                                :src="backgroundPreview"
                                class="absolute max-w-none"
                                :style="backgroundStyle"
                                draggable="false"
                                alt="" />

                            <template v-if="cell.index === 0">
                                <div
                                    v-for="item in printableItems"
                                    :key="item.id"
                                    :ref="element => { chipElements[item.id] = element as HTMLElement }"
                                    class="absolute cursor-move whitespace-nowrap select-none outline outline-1 outline-dashed"
                                    :class="item.id === selectedItemId ? 'outline-indigo-500' : 'outline-indigo-300/60'"
                                    :style="itemStyle(item)"
                                    @pointerdown="startDrag(item, $event)"
                                    @pointermove="onDrag(item, $event)"
                                    @pointerup="stopDrag"
                                    @pointercancel="stopDrag">
                                    {{ item.text }}
                                    <span
                                        v-if="item.id === selectedItemId"
                                        class="absolute -bottom-1 -right-1 h-2.5 w-2.5 cursor-nwse-resize rounded-sm border border-white bg-indigo-500"
                                        @pointerdown="startResize(item, $event)"
                                        @pointermove="onResize(item, $event)"
                                        @pointerup="stopResize"
                                        @pointercancel="stopResize" />
                                </div>
                            </template>

                            <template v-else>
                                <div
                                    v-for="item in printableItems"
                                    :key="item.id"
                                    class="absolute whitespace-nowrap select-none"
                                    :style="itemStyle(item)">
                                    {{ item.text }}
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>
