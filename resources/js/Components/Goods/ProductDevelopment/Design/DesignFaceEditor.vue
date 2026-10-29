<!--
  - Author Louis Perez
  - Created on 17-10-2026-13h-40m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue"
import axios from "axios"
import Slider from "primevue/slider"
import InputNumber from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import Select from "primevue/select"
import Checkbox from "primevue/checkbox"
import DesignColorField from "@/Components/Goods/ProductDevelopment/Design/DesignColorField.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import DesignFaceRender from "@/Components/Goods/ProductDevelopment/Design/DesignFaceRender.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import Popover from "primevue/popover"
import { COMPLIANCE_PARTS, PT_TO_MM, boundsOf, complianceParts, coverBox, type Bounds, ensureImageRatio, imageRatios, newElementId, type DesignData, type DesignElement, type DesignFace, type DesignImage } from "@/Components/Goods/ProductDevelopment/Design/boxNet"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFont, faImage, faBarcode, faShieldCheck, faSquare, faTrashAlt, faClone, faArrowUp, faArrowDown, faUndo, faRedo, faBold, faItalic, faAlignLeft, faAlignCenter, faAlignRight, faFillDrip, faTimes, faArrows, faDatabase, faMagic, faCopy, faCut, faLock, faLockOpen, faObjectGroup, faObjectUngroup, faArrowsH, faArrowsV, faBorderLeft, faBorderCenterV, faBorderRight, faBorderTop, faBorderCenterH, faBorderBottom, faExclamationTriangle, faFolder, faFolderOpen, faChevronDown, faShapes, faSquareFull, faTriangle, faMinus, faCircle, faSpinner } from "@fal"
library.add(faFont, faImage, faBarcode, faShieldCheck, faSquare, faTrashAlt, faClone, faArrowUp, faArrowDown, faUndo, faRedo, faBold, faItalic, faAlignLeft, faAlignCenter, faAlignRight, faFillDrip, faTimes, faArrows, faDatabase, faMagic, faCopy, faCut, faLock, faLockOpen, faObjectGroup, faObjectUngroup, faArrowsH, faArrowsV, faBorderLeft, faBorderCenterV, faBorderRight, faBorderTop, faBorderCenterH, faBorderBottom, faExclamationTriangle, faFolder, faFolderOpen, faChevronDown, faShapes, faSquareFull, faTriangle, faMinus, faCircle, faSpinner)

const props = defineProps<{
    size: { w: number, h: number }
    label: string
    images: Record<number, string | null>
    imageRoute: routeType
    data?: DesignData | null
}>()

const face = defineModel<DesignFace>({ required: true })

const dataPopover = ref<InstanceType<typeof Popover> | null>(null)
const shapePopover = ref<InstanceType<typeof Popover> | null>(null)

const shapeOptions = computed(() => [
    { value: "rect" as const, label: ctrans("Rectangle"), icon: "fal fa-square" },
    { value: "rounded" as const, label: ctrans("Rounded rectangle"), icon: "fal fa-square-full" },
    { value: "ellipse" as const, label: ctrans("Ellipse"), icon: "fal fa-circle" },
    { value: "triangle" as const, label: ctrans("Triangle"), icon: "fal fa-triangle" },
    { value: "line" as const, label: ctrans("Line"), icon: "fal fa-minus" },
])

const drawingShape = ref<NonNullable<DesignElement["shape"]> | null>(null)
const shapeDraft = ref<{ from: { x: number, y: number }, to: { x: number, y: number }, square: boolean } | null>(null)

/** picking a shape starts drawing it: drag on the side to give it its size, or click for a default one */
function addShape(shape: NonNullable<DesignElement["shape"]>) {
    shapePopover.value?.hide()
    selectedIds.value = []
    editingBackground.value = false
    drawingShape.value = shape
}

function shapeBox(draft: { from: { x: number, y: number }, to: { x: number, y: number }, square: boolean }) {
    let width = draft.to.x - draft.from.x
    let height = draft.to.y - draft.from.y
    if (draft.square) {
        const side = Math.max(Math.abs(width), Math.abs(height))
        width = Math.sign(width || 1) * side
        height = Math.sign(height || 1) * side
    }
    return { x: Math.min(draft.from.x, draft.from.x + width), y: Math.min(draft.from.y, draft.from.y + height), w: Math.abs(width), h: Math.abs(height) }
}

function lineGeometry(draft: { from: { x: number, y: number }, to: { x: number, y: number }, square: boolean }) {
    let angle = Math.atan2(draft.to.y - draft.from.y, draft.to.x - draft.from.x) * 180 / Math.PI
    if (draft.square) {
        angle = Math.round(angle / ROTATE_STEP) * ROTATE_STEP
    }
    const length = Math.hypot(draft.to.x - draft.from.x, draft.to.y - draft.from.y)
    const radians = angle * Math.PI / 180
    const centreX = draft.from.x + Math.cos(radians) * length / 2
    const centreY = draft.from.y + Math.sin(radians) * length / 2
    const thickness = 4
    return { x: centreX - length / 2, y: centreY - thickness / 2, w: length, h: thickness, rotate: Math.round(((angle % 360) + 360) % 360) }
}

const shapeDraftStyle = computed(() => {
    if (!shapeDraft.value || !drawingShape.value) {
        return null
    }
    if (drawingShape.value === "line") {
        const line = lineGeometry(shapeDraft.value)
        return { left: `${line.x * scale.value}px`, top: `${(line.y + line.h / 2) * scale.value}px`, width: `${line.w * scale.value}px`, height: "0px", transform: `rotate(${line.rotate}deg)`, borderTop: "2px dashed var(--app-accent)" }
    }
    const box = shapeBox(shapeDraft.value)
    return {
        left: `${box.x * scale.value}px`,
        top: `${box.y * scale.value}px`,
        width: `${box.w * scale.value}px`,
        height: `${box.h * scale.value}px`,
        border: "1.5px dashed var(--app-accent)",
        borderRadius: drawingShape.value === "ellipse" ? "9999px" : drawingShape.value === "rounded" ? "6px" : "0",
        clipPath: drawingShape.value === "triangle" ? "polygon(50% 0, 100% 100%, 0 100%)" : undefined,
        background: "color-mix(in srgb, var(--app-accent) 12%, transparent)",
    }
})

function startShapeDraw(event: PointerEvent) {
    event.preventDefault()
    const point = pointerMm(event)
    shapeDraft.value = { from: point, to: point, square: event.shiftKey }
    ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)
}

function moveShapeDraw(event: PointerEvent) {
    if (shapeDraft.value) {
        shapeDraft.value = { ...shapeDraft.value, to: pointerMm(event), square: event.shiftKey }
    }
}

function finishShapeDraw() {
    const shape = drawingShape.value
    const draft = shapeDraft.value
    shapeDraft.value = null
    drawingShape.value = null
    if (!shape || !draft) {
        return
    }
    const clicked = Math.hypot(draft.to.x - draft.from.x, draft.to.y - draft.from.y) < 1.5
    const side = round1(Math.min(props.size.w, props.size.h) * 0.3)

    if (shape === "line") {
        const line = clicked ? { x: draft.from.x, y: draft.from.y - 2, w: round1(props.size.w * 0.4), h: 4, rotate: 0 } : lineGeometry(draft)
        addElement({ type: "shape", shape, x: round1(line.x), y: round1(line.y), w: round1(Math.max(line.w, 1)), h: line.h, rotate: line.rotate, fill: "none", stroke: "#111827", stroke_px: 2 })
        return
    }

    const box = clicked ? { x: draft.from.x - side / 2, y: draft.from.y - side / 2, w: side, h: side } : shapeBox(draft)
    addElement({ type: "shape", shape, x: round1(box.x), y: round1(box.y), w: round1(Math.max(box.w, 1)), h: round1(Math.max(box.h, 1)), fill: "#d1d5db", stroke: "none", stroke_px: 1, radius: shape === "rounded" ? 3 : null })
}

const shapeFill = computed({
    get: () => selected.value?.fill ?? "#d1d5db",
    set: (value: string) => {
        if (selected.value) {
            patchElement(selected.value.id, { fill: value })
        }
    },
})

const shapeStroke = computed({
    get: () => selected.value?.stroke ?? "none",
    set: (value: string) => {
        if (selected.value) {
            patchElement(selected.value.id, { stroke: value })
        }
    },
})

const filledZone = computed(() => !!props.data?.values && selected.value?.type === "zone" && (selected.value.zone === "compliance" || selected.value.zone === "barcode"))
const isTextLike = computed(() => selected.value?.type === "text" || selected.value?.type === "data" || (filledZone.value && selected.value?.zone === "compliance"))
const complianceLabels = computed(() => Object.fromEntries((props.data?.sources.text ?? []).map((source) => [source.key, source.label])))

function toggleCompliancePart(part: string, on: boolean) {
    if (!selected.value) {
        return
    }
    remember()
    const parts = complianceParts(selected.value.source).filter((current) => current !== part)
    const next = on ? COMPLIANCE_PARTS.filter((key) => key === part || parts.includes(key)) : parts
    setField("source", next.length === COMPLIANCE_PARTS.length ? null : next.join(",") || "none")
}

function addData(type: "data" | "pictogram" | "barcode", source: string) {
    dataPopover.value?.hide()
    if (type === "pictogram") {
        const side = round1(Math.min(props.size.w, props.size.h) * 0.18)
        addElement({ type, source, w: side, h: side })
    } else if (type === "barcode") {
        addElement({ type, source, w: round1(Math.min(37.3, props.size.w * 0.45)), h: round1(Math.min(22, props.size.h * 0.25)) })
    } else {
        addElement({ type, source, w: round1(props.size.w * 0.8), h: round1(Math.max(props.size.h * 0.12, 8)), font_size: 8, font_weight: "normal", align: "left", color: "#111827", font_family: "" })
    }
}

/**
 * Lays out the SKU's label content on the side in one go: name, the trade unit's pictograms, ingredients,
 * warnings, then manufacturer and EU responsible person beside the barcode at the bottom. It replaces the
 * data items placed before; text, images and reserved areas stay.
 */
