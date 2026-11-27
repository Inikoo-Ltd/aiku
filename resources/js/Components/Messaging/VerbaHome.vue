<script setup lang="ts">
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { computed, nextTick, onMounted, ref } from "vue"
import { Link, router, usePage } from "@inertiajs/vue3"
import axios from "axios"
import { formatDistanceToNow } from "date-fns"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBirthdayCake, faBox, faBullhorn, faChartLine, faEdit, faNewspaper, faPhone, faUsers, faVideo, faWarehouse, faUserFriends, faCircle, faArrowRight, faGift } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Common/Components/Image.vue"
import { onClickOutside } from "@vueuse/core"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { useStaffCalls, type StaffCallKind } from "@/Stores/staff-calls"
import { readCachedHome, writeCachedHome } from "@/Composables/useStaffChatCache"

library.add(faBirthdayCake, faBox, faBullhorn, faChartLine, faEdit, faNewspaper, faPhone, faUsers, faVideo, faWarehouse, faUserFriends, faCircle, faArrowRight, faGift)

interface Story {
    id: number
    category: string
    importance: number
    headline: string
    blurb: string
    author: string | null
    avatar: object | null
    where: string
    image: object | string | null
    url: string
    happened_at: string
}

interface Person {
    conversation_ulid: string
    name: string
    avatar: object | null
}

const emit = defineEmits<{ newVerb: []; newVerbration: [] }>()

const calls = useStaffCalls()
const stories = ref<Story[]>([])
const people = ref<Person[]>([])
const isLoading = ref(true)

const categoryIcon: Record<string, string> = {
    celebration: "fal fa-birthday-cake",
    product: "fal fa-box",
    people: "fal fa-user-friends",
    announcement: "fal fa-bullhorn",
    ops: "fal fa-warehouse",
    sales: "fal fa-chart-line",
    other: "fal fa-newspaper",
}

const categoryLabel = (category: string): string => ({
    celebration: ctrans("Celebration"),
    product: ctrans("Product"),
    people: ctrans("People"),
    announcement: ctrans("Announcement"),
    ops: ctrans("Operations"),
    sales: ctrans("Sales"),
} as Record<string, string>)[category] ?? ctrans("News")

const tagClass: Record<string, string> = {
    celebration: "bg-pink-50 text-pink-700",
    product: "bg-amber-50 text-amber-700",
    people: "bg-violet-50 text-violet-700",
    announcement: "bg-sky-50 text-sky-700",
    ops: "bg-teal-50 text-teal-700",
    sales: "bg-emerald-50 text-emerald-700",
    other: "bg-gray-100 text-gray-600",
}

const user = computed(() => (usePage().props as { auth?: { user?: { id?: number; nickname?: string | null; contact_name?: string; username?: string } } }).auth?.user)
const firstName = computed(() => user.value?.nickname || user.value?.contact_name?.split(" ")[0] || user.value?.username || "")
const hour = new Date().getHours()
const greeting = computed(() => {
    const salutation = hour < 12 ? ctrans("Good morning") : hour < 18 ? ctrans("Good afternoon") : ctrans("Good evening")
    return firstName.value ? `${salutation}, ${firstName.value}` : salutation
})
const today = new Intl.DateTimeFormat(undefined, { weekday: "long", day: "numeric", month: "long" }).format(new Date())

const ago = (date: string) => formatDistanceToNow(new Date(date), { addSuffix: true })

const filter = ref("all")
const filterOptions = computed(() => {
    const counts = stories.value.reduce<Record<string, number>>((carry, story) => {
        carry[story.category] = (carry[story.category] ?? 0) + 1
        return carry
    }, {})
    return [
        { label: ctrans("All"), value: "all", count: stories.value.length },
        ...Object.keys(counts).map(category => ({ label: categoryLabel(category), value: category, icon: categoryIcon[category] ?? categoryIcon.other, count: counts[category] })),
    ]
})

const shown = computed(() => stories.value.filter(story => filter.value === "all" || story.category === filter.value))
const celebrations = computed(() => shown.value.filter(story => story.category === "celebration"))
const featured = computed(() => shown.value.filter(story => story.category !== "celebration" && story.importance >= 4))
const others = computed(() => shown.value.filter(story => story.category !== "celebration" && story.importance < 4))

