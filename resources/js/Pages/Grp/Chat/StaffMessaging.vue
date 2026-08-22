<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue"
import { Head, usePage } from "@inertiajs/vue3"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faComments, faPlus, faSearch, faUser } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import Image from "@/Common/Components/Image.vue"
import MessagingConversation from "@/Components/Messaging/MessagingConversation.vue"
import { useStaffMessaging, type StaffCoworker } from "@/Stores/staff-messaging"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useTruncate } from "@/Composables/useTruncate"

library.add(faComments, faPlus, faSearch, faUser)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    selected_ulid: string | null
}>()

const store = useStaffMessaging()
const myId = computed(() => usePage().props?.auth?.user?.id)

const selectedUlid = ref<string | null>(window.innerWidth < 768 ? null : props.selected_ulid)
const isMobile = ref(window.innerWidth < 768)
const query = ref("")
const coworkerResults = ref<StaffCoworker[]>([])
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

const conversationTitle = (conversation: any) =>
    conversation.name || conversation.participants.filter((p: any) => p.id !== myId.value).map((p: any) => p.name).join(", ")
const conversationAvatar = (conversation: any) =>
    conversation.participants.find((p: any) => p.id !== myId.value)?.avatar ?? null

const filteredConversations = computed(() => {
    const q = query.value.trim().toLowerCase()
    if (!q) return store.conversations
    return store.conversations.filter((c) =>
        conversationTitle(c).toLowerCase().includes(q) || (c.last_message ?? "").toLowerCase().includes(q)
    )
})

const showCoworkerResults = computed(() => query.value.trim().length >= 2)

const onSearchInput = () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    if (query.value.trim().length < 2) {
        coworkerResults.value = []
        return
    }
    searchTimeout = setTimeout(async () => {
        const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params: { q: query.value.trim() } })
        coworkerResults.value = data.data
    }, 300)
}

const selectConversation = (ulid: string) => {
    store.fullViewUlid = ulid
    store.loadMessages(ulid)
    store.markRead(ulid)

    if (isMobile.value) {
        store.openConversation(ulid)
    } else {
        selectedUlid.value = ulid
        window.history.replaceState({}, "", route("grp.chat.staff.show", ulid))
    }
}

const startChatWith = async (coworker: StaffCoworker) => {
    await store.openWithUser(coworker.id)
    const conversation = store.conversations.find((c) => c.type === "dm" && c.participants.some((p) => p.id === coworker.id))
    query.value = ""
    coworkerResults.value = []
    if (conversation) selectConversation(conversation.ulid)
}

const closeConversation = () => {
    store.fullViewUlid = null
    selectedUlid.value = null
    window.history.replaceState({}, "", route("grp.chat.staff.index"))
}

const selectedConversation = computed(() => selectedUlid.value ? store.conversationByUlid(selectedUlid.value) : null)

onMounted(async () => {
    window.addEventListener('resize', handleResize)
    await store.fetchConversations()

    if (isMobile.value && props.selected_ulid) {
        store.openConversation(props.selected_ulid)
    } else if (selectedUlid.value) {
        store.fullViewUlid = selectedUlid.value
        store.loadMessages(selectedUlid.value)
        store.markRead(selectedUlid.value)
    }
})

onUnmounted(() => {
    store.fullViewUlid = null
    window.removeEventListener('resize', handleResize)
    if (searchTimeout) clearTimeout(searchTimeout)
})
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead" />

    <div class="flex min-h-[50vh] md:min-h-[70vh] h-[calc(100vh-10rem)] border-t border-gray-200">
        <!-- LEFT: conversation list -->
        <div class="w-full md:w-80 shrink-0 border-r border-gray-200 bg-white flex flex-col" :class="selectedUlid ? 'hidden md:flex' : 'flex'">
            <div class="p-3 border-b border-gray-200">
                <div class="relative">
                    <FontAwesomeIcon icon="fal fa-search" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs" fixed-width aria-hidden="true" />
                    <input
                        v-model="query"
                        type="text"
                        :placeholder="trans('Search or start a new chat…')"
                        class="w-full pl-8 pr-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        @input="onSearchInput" />
                </div>
            </div>

            <div class="flex-1 overflow-y-auto">
                <template v-if="showCoworkerResults">
                    <div class="px-3 pt-2 pb-1 text-xs text-gray-500">{{ trans('Start a chat with…') }}</div>
                    <button
                        v-for="coworker in coworkerResults"
                        :key="'cw-' + coworker.id"
                        class="w-full flex items-center gap-x-2 px-3 py-2 hover:bg-gray-50 text-left"
                        @click="startChatWith(coworker)">
                        <div class="relative h-8 w-8 rounded-full overflow-hidden bg-gray-200 shrink-0">
                            <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                            <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-gray-400" fixed-width aria-hidden="true" />
                        </div>
                        <span class="text-sm text-gray-900 truncate">{{ coworker.name }}</span>
                    </button>
                    <div v-if="!coworkerResults.length" class="px-3 py-2 text-xs text-gray-400">{{ trans('No matches') }}</div>
                </template>

                <template v-else>
                    <button
                        v-for="conversation in filteredConversations"
                        :key="conversation.ulid"
                        class="w-full flex items-center gap-x-3 px-3 py-2.5 hover:bg-gray-50 text-left border-b border-gray-50"
                        :class="selectedUlid === conversation.ulid ? 'bg-indigo-50' : ''"
                        @click="selectConversation(conversation.ulid)">
                        <div v-if="conversation.type === 'group'" class="h-9 w-9 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                            <FontAwesomeIcon icon="fal fa-comments" class="text-indigo-500" fixed-width aria-hidden="true" />
                        </div>
                        <div v-else class="relative h-9 w-9 rounded-full overflow-hidden bg-gray-200 shrink-0">
                            <Image v-if="conversationAvatar(conversation)" :src="conversationAvatar(conversation)" :alt="conversationTitle(conversation)" image-cover />
                            <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-gray-400" fixed-width aria-hidden="true" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-900 truncate">{{ conversationTitle(conversation) }}</span>
                                <span v-if="conversation.last_message_at" class="text-xxs text-gray-400 shrink-0 ml-1">{{ useFormatTime(conversation.last_message_at, { formatTime: 'hm' }) }}</span>
                            </div>
                            <div class="text-xs text-gray-500 truncate">{{ useTruncate(conversation.last_message ?? '', 40) }}</div>
                        </div>
                        <span v-if="conversation.unread_count > 0" class="bg-indigo-600 text-white rounded-full h-5 min-w-[1.25rem] px-1.5 flex items-center justify-center text-xxs shrink-0">{{ conversation.unread_count }}</span>
                    </button>
                    <div v-if="!filteredConversations.length" class="px-3 py-6 text-center text-xs text-gray-400">{{ trans('No conversations yet') }}</div>
                </template>
            </div>
        </div>

        <!-- RIGHT: conversation -->
        <div class="flex-1 h-full min-w-0" :class="selectedUlid ? 'flex' : 'hidden md:flex'">
            <MessagingConversation
                v-if="selectedConversation"
                :conversation="selectedConversation"
                full-screen
                @close="closeConversation" />
            <div v-else class="flex-1 flex flex-col items-center justify-center text-gray-400">
                <FontAwesomeIcon icon="fal fa-comments" class="text-5xl mb-3" aria-hidden="true" />
                <span class="text-sm">{{ trans('Select a conversation') }}</span>
            </div>
        </div>
    </div>
</template>
