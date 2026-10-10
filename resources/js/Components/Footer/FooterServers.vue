<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, shallowRef } from 'vue'
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faServer, faRocketLaunch } from '@fal'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { ctrans } from '@/Composables/useTrans'

library.add(faServer, faRocketLaunch)

const SERVERS = [
    { slug: 'boro', role: 'Primary' },
    { slug: 'litio', role: 'Secondary' },
]
const STALE_AFTER_MS = 15000
const DEPLOY_SILENT_AFTER_MS = 10 * 60000

interface ServerReading { cpu: number, memory: number, receivedAt: number }
interface DeployProgress { status: string | null, head_message: string | null, deploy_done: number, deploy_total: number | null, receivedAt: number }

const layout = inject('layout', layoutStructure)
const serversChannel = `grp.${layout?.group?.id}.devops.servers`
const ciChannel = `grp.${layout?.group?.id}.devops.ci`

const latest: { servers: Record<string, ServerReading>, deploy: DeployProgress | null } = { servers: {}, deploy: null }
const snapshot = shallowRef<typeof latest>({ servers: {}, deploy: null })

const onServerReading = ({ slug, cpu_percent, memory_percent }: { slug: string, cpu_percent: number, memory_percent: number }) => {
    latest.servers[slug] = { cpu: cpu_percent, memory: memory_percent, receivedAt: Date.now() }
}

const onCiRunUpdated = ({ deploy }: { deploy?: Omit<DeployProgress, 'receivedAt'> }) => {
    if (deploy) {
        latest.deploy = deploy.status === 'completed' ? null : { ...deploy, receivedAt: Date.now() }
    }
}

const refresh = () => {
    if (!document.hidden) {
        snapshot.value = { servers: { ...latest.servers }, deploy: latest.deploy }
    }
}

let intervalId: number | undefined
onMounted(() => {
    window.Echo.private(serversChannel).listen('.server-live-metrics', onServerReading)
    window.Echo.private(ciChannel).listen('.ci-run-updated', onCiRunUpdated)
    intervalId = window.setInterval(refresh, 1000)
})
onBeforeUnmount(() => {
    window.Echo.private(serversChannel).stopListening('.server-live-metrics', onServerReading)
    window.Echo.private(ciChannel).stopListening('.ci-run-updated', onCiRunUpdated)
    clearInterval(intervalId)
})

const servers = computed(() => {
    const now = Date.now()

    return SERVERS.map(({ slug, role }) => {
        const reading = snapshot.value.servers[slug]
        const isLive = reading && now - reading.receivedAt <= STALE_AFTER_MS
        const cpu = isLive ? Math.round(reading.cpu) : null

        return {
            slug,
            cpu,
            tooltip: isLive
                ? `${slug} (${ctrans(role)}) · CPU ${cpu}% · ${ctrans('Memory')} ${Math.round(reading.memory)}%`
                : `${slug} (${ctrans(role)}): ${ctrans('no live reading')}`,
            barClass: cpu === null ? '' : cpu >= 85 ? 'bg-red-500' : cpu >= 60 ? 'bg-amber-400' : 'bg-emerald-500',
        }
    })
})

const hasReadings = computed(() => Object.keys(snapshot.value.servers).length > 0)

const deploy = computed(() => {
    const progress = snapshot.value.deploy

    return progress && Date.now() - progress.receivedAt <= DEPLOY_SILENT_AFTER_MS ? progress : null
})
</script>

<template>
    <Link v-if="hasReadings || deploy" :href="route('grp.devops.dashboard')" class="flex items-center gap-x-3 text-xs tabular-nums whitespace-nowrap hover:text-white">
        <span v-if="hasReadings" class="flex items-center gap-x-1">
            <FontAwesomeIcon icon="fal fa-server" fixed-width aria-hidden="true" />
            <span v-for="server in servers" :key="server.slug" v-tooltip="server.tooltip" class="relative h-3 w-1.5 overflow-hidden rounded-sm bg-slate-700">
                <span class="absolute inset-x-0 bottom-0 transition-[height,background-color] duration-700 ease-out" :class="server.barClass" :style="{ height: `${Math.max(server.cpu ?? 0, 8)}%` }" />
            </span>
        </span>

        <span v-if="deploy" v-tooltip="deploy.head_message" class="flex items-center gap-x-1.5 text-amber-300">
            <FontAwesomeIcon icon="fal fa-rocket-launch" fixed-width class="animate-pulse" aria-hidden="true" />
            <span>{{ ctrans('New version coming') }}</span>
            <span v-if="deploy.deploy_total" v-tooltip="`${deploy.deploy_done}/${deploy.deploy_total}`" class="inline-block h-3 w-3 rounded-full border border-current" :style="{ background: `conic-gradient(currentColor ${Math.min(100, deploy.deploy_done / deploy.deploy_total * 100)}%, transparent 0)` }" />
            <span v-if="deploy.head_message" class="hidden xl:inline max-w-56 truncate text-slate-400">{{ deploy.head_message }}</span>
        </span>
    </Link>
</template>
