<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue"
import { fittedRect, type MarkBoard } from "@/Components/Messaging/Call/staffCallMarks"

const props = defineProps<{
    board: MarkBoard
    video: HTMLVideoElement | null
    drawing: boolean
}>()

const emit = defineEmits<{
    pointer: [x: number, y: number]
    pointerGone: []
    strokePoint: [x: number, y: number]
    strokeEnd: []
}>()

const canvas = ref<HTMLCanvasElement | null>(null)
let frame: number | null = null
let resizeObserver: ResizeObserver | null = null
let isStroking = false

const picture = () => {
    const element = canvas.value!
    const width = props.video?.videoWidth || 16
    const height = props.video?.videoHeight || 9
    return fittedRect(element.clientWidth, element.clientHeight, width, height)
}

const render = () => {
    frame = null
    const element = canvas.value
    if (!element) return
    const context = element.getContext("2d")!
    const ratio = window.devicePixelRatio || 1
    context.setTransform(ratio, 0, 0, ratio, 0, 0)
    context.clearRect(0, 0, element.clientWidth, element.clientHeight)
    if (!props.board.prune()) return
    const rect = picture()
    props.board.draw(context, (x, y) => [rect.x + x * rect.width, rect.y + y * rect.height], 1)
    frame = requestAnimationFrame(render)
}

const wake = () => {
    if (frame === null) frame = requestAnimationFrame(render)
}

const resize = () => {
    const element = canvas.value
    if (!element) return
    const ratio = window.devicePixelRatio || 1
    element.width = Math.round(element.clientWidth * ratio)
    element.height = Math.round(element.clientHeight * ratio)
    wake()
}

const fraction = (event: PointerEvent): [number, number] | null => {
    const bounds = canvas.value!.getBoundingClientRect()
    const rect = picture()
    const x = (event.clientX - bounds.left - rect.x) / rect.width
    const y = (event.clientY - bounds.top - rect.y) / rect.height
    return x < 0 || x > 1 || y < 0 || y > 1 ? null : [x, y]
}

const onDown = (event: PointerEvent) => {
    if (!props.drawing) return
    const point = fraction(event)
    if (!point) return
    event.preventDefault()
    canvas.value!.setPointerCapture(event.pointerId)
    isStroking = true
    emit("strokePoint", ...point)
}

const onMove = (event: PointerEvent) => {
    if (!props.drawing) return
    const point = fraction(event)
    if (!point) return
    if (isStroking) emit("strokePoint", ...point)
    else emit("pointer", ...point)
}

const onUp = () => {
    if (!isStroking) return
    isStroking = false
    emit("strokeEnd")
}

const onLeave = () => {
    if (!isStroking && props.drawing) emit("pointerGone")
}

onMounted(() => {
    props.board.onChange = wake
    resizeObserver = new ResizeObserver(resize)
    resizeObserver.observe(canvas.value!)
    resize()
})

onBeforeUnmount(() => {
    props.board.onChange = null
    resizeObserver?.disconnect()
    if (frame !== null) cancelAnimationFrame(frame)
})
</script>

<template>
    <canvas
        ref="canvas"
        class="absolute inset-0 h-full w-full"
        :class="drawing ? 'cursor-crosshair touch-none' : 'pointer-events-none'"
        @pointerdown="onDown"
        @pointermove="onMove"
        @pointerup="onUp"
        @pointercancel="onUp"
        @pointerleave="onLeave" />
</template>
