<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { formatChatTime } from "@/Composables/chatTime"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPhone, faPhoneSlash } from "@fal"

interface PhoneCallRow {
    id: number
    status: "in_progress" | "completed" | "cancelled" | "auto_closed" | null
    contact_type: "customer" | "guest" | null
    contact_name: string | null
    notes: string | null
    started_at: string | null
    duration_seconds: number | null
    agent_name: string | null
    shop: { id: number; code: string; name: string; slug: string } | null
}

const props = defineProps<{
    shopIds: number[]
    canLogCalls: boolean
    onCall: boolean
    refreshKey: unknown
    view: "all" | "in_progress" | "today"
}>()

const emit = defineEmits<{ (e: "log-call"): void }>()

type ContactFilter = "all" | "customer" | "guest"

const contactFilter = ref<ContactFilter>("all")
const calls = ref<PhoneCallRow[]>([])
const total = ref(0)
const page = ref(1)
const hasMore = ref(false)
const isLoading = ref(false)
const loadError = ref<string | null>(null)
const isReplacing = ref(false)
let request = 0

const CONTACT_FILTERS = [
    { value: "all" as const, label: ctrans("All") },
    { value: "customer" as const, label: ctrans("Customers") },
    { value: "guest" as const, label: ctrans("Guests") },
]

const load = async (nextPage = 1) => {
    const current = ++request
    isLoading.value = true
    isReplacing.value = nextPage === 1
    loadError.value = null

    try {
        const { data } = await axios.get(route("grp.chat.phone_calls.list"), {
            params: {
                shop_ids: props.shopIds,
                page: nextPage,
                view: props.view,
                ...(contactFilter.value !== "all" ? { contact_type: contactFilter.value } : {}),
            },
        })

        if (current !== request) {
            return
        }

        calls.value = nextPage === 1 ? data.data : [...calls.value, ...data.data]
        total.value = data.pagination?.total ?? calls.value.length
        hasMore.value = data.pagination?.has_more ?? false
        page.value = nextPage
    } catch {
        if (current === request) {
            loadError.value = ctrans("The calls could not be loaded. Try again.")
        }
    } finally {
        if (current === request) {
            isLoading.value = false
            isReplacing.value = false
        }
    }
}

watch(() => [props.shopIds.join(","), contactFilter.value, props.refreshKey, props.view], () => load(1), { immediate: true })

const TITLES = {
    all: ctrans("All calls"),
    in_progress: ctrans("On the phone now"),
    today: ctrans("Calls today"),
}

const onScroll = (event: Event) => {
    const list = event.target as HTMLElement

    if (hasMore.value && !isLoading.value && list.scrollHeight - list.scrollTop - list.clientHeight < 120) {
        load(page.value + 1)
    }
}

const duration = (seconds: number | null) => {
    if (seconds === null) {
        return ""
    }

    const minutes = Math.floor(seconds / 60)

    return minutes >= 60
        ? `${Math.floor(minutes / 60)} h ${minutes % 60} m`
        : `${minutes}:${String(seconds % 60).padStart(2, "0")}`
}

const statusLabel: Record<string, string> = {
    in_progress: ctrans("On the phone now"),
    completed: ctrans("Finished"),
    cancelled: ctrans("Cancelled"),
    auto_closed: ctrans("Closed automatically"),
}
</script>

