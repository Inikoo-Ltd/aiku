<script setup lang="ts">
import { computed, ref } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCircle, faSpinner, faCheckCircle, faBan, faTasks } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useScrollArrows } from "@/Composables/useScrollArrows"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import ScrollFadeArrow from "@/Components/Utils/ScrollFadeArrow.vue"

const props = defineProps<{
    summary: {
        todo: number
        in_progress: number
        done: number
        cancelled: number
    }
}>()

const ALL_STATUSES = "todo,in_progress,done,cancelled"

const scroller = ref<HTMLElement | null>(null)
const { canScrollLeft, canScrollRight, scrollBy } = useScrollArrows(scroller)

const page = usePage()
const activeStatus = computed(() => new URLSearchParams(page.url.split("?")[1] ?? "").get("elements[status]"))
const loadingStatus = ref<string | null>(null)

const showStatus = (status: string) => {
    const isActive = activeStatus.value === status

    router.reload({
        data: { "elements[status]": isActive ? undefined : status, page: 1 },
        preserveScroll: true,
        onStart: () => (loadingStatus.value = status),
        onFinish: () => (loadingStatus.value = null),
    })
}

const toDo = computed(() => props.summary.todo + props.summary.in_progress + props.summary.done)
const donePercentage = computed(() => toDo.value ? Math.round(props.summary.done / toDo.value * 100) : 0)
const shareOfToDo = (count: number) => toDo.value ? `${count / toDo.value * 100}%` : "0%"

const segments = computed(() => [
    { key: "done", count: props.summary.done, class: "bg-green-500" },
    { key: "in_progress", count: props.summary.in_progress, class: "bg-blue-400" },
])

const stats = computed(() => [
    { key: "todo", count: props.summary.todo, label: ctrans("Todo"), icon: faCircle, class: "text-gray-500" },
    { key: "in_progress", count: props.summary.in_progress, label: ctrans("Working on it"), icon: faSpinner, class: "text-blue-500" },
    { key: "done", count: props.summary.done, label: ctrans("Done"), icon: faCheckCircle, class: "text-green-600" },
    { key: "cancelled", count: props.summary.cancelled, label: ctrans("Can't be done"), icon: faBan, class: "text-red-500", tooltip: ctrans("Not counted in the done percentage") },
])

const cellClass = (status: string) => activeStatus.value === status
    ? "bg-[--app-accent]/10 ring-2 ring-inset ring-[--app-accent]"
    : "hover:bg-gray-50"
</script>

<template>
    <div class="relative isolate mx-4 mt-2 overflow-hidden rounded-lg border border-gray-200 bg-white">
        <div ref="scroller" class="overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <div class="flex min-w-max items-stretch divide-x divide-gray-200 text-sm lg:min-w-0">
                <button
                    type="button"
                    v-tooltip="ctrans('Show every task')"
                    class="flex min-w-56 flex-col justify-center gap-1.5 px-4 py-2.5 text-left transition-colors lg:flex-[2]"
                    :class="cellClass(ALL_STATUSES)"
                    @click="showStatus(ALL_STATUSES)">
                    <span class="flex w-full items-baseline justify-between gap-x-3">
                        <span class="font-medium text-gray-700">
                            <LoadingIcon v-if="loadingStatus === ALL_STATUSES" class="mr-1" />
                            <FontAwesomeIcon v-else :icon="faTasks" class="mr-1 text-gray-400" fixed-width aria-hidden="true" />
                            {{ ctrans("Done") }}
                        </span>
                        <span class="tabular-nums text-gray-500">
                            <span class="font-semibold text-gray-800">{{ summary.done }}</span> / {{ toDo }}
                            <span class="ml-1 font-semibold" :class="donePercentage >= 80 ? 'text-green-600' : donePercentage >= 50 ? 'text-amber-600' : 'text-red-600'">{{ donePercentage }}%</span>
                        </span>
                    </span>
                    <span class="flex h-2 w-full overflow-hidden rounded-full bg-gray-100">
                        <span
                            v-for="segment in segments"
                            :key="segment.key"
                            :class="segment.class"
                            :style="{ width: shareOfToDo(segment.count) }" />
                    </span>
                </button>

                <button
                    v-for="stat in stats"
                    :key="stat.key"
                    type="button"
                    v-tooltip="stat.tooltip"
                    class="flex min-w-24 flex-col justify-center px-4 py-2.5 text-left transition-colors lg:flex-1 lg:items-center lg:text-center"
                    :class="cellClass(stat.key)"
                    @click="showStatus(stat.key)">
                    <span class="whitespace-nowrap text-xs text-gray-500">
                        <LoadingIcon v-if="loadingStatus === stat.key" />
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
