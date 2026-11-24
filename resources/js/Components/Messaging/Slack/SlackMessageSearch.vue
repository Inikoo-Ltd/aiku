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
import { faArrowLeft, faArrowRight, faChevronDown, faSearch, faTimes } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"
import Checkbox from "primevue/checkbox"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"

library.add(faArrowLeft, faArrowRight, faChevronDown, faSearch, faTimes)

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
const filterClass = (isSet: boolean) => (isSet ? "!border-[#1d1c1d]/50 !bg-[#1d1c1d]/[.06] text-[#1d1c1d]" : "!border-[#dddddd] text-[#1d1c1d] hover:!bg-[#f8f8f8]")
const filterSelectPt = { root: { class: "!h-7 !rounded-md" }, label: { class: "!py-0.5 !text-[13px] !font-bold !text-inherit" } }
const datePickerPt = (isSet: boolean) => ({ pcInputText: { root: { class: ["!h-7 !w-32 !rounded-md !py-0.5 !text-[13px] !font-bold", filterClass(isSet)] } } })

const groups = computed(() => {
    const grouped: { key: string; channel: SlackSearchResult["channel"]; results: SlackSearchResult[] }[] = []
    for (const result of results.value) {
        const last = grouped[grouped.length - 1]
        if (last && last.channel.id === result.channel.id) {
            last.results.push(result)
        } else {
            grouped.push({ key: result.channel.id + "-" + result.id, channel: result.channel, results: [result] })
        }
    }
    return grouped
})
</script>

