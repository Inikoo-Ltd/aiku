<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { dueRelativeLabel, dueUrgency, dueUrgencyClasses } from "@/Composables/useDueUrgency"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCalendar } from "@fal"
library.add(faCalendar)

const props = withDefaults(defineProps<{
    dueAt: string
    priority: string
    status: string
    clickable?: boolean
    hint?: string | null
}>(), { clickable: false, hint: null })

const emit = defineEmits<{
    click: []
}>()

const isOpen = computed(() => ["todo", "in_progress"].includes(props.status))
const urgency = computed(() => dueUrgency(props.dueAt, props.priority, isOpen.value))

const tooltip = computed(() => {
    const due = urgency.value === "overdue"
        ? ctrans("Overdue, was due :date", { date: useFormatTime(props.dueAt) })
        : ctrans("Due :date", { date: useFormatTime(props.dueAt) })

    return props.hint ? `${due} · ${props.hint}` : due
})
</script>

<template>
    <component
        :is="clickable ? 'button' : 'span'"
        :type="clickable ? 'button' : undefined"
        v-tooltip="tooltip"
        class="inline-flex shrink-0 items-center gap-x-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs ring-1 ring-inset"
        :class="[dueUrgencyClasses[urgency], clickable ? 'transition duration-200 hover:brightness-95 active:brightness-90' : 'cursor-default']"
        @click.stop="clickable && emit('click')">
        <FontAwesomeIcon icon="fal fa-calendar" fixed-width aria-hidden="true" />
        <span class="font-medium">{{ useFormatTime(dueAt, { formatTime: "d MMM" }) }}</span>
        <span v-if="isOpen" class="opacity-80">· {{ dueRelativeLabel(dueAt) }}</span>
    </component>
</template>