function autoplace() {
    remember()
    const width = props.size.w
    const height = props.size.h
    const margin = round1(Math.max(2, Math.min(width, height) * 0.04))
    const inner = width - 2 * margin
    const values = props.data?.values ?? null
    const has = (key: string) => !values || !!values.text[key]
    const bodyPt = Math.min(Math.max((height * 0.032) / PT_TO_MM, 5), 10)
    const placed: DesignElement[] = []
    let y = margin

    const text = (source: string, h: number, fontSize: number, weight: "normal" | "bold" = "normal", align: "left" | "center" = "left", x = margin, w = inner) => {
        placed.push({ id: newElementId(), type: "data", source, x: round1(x), y: round1(y), w: round1(w), h: round1(h), font_size: Math.round(fontSize * 10) / 10, font_weight: weight, align, color: "#111827", font_family: "" })
    }

    const nameHeight = Math.min(height * 0.13, 16)
    text("product_name", nameHeight, Math.min((nameHeight * 0.6) / PT_TO_MM, 28), "bold", "center")
    y += nameHeight + margin / 2

    const pictograms = (props.data?.sources.pictograms ?? []).filter((source) => values ? values.pictograms[source.key] : false)
    if (pictograms.length) {
        const side = Math.min(height * 0.16, 16, (inner - (pictograms.length - 1) * 2) / pictograms.length)
        pictograms.forEach((source, index) => placed.push({ id: newElementId(), type: "pictogram", source: source.key, x: round1(margin + index * (side + 2)), y: round1(y), w: round1(side), h: round1(side) }))
        y += side + margin / 2
    }

    const barcodeHeight = Math.min(height * 0.2, 22)
    const bottomTop = height - margin - barcodeHeight
    const blocks = [["ingredients", 0.55], ["warnings", 0.45]].filter(([key]) => has(key as string))
    const room = Math.max(bottomTop - margin / 2 - y, 0)
    for (const [key, share] of blocks) {
        const h = room * (share as number) / blocks.reduce((sum, [, part]) => sum + (part as number), 0)
        if (h > 3) {
            text(key as string, h - 1, bodyPt)
            y += h
        }
    }

    const barcodeWidth = Math.min(37.3, inner * 0.38)
    if (!values || values.barcodes.unit) {
        placed.push({ id: newElementId(), type: "barcode", source: "unit", x: round1(width - margin - barcodeWidth), y: round1(bottomTop), w: round1(barcodeWidth), h: round1(barcodeHeight) })
    }
    const leftWidth = inner - barcodeWidth - 2
    const parties = ["manufacturer", "eu_responsible"].filter(has)
    y = bottomTop
    parties.forEach((key) => {
        text(key, barcodeHeight / parties.length - 0.5, Math.max(bodyPt - 1, 5), "normal", "left", margin, leftWidth)
        y += barcodeHeight / parties.length
    })

    face.value = {
        ...face.value,
        elements: [...face.value.elements.filter((element) => !["data", "pictogram", "barcode"].includes(element.type)), ...placed],
    }
    selectedId.value = null
}

const emit = defineEmits<{
    imageUploaded: [image: DesignImage]
}>()

const fonts = [
    { value: "", label: "Default" },
    { value: "Arial, Helvetica, sans-serif", label: "Arial" },
    { value: "Georgia, serif", label: "Georgia" },
    { value: "'Times New Roman', Times, serif", label: "Times New Roman" },
    { value: "'Trebuchet MS', sans-serif", label: "Trebuchet" },
    { value: "Verdana, sans-serif", label: "Verdana" },
    { value: "'Courier New', monospace", label: "Courier New" },
]

const alignModes = computed(() => [
    { value: "left" as const, label: ctrans("Align left"), icon: "fal fa-border-left" },
    { value: "centre" as const, label: ctrans("Align centre"), icon: "fal fa-border-center-v" },
    { value: "right" as const, label: ctrans("Align right"), icon: "fal fa-border-right" },
    { value: "top" as const, label: ctrans("Align top"), icon: "fal fa-border-top" },
    { value: "middle" as const, label: ctrans("Align middle"), icon: "fal fa-border-center-h" },
    { value: "bottom" as const, label: ctrans("Align bottom"), icon: "fal fa-border-bottom" },
])

const alignOptions = [
    { value: "left", label: "Left", icon: "fal fa-align-left" },
    { value: "center", label: "Centre", icon: "fal fa-align-center" },
    { value: "right", label: "Right", icon: "fal fa-align-right" },
]

const stage = ref<HTMLElement | null>(null)
const stageSize = ref({ w: 0, h: 0 })
let observer: ResizeObserver | null = null

const scale = computed(() => {
    if (!stageSize.value.w || !props.size.w || !props.size.h) {
        return 1
    }

    return Math.min((stageSize.value.w - 48) / props.size.w, (stageSize.value.h - 48) / props.size.h)
})

const selectedIds = ref<string[]>([])
const selectedId = computed<string | null>({
    get: () => selectedIds.value.length === 1 ? selectedIds.value[0] : null,
    set: (id) => selectedIds.value = id ? [id] : [],
})
const selected = computed(() => face.value.elements.find((element) => element.id === selectedId.value) ?? null)

/** The side itself or its drawn artwork, not an item's box or handles, which sit above with their own clicks */
const isEmptySpace = (event: Event) => event.target === event.currentTarget || !!(event.target as HTMLElement).closest?.("[data-face-artwork]")

function onFacePointerDown(event: PointerEvent) {
    if (isEmptySpace(event)) {
        startMarquee(event)
    }
}

/* Drag-to-select: a rectangle drawn from empty space selects every unlocked item it touches */
const MARQUEE_THRESHOLD_PX = 3
const marquee = ref<{ startX: number, startY: number, x: number, y: number, moved: boolean, additive: boolean, base: string[] } | null>(null)

const marqueeStyle = computed(() => {
    const box = marquee.value
    const stageBox = stage.value?.getBoundingClientRect()
    if (!box?.moved || !stageBox) {
        return null
    }
    return {
        left: `${Math.min(box.startX, box.x) - stageBox.left}px`,
        top: `${Math.min(box.startY, box.y) - stageBox.top}px`,
        width: `${Math.abs(box.x - box.startX)}px`,
        height: `${Math.abs(box.y - box.startY)}px`,
    }
})

function marqueeHits(): string[] {
    const box = marquee.value
    const faceBox = faceEl.value?.getBoundingClientRect()
    if (!box || !faceBox || !scale.value) {
        return []
    }
    const left = (Math.min(box.startX, box.x) - faceBox.left) / scale.value
    const right = (Math.max(box.startX, box.x) - faceBox.left) / scale.value
    const top = (Math.min(box.startY, box.y) - faceBox.top) / scale.value
    const bottom = (Math.max(box.startY, box.y) - faceBox.top) / scale.value

    return face.value.elements
        .filter((element) => !element.locked)
        .filter((element) => {
            const bounds = boundsOf([element])
            return !!bounds && bounds.x < right && bounds.x + bounds.w > left && bounds.y < bottom && bounds.y + bounds.h > top
        })
        .map((element) => element.id)
}

function onMarqueeMove(event: PointerEvent) {
    const box = marquee.value
    if (!box) {
        return
    }
    box.x = event.clientX
    box.y = event.clientY
    if (!box.moved && Math.hypot(box.x - box.startX, box.y - box.startY) < MARQUEE_THRESHOLD_PX) {
        return
    }
    box.moved = true
    selectedIds.value = [...new Set([...box.base, ...marqueeHits()])]
}

function endMarquee() {
    window.removeEventListener("pointermove", onMarqueeMove)
    window.removeEventListener("pointerup", endMarquee)
    window.removeEventListener("pointercancel", endMarquee)
    const box = marquee.value
    marquee.value = null
    if (box && !box.moved && !box.additive) {
        selectedIds.value = []
    }
}

function startMarquee(event: PointerEvent) {
    if (event.button !== 0 || drawingShape.value || editingBackground.value) {
        return
    }
    const additive = event.shiftKey || event.ctrlKey || event.metaKey
    marquee.value = { startX: event.clientX, startY: event.clientY, x: event.clientX, y: event.clientY, moved: false, additive, base: additive ? [...selectedIds.value] : [] }
    window.addEventListener("pointermove", onMarqueeMove)
    window.addEventListener("pointerup", endMarquee)
    window.addEventListener("pointercancel", endMarquee)
}

function onFaceDoubleClick(event: MouseEvent) {
    if (isEmptySpace(event)) {
        toggleBackgroundEditing()
    }
}
const selection = computed(() => face.value.elements.filter((element) => selectedIds.value.includes(element.id)))

/** shared by every side and every editor, so items can be copied from one side and pasted on another */
const clipboard: { elements: DesignElement[], groups: Record<string, string>, pastes: number } = (globalThis as any).__designClipboard ??= { elements: [], groups: {}, pastes: 0 }
const uploading = ref(false)
const uploadError = ref<string | null>(null)
const imageInput = ref<HTMLInputElement | null>(null)
const backgroundInput = ref<HTMLInputElement | null>(null)
const textInput = ref<{ $el?: HTMLElement } | null>(null)

function focusText(selectAll = false) {
    const element = textInput.value?.$el as HTMLTextAreaElement | undefined
    element?.focus()
    if (selectAll) {
        element?.select()
    }
}

const undoStack = ref<string[]>([])
const redoStack = ref<string[]>([])

function remember() {
    undoStack.value.push(JSON.stringify(face.value))
    if (undoStack.value.length > 100) {
        undoStack.value.shift()
    }
    redoStack.value = []
}

function undo() {
    const previous = undoStack.value.pop()
    if (!previous) {
        return
    }
    redoStack.value.push(JSON.stringify(face.value))
    face.value = JSON.parse(previous)
}

function redo() {
    const next = redoStack.value.pop()
    if (!next) {
        return
    }
    undoStack.value.push(JSON.stringify(face.value))
    face.value = JSON.parse(next)
}

const round1 = (value: number) => Math.round(value * 10) / 10

function patchElement(id: string, changes: Partial<DesignElement>) {
    face.value = {
        ...face.value,
        elements: face.value.elements.map((element) => element.id === id ? { ...element, ...changes } : element),
    }
}

function addElement(element: Omit<DesignElement, "id" | "x" | "y"> & { x?: number, y?: number }) {
    remember()
    const id = newElementId()
    const added: DesignElement = {
        ...element,
        id,
        w: Math.min(element.w, props.size.w),
        h: Math.min(element.h, props.size.h),
        x: element.x ?? round1((props.size.w - Math.min(element.w, props.size.w)) / 2),
        y: element.y ?? round1((props.size.h - Math.min(element.h, props.size.h)) / 2),
    }
    face.value = { ...face.value, elements: [...face.value.elements, added] }
    selectedId.value = id
}

function addText() {
    addElement({ type: "text", w: round1(props.size.w * 0.8), h: round1(Math.max(props.size.h * 0.12, 8)), text: ctrans("Your text"), font_size: 12, font_weight: "normal", align: "center", color: "#111827", font_family: "" })
    nextTick(() => focusText(true))
}

function addZone(zone: "compliance" | "barcode" | "other") {
    const sizes = {
        compliance: { w: props.size.w * 0.85, h: props.size.h * 0.3 },
        barcode: { w: Math.min(37.3, props.size.w * 0.8), h: Math.min(25.9, props.size.h * 0.3) },
        other: { w: props.size.w * 0.5, h: props.size.h * 0.2 },
    }[zone]
    addElement({ type: "zone", zone, w: round1(sizes.w), h: round1(sizes.h), label: null })
}

async function upload(file: File): Promise<DesignImage | null> {
    uploading.value = true
    uploadError.value = null
    try {
        const form = new FormData()
        form.append("image", file)
        const { data } = await axios.post(route(props.imageRoute.name, props.imageRoute.parameters), form)
        emit("imageUploaded", data)
        return data
    } catch (error: any) {
        uploadError.value = error?.response?.data?.errors?.image?.[0] ?? error?.response?.data?.message ?? ctrans("The image could not be uploaded")
        return null
    } finally {
        uploading.value = false
    }
}

function naturalRatio(url: string): Promise<number> {
    return new Promise((resolve) => {
        const image = new Image()
        image.onload = () => resolve(image.naturalWidth ? image.naturalHeight / image.naturalWidth : 1)
        image.onerror = () => resolve(1)
        image.src = url
    })
}

async function onImagePicked(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ""
    if (!file) {
        return
    }
    const image = await upload(file)
    if (!image) {
        return
    }
    const ratio = await naturalRatio(URL.createObjectURL(file))
    let w = props.size.w * 0.6
    let h = w * ratio
    if (h > props.size.h * 0.8) {
        h = props.size.h * 0.8
        w = h / ratio
    }
    addElement({ type: "image", image_file_id: image.id, w: round1(w), h: round1(h) })
}

