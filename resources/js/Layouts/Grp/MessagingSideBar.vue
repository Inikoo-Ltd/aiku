<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, onMounted, onUnmounted, ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft, faSearch, faUser, faComments } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Common/Components/Image.vue"
import RailControls from "@/Layouts/Grp/RailControls.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useLiveUsers } from "@/Stores/active-users"
import { useStaffMessaging, type StaffCoworker } from "@/Stores/staff-messaging"
import { useTruncate } from "@/Composables/useTruncate"

library.add(faChevronLeft, faSearch, faUser, faComments)

const layout = inject("layout", layoutStructure)
const store = useStaffMessaging()

const handleToggle = () => {
    if (typeof window !== "undefined") {
        localStorage.setItem("messagingSideBar", (!layout.messagingSidebar.show).toString())
    }
    layout.messagingSidebar.show = !layout.messagingSidebar.show
}

const searchInput = ref<HTMLInputElement | null>(null)
const openSearch = () => {
    if (!layout.messagingSidebar.show) handleToggle()
    setTimeout(() => searchInput.value?.focus(), 50)
}

const search = ref("")
const coworkers = ref<StaffCoworker[]>([])
const showOffline = ref(false)
let searchTimeout: ReturnType<typeof setTimeout> | null = null
let refreshInterval: ReturnType<typeof setInterval> | null = null

const fetchCoworkers = async (q: string) => {
    const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params: q ? { q } : {} })
    coworkers.value = data.data
}

const onSearchInput = () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => fetchCoworkers(search.value), 300)
}

const isOnline = (id: number) => !!useLiveUsers().liveUsers[id]

const onlineCoworkers = computed(() =>
    coworkers.value.filter((c) => isOnline(c.id)).sort((a, b) => a.name.localeCompare(b.name))
)
const offlineCoworkers = computed(() =>
    coworkers.value.filter((c) => !isOnline(c.id)).sort((a, b) => a.name.localeCompare(b.name))
)

const myId = computed(() => layout.user?.id)
const conversationTitle = (conversation: any) =>
    conversation.name || conversation.participants.filter((p: any) => p.id !== myId.value).map((p: any) => p.name).join(", ")
const conversationOtherId = (conversation: any) =>
    conversation.participants.find((p: any) => p.id !== myId.value)?.id ?? null
const conversationAvatar = (conversation: any) =>
    conversation.participants.find((p: any) => p.id !== myId.value)?.avatar ?? null

const onlineCoworkerIds = computed(() => new Set(onlineCoworkers.value.map((c) => c.id)))

const extraConversations = computed(() =>
    store.conversations.filter((c) => c.type === "group" || !onlineCoworkerIds.value.has(conversationOtherId(c)))
)

const railOfflineWithConversation = computed(() => {
    const conversationUserIds = new Set(
        store.conversations.filter((c) => c.type === "dm").map((c) => conversationOtherId(c))
    )
    return offlineCoworkers.value.filter((c) => conversationUserIds.has(c.id))
})

const unreadForUser = (userId: number) => {
    const conversation = store.conversations.find((c) => c.type === "dm" && conversationOtherId(c) === userId)
    return conversation?.unread_count ?? 0
}

const openUser = (userId: number) => {
    const conversation = store.conversations.find((c) => c.type === "dm" && conversationOtherId(c) === userId)
    if (conversation) {
        store.openConversation(conversation.ulid)
    } else {
        store.openWithUser(userId)
    }
}

onMounted(() => {
    if (localStorage.getItem("messagingSideBar")) {
        layout.messagingSidebar.show = JSON.parse(localStorage.getItem("messagingSideBar") ?? "false")
    }
    fetchCoworkers("")
    store.fetchConversations()
    refreshInterval = setInterval(() => fetchCoworkers(search.value), 60000)
})

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval)
    if (searchTimeout) clearTimeout(searchTimeout)
})
</script>