const call = (person: Person, kind: StaffCallKind) => calls.start(person.conversation_ulid, kind)

const pickKind = ref<StaffCallKind | null>(null)
const query = ref("")
const coworkers = ref<{ id: number; name: string; avatar: object | null }[]>([])
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const picker = ref<HTMLElement | null>(null)
const pickerLeft = ref(0)

const openPicker = (kind: StaffCallKind, event: MouseEvent) => {
    const target = event.currentTarget as HTMLElement
    if (pickKind.value === kind) {
        pickKind.value = null
        return
    }
    pickKind.value = kind
    pickerLeft.value = target.offsetLeft
    query.value = ""
    coworkers.value = []
    nextTick(() => picker.value?.querySelector("input")?.focus())
}

onClickOutside(picker, () => (pickKind.value = null), { ignore: [".verba-call-button"] })

const onSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    if (query.value.trim().length < 2) {
        coworkers.value = []
        return
    }
    searchTimeout = setTimeout(async () => {
        const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params: { q: query.value.trim() } })
        coworkers.value = data.data.slice(0, 8)
    }, 250)
}

const callCoworker = async (userId: number) => {
    const kind = pickKind.value ?? "voice"
    pickKind.value = null
    const { data } = await axios.post(route("grp.chat.staff.conversations.store"), { user_ids: [userId] })
    calls.start(data.data.ulid, kind)
}

interface Home {
    stories: Story[]
    people: Person[]
}

const showHome = (home: Home) => {
    stories.value = home.stories ?? []
    people.value = home.people ?? []
    isLoading.value = false
}

onMounted(async () => {
    const userId = user.value?.id
    const download = axios.get<Home>(route("grp.chat.staff.home"))
    download.catch(() => null)

    if (userId) {
        const cached = await readCachedHome<Home>(userId)
        if (cached && isLoading.value) showHome(cached)
    }

    try {
        const { data } = await download
        showHome(data)
        if (userId) writeCachedHome(userId, data)
    } catch {
        return
    } finally {
        isLoading.value = false
    }
})
</script>