async function onBackgroundPicked(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ""
    if (!file) {
        return
    }
    const image = await upload(file)
    if (image) {
        remember()
        face.value = { ...face.value, background: { ...face.value.background, image_file_id: image.id } }
    }
}

function clearBackgroundImage() {
    remember()
    face.value = { ...face.value, background: { ...face.value.background, image_file_id: null } }
}

const backgroundColor = computed({
    get: () => face.value.background.color ?? "#ffffff",
    set: (value: string) => {
        face.value = { ...face.value, background: { ...face.value.background, color: value.toLowerCase() } }
    },
})

const textColor = computed({
    get: () => selected.value?.color ?? "#111827",
    set: (value: string) => {
        if (selected.value) {
            patchElement(selected.value.id, { color: value.toLowerCase() })
        }
    },
})

function setField<K extends keyof DesignElement>(key: K, value: DesignElement[K]) {
    if (selected.value) {
        patchElement(selected.value.id, { [key]: value } as Partial<DesignElement>)
    }
}

function removeSelected() {
    if (!selectedIds.value.length) {
        return
    }
    const removable = selection.value.filter((element) => !element.locked).map((element) => element.id)
    if (!removable.length) {
        return
    }
    remember()
    face.value = { ...face.value, elements: face.value.elements.filter((element) => !removable.includes(element.id)) }
    selectedIds.value = selectedIds.value.filter((id) => !removable.includes(id))
}

function pasteElements(elements: DesignElement[], offset: number, sourceNames: Record<string, string> = {}) {
    if (!elements.length) {
        return
    }
    remember()
    const groups: Record<string, string> = {}
    const pasted = elements.map((element) => ({
        ...element,
        id: newElementId(),
        locked: false,
        group_id: element.group_id ? (groups[element.group_id] ??= newElementId()) : null,
        x: round1(Math.min(Math.max(element.x + offset, -element.w / 2), props.size.w - element.w / 2)),
        y: round1(Math.min(Math.max(element.y + offset, -element.h / 2), props.size.h - element.h / 2)),
    }))
    const names = Object.fromEntries(Object.entries(groups).filter(([oldId]) => sourceNames[oldId]).map(([oldId, newId]) => [newId, ctrans(":name copy", { name: sourceNames[oldId] })]))
    face.value = { ...face.value, elements: [...face.value.elements, ...pasted], groups: { ...(face.value.groups ?? {}), ...names } }
    selectedIds.value = pasted.map((element) => element.id)
}

const itemActions = computed(() => [
    { key: "forward", label: ctrans("Bring forward"), icon: "fal fa-arrow-up", danger: false, run: () => moveLayer(1) },
    { key: "backward", label: ctrans("Send backward"), icon: "fal fa-arrow-down", danger: false, run: () => moveLayer(-1) },
    { key: "duplicate", label: ctrans("Duplicate"), icon: "fal fa-clone", danger: false, run: () => duplicateSelected() },
    { key: "delete", label: ctrans("Delete"), icon: "fal fa-trash-alt", danger: true, run: () => removeSelected() },
])

function duplicateSelected() {
    pasteElements(selection.value.map((element) => ({ ...element })), 3, face.value.groups ?? {})
}

function copySelected() {
    clipboard.elements = JSON.parse(JSON.stringify(selection.value))
    clipboard.groups = Object.fromEntries(Object.entries(face.value.groups ?? {}).filter(([groupId]) => selection.value.some((element) => element.group_id === groupId)))
    clipboard.pastes = 0
}

function cutSelected() {
    copySelected()
    removeSelected()
}

function paste() {
    clipboard.pastes += 1
    pasteElements(JSON.parse(JSON.stringify(clipboard.elements)), clipboard.pastes * 3, clipboard.groups)
}

function nudgeSelected(dx: number, dy: number) {
    remember()
    face.value = {
        ...face.value,
        elements: face.value.elements.map((element) => selectedIds.value.includes(element.id) && !element.locked ? { ...element, x: round1(element.x + dx), y: round1(element.y + dy) } : element),
    }
}

function moveLayer(direction: 1 | -1) {
    if (!selected.value) {
        return
    }
    const elements = [...face.value.elements]
    const index = elements.findIndex((element) => element.id === selectedId.value)
    const target = index + direction
    if (target < 0 || target >= elements.length) {
        return
    }
    remember()
    ;[elements[index], elements[target]] = [elements[target], elements[index]]
    face.value = { ...face.value, elements }
}

type Gesture =
    | { kind: "move" | "resize" | "rotate", start: DesignElement, pointer: { x: number, y: number }, handle?: [number, number], group?: Record<string, { x: number, y: number }> }
    | { kind: "background", start: { x: number, y: number }, pointer: { x: number, y: number } }
    | { kind: "group-resize", items: DesignElement[], bounds: Bounds, pointer: { x: number, y: number }, handle: [number, number] }
    | { kind: "group-rotate", items: DesignElement[], bounds: Bounds, pointer: { x: number, y: number } }

const faceEl = ref<HTMLElement | null>(null)
const gesture = ref<Gesture | null>(null)
const guides = ref<{ v: number[], h: number[] }>({ v: [], h: [] })
const editingBackground = ref(false)

const SNAP_PX = 6
const ROTATE_STEP = 15
const handles: [number, number][] = [[-1, -1], [0, -1], [1, -1], [1, 0], [1, 1], [0, 1], [-1, 1], [-1, 0]]

const backgroundUrl = computed(() => face.value.background.image_file_id ? props.images[face.value.background.image_file_id] ?? null : null)

function backgroundBox() {
    const url = backgroundUrl.value
    if (!url) {
        return null
    }
    ensureImageRatio(url)
    return coverBox(props.size.w, props.size.h, imageRatios[url] ?? 0, face.value.background.zoom ?? 1, face.value.background.x ?? 0.5, face.value.background.y ?? 0.5)
}

const backgroundGhostStyle = computed(() => {
    const box = backgroundBox()
    return box ? { left: `${box.left * scale.value}px`, top: `${box.top * scale.value}px`, width: `${box.width * scale.value}px`, height: `${box.height * scale.value}px` } : null
})

function pointerMm(event: PointerEvent): { x: number, y: number } {
    const bounds = faceEl.value!.getBoundingClientRect()
    return { x: (event.clientX - bounds.left) / scale.value, y: (event.clientY - bounds.top) / scale.value }
}

function startGesture(event: PointerEvent, next: Gesture) {
    event.preventDefault()
    event.stopPropagation()
    remember()
    gesture.value = next
    window.addEventListener("pointermove", onPointerMove)
    window.addEventListener("pointerup", endGesture, { once: true })
}

/** an item and the others of its permanent group */
function withGroup(element: DesignElement): string[] {
    return element.group_id ? face.value.elements.filter((item) => item.group_id === element.group_id).map((item) => item.id) : [element.id]
}

function startMove(event: PointerEvent, element: DesignElement) {
    editingBackground.value = false
    const members = withGroup(element)
    if (event.shiftKey || event.ctrlKey || event.metaKey) {
        event.preventDefault()
        selectedIds.value = selectedIds.value.includes(element.id)
            ? selectedIds.value.filter((id) => !members.includes(id))
            : [...new Set([...selectedIds.value, ...members])]
        return
    }
    if (!selectedIds.value.includes(element.id)) {
        selectedIds.value = members
    }
    const movable = selection.value.filter((item) => !item.locked)
    if (element.locked || !movable.length) {
        event.preventDefault()
        return
    }
    const group = Object.fromEntries(movable.map((item) => [item.id, { x: item.x, y: item.y }]))
    startGesture(event, { kind: "move", start: { ...element }, pointer: pointerMm(event), group })
}

const anyLocked = computed(() => selection.value.some((element) => element.locked))
const selectionBounds = computed(() => selectedIds.value.length > 1 ? boundsOf(selection.value) : null)

function startGroupResize(event: PointerEvent, handle: [number, number]) {
    if (selectionBounds.value) {
        startGesture(event, { kind: "group-resize", items: selection.value.map((element) => ({ ...element })), bounds: { ...selectionBounds.value }, pointer: pointerMm(event), handle })
    }
}

function startGroupRotate(event: PointerEvent) {
    if (selectionBounds.value) {
        startGesture(event, { kind: "group-rotate", items: selection.value.map((element) => ({ ...element })), bounds: { ...selectionBounds.value }, pointer: pointerMm(event) })
    }
}

function replaceItems(items: DesignElement[]) {
    const byId = Object.fromEntries(items.map((item) => [item.id, item]))
    face.value = { ...face.value, elements: face.value.elements.map((element) => byId[element.id] ?? element) }
}

/**
 * Lines up the selection on one edge or centre line: of the selection itself when several are picked,
 * of the side when only one is.
 */
function align(mode: "left" | "centre" | "right" | "top" | "middle" | "bottom") {
    const items = selection.value.filter((element) => !element.locked)
    if (!items.length) {
        return
    }
    remember()
    const target = selectedIds.value.length > 1 ? boundsOf(selection.value)! : { x: 0, y: 0, w: props.size.w, h: props.size.h }
    replaceItems(items.map((element) => {
        const own = boundsOf([element])!
        const offsetX = element.x - own.x
        const offsetY = element.y - own.y
        const x = mode === "left" ? target.x : mode === "right" ? target.x + target.w - own.w : mode === "centre" ? target.x + (target.w - own.w) / 2 : own.x
        const y = mode === "top" ? target.y : mode === "bottom" ? target.y + target.h - own.h : mode === "middle" ? target.y + (target.h - own.h) / 2 : own.y
        return { ...element, x: round1(x + offsetX), y: round1(y + offsetY) }
    }))
}

/** spaces the selection so the gaps between neighbours are equal, keeping the outer two in place */
function distribute(axis: "x" | "y") {
    const items = selection.value.filter((element) => !element.locked)
    if (items.length < 3) {
        return
    }
    remember()
    const boxes = items.map((element) => ({ element, box: boundsOf([element])! }))
        .sort((a, b) => axis === "x" ? a.box.x - b.box.x : a.box.y - b.box.y)
    const first = boxes[0].box
    const last = boxes[boxes.length - 1].box
    const span = axis === "x" ? last.x + last.w - first.x : last.y + last.h - first.y
    const used = boxes.reduce((sum, { box }) => sum + (axis === "x" ? box.w : box.h), 0)
    const gap = (span - used) / (boxes.length - 1)
    let cursor = axis === "x" ? first.x : first.y
    replaceItems(boxes.map(({ element, box }) => {
        const shift = cursor - (axis === "x" ? box.x : box.y)
        cursor += (axis === "x" ? box.w : box.h) + gap
        return axis === "x" ? { ...element, x: round1(element.x + shift) } : { ...element, y: round1(element.y + shift) }
    }))
}

function groupSelected() {
    if (selectedIds.value.length < 2) {
        return
    }
    remember()
    const groupId = newElementId()
    const members = face.value.elements.filter((element) => selectedIds.value.includes(element.id)).map((element) => ({ ...element, group_id: groupId }))
    const frontMost = Math.max(...face.value.elements.map((element, index) => selectedIds.value.includes(element.id) ? index : -1))
    const before = face.value.elements.slice(0, frontMost + 1).filter((element) => !selectedIds.value.includes(element.id))
    const after = face.value.elements.slice(frontMost + 1)
    face.value = { ...face.value, elements: [...before, ...members, ...after] }
}

