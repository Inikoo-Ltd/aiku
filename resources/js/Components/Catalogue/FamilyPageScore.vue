<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faMinus, faArrowRight, faSearch, faPencil } from "@fal"
import { faSync } from "@far"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { routeType } from "@/types/route"

interface Check {
    key: string
    label: string
    score: string
    is_passed: boolean | null
    is_crawl: boolean
}

interface Score {
    key: string
    label: string
    score: number
    passed: number
    total: number
}

const props = defineProps<{
    data: {
        steps: Array<{ key: string, label: string, owner: string, is_done: boolean }>
        score: number
        impact: { level: "high" | "medium" | "low", label: string, note: string }
        passed: number
        total: number
        last_crawl_at: string | null
        scores: Score[]
        areas: Array<{ key: string, label: string, checks: Check[] }>
        crawl_route: routeType | null
        setting_route: routeType
        issues_route: routeType
    }
}>()

const RING_RADIUS = 26
const RING_LENGTH = 2 * Math.PI * RING_RADIUS

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

const scoreTags: Record<string, string> = {
    ai_discoverability: "AI",
    seo: "SEO",
    product_data: "PD",
    content_quality: "CQ",
}

const scoreColor = (score: number) => {
    if (score >= 9) {
        return { stroke: "#34d399", text: "text-green-700" }
    }
    if (score >= 6) {
        return { stroke: "#fbbf24", text: "text-amber-700" }
    }

    return { stroke: "#f87171", text: "text-red-600" }
}

const currentStepIndex = computed(() => props.data.steps.findIndex(step => !step.is_done))

const allChecks = computed(() => props.data.areas.flatMap(area => area.checks))
const sortedChecks = computed(() => {
    const order = (check: Check) => check.is_passed === false ? 0 : check.is_passed === null ? 1 : 2

    return [...allChecks.value].sort((a, b) => order(a) - order(b))
})
const failedChecks = computed(() => allChecks.value.filter(check => check.is_passed === false))
const hasFailedSettingChecks = computed(() => failedChecks.value.some(check => !check.is_crawl))
const hasFailedCrawlChecks = computed(() => failedChecks.value.some(check => check.is_crawl))
const hasUncheckedChecks = computed(() => allChecks.value.some(check => check.is_passed === null))

const labelsOf = (checks: Check[], isPassed: boolean | null) => checks.filter(check => check.is_passed === isPassed).map(check => check.label).join(", ")

const checkClass = (check: Check) => {
    if (check.is_passed === true) {
        return "border-green-200 bg-green-50 text-gray-700"
    }
    if (check.is_passed === false) {
        return "border-red-300 bg-red-50 font-semibold text-gray-800"
    }

    return "border-gray-200 bg-gray-50 text-gray-500"
}

const checkIcon = (check: Check) => {
    if (check.is_passed === true) {
        return { icon: faCheck, class: "text-green-600" }
    }
    if (check.is_passed === false) {
        return { icon: faTimes, class: "text-red-500" }
    }

    return { icon: faMinus, class: "text-gray-400" }
}

const segmentClass = (check: Check) => {
    if (check.is_passed === true) {
        return "bg-green-500"
    }
    if (check.is_passed === false) {
        return "bg-red-400"
    }

    return "bg-gray-200"
}

const isCrawling = ref(false)

const startCrawl = () => {
    if (!props.data.crawl_route) {
        return
    }

    router.post(route(props.data.crawl_route.name, props.data.crawl_route.parameters), {}, {
        preserveScroll: true,
        onStart: () => isCrawling.value = true,
        onSuccess: () => notify({ title: ctrans("Page checked"), text: ctrans("The checks show what the page has now"), type: "success" }),
        onError: (errors) => notify({ title: ctrans("Page not checked"), text: Object.values(errors)[0] ?? ctrans("The page could not be checked"), type: "error" }),
        onFinish: () => isCrawling.value = false,
    })
}
</script>

