<script setup lang='ts'>
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowRight, faPlusCircle, faPenSquare, faTrashAlt, faUndo, faExchange, faRocketLaunch, faHistory, faPlus, faMinus } from '@fal'
import { computed, ref } from 'vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { useFormatTime } from '@/Composables/useFormatTime'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import AppThemeSwatch from '@/Components/Utils/AppThemeSwatch.vue'
import ChatThemeSwatch from '@/Components/Utils/ChatThemeSwatch.vue'
import ProfilePagination from '@/Components/Profile/ProfilePagination.vue'

library.add(faArrowRight, faPlusCircle, faPenSquare, faTrashAlt, faUndo, faExchange, faRocketLaunch, faHistory, faPlus, faMinus)

interface ProfileHistoryRecord {
    id: number
    datetime: string
    event: string
    user_name: string
    ip_address: string | null
    user_agent: string | null
    old_values: Record<string, unknown> | null
    new_values: Record<string, unknown> | null
}

interface ValueChange {
    key: string
    label: string
    oldValue: string | null
    newValue: string | null
    oldRaw: unknown
    newRaw: unknown
}

interface HistoryPage {
    data: ProfileHistoryRecord[]
    meta?: { current_page: number, last_page: number, from: number | null, to: number | null, total: number }
}

const props = defineProps<{
    data: HistoryPage
    layoutVersion?: number
}>()

const eventIcons: Record<string, string> = {
    created: 'fal fa-plus-circle',
    deleted: 'fal fa-trash-alt',
    restored: 'fal fa-undo',
    migration: 'fal fa-exchange',
    published: 'fal fa-rocket-launch',
}

const maxValueLength = 120

const historyPage = ref<HistoryPage>(props.data)
const isLoadingPage = ref(false)
const _scrollArea = ref<HTMLElement | null>(null)

const histories = computed(() => historyPage.value?.data ?? [])
const currentPage = computed(() => historyPage.value?.meta?.current_page ?? 1)
const lastPage = computed(() => Math.max(1, historyPage.value?.meta?.last_page ?? 1))
const totalRecords = computed(() => historyPage.value?.meta?.total ?? histories.value.length)

const paginationReport = computed(() => ctrans('Showing :first to :last of :total records', {
    first: String(historyPage.value?.meta?.from ?? 0),
    last: String(historyPage.value?.meta?.to ?? 0),
    total: String(totalRecords.value),
}))

const goToPage = async (page: number) => {
    isLoadingPage.value = true
    try {
        const { data } = await axios.get(route('grp.profile.history.index', { historyPage: page }), {
            headers: { 'Content-Type': 'application/json' },
        })
        historyPage.value = data
        _scrollArea.value?.scrollTo({ top: 0 })
    } catch {
        notify({ title: ctrans('Something went wrong.'), text: ctrans('Failed to load this page of history.'), type: 'error' })
    } finally {
        isLoadingPage.value = false
    }
}

const formatLabel = (key: string): string => key
    .split('.')
    .map((segment) => segment.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()))
    .join(' › ')

const formatValue = (value: unknown): string | null => {
    if (value === null || value === undefined || value === '') {
        return null
    }
    if (typeof value === 'boolean') {
        return value ? ctrans('Yes') : ctrans('No')
    }

    const text = typeof value === 'object' ? JSON.stringify(value) : String(value)

    return text.length > maxValueLength ? `${text.slice(0, maxValueLength)}…` : text
}

const getChanges = (history: ProfileHistoryRecord): ValueChange[] => {
    const oldValues = history.old_values ?? {}
    const newValues = history.new_values ?? {}
    const keys = Array.from(new Set([...Object.keys(newValues), ...Object.keys(oldValues)]))

    return keys
        .filter((key) => JSON.stringify(oldValues[key]) !== JSON.stringify(newValues[key]))
        .map((key) => ({
            key,
            label: formatLabel(key),
            oldValue: formatValue(oldValues[key]),
            newValue: formatValue(newValues[key]),
            oldRaw: oldValues[key],
            newRaw: newValues[key],
        }))
}

