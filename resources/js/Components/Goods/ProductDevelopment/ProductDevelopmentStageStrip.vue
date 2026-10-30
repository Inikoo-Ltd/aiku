<!--
  - Author Louis Perez
  - Created on 16-10-2026-00h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import Popover from "primevue/popover"
import DatePicker from "primevue/datepicker"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil } from "@fal"
import { faSave } from "@fas"
library.add(faPencil, faSave)
import { ctrans } from "@/Composables/useTrans"
import { useScrollArrows } from "@/Composables/useScrollArrows"
import ScrollFadeArrow from "@/Components/Utils/ScrollFadeArrow.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

interface Segment {
    id?: number
    key: string
    number: string
    label: string
    state: string
    is_late: boolean
    due_at: string | null
    progress: { done: number, total: number, missing: number } | null
}

const props = defineProps<{
    segments: Segment[]
    canEditDates?: boolean
}>()

const datePopover = ref()
const editing = ref<Segment | null>(null)
const editDate = ref<Date | null>(null)
const savingDate = ref(false)

function openDateEditor(event: Event, segment: Segment) {
    editing.value = segment
    editDate.value = segment.due_at ? new Date(segment.due_at) : null
    datePopover.value.toggle(event)
}

const toIso = (date: Date | null) => date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}` : null

function saveDate() {
    if (!editing.value?.id) {
        return
    }
    router.patch(route("grp.models.product_development.stage.due_date", { productDevelopmentStage: editing.value.id }), { due_at: toIso(editDate.value) }, {
        preserveScroll: true,
        onStart: () => savingDate.value = true,
        onFinish: () => savingDate.value = false,
        onSuccess: () => datePopover.value.hide(),
    })
}

const scroller = ref<HTMLElement | null>(null)
const { canScrollLeft, canScrollRight, scrollBy } = useScrollArrows(scroller)

const isReady = (segment: Segment) => segment.state === "in_progress" && !!segment.progress?.total && segment.progress.missing === 0

const barClass = (segment: Segment) => {
    if (segment.state === "completed" || segment.state === "skipped" || isReady(segment)) {
        return "bg-green-500"
    }
    if (segment.state === "in_progress") {
        return segment.is_late ? "bg-red-500" : "bg-amber-500"
    }

    return "bg-gray-200"
}

const statusLine = (segment: Segment) => {
    if (segment.key === "gate") {
        return segment.state === "completed" ? ctrans("Passed") : ctrans("Locked")
    }
    if (segment.state === "completed") {
        return ctrans("Done")
    }
    if (segment.state === "skipped") {
        return ctrans("Skipped")
    }
    if (segment.state === "in_progress" && segment.progress?.total) {
        return ctrans(":done of :total", { done: String(segment.progress.done), total: String(segment.progress.total) })
    }
    if (segment.due_at && !(props.canEditDates && segment.id)) {
        return useFormatTime(segment.due_at, { formatTime: "d MMM" })
    }
    if (segment.state === "in_progress") {
        return ctrans("In progress")
    }

    return ""
}

const statusClass = (segment: Segment) => {
    if (segment.state === "completed" || isReady(segment)) {
        return "text-green-700"
    }
    if (segment.state === "in_progress") {
        return segment.is_late ? "text-red-600" : "text-amber-700"
    }

    return "text-gray-400"
}
</script>

<template>
    <div class="relative isolate border-b border-gray-200">
    <div ref="scroller" class="overflow-x-auto px-4 py-3 [scrollbar-width:none] md:px-6 [&::-webkit-scrollbar]:hidden">
        <ol class="grid min-w-[56rem] grid-cols-10 gap-2">
            <li v-for="segment in segments" :key="segment.key" class="min-w-0">
                <div class="h-1 rounded-full" :class="barClass(segment)" />
                <div class="mt-2 text-xs tabular-nums text-gray-400">{{ segment.number }}</div>
                <div class="truncate text-sm" :class="segment.state === 'pending' || segment.state === 'locked' ? 'text-gray-500' : 'font-medium text-gray-900'" v-tooltip="segment.label">
                    {{ segment.label }}
                </div>
                <div v-if="!(canEditDates && segment.id && segment.state === 'pending')" class="text-xs tabular-nums" :class="statusClass(segment)">{{ statusLine(segment) }}</div>
                <button
                    v-if="canEditDates && segment.id && segment.state !== 'completed' && segment.state !== 'skipped'"
                    type="button"
                    class="mt-0.5 flex items-center gap-1 rounded text-xs tabular-nums text-[--app-accent] hover:text-[--app-accent-strong]"
                    @click="openDateEditor($event, segment)"
                >
                    <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                    {{ segment.due_at ? ctrans("Due :date", { date: useFormatTime(segment.due_at, { formatTime: "d MMM" }) }) : ctrans("Set due date") }}
                </button>
            </li>
        </ol>
    </div>
    <Popover ref="datePopover">
        <div class="w-56 space-y-2 text-sm">
            <div class="text-xs text-gray-500">{{ ctrans("Due date of :stage", { stage: editing?.label ?? "" }) }}</div>
            <DatePicker v-model="editDate" size="small" dateFormat="dd M yy" showIcon iconDisplay="input" showButtonBar class="w-full" panelClass="text-sm" />
            <div class="flex justify-end">
                <Button type="primary" size="xs" icon="fas fa-save" :label="ctrans('Save')" :loading="savingDate" @click="saveDate" />
            </div>
        </div>
    </Popover>
    <ScrollFadeArrow direction="left" :visible="canScrollLeft" @click="scrollBy(-1)" />
    <ScrollFadeArrow direction="right" :visible="canScrollRight" @click="scrollBy(1)" />
    </div>
</template>