<template>
    <div class="space-y-4">
        <ol class="grid grid-cols-1 gap-2 rounded-lg border border-gray-200 bg-white p-3 sm:grid-cols-2 xl:grid-cols-4">
            <li
                v-for="(step, index) in data.steps"
                :key="step.key"
                class="flex items-center gap-3 rounded-md border px-3 py-2"
                :class="index === currentStepIndex ? 'border-[--app-accent-muted] bg-[--app-accent-soft]' : 'border-transparent'">
                <span
                    class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                    :class="step.is_done ? 'bg-green-700 text-white' : index === currentStepIndex ? 'bg-[--app-accent] text-[--app-accent-text]' : 'bg-gray-100 text-gray-500'">
                    <FontAwesomeIcon v-if="step.is_done" :icon="faCheck" fixed-width aria-hidden="true" />
                    <template v-else>{{ index + 1 }}</template>
                </span>
                <div class="min-w-0">
                    <div class="text-sm font-medium text-gray-800">
                        {{ step.label }}
                        <FontAwesomeIcon v-if="index === currentStepIndex" :icon="faArrowRight" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                    </div>
                    <div class="text-xs text-gray-500">{{ step.owner }}</div>
                </div>
            </li>
        </ol>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 pt-4 pb-3">
                <div>
                    <h3 class="font-semibold text-gray-700">{{ ctrans("Page score") }}</h3>
                    <p class="text-xs text-gray-500">
                        <template v-if="data.last_crawl_at">{{ ctrans("Last crawl") }}: {{ useFormatTime(data.last_crawl_at, { formatTime: "hm" }) }}</template>
                        <template v-else>{{ ctrans("Not crawled yet") }}</template>
                    </p>
                </div>
                <Button
                    v-if="data.crawl_route"
                    type="tertiary"
                    :label="ctrans('Crawl page')"
                    :icon="faSync"
                    :loading="isCrawling"
                    :tooltip="ctrans('Checks this page now. Broken links and sitemap come from the latest site audit of the website.')"
                    @click="startCrawl" />
            </div>

            <div class="grid grid-cols-2 gap-4 border-t border-gray-100 bg-gray-50 px-4 py-5 sm:grid-cols-3 lg:grid-cols-5">
                <div v-for="score in data.scores" :key="score.key" class="flex flex-col items-center text-center">
                    <svg viewBox="0 0 64 64" class="size-16" role="img" :aria-label="ctrans(':area: :score out of 10', { area: score.label, score: score.score })">
                        <circle cx="32" cy="32" :r="RING_RADIUS" fill="#fff" stroke="#e5e7eb" stroke-width="5" />
                        <circle
                            cx="32" cy="32" :r="RING_RADIUS" fill="none" stroke-width="5" stroke-linecap="round" transform="rotate(-90 32 32)"
                            :stroke="scoreColor(score.score).stroke"
                            :stroke-dasharray="`${RING_LENGTH * score.score / 10} ${RING_LENGTH}`" />
                        <text x="32" y="38" text-anchor="middle" class="text-lg font-bold" :class="scoreColor(score.score).text" fill="currentColor">{{ score.score }}</text>
                    </svg>
                    <div class="mt-2 text-sm font-medium text-gray-700">{{ score.label }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans(":passed of :total checks passed", { passed: score.passed, total: score.total }) }}</div>
                </div>
                <div class="flex flex-col items-center text-center">
                    <div class="flex size-16 items-center justify-center">
                        <span class="flex items-center gap-1.5 whitespace-nowrap rounded-full px-3 py-1 text-sm font-semibold uppercase" :class="impactClasses[data.impact.level].pill">
                            <span class="size-2 rounded-sm" :class="impactClasses[data.impact.level].dot" aria-hidden="true" />
                            {{ data.impact.label }}
                        </span>
                    </div>
                    <div class="mt-2 text-sm font-medium text-gray-700">{{ ctrans("Expected impact") }}</div>
                    <div class="text-xs text-gray-500">{{ data.impact.note }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 border-t border-gray-100 lg:grid-cols-2">
                <div class="flex flex-col gap-4 border-gray-100 p-4 lg:border-r">
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("By area") }}</h4>
                        <p class="mt-1 text-xs text-gray-600">
                            {{ ctrans("The :total checks are grouped into :areas areas. Each block is one check:", { total: data.total, areas: data.areas.length }) }}
                            <span class="font-semibold text-green-600">{{ ctrans("green") }}</span> {{ ctrans("passed") }},
                            <span class="font-semibold text-red-500">{{ ctrans("red") }}</span> {{ ctrans("needs fixing") }}.
                        </p>
                    </div>

                    <div v-for="area in data.areas" :key="area.key">
                        <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                            <span class="flex items-center gap-2 font-semibold text-gray-700">
                                <span class="size-2.5 rounded-sm" :class="areaColors[area.key] ?? 'bg-gray-400'" aria-hidden="true" />
                                {{ area.label }}
                            </span>
                            <span
                                class="text-xs font-semibold"
                                :class="area.checks.every(check => check.is_passed) ? 'text-green-600' : 'text-red-500'">
                                {{ ctrans(":passed of :total passed", { passed: area.checks.filter(check => check.is_passed).length, total: area.checks.length }) }}
                            </span>
                        </div>
                        <div class="flex gap-1">
                            <span
                                v-for="check in area.checks"
                                :key="check.key"
                                v-tooltip="check.label"
                                class="h-2.5 flex-1 rounded-sm"
                                :class="segmentClass(check)" />
                        </div>
                        <p v-if="labelsOf(area.checks, false)" class="mt-1 text-xs font-semibold text-red-500">{{ ctrans("Needs fixing") }}: {{ labelsOf(area.checks, false) }}</p>
                        <p v-if="labelsOf(area.checks, null)" class="mt-1 text-xs text-gray-500">{{ ctrans("Not checked yet") }}: {{ labelsOf(area.checks, null) }}</p>
                        <p v-if="area.checks.every(check => check.is_passed)" class="mt-1 text-xs text-gray-500">{{ ctrans("All passed") }}: {{ labelsOf(area.checks, true) }}</p>
                    </div>

                    <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                        <span class="flex items-center gap-1"><span class="h-2 w-4 rounded-sm bg-green-500" />{{ ctrans("Passed") }}</span>
                        <span class="flex items-center gap-1"><span class="h-2 w-4 rounded-sm bg-red-400" />{{ ctrans("Needs fixing") }}</span>
                        <span v-if="hasUncheckedChecks" class="flex items-center gap-1"><span class="h-2 w-4 rounded-sm bg-gray-200" />{{ ctrans("Not checked yet, needs a crawl") }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-4 p-4">
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("All :count checks", { count: data.total }) }}</h4>
                        <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                            <div
                                v-for="check in sortedChecks"
                                :key="check.key"
                                class="flex items-center gap-1.5 rounded border px-2 py-1 text-xs"
                                :class="checkClass(check)">
                                <FontAwesomeIcon :icon="checkIcon(check).icon" :class="checkIcon(check).class" fixed-width aria-hidden="true" />
                                <span class="min-w-0 flex-1 break-words">{{ check.label }}</span>
                                <span class="shrink-0 rounded bg-white/70 px-1 text-[10px] font-semibold text-gray-500">{{ scoreTags[check.score] }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="failedChecks.length" class="mt-auto rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-gray-700">
                        <span class="font-semibold text-red-600">{{ ctransChoice(":count check needs fixing|:count checks need fixing", failedChecks.length, { count: failedChecks.length }) }}</span>
                        · {{ failedChecks.map(check => check.label).join(", ") }}
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Button v-if="hasFailedSettingChecks" type="tertiary" :label="ctrans('Fix in page setting')" @click="router.visit(route(data.setting_route.name, data.setting_route.parameters))">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faPencil" class="text-red-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <Button v-if="hasFailedCrawlChecks" type="tertiary" :label="ctrans('View issues')" @click="router.visit(route(data.issues_route.name, data.issues_route.parameters))">
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
        </div>
    </div>
</template>