function ungroupSelected() {
    if (!selection.value.some((element) => element.group_id)) {
        return
    }
    remember()
    const leaving = new Set(selectedIds.value)
    const elements = face.value.elements.map((element) => leaving.has(element.id) ? { ...element, group_id: null } : element)
    const stillGrouped = new Set(elements.map((element) => element.group_id).filter(Boolean))
    face.value = {
        ...face.value,
        elements,
        groups: Object.fromEntries(Object.entries(face.value.groups ?? {}).filter(([groupId]) => stillGrouped.has(groupId))),
    }
}

const isGrouped = computed(() => selection.value.length > 1 && !!selection.value[0].group_id && selection.value.every((element) => element.group_id === selection.value[0].group_id))

function toggleLock() {
    if (!selection.value.length) {
        return
    }
    remember()
    const lock = !anyLocked.value
    replaceItems(selection.value.map((element) => ({ ...element, locked: lock })))
}

const textTargets = computed(() => selection.value.filter((element) => element.type === "text" || element.type === "data" || (element.type === "zone" && element.zone === "compliance" && !!props.data?.values)))

/** the value every selected text shares, or undefined when they differ */
function commonValue<K extends keyof DesignElement>(key: K, fallback: DesignElement[K]): DesignElement[K] | undefined {
    const values = textTargets.value.map((element) => element[key] ?? fallback)
    return values.every((value) => value === values[0]) ? values[0] : undefined
}

function setForTexts<K extends keyof DesignElement>(key: K, value: DesignElement[K]) {
    if (!textTargets.value.length) {
        return
    }
    replaceItems(textTargets.value.map((element) => ({ ...element, [key]: value })))
}

const typeIcons: Record<string, string> = {
    text: "fal fa-font",
    image: "fal fa-image",
    zone: "fal fa-square",
    data: "fal fa-database",
    pictogram: "fal fa-exclamation-triangle",
    barcode: "fal fa-barcode",
    shape: "fal fa-shapes",
}

function layerName(element: DesignElement): string {
    if (element.type === "text") {
        return (element.text ?? "").trim().split("\n")[0] || ctrans("Text")
    }
    if (element.type === "data") {
        return props.data?.sources.text.find((source) => source.key === element.source)?.label ?? ctrans("Trade unit data")
    }
    if (element.type === "pictogram") {
        return props.data?.sources.pictograms.find((source) => source.key === element.source)?.label ?? ctrans("Pictogram")
    }
    if (element.type === "barcode") {
        return props.data?.sources.barcodes.find((source) => source.key === (element.source ?? "unit"))?.label ?? ctrans("Barcode")
    }
    if (element.type === "shape") {
        return shapeOptions.value.find((shape) => shape.value === (element.shape ?? "rect"))?.label ?? ctrans("Shape")
    }
    if (element.type === "zone") {
        return element.label || (element.zone === "compliance" ? ctrans("Compliance space") : element.zone === "barcode" ? ctrans("Barcode space") : ctrans("Reserved area"))
    }
    return ctrans("Image")
}

const GROUP_COLOURS = ["#0ea5e9", "#f97316", "#10b981", "#a855f7", "#e11d48", "#eab308"]
const groupColour = (groupId: string) => GROUP_COLOURS[[...new Set(face.value.elements.map((element) => element.group_id).filter(Boolean))].indexOf(groupId) % GROUP_COLOURS.length]

type LayerRow =
    | { kind: "item", element: DesignElement, nested: boolean }
    | { kind: "group", id: string, name: string, members: DesignElement[] }

const collapsedGroups = ref<Record<string, boolean>>({})

const groupNames = computed(() => {
    const ids = [...new Set(face.value.elements.map((element) => element.group_id).filter(Boolean))] as string[]
    return Object.fromEntries(ids.map((id, index) => [id, face.value.groups?.[id] || ctrans("Group :number", { number: String(index + 1) })]))
})

const renamingGroup = ref<string | null>(null)
const groupDraftName = ref("")

function startRenaming(groupId: string) {
    renamingGroup.value = groupId
    groupDraftName.value = groupNames.value[groupId] ?? ""
    nextTick(() => (document.getElementById(`group-name-${groupId}`) as HTMLInputElement | null)?.select())
}

function finishRenaming(save: boolean) {
    const groupId = renamingGroup.value
    renamingGroup.value = null
    if (!save || !groupId) {
        return
    }
    const name = groupDraftName.value.trim().slice(0, 60)
    if (name === (face.value.groups?.[groupId] ?? "")) {
        return
    }
    remember()
    const groups = { ...(face.value.groups ?? {}) }
    if (name) {
        groups[groupId] = name
    } else {
        delete groups[groupId]
    }
    face.value = { ...face.value, groups }
}

/** front-most first, as layers read in design tools; a group sits where its front-most member is */
const layers = computed<LayerRow[]>(() => {
    const rows: LayerRow[] = []
    const seen = new Set<string>()
    for (const element of [...face.value.elements].reverse()) {
        if (!element.group_id) {
            rows.push({ kind: "item", element, nested: false })
            continue
        }
        if (seen.has(element.group_id)) {
            continue
        }
        seen.add(element.group_id)
        const members = [...face.value.elements].reverse().filter((item) => item.group_id === element.group_id)
        rows.push({ kind: "group", id: element.group_id, name: groupNames.value[element.group_id], members })
        if (!collapsedGroups.value[element.group_id]) {
            members.forEach((member) => rows.push({ kind: "item", element: member, nested: true }))
        }
    }
    return rows
})

function selectGroupLayer(event: MouseEvent, groupId: string) {
    editingBackground.value = false
    const members = face.value.elements.filter((element) => element.group_id === groupId).map((element) => element.id)
    selectedIds.value = event.shiftKey || event.ctrlKey || event.metaKey ? [...new Set([...selectedIds.value, ...members])] : members
}

function toggleGroupLock(groupId: string) {
    remember()
    const members = face.value.elements.filter((element) => element.group_id === groupId)
    const lock = !members.every((element) => element.locked)
    replaceItems(members.map((element) => ({ ...element, locked: lock })))
}

const layerAnchor = ref<string | null>(null)

/**
 * Like a file list: a click selects one layer, Ctrl/Cmd-click adds or removes one, Shift-click selects
 * every layer from the last one clicked to this one, and Shift+Ctrl adds that run to the selection.
 */
function selectLayer(event: MouseEvent, element: DesignElement) {
    editingBackground.value = false
    const toggle = event.ctrlKey || event.metaKey
    const order = layers.value.flatMap((row) => row.kind === "item" ? [row.element.id] : [])
    const anchorIndex = layerAnchor.value ? order.indexOf(layerAnchor.value) : -1

    if (event.shiftKey && anchorIndex !== -1) {
        const clickedIndex = order.indexOf(element.id)
        const run = order.slice(Math.min(anchorIndex, clickedIndex), Math.max(anchorIndex, clickedIndex) + 1)
        selectedIds.value = toggle ? [...new Set([...selectedIds.value, ...run])] : run
        return
    }

    layerAnchor.value = element.id
    if (toggle || event.shiftKey) {
        selectedIds.value = selectedIds.value.includes(element.id)
            ? selectedIds.value.filter((id) => id !== element.id)
            : [...selectedIds.value, element.id]
        return
    }
    selectedIds.value = [element.id]
}

function toggleLayerLock(element: DesignElement) {
    remember()
    patchElement(element.id, { locked: !element.locked })
}

const draggedLayer = ref<string | null>(null)
const dropTarget = ref<string | null>(null)
const DROP_AT_BACK = "back"

/**
 * Puts what was dragged (one item, or every member of a dragged group) just in front of the item it was
 * dropped on, in front of a whole group when dropped on the group's row, or behind everything when
 * dropped below the list.
 */
function dropLayer(target: string) {
    const dragged = draggedLayer.value
    draggedLayer.value = null
    dropTarget.value = null
    if (!dragged || dragged === target) {
        return
    }
    const movingIds = dragged.startsWith("group:")
        ? face.value.elements.filter((element) => element.group_id === dragged.slice(6)).map((element) => element.id)
        : [dragged]
    if (target === DROP_AT_BACK) {
        remember()
        const moving = face.value.elements.filter((element) => movingIds.includes(element.id))
        face.value = { ...face.value, elements: [...moving, ...face.value.elements.filter((element) => !movingIds.includes(element.id))] }
        return
    }
    const targetIds = target.startsWith("group:")
        ? face.value.elements.filter((element) => element.group_id === target.slice(6)).map((element) => element.id)
        : [target]
    if (targetIds.some((id) => movingIds.includes(id))) {
        return
    }
    remember()
    const moving = face.value.elements.filter((element) => movingIds.includes(element.id))
    const rest = face.value.elements.filter((element) => !movingIds.includes(element.id))
    const anchor = Math.max(...rest.map((element, index) => targetIds.includes(element.id) ? index : -1))
    face.value = { ...face.value, elements: [...rest.slice(0, anchor + 1), ...moving, ...rest.slice(anchor + 1)] }
}

const groupTextColor = computed({
    get: () => (commonValue("color", "#111827") as string | undefined) ?? "#111827",
    set: (value: string) => setForTexts("color", value.toLowerCase()),
})

function startResize(event: PointerEvent, handle: [number, number]) {
    if (selected.value) {
        startGesture(event, { kind: "resize", start: { ...selected.value }, pointer: pointerMm(event), handle })
    }
}

function startRotate(event: PointerEvent) {
    if (selected.value) {
        startGesture(event, { kind: "rotate", start: { ...selected.value }, pointer: pointerMm(event) })
    }
}

function startBackgroundPan(event: PointerEvent) {
    startGesture(event, { kind: "background", start: { x: face.value.background.x ?? 0.5, y: face.value.background.y ?? 0.5 }, pointer: pointerMm(event) })
}

/**
 * Moves the box so the nearest of its edges or centre lines meets a face edge, the face centre or
 * another unrotated item, when it is within a few pixels.
 */
function snap(start: number, length: number, targets: number[]): { value: number, line: number | null } {
    const threshold = SNAP_PX / scale.value
    let best: { delta: number, line: number } | null = null
    for (const edge of [start, start + length / 2, start + length]) {
        for (const target of targets) {
            const delta = target - edge
            if (Math.abs(delta) <= threshold && (!best || Math.abs(delta) < Math.abs(best.delta))) {
                best = { delta, line: target }
            }
        }
    }
    return best ? { value: start + best.delta, line: best.line } : { value: start, line: null }
}

function snapTargets(axis: "x" | "y", excludeId: string): number[] {
    const length = axis === "x" ? props.size.w : props.size.h
    const others = face.value.elements.filter((element) => element.id !== excludeId && !element.rotate)
        .flatMap((element) => axis === "x" ? [element.x, element.x + element.w / 2, element.x + element.w] : [element.y, element.y + element.h / 2, element.y + element.h])
    return [0, length / 2, length, ...others]
}

