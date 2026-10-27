<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faMinus, faSlidersH, faSearch } from "@fal"
import { faSync } from "@far"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import WorkflowSteps, { type WorkflowStep } from "@/Components/DataDisplay/WorkflowSteps.vue"
import ScoreGauge from "@/Components/DataDisplay/ScoreGauge.vue"
import { routeType } from "@/types/route"
import { useWebpageCheck, type LinksCheck } from "@/Composables/useWebpageCheck"

type PageScoreCheck = {
    key: string
    label: string
    category: string
    is_passed: boolean | null
    note: string | null
    is_crawl: boolean
}

export type ProductPageScore = {
    score: number
    passed: number
    total: number
    impact: { level: "high" | "medium" | "low", label: string, note: string }
    scores: { key: string, label: string, short: string, score: number | null, passed: number, total: number }[]
    areas: { key: string, label: string, checks: PageScoreCheck[] }[]
}

export type ProductCompletenessData = {
    workflow: { current: number, label: string, steps: WorkflowStep[] } | null
    page_score: ProductPageScore | null
    setting_route: routeType
    issues_route: routeType
    crawl_route: routeType | null
    last_crawl_at: string | null
    links_check: LinksCheck | null
}

const props = defineProps<{
    completeness: ProductCompletenessData
}>()

const areaColors: Record<string, string> = {
    content: "bg-violet-500",
    structure: "bg-sky-500",
    links: "bg-amber-500",
    indexing: "bg-teal-500",
    performance: "bg-pink-500",
}

const impactClasses: Record<string, { pill: string, dot: string }> = {
    high: { pill: "bg-red-50 text-red-700", dot: "bg-red-600" },
    medium: { pill: "bg-amber-50 text-amber-700", dot: "bg-amber-500" },
    low: { pill: "bg-green-50 text-green-700", dot: "bg-green-600" },
}

const pageScore = computed(() => props.completeness.page_score)

const shortOf = computed<Record<string, string>>(() => Object.fromEntries((pageScore.value?.scores ?? []).map((score) => [score.key, score.short])))

const allChecks = computed(() => pageScore.value?.areas.flatMap((area) => area.checks) ?? [])
const sortedChecks = computed(() => {
    const order = (check: PageScoreCheck) => (check.is_passed === false ? 0 : check.is_passed === null ? 1 : 2)

    return [...allChecks.value].sort((a, b) => order(a) - order(b))
})
const failedChecks = computed(() => allChecks.value.filter((check) => check.is_passed === false))
const hasFailedSettingChecks = computed(() => failedChecks.value.some((check) => !check.is_crawl))
const hasFailedCrawlChecks = computed(() => failedChecks.value.some((check) => check.is_crawl))
const hasUncheckedChecks = computed(() => allChecks.value.some((check) => check.is_passed === null))

const labelsOf = (checks: PageScoreCheck[], isPassed: boolean | null) => checks.filter((check) => check.is_passed === isPassed).map((check) => check.label).join(", ")

const checkClass = (check: PageScoreCheck) => {
    if (check.is_passed === true) {
        return "border-green-200 bg-green-50 text-gray-700"
    }
    if (check.is_passed === false) {
        return "border-red-300 bg-red-50 font-semibold text-gray-800"
    }

    return "border-gray-200 bg-gray-50 text-gray-500"
}

const checkIcon = (check: PageScoreCheck) => {
    if (check.is_passed === true) {
        return { icon: faCheck, class: "text-green-600" }
    }
    if (check.is_passed === false) {
        return { icon: faTimes, class: "text-red-500" }
    }

    return { icon: faMinus, class: "text-gray-400" }
}

const segmentClass = (check: PageScoreCheck) => {
    if (check.is_passed === true) {
        return "bg-green-500"
    }
    if (check.is_passed === false) {
        return "bg-red-400"
    }

    return "bg-gray-200"
}

const { isCrawling, isCheckingLinks, startCrawl } = useWebpageCheck(() => props.completeness.crawl_route, () => props.completeness.links_check)

const isOpeningSetting = ref(false)

const openSetting = () => router.visit(route(props.completeness.setting_route.name, props.completeness.setting_route.parameters), {
    onStart: () => (isOpeningSetting.value = true),
    onFinish: () => (isOpeningSetting.value = false),
})
</script>

