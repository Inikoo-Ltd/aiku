<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 03 Aug 2026 21:05:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from 'vue'
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { trans } from "laravel-vue-i18n"
import { useRafFn } from '@vueuse/core'
import { PageHeadingTypes } from "@/types/PageHeading";
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch } from "@far"

library.add(faSearch)

const props = defineProps<{
    website: {
        id: number
        slug: string
        domain: string
    }
    visitors: any[]
    title: string
    breadcrumbs: any[]
    pageHead: PageHeadingTypes
}>()

const canvasRef = ref<HTMLCanvasElement | null>(null)
const containerRef = ref<HTMLDivElement | null>(null)

interface Visitor {
    session_id: string
    x: number
    y: number
    vx: number
    vy: number
    radius: number
    color: string
    label: string
    page: string
    page_title?: string
    url: string
    last_active: number
    logged_in: boolean
    pulse: number
    browser?: string
    os?: string
    search_engine?: string
    search_term?: string
    agent?: string
    department?: string
    status?: string
}

const liveVisitors = ref<Map<string, Visitor>>(new Map())
const hoveredVisitor = ref<Visitor | null>(null)
const currentGrouping = ref('country')
const searchQuery = ref('')

const colors = {
    incoming: '#b91c1c',
    assigned: '#65a30d',
    clicked: '#d97706',
    served: '#84cc16',
    triggered: '#2563eb',
    active: '#0369a1',
    idle: '#7dd3fc',
    logged_in: '#10b981',
    logged_out: '#6b7280',
}

const groupingOptions = [
    { value: 'status', label: trans('Activity') },
    { value: 'page_title', label: trans('Page title') },
    { value: 'page_url', label: trans('Page URL') },
    { value: 'country', label: trans('Country') },
    { value: 'serving_agent', label: trans('Serving agent') },
    { value: 'department', label: trans('Department') },
    { value: 'browser', label: trans('Browser') },
    { value: 'search_engine', label: trans('Search engine') },
    { value: 'search_term', label: trans('Search term') },
]

const getCountryName = (code: string) => {
    if (!code || code === 'XX') return trans('Unknown')
    try {
        const regionNames = new Intl.DisplayNames(['en'], { type: 'region' })
        return regionNames.of(code) || code
    } catch (e) {
        return code
    }
}

const getStatus = (visitor: any): string => {
    const now = Date.now() / 1000
    const elapsed = now - (visitor.last_active || 0)
    
    if (elapsed < 30) return 'active'
    if (elapsed < 300) return 'idle'
    return 'idle'
}

const initVisitor = (data: any): Visitor => {
    const isLoggedIn = String(data.logged_in) === 'true'
    const status = getStatus(data)
    
    return {
        session_id: data.session_id,
        x: Math.random() * (canvasRef.value?.width || 800),
        y: Math.random() * (canvasRef.value?.height || 500),
        vx: (Math.random() - 0.5) * 1.5,
        vy: (Math.random() - 0.5) * 1.5,
        radius: 14,
        color: isLoggedIn ? colors.active : colors.idle, // Default color, will be updated by status
        label: data.country || 'XX',
        page: data.page || '',
        page_title: data.page_title,
        url: data.url || '',
        last_active: Number(data.last_active) || Date.now() / 1000,
        logged_in: isLoggedIn,
        pulse: 0,
        browser: data.browser,
        os: data.os,
        search_engine: data.search_engine,
        search_term: data.search_term,
        status: status
    }
}

// Load initial visitors
props.visitors.forEach(v => {
    liveVisitors.value.set(v.session_id, initVisitor(v))
})

const getGroupingKey = (v: Visitor): string => {
    switch (currentGrouping.value) {
        case 'country': return getCountryName(v.label)
        case 'browser': return v.browser || trans('Unknown')
        case 'page_title': return v.page_title || v.page || trans('Home')
        case 'page_url': return v.url || '/'
        case 'search_engine': return v.search_engine || trans('Direct')
        case 'search_term': return v.search_term || trans('None')
        case 'status': return trans(v.status?.charAt(0).toUpperCase() + v.status?.slice(1))
        case 'serving_agent': return v.agent || trans('None')
        case 'department': return v.department || trans('None')
        default: return trans('Other')
    }
}