<template>
    <div class="flex-1 overflow-y-auto bg-gray-50 font-desk">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 pt-8 pb-12">
            <header>
                <div class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ today }}</div>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ greeting }}</h1>
            </header>

            <section class="relative mt-5 flex flex-wrap items-center gap-2">
                <Button type="tertiary" :label="ctrans('New Verb')" class="bg-white" @click="emit('newVerb')">
                    <template #icon><FontAwesomeIcon icon="fal fa-edit" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" :label="ctrans('New Verbration')" class="bg-white" @click="emit('newVerbration')">
                    <template #icon><FontAwesomeIcon icon="fal fa-users" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
                </Button>
                <span class="mx-1 hidden h-6 w-px bg-gray-200 sm:block" aria-hidden="true" />
                <Button type="tertiary" :label="ctrans('Call')" class="verba-call-button bg-white" @click="openPicker('voice', $event)">
                    <template #icon><FontAwesomeIcon icon="fal fa-phone" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" :label="ctrans('Video call')" class="verba-call-button bg-white" @click="openPicker('video', $event)">
                    <template #icon><FontAwesomeIcon icon="fal fa-video" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
                <div v-if="pickKind" ref="picker" class="absolute top-full z-20 mt-2 w-80 rounded-lg border border-gray-200 bg-white p-3 shadow-lg" :style="{ left: pickerLeft + 'px' }">
                    <InputText v-model="query" autofocus class="w-full" :placeholder="ctrans('Type a name')" @input="onSearch" />
                    <ul class="mt-2 divide-y divide-gray-100">
                        <li v-for="person in coworkers" :key="person.id">
                            <button type="button" class="flex w-full items-center gap-x-3 rounded px-2 py-2 text-left text-sm hover:bg-gray-50" @click="callCoworker(person.id)">
                                <span class="h-7 w-7 shrink-0 overflow-hidden rounded-full bg-gray-100"><Image v-if="person.avatar" :src="person.avatar" :alt="person.name" image-cover /></span>
                                <span class="flex-1 truncate">{{ person.name }}</span>
                                <FontAwesomeIcon :icon="pickKind === 'video' ? 'fal fa-video' : 'fal fa-phone'" class="text-gray-400" fixed-width aria-hidden="true" />
                            </button>
                        </li>
                    </ul>
                </div>
            </section>

            <div class="mt-8 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <section class="min-w-0">
                    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">{{ ctrans("Highlights") }}</h2>
                            <p class="text-sm text-gray-500">{{ ctrans("News from your chats and channels this week") }}</p>
                        </div>
                        <SegmentedToggle v-if="filterOptions.length > 2" v-model="filter" :options="filterOptions" soft :aria-label="ctrans('Filter highlights')" />
                    </div>

                    <div v-if="isLoading" class="space-y-3">
                        <div class="skeleton h-40 rounded-xl" />
                        <div class="skeleton h-28 rounded-xl" />
                        <div class="skeleton h-28 rounded-xl" />
                    </div>

                    <div v-else-if="!stories.length" class="flex flex-col items-center rounded-xl border border-dashed border-gray-300 bg-white py-16 text-gray-400">
                        <FontAwesomeIcon icon="fal fa-newspaper" class="mb-3 text-4xl" fixed-width aria-hidden="true" />
                        <span class="text-sm">{{ ctrans("No news this week yet") }}</span>
                    </div>

                    <div v-else class="space-y-4">
                        <Link v-for="story in featured" :key="story.id" :href="story.url"
                            class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition-shadow hover:shadow-md sm:flex-row">
                            <div v-if="story.image" class="shrink-0 overflow-hidden bg-gray-100 sm:w-64">
                                <img v-if="typeof story.image === 'string'" :src="story.image" :alt="story.headline" class="h-full max-h-64 w-full object-cover" />
                                <Image v-else :src="story.image" :alt="story.headline" class="h-full w-full" image-cover />
                            </div>
                            <div class="min-w-0 flex-1 p-5">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                    <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide" :class="tagClass[story.category] ?? tagClass.other">
                                        <FontAwesomeIcon :icon="categoryIcon[story.category] ?? categoryIcon.other" fixed-width aria-hidden="true" />{{ categoryLabel(story.category) }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded bg-red-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-red-600">{{ ctrans("Important") }}</span>
                                    <span class="truncate">{{ story.where }} · {{ ago(story.happened_at) }}</span>
                                </div>
                                <h3 class="mt-2 text-xl font-semibold leading-snug text-gray-900 text-balance group-hover:text-[var(--app-accent)]">{{ story.headline }}</h3>
                                <p class="mt-2 max-w-prose text-sm leading-relaxed text-gray-600">{{ story.blurb }}</p>
                                <div class="mt-4 flex items-center justify-between gap-3">
                                    <span class="flex min-w-0 items-center gap-2 text-sm text-gray-600">
                                        <span class="h-6 w-6 shrink-0 overflow-hidden rounded-full bg-gray-200"><Image v-if="story.avatar" :src="story.avatar" :alt="story.author ?? ''" image-cover /></span>
                                        <span class="truncate font-medium text-gray-800">{{ story.author }}</span>
                                    </span>
                                    <span class="flex shrink-0 items-center gap-1 text-sm font-medium text-[var(--app-accent)]">{{ ctrans("Open thread") }}<FontAwesomeIcon icon="fal fa-arrow-right" fixed-width aria-hidden="true" /></span>
                                </div>
                            </div>
                        </Link>

                        <div v-if="others.length" class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white">
                            <Link v-for="story in others" :key="story.id" :href="story.url" class="group flex gap-4 px-5 py-4 hover:bg-gray-50">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                        <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide" :class="tagClass[story.category] ?? tagClass.other">
                                            <FontAwesomeIcon :icon="categoryIcon[story.category] ?? categoryIcon.other" fixed-width aria-hidden="true" />{{ categoryLabel(story.category) }}
                                        </span>
                                        <span class="truncate">{{ story.where }} · {{ ago(story.happened_at) }}</span>
                                    </div>
                                    <h3 class="mt-1.5 font-semibold leading-snug text-gray-900 group-hover:text-[var(--app-accent)]">{{ story.headline }}</h3>
                                    <p class="mt-1 max-w-prose text-sm text-gray-600">{{ story.blurb }}</p>
                                    <div class="mt-2 flex items-center gap-2 text-xs text-gray-500">
                                        <span class="h-5 w-5 shrink-0 overflow-hidden rounded-full bg-gray-200"><Image v-if="story.avatar" :src="story.avatar" :alt="story.author ?? ''" image-cover /></span>
                                        <span class="truncate">{{ story.author }}</span>
                                    </div>
                                </div>
                                <div v-if="story.image" class="hidden h-24 w-32 shrink-0 overflow-hidden rounded-lg bg-gray-100 sm:block">
                                    <img v-if="typeof story.image === 'string'" :src="story.image" :alt="story.headline" class="h-full w-full object-cover" />
                                    <Image v-else :src="story.image" :alt="story.headline" class="h-full w-full" image-cover />
                                </div>
                            </Link>
                        </div>

                        <section v-if="celebrations.length" class="rounded-xl border border-gray-200 bg-white p-4">
                            <div class="mb-3 flex items-center gap-2">
                                <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide" :class="tagClass.celebration">
                                    <FontAwesomeIcon :icon="categoryIcon.celebration" fixed-width aria-hidden="true" />{{ ctrans("Celebrations") }}
                                </span>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div v-for="story in celebrations" :key="story.id" class="flex min-w-0 items-center gap-3 rounded-lg bg-pink-50/60 px-3 py-2.5">
                                    <span class="h-9 w-9 shrink-0 overflow-hidden rounded-full bg-pink-100"><Image v-if="story.avatar" :src="story.avatar" :alt="story.author ?? ''" image-cover /></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold text-gray-900" :title="story.headline">{{ story.headline }}</div>
                                        <div class="truncate text-xs text-gray-500">{{ story.author }} · {{ story.where }} · {{ ago(story.happened_at) }}</div>
                                    </div>
                                    <Button type="tertiary" size="xs" :label="ctrans('Send wishes')" class="shrink-0 bg-white" @click="router.visit(story.url)">
                                        <template #icon><FontAwesomeIcon icon="fal fa-gift" class="text-pink-600" fixed-width aria-hidden="true" /></template>
                                    </Button>
                                </div>
                            </div>
                        </section>

                        <div v-if="!shown.length" class="py-10 text-center text-sm text-gray-400">{{ ctrans("Nothing in this category this week") }}</div>
                    </div>
                </section>

                <aside class="space-y-4">
                    <section class="rounded-xl border border-gray-200 bg-white p-4">
                        <h2 class="mb-2 text-sm font-semibold text-gray-900">{{ ctrans("Recent chats") }}</h2>
                        <div v-if="isLoading" class="space-y-2">
                            <div v-for="n in 4" :key="n" class="skeleton h-9 rounded" />
                        </div>
                        <p v-else-if="!people.length" class="text-sm text-gray-400">{{ ctrans("People you message will show here") }}</p>
                        <div v-for="person in people" :key="person.conversation_ulid" class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-gray-50">
                            <Link :href="route('grp.chat.staff.show', person.conversation_ulid)" class="flex min-w-0 flex-1 items-center gap-3">
                                <span class="h-8 w-8 shrink-0 overflow-hidden rounded-full bg-gray-100"><Image v-if="person.avatar" :src="person.avatar" :alt="person.name" image-cover /></span>
                                <span class="truncate text-sm font-medium text-gray-800">{{ person.name }}</span>
                            </Link>
                            <button type="button" class="grid h-7 w-7 shrink-0 place-items-center rounded-md text-gray-400 hover:bg-white hover:text-[var(--app-accent)] hover:ring-1 hover:ring-gray-200" :title="ctrans('Call :name', { name: person.name })" @click="call(person, 'voice')">
                                <FontAwesomeIcon icon="fal fa-phone" fixed-width aria-hidden="true" />
                            </button>
                            <button type="button" class="grid h-7 w-7 shrink-0 place-items-center rounded-md text-gray-400 hover:bg-white hover:text-[var(--app-accent)] hover:ring-1 hover:ring-gray-200" :title="ctrans('Video call :name', { name: person.name })" @click="call(person, 'video')">
                                <FontAwesomeIcon icon="fal fa-video" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</template>
