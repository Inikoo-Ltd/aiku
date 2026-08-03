<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref } from "vue"
import { trans } from "laravel-vue-i18n"

// The holy triangle from the whiteboard: TU on top, SKO and P below, an eye in the
// middle that watches the cursor. Edge labels appear on hover, quantities always.
const props = defineProps<{
    tradeUnits: {
        code?: string
        quantity?: number | string
        packed_in?: number | string
    }[]
    productsCount?: number
}>()

const first = computed(() => props.tradeUnits?.[0] ?? {})

const sellQty = computed(() => Number(first.value.quantity) || null)          // TU—P: how we sell
const packedQty = computed(() => Number(first.value.packed_in) || null)      // TU—SKO: how is packed
const pickLabel = computed(() => {                                           // SKO—P: how we pick
    if (!sellQty.value || !packedQty.value) return null
    const packs = sellQty.value / packedQty.value
    return Number.isInteger(packs) ? `${packs}` : packs.toFixed(2)
})
const isPartialPick = computed(() => !!pickLabel.value && !Number.isInteger(sellQty.value! / packedQty.value!))

// Triangle geometry (viewBox 0 0 300 270)
const TU = { x: 150, y: 40 }
const SKO = { x: 40, y: 230 }
const P = { x: 260, y: 230 }
const CENTER = { x: 150, y: 172 }

// Eye follows the mouse anywhere on the page
const svgEl = ref<SVGSVGElement>()
const pupil = ref({ x: 0, y: 0 })
const isBlinking = ref(false)
const hoveredEdge = ref<string | null>(null)

let blinkTimer: ReturnType<typeof setTimeout> | null = null

const onMouseMove = (event: MouseEvent) => {
    const svg = svgEl.value
    if (!svg) return
    const rect = svg.getBoundingClientRect()
    const centerX = rect.left + (CENTER.x / 300) * rect.width
    const centerY = rect.top + (CENTER.y / 270) * rect.height
    const dx = event.clientX - centerX
    const dy = event.clientY - centerY
    const distance = Math.hypot(dx, dy) || 1
    const reach = Math.min(distance / 30, 7)
    pupil.value = { x: (dx / distance) * reach, y: (dy / distance) * reach }
}

const scheduleBlink = () => {
    blinkTimer = setTimeout(() => {
        isBlinking.value = true
        setTimeout(() => {
            isBlinking.value = false
            scheduleBlink()
        }, 160)
    }, 2500 + Math.random() * 4500)
}

onMounted(() => {
    window.addEventListener("mousemove", onMouseMove, { passive: true })
    scheduleBlink()
})
onBeforeUnmount(() => {
    window.removeEventListener("mousemove", onMouseMove)
    if (blinkTimer) clearTimeout(blinkTimer)
})

const edges = computed(() => [
    {
        key: 'packed',
        from: TU, to: SKO,
        label: trans('how is packed'),
        value: packedQty.value ? `${packedQty.value}` : '?',
        partial: false,
    },
    {
        key: 'sell',
        from: TU, to: P,
        label: trans('how we sell'),
        value: sellQty.value ? `${sellQty.value}` : '?',
        partial: false,
    },
    {
        key: 'pick',
        from: SKO, to: P,
        label: trans('how we pick'),
        value: pickLabel.value ?? '?',
        partial: isPartialPick.value,
    },
])

const midpoint = (edge: { from: { x: number; y: number }; to: { x: number; y: number } }) => ({
    x: (edge.from.x + edge.to.x) / 2,
    y: (edge.from.y + edge.to.y) / 2,
})
</script>

