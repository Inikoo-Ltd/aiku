
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
import { faChartLine, faCode, faDatabase, faExclamationTriangle, faFlask, faRocket, faServer, faSpinnerThird, faTools } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import CiRunCard, { CiRunDetail, CiRunSummary } from "@/Components/DevOps/CiRunCard.vue"
import TestStats, { TestStatsData } from "@/Components/DevOps/TestStats.vue"
import CodeHealth, { CodeHealthData } from "@/Components/DevOps/CodeHealth.vue"
import TelemetryPanel, { Telemetry } from "@/Components/DevOps/TelemetryPanel.vue"
import { Trace } from "@/Components/DevOps/TraceWaterfall.vue"
import { ExceptionDetailData } from "@/Components/DevOps/ExceptionDetail.vue"
import { Logs } from "@/Components/DevOps/LogsView.vue"
import ServerUsageCard, { ServerSummary } from "@/Components/DevOps/ServerUsageCard.vue"
import DatabaseReplicas, { DatabaseReplica } from "@/Components/DevOps/DatabaseReplicas.vue"
import DatabaseBackups, { DatabaseBackupsReport } from "@/Components/DevOps/DatabaseBackups.vue"
import DisasterRecovery, { DisasterRecoveryReport } from "@/Components/DevOps/DisasterRecovery.vue"
import ClaudeAccountUsage, { ClaudeUsage } from "@/Components/Ai/ClaudeAccountUsage.vue"
import { LiveServerReading, useLiveServerMetrics } from "@/Composables/useLiveServerMetrics"

library.add(faChartLine, faCode, faDatabase, faExclamationTriangle, faFlask, faRocket, faServer, faSpinnerThird, faTools)

