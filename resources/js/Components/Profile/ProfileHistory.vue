<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowRight, faPlusCircle, faPenSquare, faTrashAlt, faUndo, faExchange, faRocketLaunch, faHistory } from '@fal'
import { computed, ref } from 'vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { useFormatTime } from '@/Composables/useFormatTime'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'

library.add(faArrowRight, faPlusCircle, faPenSquare, faTrashAlt, faUndo, faExchange, faRocketLaunch, faHistory)

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
}

const props = defineProps<{
    data: {
        data: ProfileHistoryRecord[]
        links?: { next: string | null }
        meta?: { total: number }
    }
}>()

const eventIcons: Record<string, string> = {
    created: 'fal fa-plus-circle',
    deleted: 'fal fa-trash-alt',
    restored: 'fal fa-undo',
    migration: 'fal fa-exchange',
    published: 'fal fa-rocket-launch',
}

const maxValueLength = 120

const histories = ref<ProfileHistoryRecord[]>([...(props.data?.data ?? [])])
const nextPageUrl = ref<string | null>(props.data?.links?.next ?? null)
const isLoadingMore = ref(false)

const totalRecords = computed(() => props.data?.meta?.total ?? histories.value.length)

const formatLabel = (key: string): string => key
    .split('.')
    .map((segment) => segment.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()))
    .join(' › ')

const formatValue = (value: unknown): string | null => {
    if (value === null || value === undefined || value === '') {
        return null
    }
    if (typeof value === 'boolean') {
        return value ? trans('Yes') : trans('No')
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
        }))
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

const loadMore = async () => {
    if (!nextPageUrl.value || isLoadingMore.value) {
        return
    }

    isLoadingMore.value = true
    try {
        const { data } = await axios.get(nextPageUrl.value)
        histories.value = [...histories.value, ...data.data]
        nextPageUrl.value = data.links?.next ?? null
    } catch {
        notify({ title: trans('Something went wrong.'), text: trans('Failed to load more history.'), type: 'error' })
    } finally {
        isLoadingMore.value = false
    }
}
</script>

<template>
    <div class="px-6 py-6">
        <div class="mb-4 flex items-center gap-x-2 text-sm text-gray-500">
            <span class="rounded-md bg-gray-100 px-2 py-0.5 font-semibold tabular-nums text-gray-700">{{ totalRecords }}</span>
            {{ totalRecords === 1 ? trans('record') : trans('records') }}
        </div>

        <ol v-if="histories.length" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 divide-y divide-gray-100">
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
                            {{ trans('by') }} {{ history.user_name }}
                        </div>
                        <div v-if="describeAgent(history.user_agent) || history.ip_address" class="truncate text-xs text-gray-400">
                            {{ [describeAgent(history.user_agent), history.ip_address].filter(Boolean).join(' · ') }}
                        </div>
                    </div>
                </div>

                <div class="min-w-0 flex-1 space-y-1.5 text-sm">
                    <div v-for="change in getChanges(history)" :key="change.key" class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        <span class="font-medium text-gray-700">{{ change.label }}:</span>
                        <span v-if="change.oldValue" class="break-all text-gray-400 line-through">{{ change.oldValue }}</span>
                        <FontAwesomeIcon v-if="change.oldValue && change.newValue" icon="fal fa-arrow-right" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
                        <span v-if="change.newValue" class="break-all text-gray-900">{{ change.newValue }}</span>
                        <span v-else-if="change.oldValue" class="italic text-gray-400">{{ trans('removed') }}</span>
                    </div>
                    <div v-if="!getChanges(history).length" class="italic text-gray-400">{{ trans('No value changes recorded') }}</div>
                </div>
            </li>
        </ol>

        <div v-else class="flex flex-col items-center justify-center gap-y-2 py-16 text-gray-400">
            <FontAwesomeIcon icon="fal fa-history" class="text-2xl" fixed-width aria-hidden="true" />
            <span class="text-sm italic">{{ trans('No history yet') }}</span>
        </div>

        <div v-if="nextPageUrl" class="mt-4 flex justify-center">
            <button type="button" @click="loadMore" :disabled="isLoadingMore"
                class="inline-flex items-center gap-x-2 rounded-full px-4 py-2 text-sm font-medium text-gray-600 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-60">
                <LoadingIcon v-if="isLoadingMore" />
                {{ trans('Load more') }}
            </button>
        </div>
    </div>
</template>
