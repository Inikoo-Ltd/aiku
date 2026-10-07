<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Head, Link, router, usePoll } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Chart from "primevue/chart"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime, useRangeFromNow } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInfoCircle } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faInfoCircle)

type Severity = "error" | "warning" | "notice"

type AuditSummary = {
    id: number
    end_at: string
    finish_reason: string | null
    health_score: number
    pages: number
    pages_with_errors: number
    errors: number
    warnings: number
    notices: number
}

type IssueSummary = {
    type: string
    label: string
    description: string
    severity: Severity
    pages: number
    previous_pages: number | null
}

type AuditData = {
    running: { state: string, start_at: string | null, urls_processed: number, urls_found: number } | null
    latest: AuditSummary | null
    previous: AuditSummary | null
    history: Array<{ end_at: string, health_score: number, errors: number, warnings: number, notices: number }>
    issues: IssueSummary[]
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    website: { domain: string }
    canRunAudit: boolean
    runRoute: routeType & { method: string }
    severities: Record<Severity, string>
    audit: AuditData
}>()

const locale = useLocaleStore()

const severityStyles: Record<Severity, { dot: string, text: string }> = {
    error: { dot: "bg-red-500", text: "text-red-700" },
    warning: { dot: "bg-amber-500", text: "text-amber-700" },
    notice: { dot: "bg-gray-400", text: "text-gray-600" },
}

const isRunning = computed(() => props.audit.running !== null)

const { start: startPolling, stop: stopPolling } = usePoll(5000, { only: ["audit"] }, { autoStart: false })

watch(isRunning, (running) => running ? startPolling() : stopPolling(), { immediate: true })

const isStarting = ref(false)

const runAudit = () => {
    router.post(route(props.runRoute.name, props.runRoute.parameters), {}, {
        preserveScroll: true,
        onStart: () => isStarting.value = true,
        onFinish: () => isStarting.value = false,
    })
}

const issueRoute = (issueType: string) => {
    const params = route().params as Record<string, string>

    return route("grp.org.shops.show.seo.site_audit.issue", [params.organisation, params.shop, issueType])
}

const issueChange = (issue: IssueSummary) => issue.previous_pages === null ? null : issue.pages - issue.previous_pages

const severityTotals = computed(() => {
    const latest = props.audit.latest

    if (!latest) {
        return []
    }

    const previous = props.audit.previous

    return ([
        { severity: "error", value: latest.errors, previous: previous?.errors },
        { severity: "warning", value: latest.warnings, previous: previous?.warnings },
        { severity: "notice", value: latest.notices, previous: previous?.notices },
    ] as Array<{ severity: Severity, value: number, previous?: number }>).map((total) => ({
        ...total,
        label: props.severities[total.severity],
        change: total.previous === undefined ? null : total.value - total.previous,
    }))
})

const healthChange = computed(() => {
    if (!props.audit.latest || !props.audit.previous) {
        return null
    }

    return Math.round((props.audit.latest.health_score - props.audit.previous.health_score) * 10) / 10
})

const formatChange = (change: number) => change > 0 ? `+${locale.number(change)}` : locale.number(change)

const accentColor = () => getComputedStyle(document.documentElement).getPropertyValue("--app-accent").trim() || "#4f46e5"

const showTrend = computed(() => props.audit.history.length > 1)

const trendData = computed(() => ({
    labels: props.audit.history.map((audit) => useFormatTime(audit.end_at, { formatTime: "d MMM" })),
    datasets: [
        {
            label: ctrans("Site health"),
            data: props.audit.history.map((audit) => audit.health_score),
            borderColor: accentColor(),
            backgroundColor: accentColor(),
            borderWidth: 2,
            pointRadius: 3,
            tension: 0.25,
        },
    ],
}))

const trendOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { display: false }, ticks: { color: "#6b7280", maxRotation: 0 } },
        y: { min: 0, max: 100, border: { display: false }, grid: { color: "#f3f4f6" }, ticks: { color: "#6b7280", stepSize: 25, callback: (value: number) => `${value}%` } },
    },
}