<template>
    <div class="flex h-full w-full min-w-0 flex-col text-[#1d1c1d]">
        <form class="shrink-0 border-b border-[#e2e2e2] bg-white px-4 py-2.5" role="search" @submit.prevent="search()">
            <div class="flex items-center gap-2">
                <div class="relative min-w-0 flex-1">
                    <FontAwesomeIcon icon="fal fa-search" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[13px] text-[#616061]" fixed-width aria-hidden="true" />
                    <InputText
                        v-model="query"
                        :placeholder="ctrans('Search Slack messages…')"
                        :aria-label="ctrans('Search Slack messages')"
                        maxlength="200"
                        size="small"
                        class="w-full !rounded-md !border-[#dddddd] !py-1.5 !pl-8 !text-[13px]"
                        :class="fieldFocusClass"
                        @focus="showResults = true" />
                </div>
                <button v-if="isActive && !showResults" type="button" class="inline-flex h-8 shrink-0 items-center gap-x-1.5 rounded-md border border-[#dddddd] px-3 text-[13px] font-bold hover:bg-[#f8f8f8]" @click="showResults = true">
                    <FontAwesomeIcon icon="fal fa-arrow-left" fixed-width aria-hidden="true" />{{ ctrans("Back to results") }}
                </button>
                <button v-if="isActive" type="button" v-tooltip.bottom="ctrans('Close search')" :aria-label="ctrans('Close search')" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-[#616061] hover:bg-[#1d1c1d]/[.06] hover:text-[#1d1c1d]" @click="clear">
                    <FontAwesomeIcon icon="fal fa-times" class="text-base" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div v-if="isShowingResults" class="mt-2.5 flex flex-wrap items-center gap-2">
                <Select
                    v-model="channel"
                    :options="channelOptions"
                    option-label="label"
                    option-value="id"
                    filter
                    show-clear
                    :placeholder="ctrans('In')"
                    :aria-label="ctrans('Channel')"
                    size="small"
                    class="w-40"
                    :class="[fieldFocusClass, filterClass(channel !== null)]"
                    :pt="filterSelectPt" />
                <Select
                    v-model="person"
                    :options="people ?? []"
                    option-label="name"
                    option-value="slack_id"
                    filter
                    show-clear
                    :loading="people !== null && people.length === 0"
                    :placeholder="ctrans('From')"
                    :aria-label="ctrans('Written by')"
                    size="small"
                    class="w-40"
                    :class="[fieldFocusClass, filterClass(person !== null)]"
                    :pt="filterSelectPt" />
                <DatePicker v-model="from" :max-date="to ?? undefined" date-format="d M yy" show-icon icon-display="input" show-button-bar :manual-input="false" :placeholder="ctrans('After')" :aria-label="ctrans('From')" :class="fieldFocusClass" :pt="datePickerPt(from !== null)" />
                <DatePicker v-model="to" :min-date="from ?? undefined" date-format="d M yy" show-icon icon-display="input" show-button-bar :manual-input="false" :placeholder="ctrans('Before')" :aria-label="ctrans('To')" :class="fieldFocusClass" :pt="datePickerPt(to !== null)" />
                <label class="inline-flex h-7 cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-md border px-2.5 text-[13px] font-bold" :class="filterClass(hasFile)">
                    <Checkbox v-model="hasFile" binary input-id="slack-search-has-file" size="small" />
                    {{ ctrans("Has a file") }}
                </label>
            </div>
        </form>

        <div v-if="isShowingResults" class="min-h-0 flex-1 overflow-y-auto bg-white pb-4" aria-live="polite">
            <div v-if="error" class="px-4 py-2 text-[13px] text-red-600">{{ error }}</div>
            <div v-if="isLoading && !results.length" class="flex justify-center py-8"><LoadingIcon /></div>
            <div v-else-if="isTooShort" class="px-4 py-10 text-center text-[15px] text-[#616061]">{{ ctrans("Type at least 2 letters or numbers") }}</div>
            <div v-else-if="hasSearched && !results.length && !error" class="mx-auto max-w-md space-y-1 px-4 py-10 text-center">
                <p class="text-lg font-black">{{ ctrans("No messages found") }}</p>
                <p class="text-[15px] text-[#616061]">{{ ctrans("Words match from their start: \"supp\" finds \"supplier\", but \"197367\" does not find \"AWD197367\".") }}</p>
            </div>

            <div v-else class="space-y-3 px-4 pt-3" :class="isLoading && 'opacity-60'">
                <section v-for="group in groups" :key="group.key" class="overflow-hidden rounded-lg border border-[#dddddd]">
                    <h3 class="truncate px-4 pb-0.5 pt-2.5 text-[13px] font-bold"># {{ group.channel.name }}</h3>
                    <ul>
                        <li v-for="result in group.results" :key="result.id">
                            <button type="button" class="group relative flex w-full gap-x-2 px-4 py-2 text-left hover:bg-[#f8f8f8] focus:bg-[#f8f8f8] focus:outline-none" @click="open(result)">
                                <TicketUserAvatar :name="result.author.name" :avatar="result.author.avatar" size="lg" class="!rounded-lg" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline gap-x-2">
                                        <span class="truncate text-[15px] font-black" :class="!result.author.name && 'italic text-[#616061]'">{{ result.author.name ?? ctrans("Unknown person") }}</span>
                                        <span v-if="result.author.is_bot" class="shrink-0 rounded-sm bg-[#1d1c1d]/[.13] px-1 text-[10px] font-bold uppercase leading-4 text-[#616061]">{{ ctrans("App") }}</span>
                                        <span class="shrink-0 whitespace-nowrap text-xs text-[#616061]">{{ useFormatTime(result.sent_at, { formatTime: "short-datetime" }) }}</span>
                                        <span v-if="result.is_reply" class="shrink-0 whitespace-nowrap text-xs text-[#616061]">· {{ ctrans("in a thread") }}</span>
                                    </div>
                                    <p class="break-words text-[15px] leading-[22px]">
                                        <template v-for="(segment, index) in result.segments" :key="index">
                                            <mark v-if="segment.hit" class="rounded-sm bg-[#f2c744]/40 font-bold text-[#1d1c1d]">{{ segment.text }}</mark>
                                            <template v-else>{{ segment.text }}</template>
                                        </template>
                                    </p>
                                </div>
                                <span class="absolute right-3 top-2 hidden items-center gap-x-1 rounded-md border border-[#dddddd] bg-white px-2 py-0.5 text-xs font-bold shadow-sm group-hover:inline-flex group-focus:inline-flex">
                                    {{ ctrans("Jump to message") }}<FontAwesomeIcon icon="fal fa-arrow-right" fixed-width aria-hidden="true" />
                                </span>
                            </button>
                        </li>
                    </ul>
                </section>
            </div>

            <div v-if="nextCursor" class="flex justify-center pt-4">
                <button type="button" class="inline-flex h-8 items-center gap-x-1.5 rounded-md border border-[#dddddd] px-3 text-[13px] font-bold hover:bg-[#f8f8f8] disabled:opacity-60" :disabled="isLoadingMore" @click="search(true)">
                    <LoadingIcon v-if="isLoadingMore" />
                    <FontAwesomeIcon v-else icon="fal fa-chevron-down" fixed-width aria-hidden="true" />{{ ctrans("Older results") }}
                </button>
            </div>
        </div>

        <div v-else class="flex min-h-0 flex-1">
            <slot />
        </div>
    </div>
</template>
