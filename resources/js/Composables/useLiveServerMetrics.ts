import { onMounted, onBeforeUnmount, reactive } from "vue"
import { usePage } from "@inertiajs/vue3"

export interface LiveServerReading {
    t: number
    cpu_percent: number
    iowait_percent: number | null
    memory_percent: number
    net_rx_mbps: number | null
    net_tx_mbps: number | null
}

const KEEP_READINGS = 60

export const useLiveServerMetrics = (initial: Record<string, LiveServerReading[]>) => {
    const groupId = (usePage().props.layout as any)?.group?.id
    const channelName = `grp.${groupId}.devops.servers`
    const readings = reactive<Record<string, LiveServerReading[]>>({ ...initial })

    const handler = ({ slug, ...reading }: LiveServerReading & { slug: string }) => {
        readings[slug] = [...(readings[slug] ?? []).slice(1 - KEEP_READINGS), reading]
    }

    onMounted(() => {
        if (groupId) {
            window.Echo.private(channelName).listen(".server-live-metrics", handler)
        }
    })

    onBeforeUnmount(() => {
        if (groupId) {
            window.Echo.private(channelName).stopListening(".server-live-metrics", handler)
        }
    })

    return { readings }
}
