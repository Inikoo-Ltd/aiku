<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 03 Aug 2026 21:05:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from "vue"
import { useRafFn } from "@vueuse/core"
import { LIVE_VISITOR_WINDOW, LiveVisitor, liveVisitorColors, liveVisitorStatus } from "@/Composables/useLiveVisitors"

// ponytail: pairwise collision only while the crowd is small; above this the cluster attraction
// alone lays the bubbles out and the frame stays O(n).
const COLLISION_LIMIT = 250

const props = withDefaults(defineProps<{
    visitors: LiveVisitor[]
    groupKeyOf?: (visitor: LiveVisitor) => string
    highlighted?: string | null
    showLabels?: boolean
    radius?: number
    onExpire?: (sessionId: string) => void
}>(), {
    showLabels: true,
    radius: 13,
})

const emit = defineEmits<{ hover: [string | null]; select: [string | null] }>()

const canvasRef = ref<HTMLCanvasElement | null>(null)
const hovered = ref<string | null>(null)
const size = ref({ width: 0, height: 0 })

const clusters = computed(() => {
    const map = new Map<string, { x: number; y: number; label: string; count: number }>()
    if (!props.groupKeyOf || !size.value.width) {
        return map
    }

    const tally = new Map<string, number>()
    props.visitors.forEach(v => {
        const key = props.groupKeyOf!(v)
        tally.set(key, (tally.get(key) ?? 0) + 1)
    })

    const sorted = Array.from(tally.entries()).sort((a, b) => b[1] - a[1])
    const centerX = size.value.width / 2
    const centerY = size.value.height / 2
    const radius = Math.min(centerX, centerY) * 0.68

    sorted.forEach(([label, count], i) => {
        const angle = (i / sorted.length) * Math.PI * 2 - Math.PI / 2
        map.set(label, {
            x: sorted.length === 1 ? centerX : centerX + Math.cos(angle) * radius,
            y: sorted.length === 1 ? centerY : centerY + Math.sin(angle) * radius,
            label,
            count,
        })
    })

    return map
})

let observer: ResizeObserver | null = null

