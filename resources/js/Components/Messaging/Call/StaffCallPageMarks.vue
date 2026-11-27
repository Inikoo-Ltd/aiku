<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from "vue"
import { MarkBoard, PAGE_MARKS_CHANNEL, pagePlacement, readMark, encodeMark, type PageMarkEvent, type SharedSurface } from "@/Components/Messaging/Call/staffCallMarks"
import { useStaffCalls } from "@/Stores/staff-calls"

const store = useStaffCalls()
const board = new MarkBoard()
const canvas = ref<HTMLCanvasElement | null>(null)
const isLive = ref(false)
const pageHandle = crypto.randomUUID()
let shared: { surface: SharedSurface; width: number; height: number } | null = null
let channel: BroadcastChannel | null = null
let frame: number | null = null

const render = () => {
    frame = null
    const element = canvas.value
    if (!element || !shared) return
    const ratio = window.devicePixelRatio || 1
    if (element.width !== Math.round(window.innerWidth * ratio) || element.height !== Math.round(window.innerHeight * ratio)) {
        element.width = Math.round(window.innerWidth * ratio)
        element.height = Math.round(window.innerHeight * ratio)
    }
    const context = element.getContext("2d")!
    context.setTransform(ratio, 0, 0, ratio, 0, 0)
    context.clearRect(0, 0, window.innerWidth, window.innerHeight)
    if (!board.prune()) {
        isLive.value = false
        return
    }
    const place = pagePlacement(shared.surface, shared.width, shared.height)
    if (place) board.draw(context, place, shared.surface === "monitor" ? 1.5 : 1)
    frame = requestAnimationFrame(render)
}

const wake = async () => {
    isLive.value = true
    await nextTick()
    if (frame === null) frame = requestAnimationFrame(render)
}

const isThisPageShared = (event: PageMarkEvent) => {
    if (document.visibilityState !== "visible") return false
    if (event.surface !== "browser") return true
    if (event.handle) return event.handle === pageHandle
    return !store.active
}

const receive = ({ data }: MessageEvent<PageMarkEvent>) => {
    if (data?.type === "end") {
        board.clear()
        return
    }
    if (data?.type !== "mark" || !data.mark || !data.identity || !data.surface || !data.width || !data.height || !isThisPageShared(data)) return
    const mark = readMark(encodeMark(data.mark))
    if (!mark) return
    shared = { surface: data.surface, width: data.width, height: data.height }
    board.apply(data.identity, data.name ?? "", mark)
}

onMounted(() => {
    if (typeof BroadcastChannel === "undefined") return
    board.onChange = wake
    channel = new BroadcastChannel(PAGE_MARKS_CHANNEL)
    channel.onmessage = receive
    ;(navigator.mediaDevices as any)?.setCaptureHandleConfig?.({ handle: pageHandle, permittedOrigins: [window.location.origin] })
})

onBeforeUnmount(() => {
    channel?.close()
    if (frame !== null) cancelAnimationFrame(frame)
})
</script>

<template>
    <canvas v-if="isLive" ref="canvas" class="pointer-events-none fixed inset-0 z-[90] h-screen w-screen" aria-hidden="true" />
</template>