function onPointerMove(event: PointerEvent) {
    const current = gesture.value
    if (!current || !faceEl.value) {
        return
    }
    const pointer = pointerMm(event)
    const dx = pointer.x - current.pointer.x
    const dy = pointer.y - current.pointer.y

    if (current.kind === "background") {
        const box = backgroundBox()
        if (!box) {
            return
        }
        const overflowX = box.width - props.size.w
        const overflowY = box.height - props.size.h
        const clamp = (value: number) => Math.min(Math.max(value, 0), 1)
        face.value = {
            ...face.value,
            background: {
                ...face.value.background,
                x: overflowX > 0 ? Math.round(clamp(current.start.x - dx / overflowX) * 10000) / 10000 : 0.5,
                y: overflowY > 0 ? Math.round(clamp(current.start.y - dy / overflowY) * 10000) / 10000 : 0.5,
            },
        }
        return
    }

    if (current.kind === "group-resize") {
        const { bounds, handle: [handleX, handleY], items } = current
        const anchorX = handleX === 1 ? bounds.x : bounds.x + bounds.w
        const anchorY = handleY === 1 ? bounds.y : bounds.y + bounds.h
        let scaleX = handleX ? Math.max((bounds.w + handleX * dx) / bounds.w, 0.05) : 1
        let scaleY = handleY ? Math.max((bounds.h + handleY * dy) / bounds.h, 0.05) : 1
        if (handleX && handleY) {
            scaleX = scaleY = Math.max(scaleX, scaleY)
        }
        const fontScale = handleX && handleY ? scaleX : 1
        replaceItems(items.map((element) => {
            const centreX = anchorX + (element.x + element.w / 2 - anchorX) * scaleX
            const centreY = anchorY + (element.y + element.h / 2 - anchorY) * scaleY
            const width = Math.max(element.w * scaleX, 1)
            const height = Math.max(element.h * scaleY, 1)
            return {
                ...element,
                w: round1(width),
                h: round1(height),
                x: round1(centreX - width / 2),
                y: round1(centreY - height / 2),
                ...(element.font_size ? { font_size: Math.round(element.font_size * fontScale * 10) / 10 } : {}),
            }
        }))
        return
    }

    if (current.kind === "group-rotate") {
        const { bounds, items } = current
        const centreX = bounds.x + bounds.w / 2
        const centreY = bounds.y + bounds.h / 2
        const startAngle = Math.atan2(current.pointer.y - centreY, current.pointer.x - centreX)
        let delta = ((Math.atan2(pointer.y - centreY, pointer.x - centreX) - startAngle) * 180) / Math.PI
        if (!event.altKey) {
            const nearest = Math.round(delta / ROTATE_STEP) * ROTATE_STEP
            if (Math.abs(nearest - delta) <= 4 || event.shiftKey) {
                delta = nearest
            }
        }
        const radians = (delta * Math.PI) / 180
        const cos = Math.cos(radians)
        const sin = Math.sin(radians)
        replaceItems(items.map((element) => {
            const offsetX = element.x + element.w / 2 - centreX
            const offsetY = element.y + element.h / 2 - centreY
            const newCentreX = centreX + offsetX * cos - offsetY * sin
            const newCentreY = centreY + offsetX * sin + offsetY * cos
            return {
                ...element,
                x: round1(newCentreX - element.w / 2),
                y: round1(newCentreY - element.h / 2),
                rotate: Math.round(((((element.rotate ?? 0) + delta) % 360) + 360) % 360),
            }
        }))
        return
    }

    const start = current.start
    if (current.kind === "move") {
        let x = start.x + dx
        let y = start.y + dy
        const lines = { v: [] as number[], h: [] as number[] }
        if (!start.rotate && !event.altKey && Object.keys(current.group ?? {}).length <= 1) {
            const snappedX = snap(x, start.w, snapTargets("x", start.id))
            const snappedY = snap(y, start.h, snapTargets("y", start.id))
            x = snappedX.value
            y = snappedY.value
            if (snappedX.line !== null) {
                lines.v.push(snappedX.line)
            }
            if (snappedY.line !== null) {
                lines.h.push(snappedY.line)
            }
        }
        guides.value = lines
        const shiftX = x - start.x
        const shiftY = y - start.y
        const group = current.group ?? { [start.id]: { x: start.x, y: start.y } }
        face.value = {
            ...face.value,
            elements: face.value.elements.map((element) => group[element.id] ? { ...element, x: round1(group[element.id].x + shiftX), y: round1(group[element.id].y + shiftY) } : element),
        }
        return
    }

    if (current.kind === "rotate") {
        const centreX = start.x + start.w / 2
        const centreY = start.y + start.h / 2
        let angle = Math.atan2(pointer.y - centreY, pointer.x - centreX) * 180 / Math.PI + 90
        if (!event.altKey) {
            const nearest = Math.round(angle / ROTATE_STEP) * ROTATE_STEP
            if (Math.abs(nearest - angle) <= 4 || event.shiftKey) {
                angle = nearest
            }
        }
        patchElement(start.id, { rotate: Math.round(((angle % 360) + 360) % 360) })
        return
    }

    const [handleX, handleY] = current.handle ?? [1, 1]
    const radians = ((start.rotate ?? 0) * Math.PI) / 180
    const cos = Math.cos(radians)
    const sin = Math.sin(radians)
    const localX = dx * cos + dy * sin
    const localY = -dx * sin + dy * cos

    let width = Math.max(start.w + handleX * localX, 1)
    let height = Math.max(start.h + handleY * localY, 1)
    if ((start.type === "image" || event.shiftKey) && handleX && handleY) {
        const ratio = start.h / start.w
        if (width * ratio > height) {
            height = width * ratio
        } else {
            width = height / ratio
        }
    }

    const shiftX = handleX * (width - start.w) / 2
    const shiftY = handleY * (height - start.h) / 2
    const centreX = start.x + start.w / 2 + shiftX * cos - shiftY * sin
    const centreY = start.y + start.h / 2 + shiftX * sin + shiftY * cos

    patchElement(start.id, { w: round1(width), h: round1(height), x: round1(centreX - width / 2), y: round1(centreY - height / 2) })
}

function endGesture() {
    window.removeEventListener("pointermove", onPointerMove)
    gesture.value = null
    guides.value = { v: [], h: [] }
}

function toggleBackgroundEditing() {
    editingBackground.value = !editingBackground.value && !!backgroundUrl.value
    if (editingBackground.value) {
        selectedId.value = null
    }
}

const backgroundZoom = computed({
    get: () => face.value.background.zoom ?? 1,
    set: (zoom: number) => {
        face.value = { ...face.value, background: { ...face.value.background, zoom: Math.round(zoom * 100) / 100 } }
    },
})

const boxStyle = (element: DesignElement) => ({
    left: `${element.x * scale.value}px`,
    top: `${element.y * scale.value}px`,
    width: `${element.w * scale.value}px`,
    height: `${element.h * scale.value}px`,
    transform: element.rotate ? `rotate(${element.rotate}deg)` : "",
})

const handleStyle = ([handleX, handleY]: [number, number]) => ({
    left: `${(handleX + 1) * 50}%`,
    top: `${(handleY + 1) * 50}%`,
    cursor: handleX === 0 ? "ns-resize" : handleY === 0 ? "ew-resize" : handleX === handleY ? "nwse-resize" : "nesw-resize",
})

const isTyping = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null
    return !!target && (target.tagName === "INPUT" || target.tagName === "TEXTAREA" || target.isContentEditable)
}

function onKeydown(event: KeyboardEvent) {
    if (isTyping(event)) {
        return
    }
    if (drawingShape.value && event.key === "Escape") {
        event.preventDefault()
        drawingShape.value = null
        shapeDraft.value = null
        return
    }
    const command = event.ctrlKey || event.metaKey
    const key = event.key.toLowerCase()

    if (command && (key === "y" || (key === "z" && event.shiftKey))) {
        event.preventDefault()
        redo()
        return
    }
    if (command && key === "z") {
        event.preventDefault()
        undo()
        return
    }
    if (command && key === "a") {
        event.preventDefault()
        selectedIds.value = face.value.elements.map((element) => element.id)
        return
    }
    if (command && key === "v") {
        if (clipboard.elements.length) {
            event.preventDefault()
            paste()
        }
        return
    }
    if (!selectedIds.value.length) {
        return
    }
    if (command && key === "c") {
        event.preventDefault()
        copySelected()
        return
    }
    if (command && key === "x") {
        event.preventDefault()
        cutSelected()
        return
    }
    if (command && key === "g") {
        event.preventDefault()
        event.shiftKey ? ungroupSelected() : groupSelected()
        return
    }
    if (command && key === "l") {
        event.preventDefault()
        toggleLock()
        return
    }
    if (command && key === "d") {
        event.preventDefault()
        duplicateSelected()
        return
    }
    if (event.key === "Delete" || event.key === "Backspace") {
        event.preventDefault()
        removeSelected()
        return
    }
    const step = event.shiftKey ? 5 : 1
    const moves: Record<string, [number, number]> = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }
    if (moves[event.key]) {
        event.preventDefault()
        nudgeSelected(...moves[event.key])
        return
    }
    if (event.key === "Escape") {
        selectedIds.value = []
        editingBackground.value = false
    }
}

onMounted(() => {
    observer = new ResizeObserver(([entry]) => stageSize.value = { w: entry.contentRect.width, h: entry.contentRect.height })
    if (stage.value) {
        observer.observe(stage.value)
    }
    window.addEventListener("keydown", onKeydown)
})

onBeforeUnmount(() => {
    endMarquee()
    observer?.disconnect()
    window.removeEventListener("keydown", onKeydown)
})

