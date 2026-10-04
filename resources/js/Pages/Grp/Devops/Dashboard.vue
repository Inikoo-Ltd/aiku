
<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 06 Jun 2026 09:22:41 Indochina Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router, usePage } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { faDatabase, faServer } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, onBeforeUnmount, onMounted } from "vue"
import CiRunCard, { CiRunDetail, CiRunSummary } from "@/Components/DevOps/CiRunCard.vue"
import ServerUsageCard, { ServerSummary } from "@/Components/DevOps/ServerUsageCard.vue"
import { LiveServerReading, useLiveServerMetrics } from "@/Composables/useLiveServerMetrics"

library.add(faDatabase, faServer)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    servers: ServerSummary[]
    liveReadings: Record<string, LiveServerReading[]>
    ciRuns: {
        deploy: CiRunDetail | null
        tests: CiRunDetail | null
        recent_deploys: CiRunSummary[]
        recent_tests: CiRunSummary[]
        usual_deploy_seconds: number | null
    }
}>()

const { readings: liveReadings } = useLiveServerMetrics(props.liveReadings)

const groupId = (usePage().props.layout as any)?.group?.id
let ciReloadTimer: ReturnType<typeof setTimeout> | undefined
const reloadCiRuns = () => {
    clearTimeout(ciReloadTimer)
    ciReloadTimer = setTimeout(() => router.reload({ only: ["ciRuns"] }), 500)
}
onMounted(() => groupId && window.Echo.private(`grp.${groupId}.devops.ci`).listen(".ci-run-updated", reloadCiRuns))
onBeforeUnmount(() => {
    clearTimeout(ciReloadTimer)
    if (groupId) {
        window.Echo.private(`grp.${groupId}.devops.ci`).stopListening(".ci-run-updated", reloadCiRuns)
    }
})

const serverGroups = computed(() => props.servers.reduce<Record<string, ServerSummary[]>>((groups, server) => {
    (groups[server.group] ??= []).push(server)
    return groups
}, {}))

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4">
        <h3 class="mb-2 text-sm font-semibold">{{ ctrans("Deployments") }}</h3>
        <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <CiRunCard :title="ctrans('Production deploy')" :run="ciRuns.deploy" :recent="ciRuns.recent_deploys" :usual-seconds="ciRuns.usual_deploy_seconds" />
            <CiRunCard :title="ctrans('Tests on main')" :run="ciRuns.tests" :recent="ciRuns.recent_tests" />
        </div>

        <h3 class="mb-2 text-sm font-semibold">{{ ctrans("Servers") }}</h3>
        <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
            <section v-for="(groupServers, group) in serverGroups" :key="group">
                <h4 class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans(group) }}</h4>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Link v-for="server in groupServers" :key="server.slug" :href="route('grp.devops.servers.show', [server.slug])" class="block hover:shadow">
                        <ServerUsageCard :server="server" :live="liveReadings[server.slug]" />
                    </Link>
                </div>
            </section>
        </div>
    </div>
</template>