const clusters = computed(() => {
    const groupMap = new Map<string, { label: string, count: number }>()
    liveVisitors.value.forEach(v => {
        const key = getGroupingKey(v)
        if (!groupMap.has(key)) {
            groupMap.set(key, { label: key, count: 0 })
        }
        groupMap.get(key)!.count++
    })
    
    const sortedGroups = Array.from(groupMap.values()).sort((a, b) => b.count - a.count)
    const canvas = canvasRef.value
    if (!canvas) return new Map()

    const centerX = canvas.width / 2
    const centerY = canvas.height / 2
    const clusterMap = new Map<string, { x: number, y: number, label: string }>()
    
    const numGroups = sortedGroups.length
    const radius = Math.min(centerX, centerY) * 0.7

    sortedGroups.forEach((g, i) => {
        if (numGroups === 1) {
            clusterMap.set(g.label, { x: centerX, y: centerY, label: g.label })
        } else {
            const angle = (i / numGroups) * Math.PI * 2
            clusterMap.set(g.label, {
                x: centerX + Math.cos(angle) * radius,
                y: centerY + Math.sin(angle) * radius,
                label: g.label
            })
        }
    })
    
    return clusterMap
})

const visitorList = computed(() => {
    let list = Array.from(liveVisitors.value.values())
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase()
        list = list.filter(v => 
            v.page?.toLowerCase().includes(q) || 
            v.page_title?.toLowerCase().includes(q) ||
            v.url?.toLowerCase().includes(q) ||
            v.label?.toLowerCase().includes(q)
        )
    }
    return list.sort((a, b) => b.last_active - a.last_active)
})

onMounted(() => {
    const canvas = canvasRef.value
    if (!canvas) return
    const ctx = canvas.getContext('2d')
    if (!ctx) return

    const resize = () => {
        if (containerRef.value) {
            canvas.width = containerRef.value.clientWidth
            canvas.height = containerRef.value.clientHeight
        }
    }
    window.addEventListener('resize', resize)
    resize()

    useRafFn(() => {
        ctx.clearRect(0, 0, canvas.width, canvas.height)

        const now = Date.now() / 1000
        
        // Draw cluster labels first
        clusters.value.forEach(c => {
            ctx.fillStyle = '#94a3b8'
            ctx.font = '12px sans-serif'
            ctx.textAlign = 'center'
            ctx.fillText(c.label, c.x, c.y + 60)
        })

        const visitorsArr = Array.from(liveVisitors.value.values())
        
        visitorsArr.forEach((visitor, index) => {
            const sessionId = visitor.session_id
            // Remove inactive (older than 5 mins)
            if (now - visitor.last_active > 300) {
                liveVisitors.value.delete(sessionId)
                return
            }

            // Update status
            visitor.status = getStatus(visitor)

            // Force-directed grouping
            const cluster = clusters.value.get(getGroupingKey(visitor))
            if (cluster) {
                visitor.vx += (cluster.x - visitor.x) * 0.02
                visitor.vy += (cluster.y - visitor.y) * 0.02
            }

            // Collision detection
            for (let i = index + 1; i < visitorsArr.length; i++) {
                const other = visitorsArr[i]
                const dx = other.x - visitor.x
                const dy = other.y - visitor.y
                const distance = Math.sqrt(dx * dx + dy * dy)
                const minDistance = visitor.radius + other.radius + 4
                
                if (distance < minDistance) {
                    const angle = Math.atan2(dy, dx)
                    const push = (minDistance - distance) * 0.05
                    const ax = Math.cos(angle) * push
                    const ay = Math.sin(angle) * push
                    visitor.vx -= ax
                    visitor.vy -= ay
                    other.vx += ax
                    other.vy += ay
                }
            }

            // Friction
            visitor.vx *= 0.9
            visitor.vy *= 0.9

            // Move
            visitor.x += visitor.vx
            visitor.y += visitor.vy

            // Bounce from edges
            if (visitor.x < visitor.radius) { visitor.x = visitor.radius; visitor.vx *= -0.5; }
            if (visitor.x > canvas.width - visitor.radius) { visitor.x = canvas.width - visitor.radius; visitor.vx *= -0.5; }
            if (visitor.y < visitor.radius) { visitor.y = visitor.radius; visitor.vy *= -0.5; }
            if (visitor.y > canvas.height - visitor.radius) { visitor.y = canvas.height - visitor.radius; visitor.vy *= -0.5; }

            // Color based on status
            const color = colors[visitor.status as keyof typeof colors] || colors.idle

            // Draw shadow
            ctx.shadowBlur = 4
            ctx.shadowColor = "rgba(0,0,0,0.2)"
            ctx.shadowOffsetY = 2

            // Draw
            ctx.beginPath()
            ctx.arc(visitor.x, visitor.y, visitor.radius, 0, Math.PI * 2)
            ctx.fillStyle = color
            ctx.fill()
            
            ctx.shadowBlur = 0
            ctx.shadowOffsetY = 0

            // Pulse effect if recently active
            if (now - visitor.last_active < 3) {
                const p = (now - visitor.last_active) / 3
                ctx.beginPath()
                ctx.arc(visitor.x, visitor.y, visitor.radius + p * 20, 0, Math.PI * 2)
                ctx.strokeStyle = color
                ctx.lineWidth = 2
                ctx.globalAlpha = 1 - p
                ctx.stroke()
                ctx.globalAlpha = 1.0
            }

            if (hoveredVisitor.value?.session_id === sessionId) {
                 ctx.beginPath()
                 ctx.arc(visitor.x, visitor.y, visitor.radius + 2, 0, Math.PI * 2)
                 ctx.strokeStyle = '#3b82f6'
                 ctx.lineWidth = 2
                 ctx.stroke()
            }

            // Label
            ctx.fillStyle = 'white'
            ctx.font = 'bold 9px sans-serif'
            ctx.textAlign = 'center'
            ctx.textBaseline = 'middle'
            ctx.fillText(visitor.label, visitor.x, visitor.y)
        })
    })

    window.Echo.channel(`website.${props.website.id}.analytics`)
        .listen('.App\\Events\\Web\\WebsiteVisitorHit', (e: any) => {
            const existing = liveVisitors.value.get(e.session_id)
            if (existing) {
                existing.last_active = Date.now() / 1000
                existing.page = e.page
                existing.page_title = e.page_title
                existing.url = e.url
                existing.label = e.country
                existing.browser = e.browser
                existing.os = e.os
                existing.search_engine = e.search_engine
                existing.search_term = e.search_term
                existing.vx += (Math.random() - 0.5) * 5
                existing.vy += (Math.random() - 0.5) * 5
            } else {
                liveVisitors.value.set(e.session_id, initVisitor(e))
            }
        })
})