const isColourList = (value: unknown): value is string[] => Array.isArray(value) && value.length > 0 && value.every((colour) => typeof colour === 'string' && colour.startsWith('#'))

const isScalarList = (value: unknown): value is (string | number)[] => Array.isArray(value) && value.every((item) => typeof item === 'string' || typeof item === 'number')

const humanise = (item: string | number): string => {
    const text = String(item).replace(/[_-]+/g, ' ').trim()

    return text.charAt(0).toUpperCase() + text.slice(1)
}

const listChange = (change: ValueChange): { added: string[], removed: string[] } | null => {
    if (isColourList(change.oldRaw) || isColourList(change.newRaw)) {
        return null
    }
    const oldList = change.oldRaw === null || change.oldRaw === undefined ? [] : change.oldRaw
    const newList = change.newRaw === null || change.newRaw === undefined ? [] : change.newRaw
    if (!isScalarList(oldList) || !isScalarList(newList) || (!Array.isArray(change.oldRaw) && !Array.isArray(change.newRaw))) {
        return null
    }

    return {
        added: newList.filter((item) => !oldList.includes(item)).map(humanise),
        removed: oldList.filter((item) => !newList.includes(item)).map(humanise),
    }
}

const themeKind = (change: ValueChange): 'app' | 'chat' | null => {
    if (change.key.endsWith('app_theme') && [change.oldRaw, change.newRaw].some(isColourList)) {
        return 'app'
    }
    if (change.key.endsWith('chat_theme') && [change.oldRaw, change.newRaw].some((value) => typeof value === 'string' && value)) {
        return 'chat'
    }

    return null
}