onBeforeUnmount(endGesture)
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-3 py-2">
            <div class="flex items-center gap-0.5 rounded-lg border border-gray-200 bg-gray-50 p-0.5">
                <span class="px-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Add") }}</span>
                <button type="button" class="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-gray-700 transition-colors hover:bg-white hover:text-gray-900 hover:shadow-sm" @click="addText"><FontAwesomeIcon icon="fal fa-font" fixed-width aria-hidden="true" />{{ ctrans("Text") }}</button>
                <button type="button" class="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-gray-700 transition-colors hover:bg-white hover:text-gray-900 hover:shadow-sm" :disabled="uploading" @click="imageInput?.click()"><FontAwesomeIcon :icon="uploading ? 'fal fa-spinner' : 'fal fa-image'" :spin="uploading" fixed-width aria-hidden="true" />{{ ctrans("Image") }}</button>
                <button type="button" class="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-gray-700 transition-colors hover:bg-white hover:text-gray-900 hover:shadow-sm" @click="(event) => shapePopover?.toggle(event)"><FontAwesomeIcon icon="fal fa-shapes" fixed-width aria-hidden="true" />{{ ctrans("Shape") }}</button>
                <button v-if="data" type="button" class="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-gray-700 transition-colors hover:bg-white hover:text-gray-900 hover:shadow-sm" @click="(event) => dataPopover?.toggle(event)"><FontAwesomeIcon icon="fal fa-database" fixed-width aria-hidden="true" />{{ ctrans("Trade unit data") }}</button>
            </div>
            <div class="flex items-center gap-0.5 rounded-lg border border-gray-200 bg-gray-50 p-0.5">
                <span class="px-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Reserve") }}</span>
                <button type="button" class="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-gray-700 transition-colors hover:bg-white hover:text-gray-900 hover:shadow-sm" @click="addZone('compliance')"><FontAwesomeIcon icon="fal fa-shield-check" fixed-width aria-hidden="true" />{{ ctrans("Compliance") }}</button>
                <button type="button" class="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-gray-700 transition-colors hover:bg-white hover:text-gray-900 hover:shadow-sm" @click="addZone('barcode')"><FontAwesomeIcon icon="fal fa-barcode" fixed-width aria-hidden="true" />{{ ctrans("Barcode") }}</button>
                <button type="button" class="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-gray-700 transition-colors hover:bg-white hover:text-gray-900 hover:shadow-sm" @click="addZone('other')"><FontAwesomeIcon icon="fal fa-square" fixed-width aria-hidden="true" />{{ ctrans("Area") }}</button>
            </div>
            <Button v-if="data" type="tertiary" size="xs" icon="fal fa-magic" :label="ctrans('Autoplace')" :tooltip="ctrans('Lay out the SKU\'s label content on this side')" @click="autoplace" />
            <div class="flex items-center gap-0.5 rounded-lg border border-gray-200 bg-gray-50 p-0.5">
                <button type="button" class="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-gray-700 transition-colors hover:bg-white hover:text-gray-900 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-40" :disabled="!undoStack.length" v-tooltip="ctrans('Undo (Ctrl+Z)')" :aria-label="ctrans('Undo')" @click="undo"><FontAwesomeIcon icon="fal fa-undo" fixed-width aria-hidden="true" /></button>
                <button type="button" class="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-gray-700 transition-colors hover:bg-white hover:text-gray-900 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-40" :disabled="!redoStack.length" v-tooltip="ctrans('Redo (Ctrl+Y)')" :aria-label="ctrans('Redo')" @click="redo"><FontAwesomeIcon icon="fal fa-redo" fixed-width aria-hidden="true" /></button>
            </div>
            <Popover ref="shapePopover">
                <div class="grid w-56 grid-cols-5 gap-1.5">
                    <button v-for="shape in shapeOptions" :key="shape.value" type="button" v-tooltip="shape.label" class="flex aspect-square items-center justify-center rounded border border-gray-200 text-lg text-gray-600 hover:border-[--app-accent] hover:text-[--app-accent]" :aria-label="shape.label" @click="addShape(shape.value)">
                        <FontAwesomeIcon :icon="shape.icon" fixed-width aria-hidden="true" />
                    </button>
                </div>
            </Popover>
            <Popover v-if="data" ref="dataPopover">
                <div class="w-72 space-y-3 text-sm">
                    <div>
                        <div class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Text") }}</div>
                        <div class="flex flex-wrap gap-1">
                            <button v-for="source in data.sources.text" :key="source.key" type="button" class="rounded border border-gray-200 px-2 py-1 text-xs hover:border-[--app-accent] hover:bg-[--app-accent-soft]" :class="data.values && !data.values.text[source.key] ? 'text-gray-400' : 'text-gray-800'" @click="addData('data', source.key)">{{ source.label }}</button>
                        </div>
                    </div>
                    <div>
                        <div class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Pictograms") }}</div>
                        <div class="grid grid-cols-5 gap-1.5">
                            <button v-for="source in data.sources.pictograms" :key="source.key" type="button" v-tooltip="source.label" class="rounded border p-1 hover:border-[--app-accent]" :class="data.values?.pictograms[source.key] ? 'border-[--app-accent-muted] bg-[--app-accent-soft]' : 'border-gray-200'" @click="addData('pictogram', source.key)">
                                <img :src="source.icon" :alt="source.label" class="h-8 w-8 object-contain" />
                            </button>
                        </div>
                        <p v-if="data.values" class="mt-1 text-[11px] text-gray-500">{{ ctrans("Highlighted ones are switched on for this SKU") }}</p>
                    </div>
                    <div>
                        <div class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Barcodes") }}</div>
                        <div class="flex flex-wrap gap-1">
                            <button v-for="source in data.sources.barcodes" :key="source.key" type="button" class="rounded border border-gray-200 px-2 py-1 text-xs hover:border-[--app-accent] hover:bg-[--app-accent-soft]" :class="data.values && !data.values.barcodes[source.key] ? 'text-gray-400' : 'text-gray-800'" @click="addData('barcode', source.key)">{{ source.label }}</button>
                        </div>
                    </div>
                </div>
            </Popover>
            <span class="ml-auto text-xs tabular-nums text-gray-500">{{ label }} · {{ size.w }} × {{ size.h }} mm</span>
            <input ref="imageInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onImagePicked" />
            <input ref="backgroundInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onBackgroundPicked" />
        </div>
        <p v-if="uploadError" class="border-b border-red-100 bg-red-50 px-3 py-1.5 text-xs text-red-600">{{ uploadError }}</p>

        <div class="flex min-h-0 flex-1 flex-col md:flex-row">
            <aside class="hidden w-56 shrink-0 flex-col border-r border-gray-200 md:flex">
                <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2 text-xs font-medium uppercase tracking-wide text-gray-500">
                    <span>{{ ctrans("Layers") }} <span class="tabular-nums text-gray-400">{{ face.elements.length }}</span></span>
                    <span class="flex items-center gap-0.5 normal-case">
                        <button type="button" class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-[--app-accent] disabled:cursor-not-allowed disabled:opacity-40" :disabled="selectedIds.length < 2 || isGrouped" v-tooltip="ctrans('Group (Ctrl+G)')" :aria-label="ctrans('Group')" @click="groupSelected">
                            <FontAwesomeIcon icon="fal fa-object-group" fixed-width aria-hidden="true" />
                        </button>
                        <button type="button" class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-[--app-accent] disabled:cursor-not-allowed disabled:opacity-40" :disabled="!selection.some((element) => element.group_id)" v-tooltip="ctrans('Ungroup (Ctrl+Shift+G)')" :aria-label="ctrans('Ungroup')" @click="ungroupSelected">
                            <FontAwesomeIcon icon="fal fa-object-ungroup" fixed-width aria-hidden="true" />
                        </button>
                    </span>
                </div>
                <ul class="flex min-h-0 flex-1 flex-col overflow-y-auto py-1 [scrollbar-width:thin]">
                    <template v-for="row in layers" :key="row.kind === 'group' ? `group:${row.id}` : row.element.id">
                        <li
                            v-if="row.kind === 'group'"
                            draggable="true"
                            class="group flex cursor-pointer items-center gap-1.5 border-y px-2 py-1.5 text-sm"
                            :class="[
                                row.members.every((member) => selectedIds.includes(member.id)) ? 'border-transparent bg-[--app-accent-soft] text-[--app-accent-deep]' : 'border-transparent text-gray-800 hover:bg-gray-50',
                                dropTarget === `group:${row.id}` && draggedLayer !== `group:${row.id}` ? '!border-t-[--app-accent]' : '',
                            ]"
                            @click="selectGroupLayer($event, row.id)"
                            @dragstart="draggedLayer = `group:${row.id}`"
                            @dragover.prevent="dropTarget = `group:${row.id}`"
                            @drop.prevent="dropLayer(`group:${row.id}`)"
                            @dragend="draggedLayer = null; dropTarget = null"
                        >
                            <button type="button" class="shrink-0 rounded p-0.5 text-xs text-gray-400 hover:text-gray-700" :aria-label="collapsedGroups[row.id] ? ctrans('Expand') : ctrans('Collapse')" @click.stop="collapsedGroups[row.id] = !collapsedGroups[row.id]">
                                <FontAwesomeIcon icon="fal fa-chevron-down" class="transition-transform" :class="collapsedGroups[row.id] ? '-rotate-90' : ''" fixed-width aria-hidden="true" />
                            </button>
                            <FontAwesomeIcon :icon="collapsedGroups[row.id] ? 'fal fa-folder' : 'fal fa-folder-open'" class="shrink-0" :style="{ color: groupColour(row.id) }" fixed-width aria-hidden="true" />
                            <input
                                v-if="renamingGroup === row.id"
                                :id="`group-name-${row.id}`"
                                v-model="groupDraftName"
                                maxlength="60"
                                class="min-w-0 flex-1 rounded border border-[--app-accent] bg-white px-1 py-0 text-sm font-medium text-gray-900 outline-none"
                                @click.stop
                                @keydown.enter.prevent="finishRenaming(true)"
                                @keydown.esc.prevent.stop="finishRenaming(false)"
                                @blur="finishRenaming(true)"
                            />
                            <span v-else class="min-w-0 flex-1 truncate font-medium" v-tooltip="ctrans('Double-click to rename')" @dblclick.stop="startRenaming(row.id)">{{ row.name }}</span>
                            <span class="shrink-0 text-xs tabular-nums text-gray-400">{{ row.members.length }}</span>
                            <button
                                type="button"
                                class="shrink-0 rounded p-0.5 text-xs transition-opacity hover:text-[--app-accent]"
                                :class="row.members.every((member) => member.locked) ? 'text-gray-600' : 'text-gray-400 opacity-0 group-hover:opacity-100'"
                                :aria-label="ctrans('Lock or unlock the group')"
                                @click.stop="toggleGroupLock(row.id)"
                            >
                                <FontAwesomeIcon :icon="row.members.every((member) => member.locked) ? 'fal fa-lock' : 'fal fa-lock-open'" fixed-width aria-hidden="true" />
                            </button>
                        </li>
                        <li
                            v-else
                            draggable="true"
                            class="group flex cursor-pointer items-center gap-2 border-y py-1.5 pr-3 text-sm"
                            :class="[
                                row.nested ? 'pl-8' : 'pl-3',
                                selectedIds.includes(row.element.id) ? 'border-transparent bg-[--app-accent-soft] text-[--app-accent-deep]' : 'border-transparent text-gray-700 hover:bg-gray-50',
                                dropTarget === row.element.id && draggedLayer !== row.element.id ? '!border-t-[--app-accent]' : '',
                            ]"
                            @click="selectLayer($event, row.element)"
                            @dragstart="draggedLayer = row.element.id"
                            @dragover.prevent="dropTarget = row.element.id"
                            @drop.prevent="dropLayer(row.element.id)"
                            @dragend="draggedLayer = null; dropTarget = null"
                        >
                            <span v-if="row.nested" class="h-4 w-0.5 shrink-0 rounded-full" :style="{ backgroundColor: groupColour(row.element.group_id!) }" aria-hidden="true" />
                            <FontAwesomeIcon :icon="typeIcons[row.element.type] ?? 'fal fa-square'" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
                            <span class="min-w-0 flex-1 truncate">{{ layerName(row.element) }}</span>
                            <button
                                type="button"
                                class="shrink-0 rounded p-0.5 text-xs transition-opacity hover:text-[--app-accent]"
                                :class="row.element.locked ? 'text-gray-600' : 'text-gray-400 opacity-0 group-hover:opacity-100'"
                                :aria-label="row.element.locked ? ctrans('Unlock') : ctrans('Lock')"
                                @click.stop="toggleLayerLock(row.element)"
                            >
                                <FontAwesomeIcon :icon="row.element.locked ? 'fal fa-lock' : 'fal fa-lock-open'" fixed-width aria-hidden="true" />
                            </button>
                        </li>
                    </template>
                    <li v-if="!face.elements.length" class="px-3 py-4 text-xs text-gray-400">{{ ctrans("Nothing on this side yet") }}</li>
                    <li
                        v-if="face.elements.length"
                        class="min-h-10 flex-1 border-t-2 px-3 pt-1.5 text-xs transition-colors"
                        :class="draggedLayer ? (dropTarget === DROP_AT_BACK ? 'border-[--app-accent] bg-[--app-accent-soft]/40 text-[--app-accent-strong]' : 'border-transparent text-gray-400') : 'border-transparent text-transparent'"
                        :aria-hidden="!draggedLayer"
                        @dragover.prevent="dropTarget = DROP_AT_BACK"
                        @dragleave="dropTarget === DROP_AT_BACK && (dropTarget = null)"
                        @drop.prevent="dropLayer(DROP_AT_BACK)"
                    >
                        {{ ctrans("Drop here to send to the back") }}
                    </li>
                </ul>
            </aside>

            <div ref="stage" class="relative flex min-h-[18rem] flex-1 select-none items-center justify-center overflow-hidden bg-gray-100" @pointerdown.self="startMarquee">
                <div v-if="marqueeStyle" class="pointer-events-none absolute z-50 rounded-sm border border-[--app-accent] bg-[--app-accent-soft] opacity-60" :style="marqueeStyle" />
                <div v-if="drawingShape" class="pointer-events-none absolute left-1/2 top-3 z-50 -translate-x-1/2 rounded-full bg-gray-900/80 px-3 py-1 text-xs text-white">
                    {{ drawingShape === "line" ? ctrans("Drag to draw the line (Shift snaps the angle) · Esc to cancel") : ctrans("Drag to draw the shape (Shift keeps it even) · click for a default size · Esc to cancel") }}
                </div>
                <div
                    ref="faceEl"
                    class="relative shadow-md"
                    :style="{ width: `${size.w * scale}px`, height: `${size.h * scale}px` }"
                    @pointerdown="onFacePointerDown"
                    @dblclick="onFaceDoubleClick"
                >
                    <img
                        v-if="editingBackground && backgroundUrl && backgroundGhostStyle"
                        :src="backgroundUrl"
                        alt=""
                        class="pointer-events-none absolute max-w-none select-none opacity-30"
                        :style="backgroundGhostStyle"
                        draggable="false"
                    />
                    <DesignFaceRender data-face-artwork :face="face" :scale="scale" :images="images" :size="size" :data="data" />

                    <div
                        v-if="drawingShape"
                        class="draw-cursor absolute inset-0 z-40 touch-none"
                        @pointerdown="startShapeDraw"
                        @pointermove="moveShapeDraw"
                        @pointerup="finishShapeDraw"
                        @pointercancel="shapeDraft = null; drawingShape = null"
                    >
                        <div v-if="shapeDraftStyle" class="pointer-events-none absolute" :style="shapeDraftStyle" />
                    </div>

                    <div
                        v-if="editingBackground"
                        class="absolute inset-0 z-20 cursor-grab ring-2 ring-[--app-accent] active:cursor-grabbing"
                        @pointerdown="startBackgroundPan"
                        @dblclick="editingBackground = false"
                    />

                    <template v-else>
                        <div
                            v-for="element in face.elements"
                            :key="element.id"
                            class="absolute cursor-move touch-none"
                            :class="selectedIds.includes(element.id) ? (selectedIds.length > 1 ? 'outline outline-2 outline-[--app-accent]' : '') : 'hover:outline hover:outline-1 hover:outline-[--app-accent]'"
                            :style="boxStyle(element)"
                            @pointerdown="startMove($event, element)"
                            @dblclick="() => { if (element.group_id) { selectedIds = [element.id] } if (element.type === 'text') { focusText() } }"
                        />

                        <template v-for="element in face.elements" :key="`lock-${element.id}`">
                            <span v-if="element.locked" class="pointer-events-none absolute z-10 flex h-4 w-4 items-center justify-center rounded bg-white/90 text-[9px] text-gray-600 shadow" :style="{ left: `${(element.x + element.w) * scale - 16}px`, top: `${element.y * scale}px` }">
                                <FontAwesomeIcon icon="fal fa-lock" fixed-width aria-hidden="true" />
                            </span>
                        </template>

                        <div
                            v-if="selectionBounds && !anyLocked"
                            class="pointer-events-none absolute z-10"
                            :style="{ left: `${selectionBounds.x * scale}px`, top: `${selectionBounds.y * scale}px`, width: `${selectionBounds.w * scale}px`, height: `${selectionBounds.h * scale}px` }"
                        >
                            <div class="absolute inset-0 outline-dashed outline-1 outline-[--app-accent]" />
                            <span class="absolute left-1/2 top-0 h-5 w-px -translate-y-full bg-[--app-accent]" />
                            <button
                                type="button"
                                class="pointer-events-auto absolute left-1/2 top-0 h-3.5 w-3.5 -translate-x-1/2 -translate-y-[calc(100%+1.25rem)] cursor-grab touch-none rounded-full border-2 border-[--app-accent] bg-white"
                                :aria-label="ctrans('Rotate the selection')"
                                @pointerdown="startGroupRotate"
                            />
                            <button
                                v-for="handle in handles"
                                :key="`g${handle.join()}`"
                                type="button"
                                class="pointer-events-auto absolute h-3 w-3 -translate-x-1/2 -translate-y-1/2 touch-none rounded-sm border border-white bg-[--app-accent] shadow"
                                :style="handleStyle(handle)"
                                :aria-label="ctrans('Resize the selection')"
                                @pointerdown="startGroupResize($event, handle)"
                            />
                        </div>

                        <div v-if="selected && !selected.locked" class="pointer-events-none absolute z-10" :style="boxStyle(selected)">
                            <div class="absolute inset-0 outline outline-1 outline-[--app-accent]" />
                            <span class="absolute left-1/2 top-0 h-5 w-px -translate-y-full bg-[--app-accent]" />
                            <button
                                type="button"
                                class="pointer-events-auto absolute left-1/2 top-0 h-3.5 w-3.5 -translate-x-1/2 -translate-y-[calc(100%+1.25rem)] cursor-grab touch-none rounded-full border-2 border-[--app-accent] bg-white"
                                :aria-label="ctrans('Rotate')"
                                @pointerdown="startRotate"
                            />
                            <button
                                v-for="handle in handles"
                                :key="handle.join()"
                                type="button"
                                class="pointer-events-auto absolute h-3 w-3 -translate-x-1/2 -translate-y-1/2 touch-none rounded-sm border border-white bg-[--app-accent] shadow"
                                :style="handleStyle(handle)"
                                :aria-label="ctrans('Resize')"
                                @pointerdown="startResize($event, handle)"
                            />
                        </div>

                        <span v-for="line in guides.v" :key="`v${line}`" class="pointer-events-none absolute inset-y-0 z-30 w-px bg-pink-500" :style="{ left: `${line * scale}px` }" />
                        <span v-for="line in guides.h" :key="`h${line}`" class="pointer-events-none absolute inset-x-0 z-30 h-px bg-pink-500" :style="{ top: `${line * scale}px` }" />
                    </template>
                </div>
            </div>

            <aside class="w-full shrink-0 space-y-4 overflow-y-auto border-t border-gray-200 p-3 text-sm [scrollbar-width:thin] md:w-72 md:border-l md:border-t-0">
                <div v-if="selectedIds.length" class="space-y-2 border-b border-gray-100 pb-3">
                    <div class="flex flex-wrap items-center gap-1">
                        <Button v-for="mode in alignModes" :key="mode.value" type="tertiary" size="xs" :icon="mode.icon" :tooltip="mode.label" :disabled="anyLocked" class="disabled:cursor-not-allowed disabled:opacity-50" @click="align(mode.value)" />
                        <span class="mx-0.5 h-5 w-px bg-gray-200" />
                        <Button type="tertiary" size="xs" icon="fal fa-arrows-h" :tooltip="ctrans('Distribute across')" :disabled="selectedIds.length < 3 || anyLocked" class="disabled:cursor-not-allowed disabled:opacity-50" @click="distribute('x')" />
                        <Button type="tertiary" size="xs" icon="fal fa-arrows-v" :tooltip="ctrans('Distribute down')" :disabled="selectedIds.length < 3 || anyLocked" class="disabled:cursor-not-allowed disabled:opacity-50" @click="distribute('y')" />
                    </div>
                    <div class="flex flex-wrap items-center gap-1">
                        <Button v-if="selectedIds.length > 1 && !isGrouped" type="tertiary" size="xs" icon="fal fa-object-group" :label="ctrans('Group')" :tooltip="ctrans('Ctrl+G')" @click="groupSelected" />
                        <Button v-if="isGrouped || selection.some((element) => element.group_id)" type="tertiary" size="xs" icon="fal fa-object-ungroup" :label="ctrans('Ungroup')" :tooltip="ctrans('Ctrl+Shift+G')" @click="ungroupSelected" />
                        <Button :type="anyLocked ? 'primary' : 'tertiary'" size="xs" :icon="anyLocked ? 'fal fa-lock' : 'fal fa-lock-open'" :label="anyLocked ? ctrans('Unlock') : ctrans('Lock')" :tooltip="ctrans('Ctrl+L')" @click="toggleLock" />
                    </div>
                    <p v-if="anyLocked" class="text-xs text-gray-500">{{ ctrans("Locked items cannot be moved, resized, rotated or deleted until unlocked.") }}</p>
                </div>

                <template v-if="selected">
                    <div class="space-y-2">
                        <span class="block truncate font-medium text-gray-800">{{ ({ text: ctrans('Text'), image: ctrans('Image'), zone: ctrans('Reserved area'), data: ctrans('Trade unit data'), pictogram: ctrans('Pictogram'), barcode: ctrans('Barcode'), shape: ctrans('Shape') } as Record<string, string>)[selected.type] }}</span>
                        <div class="grid grid-cols-4 divide-x divide-gray-200 overflow-hidden rounded-md border border-gray-200 bg-white" role="toolbar" :aria-label="ctrans('Item actions')">
                            <button
                                v-for="action in itemActions"
                                :key="action.key"
                                type="button"
                                class="flex h-8 items-center justify-center text-sm transition-colors"
                                :class="action.danger ? 'text-red-500 hover:bg-red-50 hover:text-red-600' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                                v-tooltip="action.label"
                                :aria-label="action.label"
                                @click="action.run"
                            >
                                <FontAwesomeIcon :icon="action.icon" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                    </div>

                    <label v-if="data && ['data', 'pictogram', 'barcode'].includes(selected.type)" class="block space-y-1">
                        <span class="text-xs text-gray-500">{{ ctrans("Shows") }}</span>
                        <Select
                            :modelValue="selected.source ?? null"
                            :options="selected.type === 'data' ? data.sources.text : selected.type === 'pictogram' ? data.sources.pictograms : data.sources.barcodes"
                            optionLabel="label"
                            optionValue="key"
                            size="small"
                            class="w-full"
                            overlayClass="text-sm"
                            @update:modelValue="(value) => { remember(); setField('source', value) }"
                        />
                    </label>

                    <div v-if="filledZone && selected.zone === 'compliance'" class="space-y-1.5">
                        <span class="text-xs text-gray-500">{{ ctrans("Shows the SKU's") }}</span>
                        <label v-for="part in COMPLIANCE_PARTS" :key="part" class="flex items-center gap-2 text-sm text-gray-700">
                            <Checkbox :modelValue="complianceParts(selected.source).includes(part)" binary @update:modelValue="(value) => toggleCompliancePart(part, !!value)" />
                            {{ complianceLabels[part] ?? part }}
                        </label>
                    </div>
                    <label v-if="filledZone && selected.zone === 'barcode' && data" class="block space-y-1">
                        <span class="text-xs text-gray-500">{{ ctrans("Shows") }}</span>
                        <Select :modelValue="selected.source ?? 'unit'" :options="data.sources.barcodes" optionLabel="label" optionValue="key" size="small" class="w-full" overlayClass="text-sm" @update:modelValue="(value) => { remember(); setField('source', value) }" />
                    </label>

                    <template v-if="selected.type === 'shape'">
                        <label class="block space-y-1">
                            <span class="text-xs text-gray-500">{{ ctrans("Shape") }}</span>
                            <Select :modelValue="selected.shape ?? 'rect'" :options="shapeOptions" optionLabel="label" optionValue="value" size="small" class="w-full" overlayClass="text-sm" @update:modelValue="(value) => { remember(); setField('shape', value) }" />
                        </label>
                        <div v-if="selected.shape !== 'line'" class="space-y-1">
                            <span class="text-xs text-gray-500">{{ ctrans("Fill") }}</span>
                            <DesignColorField v-model="shapeFill" allowGradient allowNone fallback="#d1d5db" @open="remember" />
                        </div>
                        <div class="space-y-1">
                            <span class="text-xs text-gray-500">{{ selected.shape === 'line' ? ctrans("Line colour") : ctrans("Outline") }}</span>
                            <DesignColorField v-model="shapeStroke" :allowNone="selected.shape !== 'line'" fallback="#111827" @open="remember" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <label v-if="selected.stroke && selected.stroke !== 'none' || selected.shape === 'line'" class="space-y-1">
                                <span class="text-xs text-gray-500">{{ ctrans("Width (px)") }}</span>
                                <InputNumber :modelValue="selected.stroke_px ?? 1" :min="0" :max="50" :maxFractionDigits="1" size="small" inputClass="w-full" class="w-full" @focus="remember" @update:modelValue="(value) => setField('stroke_px', value ?? 0)" />
                            </label>
                            <label v-if="selected.shape === 'rounded'" class="space-y-1">
                                <span class="text-xs text-gray-500">{{ ctrans("Corner (mm)") }}</span>
                                <InputNumber :modelValue="selected.radius ?? 3" :min="0" :max="500" :maxFractionDigits="1" size="small" inputClass="w-full" class="w-full" @focus="remember" @update:modelValue="(value) => setField('radius', value ?? 0)" />
                            </label>
                        </div>
                    </template>

                    <template v-if="isTextLike">
                        <Textarea v-if="selected.type === 'text'" ref="textInput" :modelValue="selected.text ?? ''" rows="3" autoResize class="w-full text-sm" @focus="remember" @update:modelValue="(value) => setField('text', value)" />
                        <div class="grid grid-cols-2 gap-2">
                            <label class="space-y-1">
                                <span class="text-xs text-gray-500">{{ ctrans("Size (pt)") }}</span>
                                <InputNumber :modelValue="selected.font_size ?? (selected.type === 'zone' ? 6 : 12)" :min="2" :max="400" :maxFractionDigits="1" size="small" inputClass="w-full" class="w-full" @update:modelValue="(value) => setField('font_size', value)" />
                            </label>
                            <div class="col-span-2 space-y-1">
                                <span class="text-xs text-gray-500">{{ ctrans("Colour") }}</span>
                                <DesignColorField v-model="textColor" allowGradient @open="remember" />
                            </div>
                        </div>
                        <Select :modelValue="selected.font_family ?? ''" :options="fonts" optionLabel="label" optionValue="value" size="small" class="w-full" overlayClass="text-sm" @update:modelValue="(value) => setField('font_family', value)">
                            <template #option="{ option }"><span :style="{ fontFamily: option.value || 'inherit' }">{{ option.label }}</span></template>
                        </Select>
                        <div class="flex items-center gap-2">
                            <Button :type="selected.font_weight === 'bold' ? 'primary' : 'tertiary'" size="xs" icon="fal fa-bold" :tooltip="ctrans('Bold')" @click="setField('font_weight', selected.font_weight === 'bold' ? 'normal' : 'bold')" />
                            <Button :type="selected.italic ? 'primary' : 'tertiary'" size="xs" icon="fal fa-italic" :tooltip="ctrans('Italic')" @click="setField('italic', !selected.italic)" />
                            <SegmentedToggle :modelValue="selected.align ?? (selected.type === 'zone' ? 'left' : 'center')" :options="alignOptions" iconOnly :ariaLabel="ctrans('Alignment')" @update:modelValue="(value) => setField('align', value as DesignElement['align'])" />
                        </div>
                    </template>

                    <label v-if="selected.type === 'zone' && !filledZone" class="block space-y-1">
                        <span class="text-xs text-gray-500">{{ ctrans("Label") }}</span>
                        <Textarea :modelValue="selected.label ?? ''" rows="2" autoResize class="w-full text-sm" :placeholder="selected.zone === 'barcode' ? 'EAN-13' : ctrans('What goes here')" @focus="remember" @update:modelValue="(value) => setField('label', value || null)" />
                    </label>

                    <div class="grid grid-cols-2 gap-2">
                        <label v-for="field in (['x', 'y', 'w', 'h'] as const)" :key="field" class="space-y-1">
                            <span class="text-xs uppercase text-gray-500">{{ field }} (mm)</span>
                            <InputNumber :modelValue="selected[field]" :maxFractionDigits="1" :min="field === 'w' || field === 'h' ? 1 : undefined" size="small" inputClass="w-full" class="w-full" @update:modelValue="(value) => value !== null && setField(field, value)" />
                        </label>
                        <label class="col-span-2 space-y-1">
                            <span class="text-xs text-gray-500">{{ ctrans("Rotation (°)") }}</span>
                            <InputNumber :modelValue="selected.rotate ?? 0" :min="-360" :max="360" size="small" inputClass="w-full" class="w-full" @update:modelValue="(value) => setField('rotate', value ?? 0)" />
                        </label>
                    </div>
                </template>

                <template v-else-if="selectedIds.length > 1">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-800">{{ ctrans(":count items selected", { count: String(selectedIds.length) }) }}</span>
                        <div class="flex gap-1">
                            <Button type="tertiary" size="xs" icon="fal fa-clone" :tooltip="ctrans('Duplicate (Ctrl+D)')" @click="duplicateSelected" />
                            <Button type="negative" size="xs" icon="fal fa-trash-alt" :tooltip="ctrans('Delete')" @click="removeSelected" />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button type="tertiary" size="xs" icon="fal fa-copy" :label="ctrans('Copy')" @click="copySelected" />
                        <Button type="tertiary" size="xs" icon="fal fa-cut" :label="ctrans('Cut')" @click="cutSelected" />
                    </div>
                    <div v-if="textTargets.length" class="space-y-2 border-t border-gray-100 pt-3">
                        <span class="text-xs font-medium text-gray-600">{{ ctrans("Text of the :count selected", { count: String(textTargets.length) }) }}</span>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="space-y-1">
                                <span class="text-xs text-gray-500">{{ ctrans("Size (pt)") }}</span>
                                <InputNumber :modelValue="(commonValue('font_size', 12) as number | undefined) ?? null" :placeholder="ctrans('Mixed')" :min="2" :max="400" :maxFractionDigits="1" size="small" inputClass="w-full" class="w-full" @focus="remember" @update:modelValue="(value) => value && setForTexts('font_size', value)" />
                            </label>
                            <div class="col-span-2 space-y-1">
                                <span class="text-xs text-gray-500">{{ ctrans("Colour") }}</span>
                                <DesignColorField v-model="groupTextColor" allowGradient @open="remember" />
                            </div>
                        </div>
                        <Select :modelValue="(commonValue('font_family', '') as string | undefined) ?? null" :options="fonts" optionLabel="label" optionValue="value" :placeholder="ctrans('Mixed')" size="small" class="w-full" overlayClass="text-sm" @update:modelValue="(value) => { remember(); setForTexts('font_family', value) }" />
                        <div class="flex items-center gap-2">
                            <Button :type="commonValue('font_weight', 'normal') === 'bold' ? 'primary' : 'tertiary'" size="xs" icon="fal fa-bold" :tooltip="ctrans('Bold')" @click="() => { remember(); setForTexts('font_weight', commonValue('font_weight', 'normal') === 'bold' ? 'normal' : 'bold') }" />
                            <Button :type="commonValue('italic', false) ? 'primary' : 'tertiary'" size="xs" icon="fal fa-italic" :tooltip="ctrans('Italic')" @click="() => { remember(); setForTexts('italic', !commonValue('italic', false)) }" />
                            <SegmentedToggle :modelValue="(commonValue('align', 'center') as string | undefined) ?? ''" :options="alignOptions" iconOnly :ariaLabel="ctrans('Alignment')" @update:modelValue="(value) => { remember(); setForTexts('align', value as DesignElement['align']) }" />
                        </div>
                    </div>
                    <p class="text-xs leading-relaxed text-gray-500">{{ ctrans("Drag any of them to move them together, or use the frame around them to resize or rotate them all. Shift-click an item to add it or take it out of the selection.") }}</p>
                </template>

                <template v-else>
                    <div class="font-medium text-gray-800">{{ ctrans("Background") }}</div>
                    <div class="flex items-center gap-2">
                        <DesignColorField v-model="backgroundColor" allowGradient fallback="#ffffff" @open="remember" />
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button v-if="face.background.image_file_id" :type="editingBackground ? 'primary' : 'tertiary'" size="xs" icon="fal fa-arrows" :label="editingBackground ? ctrans('Done moving') : ctrans('Move background')" @click="toggleBackgroundEditing" />
                        <Button type="tertiary" size="xs" icon="fal fa-fill-drip" :label="face.background.image_file_id ? ctrans('Change image') : ctrans('Background image')" :loading="uploading" @click="backgroundInput?.click()" />
                        <Button v-if="face.background.image_file_id" type="negative" size="xs" icon="fal fa-times" :label="ctrans('Remove')" @click="clearBackgroundImage" />
                    </div>
                    <label v-if="face.background.image_file_id" class="block space-y-2">
                        <span class="flex justify-between text-xs text-gray-500"><span>{{ ctrans("Zoom level") }}</span><span class="tabular-nums">{{ Math.round(backgroundZoom * 100) }}%</span></span>
                        <Slider v-model="backgroundZoom" :min="1" :max="3" :step="0.05" @slideend="remember" />
                    </label>
                    <p v-if="editingBackground" class="rounded-md bg-[--app-accent-soft] px-2 py-1.5 text-xs text-[--app-accent-deep]">{{ ctrans("Drag the image to choose which part shows. It always covers the whole side.") }}</p>
                    <p class="text-xs leading-relaxed text-gray-500">
                        {{ ctrans("Drag an item to move it, its corners to resize (Shift keeps the shape) and the round handle to rotate; hold Alt to stop snapping. Shift-click, or drag a box from an empty spot, to select several. Ctrl+C, Ctrl+X and Ctrl+V copy, cut and paste (also onto another side), Ctrl+D duplicates, Ctrl+A selects all, Ctrl+Z undoes and Ctrl+Y redoes. Arrow keys nudge by 1 mm (Shift: 5 mm), Delete removes. Double-click the side to move its background.") }}
                    </p>
                </template>
            </aside>
        </div>
    </div>
</template>

<style scoped>
/* A dark crosshair with a white outline, so it shows on white artwork and on dark artwork alike;
   the system crosshair is white on some computers and vanishes on a white side */
.draw-cursor {
    cursor: url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24'><path d='M12 2v8M12 14v8M2 12h8M14 12h8' stroke='white' stroke-width='4' stroke-linecap='round'/><path d='M12 2v8M12 14v8M2 12h8M14 12h8' stroke='%23111827' stroke-width='1.5' stroke-linecap='round'/><circle cx='12' cy='12' r='1.5' fill='%23111827' stroke='white' stroke-width='1'/></svg>") 12 12, crosshair;
}
</style>