onUnmounted(() => {
    window.Echo.leaveChannel(`website.${props.website.id}.analytics`)
})

const handleMouseMove = (e: MouseEvent) => {
    if (!canvasRef.value) return
    const rect = canvasRef.value.getBoundingClientRect()
    const x = e.clientX - rect.left
    const y = e.clientY - rect.top
    
    let found = null
    liveVisitors.value.forEach(v => {
        const dist = Math.sqrt((v.x - x)**2 + (v.y - y)**2)
        if (dist < v.radius + 5) {
            found = v
        }
    })
    hoveredVisitor.value = found
}

</script>

<template>
    <Head :title="title" />

    <PageHeading :data="pageHead" />

    <div class="py-6 px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Graphical View -->
            <div class="lg:col-span-3 bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 flex flex-col">
                <div class="p-6 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <select 
                            v-model="currentGrouping" 
                            class="rounded-md border-gray-300 py-1.5 pl-3 pr-10 text-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500"
                        >
                            <option v-for="opt in groupingOptions" :key="opt.value" :value="opt.value">
                                {{ trans('Group by') }} {{ opt.label }}
                            </option>
                        </select>
                        <span class="text-sm text-gray-500">{{ trans('Total') }}: {{ liveVisitors.size }}</span>
                    </div>

                    <div class="relative">
                        <input 
                            v-model="searchQuery" 
                            type="text" 
                            :placeholder="trans('Search')"
                            class="rounded-md border-gray-300 py-1.5 pl-3 pr-10 text-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 w-64"
                        >
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <FontAwesomeIcon :icon="faSearch" class="text-gray-400" />
                        </div>
                    </div>
                </div>

                <div ref="containerRef" class="relative flex-1 min-h-[600px] bg-white cursor-crosshair overflow-hidden">
                    <canvas 
                        ref="canvasRef" 
                        class="w-full h-full"
                        @mousemove="handleMouseMove"
                    ></canvas>
                    
                    <div v-if="liveVisitors.size === 0" class="absolute inset-0 flex items-center justify-center text-gray-400 italic">
                        {{ trans('No live visitors currently detected.') }}
                    </div>

                    <!-- Tooltip -->
                    <div 
                        v-if="hoveredVisitor" 
                        class="absolute bg-white/95 backdrop-blur-sm border border-gray-200 p-3 rounded shadow-xl pointer-events-none z-10 text-xs min-w-[250px]"
                        :style="{ left: `${hoveredVisitor.x + 20}px`, top: `${hoveredVisitor.y - 40}px` }"
                    >
                        <div class="font-bold border-b border-gray-100 pb-1 mb-2 flex justify-between items-center">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: colors[hoveredVisitor.status as keyof typeof colors] || colors.idle }"></span>
                                {{ getGroupingKey(hoveredVisitor) }}
                            </span>
                            <span class="text-gray-400 font-mono">{{ hoveredVisitor.label }}</span>
                        </div>
                        
                        <div class="space-y-1">
                            <div class="text-gray-600">
                                <span class="font-semibold">{{ trans('Page') }}:</span> 
                                <span class="text-indigo-600">{{ hoveredVisitor.page_title || hoveredVisitor.page || 'Home' }}</span>
                            </div>
                            <div class="text-gray-400 truncate text-[10px]">{{ hoveredVisitor.url }}</div>
                            
                            <div v-if="hoveredVisitor.browser" class="text-gray-600 pt-1 flex gap-2">
                                <span><span class="font-semibold">{{ trans('Browser') }}:</span> {{ hoveredVisitor.browser }}</span>
                                <span><span class="font-semibold">{{ trans('OS') }}:</span> {{ hoveredVisitor.os }}</span>
                            </div>
                            
                            <div v-if="hoveredVisitor.search_engine" class="text-gray-600 pt-1">
                                <span class="font-semibold">{{ trans('Referrer') }}:</span> {{ hoveredVisitor.search_engine }}
                                <span v-if="hoveredVisitor.search_term" class="text-gray-400 italic"> ({{ hoveredVisitor.search_term }})</span>
                            </div>

                            <div class="text-gray-400 mt-2 border-t border-gray-50 pt-1 text-[10px] flex justify-between">
                                <span>ID: {{ hoveredVisitor.session_id.substring(0, 8) }}</span>
                                <span>{{ trans('Last active') }}: {{ trans('Just now') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Legend & List -->
            <div class="flex flex-col gap-6">
                <!-- Legend -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4 uppercase tracking-wider">{{ trans('Legend') }}</h3>
                    <div class="space-y-3">
                        <div v-for="(color, name) in colors" :key="name" class="flex items-center justify-between text-xs">
                           <div class="flex items-center gap-2">
                               <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: color }"></span>
                               <span class="capitalize text-gray-700">{{ trans(name.replace('_', ' ')) }}</span>
                           </div>
                        </div>
                    </div>
                </div>

                <!-- List View -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 flex flex-col flex-1 max-h-[500px]">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">{{ trans('Active Visitors') }}</h3>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <ul class="divide-y divide-gray-100">
                            <li 
                                v-for="visitor in visitorList" 
                                :key="visitor.session_id"
                                class="p-3 hover:bg-gray-50 transition-colors cursor-default"
                                @mouseenter="hoveredVisitor = visitor"
                                @mouseleave="hoveredVisitor = null"
                            >
                                <div class="flex items-center gap-3">
                                    <div 
                                        class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-xs shrink-0 shadow-sm"
                                        :style="{ backgroundColor: colors[visitor.status as keyof typeof colors] || colors.idle }"
                                    >
                                        {{ visitor.label }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[11px] font-medium text-gray-900 truncate">
                                            {{ visitor.page_title || visitor.page || 'Home' }}
                                        </p>
                                        <p class="text-[9px] text-gray-400 truncate">
                                            {{ getGroupingKey(visitor) }}
                                        </p>
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <div v-if="liveVisitors.size === 0" class="p-8 text-center text-gray-400 italic text-sm">
                             {{ trans('Waiting for activity...') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
canvas {
    image-rendering: auto;
}
</style>