const describeAgent = (userAgent: string | null): string => {
    if (!userAgent) {
        return ''
    }

    const browser = userAgent.includes('Edg/') ? 'Edge'
        : userAgent.includes('Firefox/') ? 'Firefox'
            : userAgent.includes('Chrome/') ? 'Chrome'
                : userAgent.includes('Safari/') ? 'Safari' : ''
    const operatingSystem = userAgent.includes('Mac OS X') ? 'macOS'
        : userAgent.includes('Windows') ? 'Windows'
            : userAgent.includes('Android') ? 'Android'
                : userAgent.includes('iPhone') || userAgent.includes('iPad') ? 'iOS'
                    : userAgent.includes('Linux') ? 'Linux' : ''

    return [browser, operatingSystem].filter(Boolean).join(' · ')
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col px-4 py-4 sm:px-6">
        <div class="mb-3 flex shrink-0 items-center gap-x-2 text-sm text-gray-500">
            <span class="rounded-md bg-gray-100 px-2 py-0.5 font-semibold tabular-nums text-gray-700">{{ totalRecords }}</span>
            {{ totalRecords === 1 ? ctrans('record') : ctrans('records') }}
        </div>

        <div class="relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
            <ol v-if="histories.length" ref="_scrollArea" class="min-h-0 flex-1 overflow-y-auto divide-y divide-gray-100">
                <li v-for="history in histories" :key="history.id" class="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-start md:gap-6">
                    <div class="flex shrink-0 items-start gap-x-3 md:w-64">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-500 ring-1 ring-gray-200"
                            v-tooltip="history.event?.replace(/_/g, ' ')">
                            <FontAwesomeIcon :icon="eventIcons[history.event] ?? 'fal fa-pen-square'" fixed-width aria-hidden="true" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-900">{{ useFormatTime(history.datetime, { formatTime: 'short-datetime' }) }}</div>
                            <div class="truncate text-xs text-gray-500">
                                <span class="capitalize">{{ history.event?.replace(/_/g, ' ') }}</span>
                                {{ ctrans('by') }} {{ history.user_name }}
                            </div>
                            <div v-if="describeAgent(history.user_agent) || history.ip_address" class="truncate text-xs text-gray-400">
                                {{ [describeAgent(history.user_agent), history.ip_address].filter(Boolean).join(' · ') }}
                            </div>
                        </div>
                    </div>

                    <div class="min-w-0 flex-1 space-y-1.5 text-sm">
                        <template v-for="change in getChanges(history)" :key="change.key">
                            <div v-if="themeKind(change)" class="space-y-1">
                                <span class="font-medium text-gray-700">{{ change.label }}:</span>
                                <div class="flex items-center gap-x-3">
                                    <div v-for="side in (['before', 'after'] as const)" :key="side" class="contents">
                                        <FontAwesomeIcon v-if="side === 'after'" icon="fal fa-arrow-right" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
                                        <div class="w-24 shrink-0 space-y-0.5" :class="side === 'before' ? 'opacity-60' : ''">
                                            <AppThemeSwatch v-if="themeKind(change) === 'app' && isColourList(side === 'before' ? change.oldRaw : change.newRaw)"
                                                :colors="(side === 'before' ? change.oldRaw : change.newRaw) as string[]" />
                                            <ChatThemeSwatch v-else-if="themeKind(change) === 'chat' && (side === 'before' ? change.oldRaw : change.newRaw)"
                                                :theme-key="String(side === 'before' ? change.oldRaw : change.newRaw)" />
                                            <div v-else class="flex aspect-[16/9] items-center justify-center rounded border border-dashed border-gray-300 text-xs italic text-gray-400">{{ ctrans('Default') }}</div>
                                            <div class="text-center text-[11px]" :class="side === 'before' ? 'text-gray-500' : 'font-medium text-gray-700'">
                                                {{ side === 'before' ? ctrans('Before') : ctrans('After') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else-if="listChange(change)" class="flex flex-wrap items-center gap-1.5">
                                <span class="mr-0.5 font-medium text-gray-700">{{ change.label }}:</span>
                                <span v-for="item in listChange(change)!.added" :key="`added-${item}`"
                                    class="inline-flex items-center gap-x-1 rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700 ring-1 ring-inset ring-green-600/20">
                                    <FontAwesomeIcon icon="fal fa-plus" class="text-[10px]" fixed-width aria-hidden="true" />
                                    {{ item }}
                                </span>
                                <span v-for="item in listChange(change)!.removed" :key="`removed-${item}`"
                                    class="inline-flex items-center gap-x-1 rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700 line-through ring-1 ring-inset ring-red-600/20">
                                    <FontAwesomeIcon icon="fal fa-minus" class="text-[10px]" fixed-width aria-hidden="true" />
                                    {{ item }}
                                </span>
                                <span v-if="!listChange(change)!.added.length && !listChange(change)!.removed.length" class="text-xs italic text-gray-400">{{ ctrans('Order changed') }}</span>
                            </div>
                            <div v-else class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                <span class="font-medium text-gray-700">{{ change.label }}:</span>
                                <span v-if="change.oldValue" class="break-all text-gray-400 line-through">{{ change.oldValue }}</span>
                                <FontAwesomeIcon v-if="change.oldValue && change.newValue" icon="fal fa-arrow-right" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
                                <span v-if="change.newValue" class="break-all text-gray-900">{{ change.newValue }}</span>
                                <span v-else-if="change.oldValue" class="italic text-gray-400">{{ ctrans('removed') }}</span>
                            </div>
                        </template>
                        <div v-if="!getChanges(history).length" class="italic text-gray-400">{{ ctrans('No value changes recorded') }}</div>
                    </div>
                </li>
            </ol>

            <div v-else class="flex flex-1 flex-col items-center justify-center gap-y-2 py-16 text-gray-400">
                <FontAwesomeIcon icon="fal fa-history" class="text-2xl" fixed-width aria-hidden="true" />
                <span class="text-sm italic">{{ ctrans('No history yet') }}</span>
            </div>

            <div v-if="isLoadingPage" class="absolute inset-0 flex items-center justify-center bg-white/60">
                <LoadingIcon size="2x" />
            </div>
        </div>

        <ProfilePagination
            v-if="totalRecords"
            class="mt-3"
            :current-page="currentPage"
            :last-page="lastPage"
            :report="paginationReport"
            :disabled="isLoadingPage"
            @page="goToPage" />
    </div>
</template>