<template>
    <div
        class="hidden md:flex md:flex-col fixed inset-y-0 right-0 h-full bg-[#282a36] border-l border-[#44475a] z-[22] transition-all duration-300 ease-in-out"
        :class="[
            layout.messagingSidebar.show ? 'md:w-48' : 'md:w-12',
        ]"
        id="messagingSidebar">
        <!-- Toggle: collapse-expand MessagingSideBar -->
        <div
            @click="handleToggle"
            class="absolute z-10 left-0 top-2/4 -translate-y-full -translate-x-1/2 w-8 lg:w-5 aspect-square border border-[#6272a4] rounded-full bg-[#44475a] flex justify-center items-center cursor-pointer"
            :title="layout.messagingSidebar.show ? 'Collapse the bar' : 'Expand the bar'">
            <FontAwesomeIcon
                icon="far fa-chevron-left"
                class="h-[10px] leading-none transition-all duration-300 ease-in-out text-[#f8f8f2]"
                aria-hidden="true"
                :class="layout.messagingSidebar.show ? 'rotate-180' : ''" />
        </div>

        <RailControls />

        <!-- COLLAPSED: avatar rail -->
        <div v-if="!layout.messagingSidebar.show" class="flex flex-col items-center gap-y-2 pt-3 overflow-y-auto custom-hide-scrollbar">
            <button
                v-for="coworker in onlineCoworkers"
                :key="'rail-on-' + coworker.id"
                class="relative h-9 w-9 rounded-full overflow-hidden bg-[#44475a] shrink-0"
                v-tooltip="coworker.name"
                @click="openUser(coworker.id)">
                <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[#6272a4]" fixed-width aria-hidden="true" />
                <span class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full bg-[#50fa7b] ring-1 ring-[#282a36]" />
                <span v-if="unreadForUser(coworker.id) > 0" class="absolute -top-0.5 -right-0.5 bg-[#ff5555] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs">{{ unreadForUser(coworker.id) }}</span>
            </button>
            <button
                v-for="coworker in railOfflineWithConversation"
                :key="'rail-off-' + coworker.id"
                class="relative h-9 w-9 rounded-full overflow-hidden bg-[#44475a] shrink-0 opacity-40"
                v-tooltip="coworker.name"
                @click="openUser(coworker.id)">
                <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[#6272a4]" fixed-width aria-hidden="true" />
                <span class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full bg-[#6272a4] ring-1 ring-[#282a36]" />
                <span v-if="unreadForUser(coworker.id) > 0" class="absolute -top-0.5 -right-0.5 bg-[#ff5555] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs">{{ unreadForUser(coworker.id) }}</span>
            </button>
        </div>

        <!-- EXPANDED -->
        <div v-else class="flex flex-col flex-grow overflow-y-auto custom-hide-scrollbar pb-3">
            <div class="p-2 shrink-0">
                <div class="relative">
                    <FontAwesomeIcon icon="fal fa-search" class="absolute left-2 top-1/2 -translate-y-1/2 text-[#6272a4] text-xs" fixed-width aria-hidden="true" />
                    <input
                        ref="searchInput"
                        v-model="search"
                        type="text"
                        :placeholder="trans('Search coworkers…')"
                        class="w-full pl-7 pr-2 py-1.5 text-xs bg-[#21222c] text-[#f8f8f2] placeholder-[#6272a4] border border-[#44475a] rounded-md focus:outline-none focus:ring-1 focus:ring-[#bd93f9]"
                        @input="onSearchInput" />
                </div>
            </div>

            <div class="px-2 pt-1 pb-1 text-xs text-[#6272a4]">{{ trans('Online') }} ({{ onlineCoworkers.length }})</div>
            <button
                v-for="coworker in onlineCoworkers"
                :key="'exp-on-' + coworker.id"
                class="w-full flex items-center gap-x-2 px-2 py-2 hover:bg-[#44475a] text-left"
                @click="openUser(coworker.id)">
                <div class="relative h-8 w-8 rounded-full overflow-hidden bg-[#44475a] shrink-0">
                    <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                    <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[#6272a4]" fixed-width aria-hidden="true" />
                    <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full bg-[#50fa7b] ring-1 ring-[#282a36]" />
                </div>
                <span class="flex-1 text-xs truncate text-[#f8f8f2]">{{ coworker.name }}</span>
                <span v-if="unreadForUser(coworker.id) > 0" class="bg-[#ff5555] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs shrink-0">{{ unreadForUser(coworker.id) }}</span>
            </button>

            <div class="border-t border-[#44475a] mt-2">
                <div class="px-2 pt-2 pb-1 text-xs text-[#6272a4]">{{ trans('Conversations') }}</div>
                <button
                    v-for="conversation in extraConversations"
                    :key="'conv-' + conversation.ulid"
                    class="w-full flex items-center gap-x-2 px-2 py-2 hover:bg-[#44475a] text-left"
                    @click="store.openConversation(conversation.ulid)">
                    <div v-if="conversation.type === 'group'" class="h-8 w-8 rounded-full bg-[#44475a] flex items-center justify-center shrink-0">
                        <FontAwesomeIcon icon="fal fa-comments" class="text-[#bd93f9]" fixed-width aria-hidden="true" />
                    </div>
                    <div v-else class="relative h-8 w-8 rounded-full overflow-hidden bg-[#44475a] shrink-0">
                        <Image v-if="conversationAvatar(conversation)" :src="conversationAvatar(conversation)" :alt="conversationTitle(conversation)" image-cover />
                        <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[#6272a4]" fixed-width aria-hidden="true" />
                        <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full ring-1 ring-[#282a36]" :class="isOnline(conversationOtherId(conversation)) ? 'bg-[#50fa7b]' : 'bg-[#6272a4]'" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs truncate text-[#f8f8f2]">{{ conversationTitle(conversation) }}</div>
                        <div class="text-xxs text-[#6272a4] truncate">{{ useTruncate(conversation.last_message ?? '', 26) }}</div>
                    </div>
                    <span v-if="conversation.unread_count > 0" class="bg-[#ff5555] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs shrink-0">{{ conversation.unread_count }}</span>
                </button>
            </div>

            <div class="border-t border-[#44475a] mt-2">
                <button class="w-full text-left px-2 py-2 text-xs text-[#6272a4] hover:text-[#f8f8f2]" @click="showOffline = !showOffline">
                    {{ trans('Show offline') }} ({{ offlineCoworkers.length }})
                </button>
                <button
                    v-if="showOffline"
                    v-for="coworker in offlineCoworkers"
                    :key="'exp-off-' + coworker.id"
                    class="w-full flex items-center gap-x-2 px-2 py-2 hover:bg-[#44475a] text-left opacity-40"
                    @click="openUser(coworker.id)">
                    <div class="relative h-8 w-8 rounded-full overflow-hidden bg-[#44475a] shrink-0">
                        <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                        <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[#6272a4]" fixed-width aria-hidden="true" />
                        <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full bg-[#6272a4] ring-1 ring-[#282a36]" />
                    </div>
                    <span class="flex-1 text-xs truncate text-[#f8f8f2]">{{ coworker.name }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<style>
.custom-hide-scrollbar::-webkit-scrollbar {
    display: none;
}

.custom-hide-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