const props = defineProps<{
    claudeUsage: Record<string, ClaudeUsage>
    title: string
    pageHead: PageHeadingTypes
    servers: ServerSummary[]
    liveReadings: Record<string, LiveServerReading[]>
    databaseReplicas: DatabaseReplica[]
    databaseBackups: DatabaseBackupsReport | null
    disasterRecovery: DisasterRecoveryReport
    ciRuns: {
        deploy: CiRunDetail | null
        tests: CiRunDetail | null
        recent_deploys: CiRunSummary[]
        recent_tests: CiRunSummary[]
        usual_deploy_seconds: number | null
        usual_tests_seconds: number | null
        test_stats: TestStatsData
    }
    codeHealth?: CodeHealthData | null
    telemetry?: Telemetry | null
    telemetryTrace?: Trace | null
    telemetryException?: ExceptionDetailData | null
    telemetryLogs?: Logs | null
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

const isRunning = (run: CiRunDetail | null) => run && run.status !== "completed" && !run.conclusion ? run : null
const runningDeploy = computed(() => isRunning(props.ciRuns.deploy))
const runningTests = computed(() => isRunning(props.ciRuns.tests))
const spinning = { icon: "fal fa-spinner-third", iconClass: "animate-spin text-sky-500" }

const tabs = computed(() => ({
    servers: { title: ctrans("Servers"), icon: "fal fa-server" },
    deployments: runningDeploy.value
        ? {
            title: runningDeploy.value.deploy_total
                ? `${ctrans("Deploying")} ${runningDeploy.value.deploy_done}/${runningDeploy.value.deploy_total}`
                : ctrans("Deploying"),
            ...spinning,
        }
        : { title: ctrans("Deployments"), icon: "fal fa-rocket" },
    tests: runningTests.value ? { title: ctrans("Testing"), ...spinning } : { title: ctrans("Tests"), icon: "fal fa-flask" },
    code: { title: ctrans("Code health"), icon: "fal fa-code" },
    telemetry: { title: ctrans("Telemetry"), icon: "fal fa-chart-line" },
}))
const requestedTab = new URLSearchParams(window.location.search).get("tab") ?? ""
const currentTab = ref<string>(["deployments", "tests", "code", "telemetry"].includes(requestedTab) ? requestedTab : "servers")
const changeTab = (tab: string | number) => {
    currentTab.value = String(tab)
    const url = new URL(window.location.href)
    url.searchParams.set("tab", currentTab.value)
    window.history.replaceState(window.history.state, "", url)
}

watch(currentTab, (tab) => {
    if (tab === "code" && !props.codeHealth) {
        router.reload({ only: ["codeHealth"] })
    }
}, { immediate: true })

const problems = computed(() => [
    ...props.databaseReplicas.map((replica) => replica.issue),
    ...(props.databaseBackups ? [props.databaseBackups.archive_issue, props.databaseBackups.backup_issue] : []),
].filter((issue): issue is string => !!issue).map((issue) => issue.replace(/`/g, "")))

const serverGroups = computed(() => props.servers.reduce<Record<string, ServerSummary[]>>((groups, server) => {
    (groups[server.group] ??= []).push(server)
    return groups
}, {}))

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div v-if="problems.length" class="mx-4 mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800" role="alert">
        <div class="flex items-center gap-x-2 font-semibold">
            <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-red-600" fixed-width aria-hidden="true" />
            {{ ctrans(":count problem(s) with the production database", { count: problems.length }) }}
        </div>
        <ul class="mt-1.5 list-disc space-y-1 pl-9">
            <li v-for="problem in problems" :key="problem">{{ problem }}</li>
        </ul>
    </div>
    <div v-if="disasterRecovery.issue" class="mx-4 mt-3 flex items-center gap-x-2 rounded-lg border border-red-300 bg-red-50 p-3 text-sm font-semibold text-red-800" role="alert">
        <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-red-600" fixed-width aria-hidden="true" />
        {{ disasterRecovery.issue }}
    </div>
    <Tabs :current="currentTab" :navigation="tabs" @update:tab="changeTab" />

    <div class="p-4">
        <div v-if="currentTab === 'servers'" class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
            <section v-for="(groupServers, group) in serverGroups" :key="group">
                <h4 class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans(group) }}</h4>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Link v-for="server in groupServers" :key="server.slug" :href="route('grp.devops.servers.show', [server.slug])" class="block hover:shadow">
                        <ServerUsageCard :server="server" :live="liveReadings[server.slug]" />
                    </Link>
                </div>
            </section>
        </div>
        <DatabaseReplicas v-if="currentTab === 'servers' && databaseReplicas.length" :replicas="databaseReplicas" class="mb-6" />
        <DatabaseBackups v-if="currentTab === 'servers' && databaseBackups && (databaseBackups.archive_mode || databaseBackups.backups.length)" :report="databaseBackups" class="mb-6" />
        <DisasterRecovery v-if="currentTab === 'servers'" :report="disasterRecovery" class="mb-6" />
        <ClaudeAccountUsage v-if="currentTab === 'servers'" :usage="claudeUsage" class="mb-6 max-w-xl" />
        <div v-if="currentTab === 'deployments'" class="mb-6 max-w-4xl">
            <CiRunCard :title="ctrans('Production deploy')" :run="ciRuns.deploy" :recent="ciRuns.recent_deploys" :usual-seconds="ciRuns.usual_deploy_seconds" />
        </div>
        <div v-if="currentTab === 'tests'" class="mb-6 max-w-4xl space-y-4">
            <TestStats :stats="ciRuns.test_stats" />
            <CiRunCard :title="ctrans('Tests on main')" :run="ciRuns.tests" :recent="ciRuns.recent_tests" :usual-seconds="ciRuns.usual_tests_seconds" />
        </div>
        <template v-if="currentTab === 'code'">
            <CodeHealth v-if="codeHealth" :health="codeHealth" class="mb-6 max-w-5xl" />
            <div v-else class="mb-6 max-w-5xl space-y-4" aria-busy="true">
                <div class="h-72 animate-pulse rounded-lg bg-gray-100" />
                <div class="h-72 animate-pulse rounded-lg bg-gray-100" />
            </div>
        </template>
        <TelemetryPanel v-if="currentTab === 'telemetry'" :telemetry="telemetry" :trace="telemetryTrace" :exception="telemetryException" :logs="telemetryLogs" />
    </div>
</template>
