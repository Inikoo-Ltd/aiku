<!--
  - Author Louis Perez
  - Created on 18-10-2026-04h-00m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from "vue"
import pdfWorkerUrl from "pdfjs-dist/legacy/build/pdf.worker.min.mjs?url"
import { ctrans } from "@/Composables/useTrans"
import { markupColour } from "@/Components/Goods/ProductDevelopment/Design/boxNet"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSpinner } from "@fal"
library.add(faSpinner)

export interface ArtworkPin {
    id: number
    number: number
    x: number
    y: number
    page: number
    resolved: boolean
    active?: boolean
}

export interface ArtworkStroke {
    id: number
    number: number
    page: number
    points: [number, number][]
    resolved: boolean
    active?: boolean
}

export type MarkupTool = "pin" | "draw" | null

const props = defineProps<{
    upload: { kind: "pdf" | "image", preview_url: string, name?: string }
    pins?: ArtworkPin[]
    strokes?: ArtworkStroke[]
    tool?: MarkupTool
    maxPages?: number
    nextNumber?: number
}>()

const emit = defineEmits<{
    pin: [point: { x: number, y: number, page: number }]
    stroke: [stroke: { page: number, points: [number, number][] }]
    focus: [id: number]
}>()

const MIN_STEP = 0.004
const MAX_POINTS = 600

const container = ref<HTMLElement | null>(null)
const width = ref(0)
const pages = shallowRef<{ number: number, ratio: number }[]>([])
const canvases = ref<Record<number, HTMLCanvasElement | null>>({})
const loading = ref(false)
const failed = ref(false)
const drawing = ref<{ page: number, points: [number, number][] } | null>(null)
let observer: ResizeObserver | null = null
let pdfDocument: any = null
let renderToken = 0
const renderTasks: Record<number, any> = {}

let pdfjs: typeof import("pdfjs-dist/legacy/build/pdf.mjs") | null = null
async function loadPdfjs() {
    if (!pdfjs) {
        pdfjs = await import("pdfjs-dist/legacy/build/pdf.mjs")
        pdfjs.GlobalWorkerOptions.workerSrc = pdfWorkerUrl
    }
    return pdfjs
}

async function loadPdf() {
    loading.value = true
    failed.value = false
    try {
        const { getDocument } = await loadPdfjs()
        pdfDocument = await getDocument({ url: props.upload.preview_url }).promise
        const count = Math.min(pdfDocument.numPages, props.maxPages ?? 50)
        const list = []
        for (let number = 1; number <= count; number++) {
            const page = await pdfDocument.getPage(number)
            const viewport = page.getViewport({ scale: 1 })
            list.push({ number, ratio: viewport.height / viewport.width })
        }
        pages.value = list
        await nextTick()
        renderPages()
    } catch {
        failed.value = true
    } finally {
        loading.value = false
    }
}

async function renderPages() {
    if (!pdfDocument || !width.value) {
        return
    }
    const token = ++renderToken
    const pixelRatio = Math.min(window.devicePixelRatio || 1, 2)
    for (const entry of pages.value) {
        const canvas = canvases.value[entry.number]
        if (!canvas || token !== renderToken) {
            continue
        }
        const running = renderTasks[entry.number]
        if (running) {
            running.cancel()
            await running.promise.catch(() => {})
        }
        if (token !== renderToken) {
            continue
        }
        const page = await pdfDocument.getPage(entry.number)
        if (token !== renderToken || renderTasks[entry.number]) {
            continue
        }
        const base = page.getViewport({ scale: 1 })
        const viewport = page.getViewport({ scale: (width.value / base.width) * pixelRatio })
        canvas.width = viewport.width
        canvas.height = viewport.height
        const task = page.render({ canvasContext: canvas.getContext("2d")!, viewport })
        renderTasks[entry.number] = task
        try {
            await task.promise
        } catch (error: any) {
            if (error?.name !== "RenderingCancelledException") {
                throw error
            }
        } finally {
            if (renderTasks[entry.number] === task) {
                delete renderTasks[entry.number]
            }
        }
    }
}

let resizeTimer: ReturnType<typeof setTimeout> | undefined
onMounted(() => {
    observer = new ResizeObserver(([entry]) => {
        width.value = entry.contentRect.width
        clearTimeout(resizeTimer)
        resizeTimer = setTimeout(renderPages, 150)
    })
    if (container.value) {
        observer.observe(container.value)
        width.value = container.value.clientWidth
    }
    if (props.upload.kind === "pdf") {
        loadPdf()
    } else {
        pages.value = [{ number: 1, ratio: 0 }]
    }
})

function stopRendering() {
    renderToken++
    clearTimeout(resizeTimer)
    for (const number of Object.keys(renderTasks)) {
        renderTasks[Number(number)].cancel()
        delete renderTasks[Number(number)]
    }
    pdfDocument?.destroy?.()
}

onBeforeUnmount(() => {
    observer?.disconnect()
    stopRendering()
})

