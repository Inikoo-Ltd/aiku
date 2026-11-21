<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script lang="ts">
import { ref } from "vue"

interface SlackSearchResult {
    id: number
    channel: { id: number; name: string | null }
    author: { name: string | null; avatar: Record<string, string> | null; is_bot: boolean }
    sent_at: string
    is_reply: boolean
    segments: { text: string; hit: boolean }[]
    url: string
}

const query = ref("")
const channel = ref<number | null>(null)
const person = ref<string | null>(null)
const from = ref<Date | null>(null)
const to = ref<Date | null>(null)
const hasFile = ref(false)

const results = ref<SlackSearchResult[]>([])
const nextCursor = ref<string | null>(null)
const isLoading = ref(false)
const isLoadingMore = ref(false)
const isTooShort = ref(false)
const error = ref<string | null>(null)
const hasSearched = ref(false)
const showResults = ref(true)
const people = ref<{ slack_id: string; name: string }[] | null>(null)
let ownerId: number | null = null
</script>

<script setup lang="ts">
import { computed, onUnmounted, watch } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import axios from "axios"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArrowLeft, faChevronDown, faRobot, faSearch, faTimes } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"
import Checkbox from "primevue/checkbox"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"

library.add(faArrowLeft, faChevronDown, faRobot, faSearch, faTimes)

const props = defineProps<{
    channels: { id: number; name: string | null }[]
}>()


const viewerId = (usePage().props?.auth as { user?: { id?: number } } | undefined)?.user?.id ?? null
if (ownerId !== viewerId) {
    ownerId = viewerId
    query.value = ""
    channel.value = null
    person.value = null
    from.value = null
    to.value = null
    hasFile.value = false
    results.value = []
    nextCursor.value = null
    hasSearched.value = false
    people.value = null
}
showResults.value = false
onUnmounted(router.on("navigate", () => {
    showResults.value = false
}))

const isActive = computed(() => query.value.trim() !== "")
const isShowingResults = computed(() => isActive.value && showResults.value)
const channelOptions = computed(() => props.channels.map((option) => ({ id: option.id, label: "#" + (option.name ?? "") })))

const startOfDay = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate()).toISOString()
const endOfDay = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate(), 23, 59, 59, 999).toISOString()

const params = (cursor: string | null) => ({
    q: query.value.trim(),
    channel: channel.value ?? undefined,
    person: person.value ?? undefined,
    from: from.value ? startOfDay(from.value) : undefined,
    to: to.value ? endOfDay(to.value) : undefined,
    has_file: hasFile.value ? 1 : undefined,
    cursor: cursor ?? undefined,
})

let requestNumber = 0
const search = async (more = false) => {
    if (!isActive.value) {
        return
    }
    const thisRequest = ++requestNumber
    error.value = null
    more ? (isLoadingMore.value = true) : (isLoading.value = true)
    try {
        const { data } = await axios.get(route("grp.chat.staff.slack.search"), { params: params(more ? nextCursor.value : null) })
        if (thisRequest !== requestNumber) {
            return
        }
        results.value = more ? [...results.value, ...data.data] : data.data
        nextCursor.value = data.next_cursor
        isTooShort.value = data.too_short
        hasSearched.value = true
    } catch (exception: any) {
        if (thisRequest === requestNumber) {
            error.value = exception?.response?.data?.message ?? ctrans("The search could not be done, please try again")
        }
    } finally {
        if (thisRequest === requestNumber) {
            isLoading.value = false
            isLoadingMore.value = false
        }
    }
}

const loadPeople = async () => {
    if (people.value !== null) {
        return
    }
    people.value = []
    try {
        people.value = (await axios.get(route("grp.chat.staff.slack.search.people"))).data
    } catch {
        people.value = null
    }
}

let debounce: ReturnType<typeof setTimeout> | null = null
watch([query, channel, person, from, to, hasFile], () => {
    showResults.value = true
    if (debounce) {
        clearTimeout(debounce)
    }
    if (!isActive.value) {
        requestNumber++
        results.value = []
        nextCursor.value = null
        hasSearched.value = false
        isLoading.value = false
        return
    }
    loadPeople()
    debounce = setTimeout(() => search(), 350)
})

const clear = () => {
    query.value = ""
    channel.value = null
    person.value = null
    from.value = null
    to.value = null
    hasFile.value = false
}

const open = (result: SlackSearchResult) => {
    showResults.value = false
    router.visit(result.url)
}

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"
const smallSelectPt = { label: { class: "!py-1 !text-xs" } }
const datePickerPt = { pcInputText: { root: { class: "!w-28 !py-1 !text-xs" } } }
</script>