const finishReasonText = computed(() => {
    const reason = props.audit.latest?.finish_reason

    if (reason === "max_pages_reached") {
        return ctrans("Stopped at the page limit, so some pages were not checked.")
    }

    if (reason === "interrupted") {
        return ctrans("Stopped before the end, so some pages were not checked.")
    }

    return ""
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button
                v-if="canRunAudit"
                type="secondary"
                :label="isRunning ? ctrans('Audit running') : ctrans('Run audit now')"
                icon="fal fa-clipboard-check"
                :loading="isStarting"
                :disabled="isRunning || isStarting"
                @click="runAudit" />
        </template>
    </PageHeading>

    <div
        v-if="audit.running"
        role="status"
        class="mx-4 mt-4 flex flex-wrap items-baseline gap-x-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
        <span class="font-medium text-gray-900">{{ ctrans("Auditing :domain", { domain: website.domain }) }}</span>
        <span v-if="audit.running.state === 'ready'">{{ ctrans("Waiting for a free slot. Audits share the crawler with cache warming.") }}</span>
        <span v-else class="tabular-nums">
            {{ ctrans(":checked pages checked, :found found so far", { checked: locale.number(audit.running.urls_processed), found: locale.number(audit.running.urls_found) }) }}
        </span>
    </div>

    <section
        v-if="!audit.latest"
        class="mx-4 my-4 rounded-xl bg-white px-5 py-6 text-sm text-gray-600 ring-1 ring-gray-200">
        <p class="font-medium text-gray-900">{{ ctrans("No audit has finished for :domain yet.", { domain: website.domain }) }}</p>
        <p class="mt-1">
            {{ ctrans("An audit reads the sitemap and follows the links on every page, then lists broken pages, redirects, and missing or duplicate titles and descriptions. Audits run every week.") }}
        </p>
    </section>

    <template v-else>
        <section
            :aria-label="ctrans('Site health')"
            class="mx-4 my-4 rounded-xl bg-white ring-1 ring-gray-200">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 pt-4">
                <span class="text-sm font-medium text-gray-900">{{ website.domain }}</span>
                <span class="text-xs text-gray-500">
                    {{ ctrans("Audit finished :date", { date: useRangeFromNow(audit.latest.end_at) }) }}
                </span>
            </div>

            <div
                class="grid grid-cols-1 gap-x-8 gap-y-6 px-5 pb-5 pt-5"
                :class="{ 'lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)]': showTrend }">
                <div class="flex flex-col justify-between gap-y-5">
                    <div>
                        <div class="flex items-baseline gap-x-3">
                            <span class="text-4xl font-semibold tabular-nums tracking-tight text-gray-900">{{ locale.number(audit.latest.health_score) }}%</span>
                            <span v-if="healthChange" class="text-sm tabular-nums" :class="healthChange > 0 ? 'text-green-700' : 'text-red-700'">
                                {{ formatChange(healthChange) }}
                            </span>
                        </div>
                        <div class="mt-1 text-sm text-gray-600">{{ ctrans("Site health") }}</div>
                        <div class="text-xs text-gray-500">
                            {{ ctrans(":clean of :pages pages have no errors", { clean: locale.number(audit.latest.pages - audit.latest.pages_with_errors), pages: locale.number(audit.latest.pages) }) }}
                        </div>
                        <p v-if="finishReasonText" class="mt-2 text-xs text-amber-700">{{ finishReasonText }}</p>
                    </div>

                    <dl class="space-y-1 text-sm">
                        <div v-for="total in severityTotals" :key="total.severity" class="flex items-center gap-2">
                            <span class="size-2.5 shrink-0 rounded-full" :class="severityStyles[total.severity].dot" aria-hidden="true" />
                            <dt class="text-gray-600">{{ total.label }}</dt>
                            <dd class="ml-auto tabular-nums text-gray-900">
                                {{ locale.number(total.value) }}
                                <span v-if="total.change" class="ml-1.5 inline-block w-12 text-right text-xs" :class="total.change > 0 ? 'text-red-700' : 'text-green-700'">
                                    {{ formatChange(total.change) }}
                                </span>
                                <span v-else class="ml-1.5 inline-block w-12" />
                            </dd>
                        </div>
                    </dl>
                </div>

                <div v-if="showTrend" class="min-w-0">
                    <div class="h-48 sm:h-56" role="img" :aria-label="ctrans('Site health of the last :count audits', { count: audit.history.length })">
                        <Chart type="line" :data="trendData" :options="trendOptions" class="h-full" />
                    </div>
                </div>
            </div>
        </section>

        <section
            :aria-label="ctrans('Issues')"
            class="mx-4 mb-4 rounded-xl bg-white ring-1 ring-gray-200">
            <p v-if="!audit.issues.length" class="px-5 py-5 text-sm text-gray-600">
                {{ ctrans("The latest audit found no issues.") }}
            </p>

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                        <th scope="col" class="px-5 py-3 font-medium">{{ ctrans("Issue") }}</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">{{ ctrans("Pages") }}</th>
                        <th scope="col" class="w-28 px-5 py-3 text-right font-medium">{{ ctrans("Since last audit") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="issue in audit.issues" :key="issue.type" class="hover:bg-gray-50">
                        <td class="px-5 py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="size-2.5 shrink-0 rounded-full" :class="severityStyles[issue.severity].dot" :title="severities[issue.severity]" aria-hidden="true" />
                                <span class="sr-only">{{ severities[issue.severity] }}:</span>
                                <Link v-if="issue.pages" :href="issueRoute(issue.type)" class="text-gray-900 underline-offset-2 hover:underline focus-visible:underline">
                                    {{ issue.label }}
                                </Link>
                                <span v-else class="text-gray-500">{{ issue.label }}</span>
                                <button
                                    type="button"
                                    v-tooltip="issue.description"
                                    :aria-label="issue.description"
                                    class="text-gray-400 hover:text-gray-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[--app-accent]">
                                    <FontAwesomeIcon :icon="faInfoCircle" fixed-width aria-hidden="true" />
                                </button>
                            </div>
                        </td>
                        <td class="px-5 py-2.5 text-right tabular-nums text-gray-900">{{ locale.number(issue.pages) }}</td>
                        <td class="px-5 py-2.5 text-right tabular-nums">
                            <span v-if="issueChange(issue)" :class="issueChange(issue)! > 0 ? 'text-red-700' : 'text-green-700'">
                                {{ formatChange(issueChange(issue)!) }}
                            </span>
                            <span v-else class="text-gray-400">-</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </template>
</template>
