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
import SeoExportButton from "@/Components/Seo/SeoExportButton.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime, useRangeFromNow } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"

library.add(faInfoCircle)

type Severity = "error" | "warning" | "notice"

type AuditSummary = {
    id: number
    end_at: string
    finish_reason: string | null
    health_score: number
    pages: number
    urls_found: number
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
    stopped: { end_at: string, finish_reason: string | null, urls_processed: number, urls_found: number } | null
    latest: AuditSummary | null
    previous: AuditSummary | null
    history: Array<{ end_at: string, health_score: number, errors: number, warnings: number, notices: number }>
    issues: IssueSummary[]
    reviews: Array<{ id: number, url: string, title: string | null, score: number | null, summary: string, findings: Array<{ priority: string, area: string, finding: string, fix: string }> }>
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

const routeParams = route().params as Record<string, string>

const severityDots: Record<Severity, string> = {
    error: "bg-red-500",
    warning: "bg-amber-500",
    notice: "bg-gray-400",
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

const pagesRoute = computed(() => {
    const params = route().params as Record<string, string>

    return route("grp.org.shops.show.seo.site_audit.pages", [params.organisation, params.shop])
})

const issueRoute = (issueType: string) => {
    const params = route().params as Record<string, string>

    return route("grp.org.shops.show.seo.site_audit.issue", [params.organisation, params.shop, issueType])
}

const issueChange = (issue: IssueSummary) => issue.previous_pages === null ? null : issue.pages - issue.previous_pages

const hasPreviousAudit = computed(() => props.audit.previous !== null)

const severityTotal = (severity: Severity) => {
    const latest = props.audit.latest

    if (!latest) {
        return 0
    }

    return { error: latest.errors, warning: latest.warnings, notice: latest.notices }[severity]
}

const issueSections = computed(() => (["error", "warning", "notice"] as Severity[]).map((severity) => ({
    severity,
    label: props.severities[severity],
    total: severityTotal(severity),
    issues: props.audit.issues.filter((issue) => issue.severity === severity && (issue.pages > 0 || issue.previous_pages)),
})))

const shareOfPages = (pages: number) => {
    const checkedPages = props.audit.latest?.pages ?? 0

    return checkedPages > 0 ? Math.round(pages / checkedPages * 1000) / 10 : 0
}

const coverageText = computed(() => {
    const latest = props.audit.latest

    if (!latest) {
        return ""
    }

    return ctrans(":checked of :found URLs checked", { checked: locale.number(latest.pages), found: locale.number(Math.max(latest.urls_found, latest.pages)) })
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

const reasonText = (reason: string | null | undefined) => {
    if (reason === "max_pages_reached") {
        return ctrans("Stopped at the page limit, so some pages were not checked.")
    }

    if (reason === "interrupted" || reason === "stale") {
        return ctrans("Stopped before the end, so some pages were not checked.")
    }

    if (reason === "failed") {
        return ctrans("Stopped by an error, so some pages were not checked.")
    }

    if (reason === "blocked_by_robots") {
        return ctrans("The robots.txt of the website does not let the audit read any page.")
    }

    return ""
}

const finishReasonText = computed(() => reasonText(props.audit.latest?.finish_reason))
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <SeoExportButton
                v-if="audit.latest"
                routeName="grp.org.shops.show.seo.site_audit.export"
                :routeParameters="[routeParams.organisation, routeParams.shop]"
                :tooltip="ctrans('Download the latest audit as an Excel file: its numbers, the issue types, and every page with every issue found on it')" />
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
        <span v-if="audit.running.state === 'ready'">{{ ctrans("Waiting for other audits to finish. A few audits run at a time, so the websites stay fast for customers.") }}</span>
        <span v-else class="tabular-nums">
            {{ ctrans(":checked pages checked, :found found so far", { checked: locale.number(audit.running.urls_processed), found: locale.number(audit.running.urls_found) }) }}
        </span>
    </div>

    <div
        v-else-if="audit.stopped"
        role="status"
        class="mx-4 mt-4 flex flex-wrap items-baseline gap-x-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <span class="font-medium">{{ ctrans("The audit of :date has no report", { date: useRangeFromNow(audit.stopped.end_at, { addSuffix: true }) }) }}</span>
        <span>{{ reasonText(audit.stopped.finish_reason) }}</span>
        <span class="tabular-nums">{{ ctrans(":checked of :found pages checked.", { checked: locale.number(audit.stopped.urls_processed), found: locale.number(audit.stopped.urls_found) }) }}</span>
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
            :aria-label="ctrans('Latest audit')"
            class="mx-4 mt-4 rounded-xl bg-white ring-1 ring-gray-200">
            <div class="flex flex-wrap items-end gap-x-10 gap-y-4 px-5 py-4">
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Site health") }}</div>
                    <div class="mt-0.5 flex items-baseline gap-x-2">
                        <span class="text-3xl font-semibold tabular-nums tracking-tight text-gray-900">{{ locale.number(audit.latest.health_score) }}%</span>
                        <span v-if="healthChange" class="text-sm tabular-nums" :class="healthChange > 0 ? 'text-green-700' : 'text-red-700'">
                            {{ formatChange(healthChange) }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ ctrans(":clean of :pages pages have no errors", { clean: locale.number(audit.latest.pages - audit.latest.pages_with_errors), pages: locale.number(audit.latest.pages) }) }}
                    </div>
                </div>

                <dl class="flex gap-x-8">
                    <div v-for="section in issueSections" :key="section.severity">
                        <dt class="flex items-center gap-1.5 text-xs text-gray-500">
                            <span class="size-2 rounded-full" :class="severityDots[section.severity]" aria-hidden="true" />
                            {{ section.label }}
                        </dt>
                        <dd class="mt-0.5 text-xl font-medium tabular-nums text-gray-900">{{ locale.number(section.total) }}</dd>
                    </div>
                </dl>

                <div class="ml-auto text-right text-xs text-gray-500">
                    <div class="font-medium text-gray-900">{{ website.domain }}</div>
                    <div>{{ ctrans("Finished :date", { date: useRangeFromNow(audit.latest.end_at, { addSuffix: true }) }) }}</div>
                    <div :class="{ 'text-amber-700': finishReasonText }">
                        <Link :href="pagesRoute" class="underline underline-offset-2">{{ coverageText }}</Link>
                    </div>
                    <div v-if="finishReasonText" class="text-amber-700">{{ finishReasonText }}</div>
                </div>
            </div>

            <div v-if="showTrend" class="border-t border-gray-100 px-5 py-4">
                <h3 class="text-xs font-medium text-gray-700">{{ ctrans("Site health per audit") }}</h3>
                <div class="mt-2 h-36" role="img" :aria-label="ctrans('Site health of the last :count audits', { count: audit.history.length })">
                    <Chart type="line" :data="trendData" :options="trendOptions" class="h-full" />
                </div>
            </div>
        </section>

        <section
            v-if="audit.reviews?.length"
            aria-labelledby="ai-reviews"
            class="mx-4 mt-4 rounded-xl bg-white ring-1 ring-gray-200">
            <div class="border-b border-gray-100 px-5 py-3">
                <h2 id="ai-reviews" class="text-sm font-medium text-gray-900">{{ ctrans("Review of the most visited pages") }}</h2>
                <p class="text-xs text-gray-500">{{ ctrans("The home page and the pages with the most Google clicks, read by AI after the audit. Weakest first.") }}</p>
            </div>
            <ul class="divide-y divide-gray-100">
                <li v-for="review in audit.reviews" :key="review.id" class="px-5 py-3 text-sm">
                    <details>
                        <summary class="flex cursor-pointer items-baseline gap-x-3">
                            <span class="w-10 shrink-0 text-right font-medium tabular-nums" :class="(review.score ?? 0) < 50 ? 'text-red-700' : (review.score ?? 0) < 75 ? 'text-amber-700' : 'text-green-700'">{{ review.score }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-gray-900">{{ review.title || review.url }}</span>
                                <span class="block text-gray-600">{{ review.summary }}</span>
                            </span>
                        </summary>
                        <ol class="mt-2 space-y-2 pl-13">
                            <li v-for="(finding, index) in review.findings" :key="index">
                                <div class="text-gray-900"><span class="text-xs uppercase text-gray-500">{{ finding.priority }} · {{ finding.area }}</span> {{ finding.finding }}</div>
                                <div v-if="finding.fix" class="text-gray-600">{{ finding.fix }}</div>
                            </li>
                        </ol>
                        <a :href="review.url" target="_blank" rel="noopener noreferrer" class="ml-10 mt-2 inline-block text-xs underline underline-offset-2">{{ review.url }}</a>
                    </details>
                </li>
            </ul>
        </section>

        <p v-if="!audit.issues.length" class="mx-4 mt-4 rounded-xl bg-white px-5 py-5 text-sm text-gray-600 ring-1 ring-gray-200">
            {{ ctrans("The latest audit found no issues.") }}
        </p>

        <div v-else class="mb-4">
            <section
                v-for="section in issueSections"
                :key="section.severity"
                :aria-labelledby="`issues-${section.severity}`"
                class="mx-4 mt-4 rounded-xl bg-white ring-1 ring-gray-200">
                <div class="flex items-baseline gap-2 border-b border-gray-100 px-5 py-3">
                    <span class="size-2 shrink-0 self-center rounded-full" :class="severityDots[section.severity]" aria-hidden="true" />
                    <h2 :id="`issues-${section.severity}`" class="text-sm font-medium text-gray-900">{{ section.label }}</h2>
                    <span class="text-xs text-gray-500">{{ ctrans(":count issues", { count: locale.number(section.total) }) }}</span>
                </div>

                <p v-if="!section.issues.length" class="px-5 py-3 text-sm text-gray-500">
                    {{ ctrans("None found in this audit.") }}
                </p>

                <ul v-else class="divide-y divide-gray-100">
                    <li
                        v-for="issue in section.issues"
                        :key="issue.type"
                        class="flex items-center gap-x-4 px-5 py-2.5 text-sm">
                        <span class="w-12 shrink-0 text-right font-medium tabular-nums text-gray-900">{{ locale.number(issue.pages) }}</span>

                        <div class="flex min-w-0 flex-1 items-center gap-1.5">
                            <Link
                                v-if="issue.pages"
                                :href="issueRoute(issue.type)"
                                class="truncate text-gray-900 underline-offset-2 hover:underline focus-visible:underline">
                                {{ issue.label }}
                            </Link>
                            <span v-else class="truncate text-gray-500">{{ issue.label }}</span>
                            <button
                                type="button"
                                v-tooltip="issue.description"
                                :aria-label="issue.description"
                                class="shrink-0 text-gray-400 hover:text-gray-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--app-accent)]">
                                <FontAwesomeIcon :icon="faInfoCircle" fixed-width aria-hidden="true" />
                            </button>
                        </div>

                        <span class="hidden shrink-0 text-xs tabular-nums text-gray-500 sm:inline">
                            {{ ctrans(":share% of pages", { share: locale.number(shareOfPages(issue.pages)) }) }}
                        </span>

                        <span v-if="hasPreviousAudit" class="w-36 shrink-0 text-right text-xs tabular-nums">
                            <span v-if="issueChange(issue)" :class="issueChange(issue)! > 0 ? 'text-red-700' : 'text-green-700'">
                                {{ ctrans(":change since last audit", { change: formatChange(issueChange(issue)!) }) }}
                            </span>
                            <span v-else class="text-gray-400">{{ ctrans("No change") }}</span>
                        </span>
                    </li>
                </ul>
            </section>
        </div>
    </template>
</template>