<template>
    <div class="select-none">
        <svg ref="svgEl" viewBox="0 0 300 270" class="w-full max-w-xs mx-auto" aria-hidden="true">
            <!-- Edges -->
            <g v-for="edge in edges" :key="edge.key"
                @mouseenter="hoveredEdge = edge.key" @mouseleave="hoveredEdge = null"
                class="cursor-help">
                <line :x1="edge.from.x" :y1="edge.from.y" :x2="edge.to.x" :y2="edge.to.y"
                    stroke="transparent" stroke-width="22" />
                <line :x1="edge.from.x" :y1="edge.from.y" :x2="edge.to.x" :y2="edge.to.y"
                    :stroke="edge.partial ? '#d97706' : (hoveredEdge === edge.key ? '#0d9488' : '#94a3b8')"
                    :stroke-width="hoveredEdge === edge.key ? 3 : 2"
                    class="transition-all duration-150" />

                <!-- Quantity, always visible -->
                <g :transform="`translate(${midpoint(edge).x}, ${midpoint(edge).y})`">
                    <circle r="13" fill="white" :stroke="edge.partial ? '#d97706' : '#cbd5e1'" stroke-width="1" />
                    <text text-anchor="middle" dominant-baseline="central" class="text-[11px] font-bold"
                        :fill="edge.partial ? '#d97706' : '#334155'">{{ edge.value }}</text>
                </g>

                <!-- Handwritten label, on hover -->
                <text v-if="hoveredEdge === edge.key"
                    :x="midpoint(edge).x" :y="midpoint(edge).y - 22"
                    text-anchor="middle" fill="#0d9488"
                    class="text-[13px] italic" style="font-family: 'Comic Sans MS', 'Segoe Script', cursive">
                    {{ edge.label }}
                </text>
            </g>

            <!-- Corners -->
            <g v-for="corner in [
                { at: TU, label: 'TU', title: trans('Trade unit'), dy: -18 },
                { at: SKO, label: 'SKO', title: trans('Org stock'), dy: 26 },
                { at: P, label: 'P', title: trans('Product'), dy: 26 },
            ]" :key="corner.label">
                <circle :cx="corner.at.x" :cy="corner.at.y" r="14" fill="#f8fafc" stroke="#475569" stroke-width="2" />
                <circle :cx="corner.at.x" :cy="corner.at.y" r="3" fill="#475569" />
                <text :x="corner.at.x" :y="corner.at.y + corner.dy" text-anchor="middle"
                    fill="#334155" class="text-[13px] font-semibold">
                    {{ corner.label }}
                    <title>{{ corner.title }}</title>
                </text>
            </g>

            <!-- The all-seeing eye -->
            <g :transform="`translate(${CENTER.x}, ${CENTER.y})`">
                <!-- rays -->
                <g stroke="#f59e0b" stroke-width="1.5" opacity="0.7">
                    <line v-for="ray in 8" :key="ray"
                        :x1="Math.cos((ray * Math.PI) / 4) * 22" :y1="Math.sin((ray * Math.PI) / 4) * 22"
                        :x2="Math.cos((ray * Math.PI) / 4) * 28" :y2="Math.sin((ray * Math.PI) / 4) * 28" />
                </g>
                <!-- eyelids -->
                <g :transform="isBlinking ? 'scale(1, 0.08)' : 'scale(1, 1)'" class="transition-transform duration-100">
                    <path d="M -18 0 Q 0 -14 18 0 Q 0 14 -18 0 Z" fill="white" stroke="#475569" stroke-width="2" />
                    <circle :cx="pupil.x * 0.6" :cy="pupil.y * 0.35" r="6.5" fill="#0d9488" />
                    <circle :cx="pupil.x * 0.6" :cy="pupil.y * 0.35" r="2.8" fill="#1e293b" />
                    <circle :cx="pupil.x * 0.6 - 1.5" :cy="pupil.y * 0.35 - 1.5" r="1" fill="white" />
                </g>
                <!-- lashes when blinking -->
                <path v-if="isBlinking" d="M -18 0 Q 0 4 18 0" fill="none" stroke="#475569" stroke-width="2" />
            </g>
        </svg>

        <div class="text-center text-xs text-gray-400 mt-1">
            <template v-if="tradeUnits?.length > 1">
                {{ trans(':code and :count more', { code: first.code ?? 'TU', count: tradeUnits.length - 1 }) }}
            </template>
            <template v-else-if="first.code">{{ first.code }}</template>
        </div>
    </div>
</template>
