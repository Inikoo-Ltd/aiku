<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPhone, faVideo, faDesktop, faArrowDown, faArrowUp, faUser, faHashtag } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@/Common/Components/Image.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useStaffCalls } from "@/Stores/staff-calls"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faPhone, faVideo, faDesktop, faArrowDown, faArrowUp, faUser, faHashtag)

type CallRow = {
    ulid: string
    direction: "incoming" | "outgoing"
    who: string
    avatar: Record<string, string> | null
    started_by: string | null
    kind: "voice" | "video" | "screen"
    status: "live" | "answered" | "missed" | "no_answer" | "declined" | "cancelled"
    started_at: string
    duration_seconds: number | null
    conversation_ulid: string | null
    can_call_back: boolean
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    missedOnly: boolean
    calls: object
}>()

const calls = useStaffCalls()

const showModel = computed({
    get: () => (props.missedOnly ? "missed" : "all"),
    set: (value: string) => router.get(route("grp.chat.staff.calls.index"), value === "missed" ? { calls_filter: { missed: 1 } } : {}, { preserveState: true }),
})
const showOptions = [
    { label: ctrans("All"), value: "all" },
    { label: ctrans("Missed"), value: "missed", tone: "red" as const },
]

const statusLabel: Record<CallRow["status"], string> = {
    live: ctrans("On now"),
    answered: ctrans("Answered"),
    missed: ctrans("Missed"),
    no_answer: ctrans("No answer"),
    declined: ctrans("Declined"),
    cancelled: ctrans("Cancelled"),
}
const isBad = (row: CallRow) => ["missed", "no_answer", "declined"].includes(row.status)
const kindIcon = { voice: "fal fa-phone", video: "fal fa-video", screen: "fal fa-desktop" }
const kindLabel = { voice: ctrans("Voice"), video: ctrans("Video"), screen: ctrans("Screen") }

const duration = (seconds: number | null) => {
    if (!seconds) {
        return "—"
    }
    const minutes = Math.floor(seconds / 60)
    return minutes >= 60 ? `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, "0")}:${String(seconds % 60).padStart(2, "0")}` : `${minutes}:${String(seconds % 60).padStart(2, "0")}`
}
</script>

<template>
    <div class="min-h-full bg-[var(--vx-soft,#f5f3f5)]">
        <Head :title="capitalize(title)" />
        <div class="verba-calls-heading bg-[var(--vx-sidebar,#3f0e40)] text-[var(--vx-sidebar-strong,#ffffff)]">
            <PageHeading :data="pageHead" />
        </div>

        <div class="space-y-3 px-4 pb-8 pt-4">
            <SegmentedToggle v-model="showModel" :options="showOptions" :aria-label="ctrans('Show')" />

            <Table :resource="calls" name="calls">
                <template #cell(status)="{ item }">
                    <span class="inline-flex items-center gap-x-1.5 whitespace-nowrap text-xs" :class="isBad(item) ? 'text-red-600' : item.status === 'live' ? 'text-amber-600' : 'text-gray-500'">
                        <FontAwesomeIcon :icon="item.direction === 'incoming' ? 'fal fa-arrow-down' : 'fal fa-arrow-up'" transform="rotate-45" fixed-width :title="item.direction === 'incoming' ? ctrans('Called you') : ctrans('You called')" />
                        {{ statusLabel[item.status as CallRow["status"]] }}
                    </span>
                </template>
                <template #cell(who)="{ item }">
                    <div class="flex items-center gap-x-2">
                        <div class="relative h-7 w-7 shrink-0 overflow-hidden rounded-full bg-gray-100">
                            <Image v-if="item.avatar" :src="item.avatar" :alt="item.who" image-cover />
                            <FontAwesomeIcon v-else icon="fal fa-user" class="flex h-full items-center justify-center text-gray-400" fixed-width aria-hidden="true" />
                        </div>
                        <span :class="isBad(item) && item.direction === 'incoming' ? 'font-semibold text-red-600' : 'text-gray-800'">{{ item.who }}</span>
                    </div>
                </template>
                <template #cell(kind)="{ item }">
                    <span class="inline-flex items-center gap-x-1.5 whitespace-nowrap text-gray-500">
                        <FontAwesomeIcon :icon="kindIcon[item.kind as CallRow['kind']]" fixed-width aria-hidden="true" />
                        {{ kindLabel[item.kind as CallRow["kind"]] }}
                    </span>
                </template>
                <template #cell(started_at)="{ item }">
                    <span class="whitespace-nowrap">{{ useFormatTime(item.started_at, { formatTime: "short-datetime" }) }}</span>
                </template>
                <template #cell(duration_seconds)="{ item }">
                    <span class="tabular-nums">{{ duration(item.duration_seconds) }}</span>
                </template>
                <template #cell(actions)="{ item }">
                    <Button v-if="item.can_call_back" type="tertiary" size="xs" :label="ctrans('Call back')" :disabled="!!calls.active || calls.joining === item.conversation_ulid" @click="calls.start(item.conversation_ulid, 'voice')">
                        <template #icon><FontAwesomeIcon icon="fal fa-phone" class="text-green-600" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <p v-if="calls.error?.conversationUlid === item.conversation_ulid" class="mt-1 text-xs text-red-600">{{ calls.error?.text }}</p>
                </template>
            </Table>
        </div>
    </div>
</template>

<style scoped>
.verba-calls-heading :deep(*) {
    color: inherit;
}
</style>
