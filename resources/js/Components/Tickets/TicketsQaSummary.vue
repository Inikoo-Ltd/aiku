<script setup lang="ts">
import { computed, ref } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faShieldCheck, faShield, faForward, faVial, faHourglassHalf, faCheckCircle } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useScrollArrows } from "@/Composables/useScrollArrows"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import ScrollFadeArrow from "@/Components/Utils/ScrollFadeArrow.vue"

const props = defineProps<{
    summary: {
        done: number
        passed: number
        failed: number
        skipped: number
        in_qa: number
        not_checked: number
    }
    periodLabel?: string
}>()

const scroller = ref<HTMLElement | null>(null)
const { canScrollLeft, canScrollRight, scrollBy } = useScrollArrows(scroller)

const page = usePage()
const activeState = computed(() => new URLSearchParams(page.url.split("?")[1] ?? "").get("filter[qa_state]"))
const loadingState = ref<string | null>(null)

const showState = (state: string) => {
    const isActive = activeState.value === state

    router.reload({
        data: {
            "filter[qa_state]": isActive ? undefined : state,
            "elements[qa_status]": isActive ? undefined : "",
            "elements[qa_checker]": isActive ? undefined : "",
            page: 1,
        },
        preserveScroll: true,
        onStart: () => loadingState.value = state,
        onFinish: () => loadingState.value = null,
    })
}

const checked = computed(() => props.summary.passed + props.summary.failed + props.summary.skipped)
const checkedPercentage = computed(() => props.summary.done ? Math.round(checked.value / props.summary.done * 100) : 0)
const shareOfDone = (count: number) => props.summary.done ? `${count / props.summary.done * 100}%` : "0%"

const segments = computed(() => [
    { key: "passed", count: props.summary.passed, class: "bg-green-500", label: ctrans("Passed") },
    { key: "failed", count: props.summary.failed, class: "bg-red-500", label: ctrans("Failed") },
    { key: "skipped", count: props.summary.skipped, class: "bg-gray-400", label: ctrans("Skipped") },
    { key: "in_qa", count: props.summary.in_qa, class: "bg-amber-400", label: ctrans("In QA") },
])

const stats = computed(() => [
    { key: "passed", count: props.summary.passed, label: ctrans("Passed"), icon: faShieldCheck, class: "text-green-600" },
    { key: "failed", count: props.summary.failed, label: ctrans("Failed"), icon: faShield, class: "text-red-500" },
    { key: "skipped", count: props.summary.skipped, label: ctrans("Skipped"), icon: faForward, class: "text-gray-500" },
    { key: "in_qa", count: props.summary.in_qa, label: ctrans("In QA"), icon: faVial, class: "text-amber-500", tooltip: ctrans("QA requested or being checked") },
    { key: "not_checked", count: props.summary.not_checked, label: ctrans("Not QA'd yet"), icon: faHourglassHalf, class: props.summary.not_checked ? "text-red-600" : "text-gray-400", tooltip: ctrans("Done, but no QA requested yet") },
])

const cellClass = (state: string) => activeState.value === state
    ? "bg-[--app-accent]/10 ring-2 ring-inset ring-[--app-accent]"
    : "hover:bg-gray-50"
</script>

<template>
    <div class="relative isolate mx-4 mt-2 overflow-hidden rounded-lg border border-gray-200 bg-white">
        <div ref="scroller" class="overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <div class="flex min-w-max items-stretch divide-x divide-gray-200 text-sm lg:min-w-0">
            <button
                type="button"
                v-tooltip="ctrans('Show every ticket done in this period')"
                class="flex min-w-56 flex-col justify-center gap-1.5 px-4 py-2.5 text-left transition-colors lg:flex-[2]"
                :class="cellClass('done')"
                @click="showState('done')">
                <span class="flex w-full items-baseline justify-between gap-x-3">
                    <span class="font-medium text-gray-700">
                        <LoadingIcon v-if="loadingState === 'done'" class="mr-1" />
                        <FontAwesomeIcon v-else :icon="faCheckCircle" class="mr-1 text-gray-400" fixed-width aria-hidden="true" />
                        {{ ctrans("QA checked") }}
                    </span>
                    <span class="tabular-nums text-gray-500">
                        <span class="font-semibold text-gray-800">{{ checked }}</span> / {{ summary.done }}
                        <span class="ml-1 font-semibold" :class="checkedPercentage >= 80 ? 'text-green-600' : checkedPercentage >= 50 ? 'text-amber-600' : 'text-red-600'">{{ checkedPercentage }}%</span>
                    </span>
                </span>
                <span class="flex h-2 w-full overflow-hidden rounded-full bg-gray-100">
                    <span
                        v-for="segment in segments"
                        :key="segment.key"
                        :class="segment.class"
                        :style="{ width: shareOfDone(segment.count) }" />
                </span>
                <span v-if="periodLabel" class="text-xs text-gray-400">
                    {{ ctrans(":count tickets done", { count: summary.done }) }} · {{ periodLabel }}
                </span>
            </button>

            <button
                v-for="stat in stats"
                :key="stat.key"
                type="button"
                v-tooltip="stat.tooltip"
                class="flex min-w-24 flex-col justify-center px-4 py-2.5 text-left transition-colors lg:flex-1 lg:items-center lg:text-center"
                :class="cellClass(stat.key)"
                @click="showState(stat.key)">
                <span class="text-xs text-gray-500 whitespace-nowrap">
                    <LoadingIcon v-if="loadingState === stat.key" />
                    <FontAwesomeIcon v-else :icon="stat.icon" :class="stat.class" fixed-width aria-hidden="true" />
                    {{ stat.label }}
                </span>
                <span class="text-lg font-semibold tabular-nums lg:text-2xl" :class="stat.count ? 'text-gray-800' : 'text-gray-300'">{{ stat.count }}</span>
            </button>
        </div>
        </div>
        <ScrollFadeArrow direction="left" :visible="canScrollLeft" @click="scrollBy(-1)" />
        <ScrollFadeArrow direction="right" :visible="canScrollRight" @click="scrollBy(1)" />
    </div>
</template>