<template>
    <div class="flex h-full w-full min-w-0 flex-col">
        <form class="shrink-0 space-y-2 border-b border-gray-200 bg-white px-3 py-2" role="search" @submit.prevent="search()">
            <div class="flex items-center gap-2">
                <div class="relative min-w-0 flex-1">
                    <FontAwesomeIcon icon="fal fa-search" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400" fixed-width aria-hidden="true" />
                    <InputText
                        v-model="query"
                        :placeholder="ctrans('Search Slack messages…')"
                        :aria-label="ctrans('Search Slack messages')"
                        maxlength="200"
                        size="small"
                        class="w-full !pl-8"
                        :class="fieldFocusClass"
                        @focus="showResults = true" />
                </div>
                <Button v-if="isActive && !showResults" type="tertiary" size="xs" :label="ctrans('Back to results')" @click="showResults = true">
                    <template #icon><FontAwesomeIcon icon="fal fa-arrow-left" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button v-if="isActive" type="tertiary" size="xs" :label="ctrans('Close search')" @click="clear">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>

            <div v-if="isShowingResults" class="flex flex-wrap items-center gap-2">
                <Select
                    v-model="channel"
                    :options="channelOptions"
                    option-label="label"
                    option-value="id"
                    filter
                    show-clear
                    :placeholder="ctrans('All channels')"
                    :aria-label="ctrans('Channel')"
                    size="small"
                    class="w-44"
                    :class="fieldFocusClass"
                    :pt="smallSelectPt" />
                <Select
                    v-model="person"
                    :options="people ?? []"
                    option-label="name"
                    option-value="slack_id"
                    filter
                    show-clear
                    :loading="people !== null && people.length === 0"
                    :placeholder="ctrans('Anyone')"
                    :aria-label="ctrans('Written by')"
                    size="small"
                    class="w-44"
                    :class="fieldFocusClass"
                    :pt="smallSelectPt" />
                <DatePicker v-model="from" :max-date="to ?? undefined" date-format="d M yy" show-icon icon-display="input" show-button-bar :manual-input="false" :placeholder="ctrans('From')" :aria-label="ctrans('From')" :class="fieldFocusClass" :pt="datePickerPt" />
                <DatePicker v-model="to" :min-date="from ?? undefined" date-format="d M yy" show-icon icon-display="input" show-button-bar :manual-input="false" :placeholder="ctrans('To')" :aria-label="ctrans('To')" :class="fieldFocusClass" :pt="datePickerPt" />
                <label class="inline-flex cursor-pointer items-center gap-1.5 whitespace-nowrap text-xs text-gray-700">
                    <Checkbox v-model="hasFile" binary input-id="slack-search-has-file" />
                    {{ ctrans("Has a file") }}
                </label>
            </div>
        </form>

        <div v-if="isShowingResults" class="min-h-0 flex-1 overflow-y-auto bg-white" aria-live="polite">
            <div v-if="error" class="px-3 py-2 text-xs text-red-600">{{ error }}</div>
            <div v-if="isLoading && !results.length" class="flex justify-center py-6"><LoadingIcon /></div>
            <div v-else-if="isTooShort" class="px-3 py-8 text-center text-xs text-gray-500">{{ ctrans("Type at least 2 letters or numbers") }}</div>
            <div v-else-if="hasSearched && !results.length && !error" class="px-3 py-8 text-center text-xs text-gray-500 space-y-1">
                <p class="font-medium text-gray-700">{{ ctrans("No messages found") }}</p>
                <p>{{ ctrans("Words match from their start: \"supp\" finds \"supplier\", but \"197367\" does not find \"AWD197367\".") }}</p>
            </div>

            <ul v-else class="divide-y divide-gray-100" :class="isLoading && 'opacity-60'">
                <li v-for="result in results" :key="result.id">
                    <button type="button" class="block w-full px-3 py-2 text-left hover:bg-gray-50 focus:bg-[var(--app-accent-soft)] focus:outline-none" @click="open(result)">
                        <div class="flex items-center gap-x-2 text-xs">
                            <TicketUserAvatar :name="result.author.name" :avatar="result.author.avatar" size="sm" />
                            <span class="truncate font-semibold" :class="result.author.name ? 'text-gray-900' : 'italic text-gray-500'">{{ result.author.name ?? ctrans("Unknown person") }}</span>
                            <span v-if="result.author.is_bot" class="inline-flex shrink-0 items-center gap-0.5 rounded bg-gray-200 px-1 text-xxs font-medium uppercase text-gray-600">
                                <FontAwesomeIcon icon="fal fa-robot" fixed-width aria-hidden="true" />{{ ctrans("Bot") }}
                            </span>
                            <span class="shrink-0 whitespace-nowrap text-gray-500">#{{ result.channel.name }}</span>
                            <span v-if="result.is_reply" class="shrink-0 whitespace-nowrap text-xxs text-gray-400">{{ ctrans("in a thread") }}</span>
                            <span class="ml-auto shrink-0 whitespace-nowrap text-xxs text-gray-400">{{ useFormatTime(result.sent_at, { formatTime: "short-datetime" }) }}</span>
                        </div>
                        <p class="mt-1 break-words pl-8 text-sm leading-relaxed text-gray-700">
                            <template v-for="(segment, index) in result.segments" :key="index">
                                <mark v-if="segment.hit" class="rounded bg-[var(--app-accent-soft)] px-0.5 font-semibold text-gray-900">{{ segment.text }}</mark>
                                <template v-else>{{ segment.text }}</template>
                            </template>
                        </p>
                    </button>
                </li>
            </ul>

            <div v-if="nextCursor" class="flex justify-center py-3">
                <Button type="tertiary" size="xs" :label="ctrans('Older results')" :loading="isLoadingMore" @click="search(true)">
                    <template #icon><FontAwesomeIcon icon="fal fa-chevron-down" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>
        </div>

        <div v-else class="flex min-h-0 flex-1">
            <slot />
        </div>
    </div>
</template>
