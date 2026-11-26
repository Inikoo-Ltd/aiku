<script setup lang="ts">
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { nextTick, onMounted, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import axios from "axios"
import { formatDistanceToNow } from "date-fns"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBirthdayCake, faBox, faBullhorn, faChartLine, faEdit, faNewspaper, faPhone, faUsers, faVideo, faWarehouse, faUserFriends, faCircle } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Common/Components/Image.vue"
import { onClickOutside } from "@vueuse/core"
import InputText from "primevue/inputtext"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useStaffCalls, type StaffCallKind } from "@/Stores/staff-calls"

library.add(faBirthdayCake, faBox, faBullhorn, faChartLine, faEdit, faNewspaper, faPhone, faUsers, faVideo, faWarehouse, faUserFriends, faCircle)

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

const tileClass: Record<string, string> = {
    celebration: "bg-pink-50 text-pink-950",
    product: "bg-amber-50 text-amber-950",
    people: "bg-violet-50 text-violet-950",
    announcement: "bg-sky-50 text-sky-950",
    ops: "bg-slate-100 text-slate-900",
    sales: "bg-emerald-50 text-emerald-950",
    other: "bg-gray-50 text-gray-900",
}

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

onMounted(async () => {
    try {
        const { data } = await axios.get(route("grp.chat.staff.home"))
        stories.value = data.stories
        people.value = data.people
    } finally {
        isLoading.value = false
    }
})
</script>

<template>
    <div class="flex-1 overflow-y-auto font-desk">
        <div class="mx-auto max-w-7xl px-6 py-6 space-y-8">
            <section class="flex flex-wrap items-center gap-x-6 gap-y-4">
                <div class="relative flex gap-2">
                    <button type="button" class="verba-call-button flex items-center gap-x-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="openPicker('voice', $event)">
                        <FontAwesomeIcon icon="fal fa-phone" fixed-width aria-hidden="true" />{{ ctrans("Call") }}
                    </button>
                    <button type="button" class="verba-call-button flex items-center gap-x-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="openPicker('video', $event)">
                        <FontAwesomeIcon icon="fal fa-video" fixed-width aria-hidden="true" />{{ ctrans("Video call") }}
                    </button>
                    <button type="button" class="flex items-center gap-x-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="emit('newVerb')">
                        <FontAwesomeIcon icon="fal fa-edit" fixed-width aria-hidden="true" />{{ ctrans("New Verb") }}
                    </button>
                    <button type="button" class="flex items-center gap-x-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="emit('newVerbration')">
                        <FontAwesomeIcon icon="fal fa-users" fixed-width aria-hidden="true" />{{ ctrans("New Verbration") }}
                    </button>
                    <div v-if="pickKind" ref="picker" class="absolute top-full z-20 mt-2 w-80 rounded-lg border border-gray-200 bg-white p-3 shadow-lg" :style="{ left: pickerLeft + 'px' }">
            <InputText v-model="query" autofocus class="w-full" :placeholder="ctrans('Type a name')" @input="onSearch" />
            <ul class="mt-2 divide-y divide-gray-100">
                <li v-for="person in coworkers" :key="person.id">
                    <button type="button" class="flex w-full items-center gap-x-3 rounded px-2 py-2 text-left text-sm hover:bg-gray-50" @click="callCoworker(person.id)">
                        <span class="h-7 w-7 shrink-0 overflow-hidden rounded-lg bg-gray-100"><Image v-if="person.avatar" :src="person.avatar" :alt="person.name" image-cover /></span>
                        <span class="flex-1 truncate">{{ person.name }}</span>
                        <FontAwesomeIcon :icon="pickKind === 'video' ? 'fal fa-video' : 'fal fa-phone'" class="text-gray-400" fixed-width aria-hidden="true" />
                    </button>
                </li>
            </ul>
