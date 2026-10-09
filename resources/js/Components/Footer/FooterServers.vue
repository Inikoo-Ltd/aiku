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
            textClass: cpu === null ? 'text-slate-600' : cpu >= 85 ? 'text-red-400' : cpu >= 60 ? 'text-amber-400' : '',
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
        <span v-if="hasReadings" class="flex items-center gap-x-1.5">
            <FontAwesomeIcon icon="fal fa-server" fixed-width aria-hidden="true" />
            <template v-for="(server, index) in servers" :key="server.slug">
                <span v-if="index" class="text-slate-600">·</span>
                <span v-tooltip="server.tooltip" :class="server.textClass">{{ server.cpu ?? '—' }}%</span>
            </template>
        </span>

        <span v-if="deploy" v-tooltip="deploy.head_message" class="flex items-center gap-x-1.5 text-amber-300">
            <FontAwesomeIcon icon="fal fa-rocket-launch" fixed-width class="animate-pulse" aria-hidden="true" />
            {{ ctrans('Deploying') }}<template v-if="deploy.deploy_total"> {{ deploy.deploy_done }}/{{ deploy.deploy_total }}</template>
            <span v-if="deploy.head_message" class="hidden xl:inline max-w-56 truncate text-slate-400">{{ deploy.head_message }}</span>
        </span>
    </Link>
</template>