<template>
    <div class="flex min-w-0 flex-1 flex-col">
        <div class="flex items-center gap-2 border-b px-3 py-1.5">
            <div class="min-w-0 flex-1">
                <div class="mb-0.5 truncate text-sm font-semibold text-gray-800">{{ TITLES[view] }}</div>
                <div class="truncate text-[11px] text-gray-500">
                    {{ ctrans(":count calls in :shops shops, newest first", { count: String(total), shops: String(shopIds.length) }) }}
                </div>
            </div>
            <Button v-if="canLogCalls" type="tertiary" size="xs" :label="onCall ? ctrans('You are on a call') : ctrans('Log a call')"
                @click="emit('log-call')">
                <template #icon>
                    <FontAwesomeIcon :icon="faPhone" :class="onCall ? 'text-emerald-600' : 'text-green-600'" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>

        <div class="flex items-center gap-2 border-b px-3 py-1.5">
            <SegmentedToggle v-model="contactFilter" :options="CONTACT_FILTERS" soft :aria-label="ctrans('Who called')" />
        </div>

        <div class="flex-1 overflow-y-auto overflow-x-hidden [scrollbar-width:thin]" @scroll="onScroll">
            <div v-if="loadError" class="px-3 py-6 text-center text-xs text-red-600">{{ loadError }}</div>
            <div v-else-if="isReplacing" aria-busy="true" :aria-label="ctrans('Loading calls')">
                <div v-for="row in 6" :key="row" class="flex items-start gap-3 border-b border-gray-100 px-3 py-2.5">
                    <span class="h-7 w-7 shrink-0 animate-pulse rounded-full bg-gray-200" />
                    <div class="flex-1 space-y-2">
                        <div class="flex gap-2">
                            <span class="h-3.5 w-32 animate-pulse rounded bg-gray-200" />
                            <span class="h-3.5 w-14 animate-pulse rounded bg-gray-100" />
                        </div>
                        <span class="block h-3 w-3/4 animate-pulse rounded bg-gray-100" />
                    </div>
                    <span class="h-3 w-16 animate-pulse rounded bg-gray-100" />
                </div>
            </div>
            <div v-else-if="!calls.length" class="flex flex-col items-center gap-2 py-10 text-center">
                <FontAwesomeIcon :icon="faPhone" class="text-2xl text-gray-300" aria-hidden="true" />
                <div class="text-sm font-medium text-gray-700">{{ ctrans("No calls") }}</div>
            </div>

            <div v-for="call in isReplacing ? [] : calls" :key="call.id"
                class="flex items-start gap-3 border-b border-gray-100 px-3 py-2.5 text-[13px]">
                <span class="relative mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
                    :class="call.status === 'in_progress' ? 'bg-emerald-50 text-emerald-600' : call.status === 'completed' ? 'bg-gray-100 text-gray-600' : 'bg-gray-50 text-gray-400'"
                    v-tooltip="statusLabel[call.status ?? ''] ?? ''">
                    <FontAwesomeIcon :icon="call.status === 'cancelled' || call.status === 'auto_closed' ? faPhoneSlash : faPhone" class="text-xs" fixed-width aria-hidden="true" />
                    <span v-if="call.status === 'in_progress'" class="absolute -right-0.5 -top-0.5 flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                    </span>
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5">
                        <span class="truncate font-semibold text-gray-900">{{ call.contact_name || ctrans("Unknown caller") }}</span>
                        <span v-if="call.contact_type" class="shrink-0 rounded px-1.5 text-[11px] font-medium"
                            :class="call.contact_type === 'customer' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">
                            {{ call.contact_type === "customer" ? ctrans("Customer") : ctrans("Guest") }}
                        </span>
                        <span v-if="call.shop" v-tooltip="call.shop.name" class="shrink-0 rounded bg-gray-100 px-1.5 text-[11px] font-semibold text-gray-600">
                            {{ call.shop.code }}
                        </span>
                        <span v-if="call.status === 'in_progress'" class="shrink-0 text-[11px] font-semibold text-emerald-700">{{ statusLabel.in_progress }}</span>
                    </div>
                    <div v-if="call.notes" class="mt-0.5 line-clamp-2 whitespace-pre-line text-gray-600">{{ call.notes }}</div>
                    <div class="mt-0.5 text-[11px] text-gray-500">{{ call.agent_name ?? "" }}</div>
                </div>

                <div class="shrink-0 text-right text-xs text-gray-500">
                    <div>{{ call.started_at ? formatChatTime(new Date(call.started_at).getTime()) : "" }}</div>
                    <div v-if="call.duration_seconds !== null" class="tabular-nums">{{ duration(call.duration_seconds) }}</div>
                </div>
            </div>

            <div v-if="isLoading && calls.length" class="flex justify-center py-3">
                <LoadingIcon />
            </div>
        </div>
    </div>
</template>