watch(() => props.upload.preview_url, () => {
    if (props.upload.kind === "pdf") {
        stopRendering()
        loadPdf()
    } else {
        pages.value = [{ number: 1, ratio: 0 }]
    }
})

function fraction(event: PointerEvent): [number, number] {
    const bounds = (event.currentTarget as HTMLElement).getBoundingClientRect()
    return [
        Math.min(Math.max((event.clientX - bounds.left) / bounds.width, 0), 1),
        Math.min(Math.max((event.clientY - bounds.top) / bounds.height, 0), 1),
    ]
}

function onPointerDown(event: PointerEvent, page: number) {
    if (props.tool === "pin") {
        const [x, y] = fraction(event)
        emit("pin", { x, y, page })
        return
    }
    if (props.tool === "draw") {
        (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)
        drawing.value = { page, points: [fraction(event)] }
    }
}

function onPointerMove(event: PointerEvent, page: number) {
    if (!drawing.value || drawing.value.page !== page) {
        return
    }
    const point = fraction(event)
    const last = drawing.value.points[drawing.value.points.length - 1]
    if (Math.hypot(point[0] - last[0], point[1] - last[1]) >= MIN_STEP) {
        drawing.value.points.push(point)
    }
}

function onPointerUp() {
    const current = drawing.value
    drawing.value = null
    if (!current || current.points.length < 2) {
        return
    }
    const step = Math.ceil(current.points.length / MAX_POINTS)
    const points = step > 1 ? current.points.filter((_, index) => index % step === 0 || index === current.points.length - 1) : current.points
    emit("stroke", { page: current.page, points })
}

const nextNumber = computed(() => props.nextNumber ?? ((props.pins?.length ?? 0) + (props.strokes?.length ?? 0) + 1))

const polyline = (points: [number, number][]) => points.map(([x, y]) => `${x * 1000},${y * 1000}`).join(" ")
const pageHeight = (ratio: number) => ratio ? `${width.value * ratio}px` : undefined
</script>

<template>
    <div ref="container" class="w-full min-w-0 space-y-3 overflow-hidden">
        <div v-if="loading" class="flex h-48 items-center justify-center text-sm text-gray-400">
            <FontAwesomeIcon icon="fal fa-spinner" spin fixed-width aria-hidden="true" />
        </div>
        <p v-if="failed" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ ctrans("This PDF could not be shown") }}</p>

        <div v-for="page in pages" :key="page.number" class="relative overflow-hidden bg-white shadow-sm ring-1 ring-gray-200" :style="{ height: pageHeight(page.ratio) }">
            <canvas v-if="upload.kind === 'pdf'" :ref="(el) => canvases[page.number] = el as HTMLCanvasElement | null" class="block h-full w-full" />
            <img v-else :src="upload.preview_url" :alt="upload.name ?? ''" class="block w-full select-none" draggable="false" />

            <div
                class="absolute inset-0"
                :class="tool === 'pin' ? 'cursor-crosshair' : tool === 'draw' ? 'cursor-crosshair touch-none' : ''"
                @pointerdown="onPointerDown($event, page.number)"
                @pointermove="onPointerMove($event, page.number)"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
            >
                <svg class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 1000 1000" preserveAspectRatio="none" aria-hidden="true">
                    <polyline
                        v-for="stroke in (strokes ?? []).filter((item) => item.page === page.number)"
                        :key="stroke.id"
                        :points="polyline(stroke.points)"
                        fill="none"
                        :stroke="markupColour(stroke.number)"
                        :stroke-opacity="stroke.resolved ? 0.45 : 1"
                        :stroke-width="stroke.active ? 4 : 2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        vector-effect="non-scaling-stroke"
                    />
                    <polyline v-if="drawing && drawing.page === page.number" :points="polyline(drawing.points)" fill="none" :stroke="markupColour(nextNumber)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                </svg>

                <button
                    v-for="marker in [
                        ...(pins ?? []).filter((item) => item.page === page.number).map((item) => ({ ...item, at: [item.x, item.y] })),
                        ...(strokes ?? []).filter((item) => item.page === page.number).map((item) => ({ ...item, at: item.points[0] })),
                    ]"
                    :key="`m${marker.id}`"
                    type="button"
                    class="absolute z-10 flex h-6 w-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white text-xs font-semibold shadow text-white"
                    :class="[marker.resolved ? 'opacity-50' : '', marker.active ? 'scale-125 ring-2 ring-gray-400' : '']"
                    :style="{ left: `${marker.at[0] * 100}%`, top: `${marker.at[1] * 100}%`, backgroundColor: markupColour(marker.number) }"
                    :aria-label="ctrans('Comment :number', { number: String(marker.number) })"
                    @pointerdown.stop
                    @click.stop="emit('focus', marker.id)"
                >
                    {{ marker.number }}
                </button>
            </div>
        </div>
    </div>
</template>