</div>
                </div>

                <div v-if="people.length" class="flex items-center gap-x-4">
                    <div v-for="person in people" :key="person.conversation_ulid" class="group flex flex-col items-center gap-y-1 w-16">
                        <Link :href="route('grp.chat.staff.show', person.conversation_ulid)" class="h-10 w-10 overflow-hidden rounded-lg bg-gray-100" :title="person.name">
                            <Image v-if="person.avatar" :src="person.avatar" :alt="person.name" image-cover />
                        </Link>
                        <span class="w-full truncate text-center text-xs text-gray-600">{{ person.name }}</span>
                        <div class="flex gap-x-1">
                            <button type="button" class="h-6 w-6 rounded text-gray-400 hover:bg-gray-100 hover:text-gray-700" :title="ctrans('Call :name', { name: person.name })" @click="call(person, 'voice')">
                                <FontAwesomeIcon icon="fal fa-phone" fixed-width aria-hidden="true" />
                            </button>
                            <button type="button" class="h-6 w-6 rounded text-gray-400 hover:bg-gray-100 hover:text-gray-700" :title="ctrans('Video call :name', { name: person.name })" @click="call(person, 'video')">
                                <FontAwesomeIcon icon="fal fa-video" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <div v-if="isLoading" class="flex justify-center py-16 text-gray-400"><LoadingIcon /></div>

            <div v-else-if="!stories.length" class="flex flex-col items-center py-16 text-gray-400">
                <FontAwesomeIcon icon="fal fa-newspaper" class="text-5xl mb-3" fixed-width aria-hidden="true" />
                <span class="text-sm">{{ ctrans("No news this week yet") }}</span>
            </div>

            <section v-else class="columns-1 sm:columns-2 lg:columns-3 2xl:columns-4 gap-4">
                <Link v-for="story in stories" :key="story.id" :href="story.url"
                    class="group mb-4 block break-inside-avoid overflow-hidden rounded-2xl hover:shadow-lg transition-shadow"
                    :class="story.image ? 'bg-white border border-gray-200' : tileClass[story.category] ?? tileClass.other">
                    <div v-if="story.image" class="overflow-hidden">
                        <img v-if="typeof story.image === 'string'" :src="story.image" :alt="story.headline" class="w-full h-auto group-hover:scale-[1.02] transition-transform" />
                        <Image v-else :src="story.image" :alt="story.headline" class="w-full h-auto" />
                    </div>
                    <div v-else-if="story.category === 'celebration' || story.importance >= 4" class="flex h-28 items-center justify-center text-5xl opacity-80">
                        <FontAwesomeIcon :icon="categoryIcon[story.category] ?? categoryIcon.other" fixed-width aria-hidden="true" class="group-hover:scale-110 transition-transform" />
                    </div>
                    <div class="p-4">
                        <div class="flex items-center gap-x-1.5 text-[11px] uppercase tracking-wide opacity-70">
                            <FontAwesomeIcon :icon="categoryIcon[story.category] ?? categoryIcon.other" fixed-width aria-hidden="true" />
                            <span>{{ categoryLabel(story.category) }}</span>
                        </div>
                        <h3 class="mt-1 font-bold leading-snug" :class="story.image ? 'text-base text-gray-900' : 'text-lg'">
                            <FontAwesomeIcon v-if="story.importance >= 4" icon="fal fa-circle" class="mr-1 text-[0.5rem] align-middle text-red-500" aria-hidden="true" />{{ story.headline }}
                        </h3>
                        <p class="mt-1 text-sm" :class="story.image ? 'text-gray-600' : 'opacity-80'">{{ story.blurb }}</p>
                        <div class="mt-3 flex items-center gap-x-2 text-xs opacity-70">
                            <span class="h-5 w-5 shrink-0 overflow-hidden rounded-full bg-gray-200"><Image v-if="story.avatar" :src="story.avatar" :alt="story.author ?? ''" image-cover /></span>
                            <span class="truncate">{{ story.author }} · {{ story.where }} · {{ formatDistanceToNow(new Date(story.happened_at), { addSuffix: true }) }}</span>
                        </div>
                    </div>
                </Link>
            </section>
        </div>
    </div>
</template>