onMounted(() => {
    const canvas = canvasRef.value
    const ctx = canvas?.getContext("2d")
    if (!canvas || !ctx) {
        return
    }

    const resize = () => {
        const dpr = window.devicePixelRatio || 1
        size.value = { width: canvas.clientWidth, height: canvas.clientHeight }
        canvas.width = canvas.clientWidth * dpr
        canvas.height = canvas.clientHeight * dpr
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    }
    observer = new ResizeObserver(resize)
    observer.observe(canvas)
    resize()

    useRafFn(() => {
        const { width, height } = size.value
        const now = Date.now() / 1000

        ctx.clearRect(0, 0, width, height)

        if (props.showLabels) {
            clusters.value.forEach(cluster => {
                ctx.fillStyle = "#cbd5e1"
                ctx.font = "600 11px ui-sans-serif, system-ui, sans-serif"
                ctx.textAlign = "center"
                ctx.fillText(`${cluster.label} · ${cluster.count}`, cluster.x, cluster.y + 62)
            })
        }

        const bubbles = props.visitors
        const collide = bubbles.length <= COLLISION_LIMIT

        bubbles.forEach((visitor, index) => {
            if (now - visitor.last_active > LIVE_VISITOR_WINDOW) {
                props.onExpire?.(visitor.session_id)

                return
            }

            visitor.radius = props.radius
            visitor.status = liveVisitorStatus(visitor)

            const cluster = props.groupKeyOf ? clusters.value.get(props.groupKeyOf(visitor)) : null
            if (cluster) {
                visitor.vx += (cluster.x - visitor.x) * 0.02
                visitor.vy += (cluster.y - visitor.y) * 0.02
            }

            if (collide) {
                for (let i = index + 1; i < bubbles.length; i++) {
                    const other = bubbles[i]
                    const dx = other.x - visitor.x
                    const dy = other.y - visitor.y
                    const minDistance = visitor.radius + other.radius + 4
                    const squared = dx * dx + dy * dy
                    if (squared >= minDistance * minDistance || squared === 0) {
                        continue
                    }

                    const distance = Math.sqrt(squared)
                    const push = (minDistance - distance) * 0.05
                    const ax = (dx / distance) * push
                    const ay = (dy / distance) * push
                    visitor.vx -= ax
                    visitor.vy -= ay
                    other.vx += ax
                    other.vy += ay
                }
            }

            visitor.vx *= 0.9
            visitor.vy *= 0.9
            visitor.x += visitor.vx
            visitor.y += visitor.vy

            if (visitor.x < visitor.radius) { visitor.x = visitor.radius; visitor.vx *= -0.5 }
            if (visitor.x > width - visitor.radius) { visitor.x = width - visitor.radius; visitor.vx *= -0.5 }
            if (visitor.y < visitor.radius) { visitor.y = visitor.radius; visitor.vy *= -0.5 }
            if (visitor.y > height - visitor.radius) { visitor.y = height - visitor.radius; visitor.vy *= -0.5 }

            const color = liveVisitorColors[visitor.status] ?? liveVisitorColors.idle
            const isHighlighted = props.highlighted === visitor.session_id
            const age = now - visitor.last_active

            if (age < 3) {
                ctx.beginPath()
                ctx.arc(visitor.x, visitor.y, visitor.radius + (age / 3) * 22, 0, Math.PI * 2)
                ctx.strokeStyle = color
                ctx.lineWidth = 2
                ctx.globalAlpha = 1 - age / 3
                ctx.stroke()
                ctx.globalAlpha = 1
            }

            ctx.shadowBlur = isHighlighted ? 14 : 4
            ctx.shadowColor = isHighlighted ? color : "rgba(15,23,42,0.25)"
            ctx.shadowOffsetY = isHighlighted ? 0 : 2
            ctx.beginPath()
            ctx.arc(visitor.x, visitor.y, isHighlighted ? visitor.radius + 3 : visitor.radius, 0, Math.PI * 2)
            ctx.fillStyle = color
            ctx.fill()
            ctx.shadowBlur = 0
            ctx.shadowOffsetY = 0

            if (isHighlighted) {
                ctx.beginPath()
                ctx.arc(visitor.x, visitor.y, visitor.radius + 9, 0, Math.PI * 2)
                ctx.strokeStyle = "#0f172a"
                ctx.lineWidth = 1.5
                ctx.stroke()
            }

            if (visitor.radius >= 10) {
                ctx.fillStyle = "white"
                ctx.font = "bold 9px ui-sans-serif, system-ui, sans-serif"
                ctx.textAlign = "center"
                ctx.textBaseline = "middle"
                ctx.fillText(visitor.country, visitor.x, visitor.y)
            }
        })
    })
})

onUnmounted(() => observer?.disconnect())

const visitorAt = (e: MouseEvent): string | null => {
    const canvas = canvasRef.value
    if (!canvas) {
        return null
    }
    const rect = canvas.getBoundingClientRect()
    const x = e.clientX - rect.left
    const y = e.clientY - rect.top

    let found: string | null = null
    props.visitors.forEach(v => {
        if ((v.x - x) ** 2 + (v.y - y) ** 2 < (v.radius + 5) ** 2) {
            found = v.session_id
        }
    })

    return found
}

const handleMouseMove = (e: MouseEvent) => {
    const found = visitorAt(e)
    if (found !== hovered.value) {
        hovered.value = found
        emit("hover", found)
    }
}
</script>

<template>
    <canvas
        ref="canvasRef"
        class="w-full h-full block"
        @mousemove="handleMouseMove"
        @mouseleave="hovered = null; emit('hover', null)"
        @click="emit('select', hovered)"
    />
</template>