<template>
    <div class="space-y-3">
        <WorkflowSteps v-if="completeness.workflow" :steps="completeness.workflow.steps" :current="completeness.workflow.current" />

        <section v-if="pageScore" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">{{ ctrans("Page score") }}</h3>
                    <p class="text-xs text-gray-500">
                        <template v-if="completeness.last_crawl_at">{{ ctrans("Last crawl") }}: {{ useFormatTime(completeness.last_crawl_at, { formatTime: "hm" }) }}</template>
                        <template v-else>{{ ctrans("Not crawled yet") }}</template>
                    </p>
                </div>
                <Button
                    v-if="completeness.crawl_route"
                    type="tertiary"
                    :label="isCheckingLinks ? ctrans('Checking links') : ctrans('Crawl page')"
                    :loading="isCrawling || isCheckingLinks"
                    :tooltip="ctrans('Checks this page now, with its robots.txt and every link on it. The sitemap comes from the latest site audit of the website.')"
                    @click="startCrawl">
                    <template #icon>
                        <FontAwesomeIcon :icon="faSync" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </header>

            <div class="grid grid-cols-2 gap-x-4 gap-y-6 border-b border-gray-100 px-4 py-6 sm:grid-cols-3 lg:grid-cols-5">
                <ScoreGauge
                    v-for="score in pageScore.scores"
                    :key="score.key"
                    :value="score.score"
                    :label="score.label"
                    :caption="score.score === null ? ctrans('Not measured') : ctrans(':passed of :total checks passed', { passed: String(score.passed), total: String(score.total) })" />
                <div class="flex flex-col items-center text-center">
                    <div class="flex h-16 items-center">
                        <span class="flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold uppercase" :class="impactClasses[pageScore.impact.level].pill">
                            <span class="size-2 rounded-sm" :class="impactClasses[pageScore.impact.level].dot" aria-hidden="true" />
                            {{ pageScore.impact.label }}
                        </span>
                    </div>
                    <span class="mt-2 text-sm font-medium leading-tight text-gray-800">{{ ctrans("Expected impact") }}</span>
                    <span class="mt-1 text-xs text-gray-500">{{ pageScore.impact.note }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2">
                <div class="flex flex-col gap-4 border-gray-100 p-4 lg:border-r">
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("By area") }}</h4>
                        <p class="mt-1 text-xs text-gray-600">
                            {{ ctrans("The :total checks are grouped into :areas areas. Each block is one check:", { total: String(pageScore.total), areas: String(pageScore.areas.length) }) }}
                            <span class="font-semibold text-green-600">{{ ctrans("green") }}</span> {{ ctrans("passed") }},
                            <span class="font-semibold text-red-500">{{ ctrans("red") }}</span> {{ ctrans("needs fixing") }}.
                        </p>
                    </div>

                    <div v-for="area in pageScore.areas" :key="area.key">
                        <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                            <span class="flex items-center gap-2 font-semibold text-gray-700">
                                <span class="size-2.5 rounded-sm" :class="areaColors[area.key] ?? 'bg-gray-400'" aria-hidden="true" />
                                {{ area.label }}
                            </span>
                            <span class="text-xs font-semibold" :class="area.checks.every((check) => check.is_passed) ? 'text-green-600' : 'text-red-500'">
                                {{ ctrans(":passed of :total passed", { passed: String(area.checks.filter((check) => check.is_passed).length), total: String(area.checks.length) }) }}
                            </span>
                        </div>
                        <div class="flex gap-1">
                            <span v-for="check in area.checks" :key="check.key" v-tooltip="check.label" class="h-2.5 flex-1 rounded-sm" :class="segmentClass(check)" />
                        </div>
                        <p v-if="labelsOf(area.checks, false)" class="mt-1 text-xs font-semibold text-red-500">{{ ctrans("Needs fixing") }}: {{ labelsOf(area.checks, false) }}</p>
                        <p v-for="check in area.checks.filter((check) => check.note)" :key="`note-${check.key}`" class="mt-0.5 text-xs text-gray-600">
                            <span class="font-semibold">{{ check.label }}:</span> {{ check.note }}
                        </p>
                        <p v-if="labelsOf(area.checks, null)" class="mt-1 text-xs text-gray-500">{{ ctrans("Not checked yet") }}: {{ labelsOf(area.checks, null) }}</p>
                        <p v-if="area.checks.every((check) => check.is_passed)" class="mt-1 text-xs text-gray-500">{{ ctrans("All passed") }}: {{ labelsOf(area.checks, true) }}</p>
                    </div>

                    <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                        <span class="flex items-center gap-1"><span class="h-2 w-4 rounded-sm bg-green-500" />{{ ctrans("Passed") }}</span>
                        <span class="flex items-center gap-1"><span class="h-2 w-4 rounded-sm bg-red-400" />{{ ctrans("Needs fixing") }}</span>
                        <span v-if="hasUncheckedChecks" class="flex items-center gap-1"><span class="h-2 w-4 rounded-sm bg-gray-200" />{{ ctrans("Not checked yet, needs a crawl") }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-4 p-4">
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("All :count checks", { count: String(pageScore.total) }) }}</h4>
                        <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                            <div v-for="check in sortedChecks" :key="check.key" v-tooltip="check.note" class="flex items-center gap-1.5 rounded border px-2 py-1 text-xs" :class="checkClass(check)">
                                <FontAwesomeIcon :icon="checkIcon(check).icon" :class="checkIcon(check).class" fixed-width aria-hidden="true" />
                                <span class="min-w-0 flex-1 break-words">{{ check.label }}</span>
                                <span class="shrink-0 text-[10px] font-medium text-gray-500">{{ shortOf[check.category] }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="failedChecks.length" class="mt-auto rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-gray-700">
                        <span class="font-semibold text-red-600">{{ ctransChoice(":count check needs fixing|:count checks need fixing", failedChecks.length, { count: String(failedChecks.length) }) }}</span>
                        · {{ failedChecks.map((check) => check.label).join(", ") }}
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Button v-if="hasFailedSettingChecks" type="tertiary" :label="ctrans('Edit in Product setting')" :loading="isOpeningSetting" @click="openSetting">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faSlidersH" class="text-red-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <Button v-if="hasFailedCrawlChecks" type="tertiary" :label="ctrans('View issues')" @click="router.visit(route(completeness.issues_route.name, completeness.issues_route.parameters))">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faSearch" class="text-red-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </div>
                    </div>
                    <div v-else-if="!hasUncheckedChecks" class="mt-auto rounded-lg border border-green-200 bg-green-50 p-3 text-sm font-semibold text-green-700">
                        {{ ctrans("All checks passed") }}
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
