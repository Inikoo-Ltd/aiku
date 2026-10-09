<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, shallowRef } from 'vue'
import { Link } from '@inertiajs/vue3'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { ctrans } from '@/Composables/useTrans'

const STALE_AFTER_MS = 15000

const layout = inject('layout', layoutStructure)
const channelName = `grp.${layout?.group?.id}.devops.servers`

const latestBySlug: Record<string, { cpu: number, memory: number, receivedAt: number }> = {}
const snapshot = shallowRef<Record<string, { cpu: number, memory: number, receivedAt: number }>>({})

const handler = ({ slug, cpu_percent, memory_percent }: { slug: string, cpu_percent: number, memory_percent: number }) => {
    latestBySlug[slug] = { cpu: cpu_percent, memory: memory_percent, receivedAt: Date.now() }
}

const refresh = () => {
    if (!document.hidden) {
        snapshot.value = { ...latestBySlug }
    }
}

let intervalId: number | undefined
onMounted(() => {
    window.Echo.private(channelName).listen('.server-live-metrics', handler)
    intervalId = window.setInterval(refresh, 1000)
})
onBeforeUnmount(() => {
    window.Echo.private(channelName).stopListening('.server-live-metrics', handler)
    clearInterval(intervalId)
})

const servers = computed(() => {
    const now = Date.now()

    return Object.keys(snapshot.value).sort().map((slug) => {
        const { cpu, memory, receivedAt } = snapshot.value[slug]
        const isStale = now - receivedAt > STALE_AFTER_MS

        return {
            slug,
            cpu: isStale ? null : Math.round(cpu),
            tooltip: isStale
                ? `${slug}: ${ctrans('no reading for over :seconds s', { seconds: STALE_AFTER_MS / 1000 })}`
                : `${slug} · CPU ${Math.round(cpu)}% · ${ctrans('Memory')} ${Math.round(memory)}%`,
            dotClass: isStale ? 'bg-slate-600' : cpu >= 85 ? 'bg-red-500' : cpu >= 60 ? 'bg-amber-400' : 'bg-emerald-500',
        }
    })
})
</script>

<template>
    <Link v-if="servers.length" :href="route('grp.devops.dashboard')" class="flex items-center gap-x-3 text-xs tabular-nums hover:text-white">
        <span v-for="server in servers" :key="server.slug" v-tooltip="server.tooltip" class="flex items-center gap-x-1 whitespace-nowrap">
            <span class="size-1.5 rounded-full" :class="server.dotClass" />
            {{ server.slug }} {{ server.cpu ?? '—' }}%
        </span>
    </Link>
</template>
