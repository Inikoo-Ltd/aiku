<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue"
import { Head, Link, router, usePage } from "@inertiajs/vue3"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArchive, faChevronRight, faComments, faHashtag, faPlus, faSearch, faUser, faTasks } from "@fal"
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import Image from "@/Common/Components/Image.vue"
import MessagingConversation from "@/Components/Messaging/MessagingConversation.vue"
import { useStaffMessaging, type StaffCoworker } from "@/Stores/staff-messaging"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useTruncate } from "@/Composables/useTruncate"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import SlackChannelStream, { type SlackStreamMessage } from "@/Components/Messaging/Slack/SlackChannelStream.vue"

library.add(faArchive, faChevronRight, faComments, faHashtag, faPlus, faSearch, faUser, faTasks)

interface SlackChannelListItem {
    id: number
    slack_id: string
    name: string | null
    topic: string | null
    is_archived: boolean
    last_message_at: string | null
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    selected_ulid: string | null
    slack_channels?: SlackChannelListItem[]
    slack_channel?: { id: number; name: string | null; topic: string | null; is_archived: boolean } | null
    slack_messages?: { data: SlackStreamMessage[] } | null
}>()

const isSlack = computed(() => props.slack_channels !== undefined)
const sourceToggle = computed({
    get: () => (isSlack.value ? "slack" : "chats"),
    set: (source: string) => router.visit(route(source === "slack" ? "grp.chat.staff.slack.index" : "grp.chat.staff.index")),
})
const slackFilter = ref("")
const isArchivedOpen = ref(false)
const slackChannelLinks = computed(() => Object.fromEntries((props.slack_channels ?? []).map((channel) => [channel.slack_id, channel.id])))
const filteredSlackChannels = computed(() => {
    const q = slackFilter.value.trim().toLowerCase().replace(/^#/, "")
    return (props.slack_channels ?? []).filter((channel) => !q || (channel.name ?? "").toLowerCase().includes(q) || (channel.topic ?? "").toLowerCase().includes(q))
})
const activeSlackChannels = computed(() => filteredSlackChannels.value.filter((channel) => !channel.is_archived))
const archivedSlackChannels = computed(() => filteredSlackChannels.value.filter((channel) => channel.is_archived))
const showArchived = computed(() => isArchivedOpen.value || (slackFilter.value.trim() !== "" && archivedSlackChannels.value.length > 0) || !!props.slack_channel?.is_archived)

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

const isTaskThread = (conversation: { context_type?: string | null }) => conversation.context_type === "StaffTask"

const messagingConversations = computed(() => store.conversations.filter((c) => !isTaskThread(c)))
const taskThreadCount = computed(() => store.conversations.length - messagingConversations.value.length)

const filteredConversations = computed(() => {
    const q = query.value.trim().toLowerCase()
    if (!q) return messagingConversations.value
    return messagingConversations.value.filter((c) =>
        conversationTitle(c).toLowerCase().includes(q) || (c.last_message ?? "").toLowerCase().includes(q)
    )
})

const openTaskThreadPage = (ulid: string) => {
    const conversation = store.conversationByUlid(ulid)
    if (!conversation || !isTaskThread(conversation) || !conversation.context_url) return false
    router.visit(conversation.context_url)
    return true
}

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

watch(() => store.fullViewUlid, (ulid) => {
    if (!ulid || ulid === selectedUlid.value) {
        return
    }
    if (openTaskThreadPage(ulid)) {
        store.fullViewUlid = null
        return
    }
    if (isMobile.value) {
        store.openConversation(ulid)
        return
    }
    selectedUlid.value = ulid
    store.loadMessages(ulid)
    store.markRead(ulid)
    window.history.replaceState({}, "", route("grp.chat.staff.show", ulid))
})

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
        <div class="w-full md:w-80 shrink-0 border-r border-gray-200 bg-white flex flex-col" :class="selectedUlid || slack_channel ? 'hidden md:flex' : 'flex'">
            <div class="p-3 border-b border-gray-200 space-y-2">
                <SegmentedToggle
                    v-model="sourceToggle"
                    :options="[{ label: ctrans('Chats'), value: 'chats', icon: 'fal fa-comments' }, { label: ctrans('Slack'), value: 'slack', icon: 'fal fa-hashtag' }]"
                    :aria-label="ctrans('Messages from')" />
                <div class="relative">
                    <FontAwesomeIcon icon="fal fa-search" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs" fixed-width aria-hidden="true" />
                    <InputText
                        v-if="isSlack"
                        v-model="slackFilter"
                        :placeholder="ctrans('Filter channels')"
                        size="small"
                        class="w-full !pl-8" />
                    <InputText
                        v-else
                        v-model="query"
                        :placeholder="ctrans('Search or start a new chat…')"
                        size="small"
                        class="w-full !pl-8"
                        @input="onSearchInput" />
                </div>
            </div>

            <div v-if="isSlack" class="flex-1 overflow-y-auto">
                <div v-if="!slack_channels?.length" class="px-4 py-8 text-center text-xs text-gray-500 space-y-1">
                    <FontAwesomeIcon icon="fal fa-hashtag" class="text-2xl text-gray-300" fixed-width aria-hidden="true" />
                    <p class="font-medium text-gray-700">{{ ctrans("No Slack channels to show") }}</p>
                    <p>{{ ctrans("Public Slack channels show here once your aiku account is linked to your Slack account by the same email address.") }}</p>
                </div>
                <template v-else>
                    <Link
                        v-for="channel in activeSlackChannels"
                        :key="'slack-' + channel.id"
                        :href="route('grp.chat.staff.slack.show', channel.id)"
                        preserve-state
                        preserve-scroll
                        class="flex items-start gap-x-2 px-3 py-2 border-b border-gray-50 hover:bg-gray-50"
                        :class="slack_channel?.id === channel.id ? 'bg-[var(--app-accent-soft)]' : ''">
                        <FontAwesomeIcon icon="fal fa-hashtag" class="mt-1 text-gray-400 text-xs" fixed-width aria-hidden="true" />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-x-1">
                                <span class="text-sm font-medium text-gray-900 truncate">{{ channel.name }}</span>
                                <span v-if="channel.last_message_at" class="text-xxs text-gray-400 shrink-0 whitespace-nowrap">{{ useFormatTime(channel.last_message_at, { formatTime: 'dd MMM' }) }}</span>
                            </div>
                            <div v-if="channel.topic" class="text-xs text-gray-500 truncate">{{ channel.topic }}</div>
                        </div>
                    </Link>
                    <div v-if="!filteredSlackChannels.length" class="px-3 py-6 text-center text-xs text-gray-400">{{ ctrans('No matches') }}</div>
                    <template v-if="archivedSlackChannels.length">
                        <button
                            type="button"
                            class="w-full flex items-center gap-x-2 px-3 py-2 text-xs text-gray-500 hover:bg-gray-50"
                            :aria-expanded="showArchived"
                            @click="isArchivedOpen = !isArchivedOpen">
                            <FontAwesomeIcon icon="fal fa-chevron-right" class="text-[10px] transition-transform" :class="showArchived && 'rotate-90'" fixed-width aria-hidden="true" />
                            <FontAwesomeIcon icon="fal fa-archive" fixed-width aria-hidden="true" />
                            {{ ctrans("Archived (:count)", { count: String(archivedSlackChannels.length) }) }}
                        </button>
                        <template v-if="showArchived">
                            <Link
                                v-for="channel in archivedSlackChannels"
                                :key="'slack-' + channel.id"
                                :href="route('grp.chat.staff.slack.show', channel.id)"
                                preserve-state
                                preserve-scroll
                                class="flex items-center gap-x-2 px-3 py-1.5 pl-8 text-gray-500 hover:bg-gray-50"
                                :class="slack_channel?.id === channel.id ? 'bg-[var(--app-accent-soft)]' : ''">
                                <FontAwesomeIcon icon="fal fa-hashtag" class="text-xs" fixed-width aria-hidden="true" />
                                <span class="text-sm truncate">{{ channel.name }}</span>
                            </Link>
                        </template>
                    </template>
                </template>
            </div>

            <div v-else class="flex-1 overflow-y-auto">
                <template v-if="showCoworkerResults">
                    <div class="px-3 pt-2 pb-1 text-xs text-gray-500">{{ ctrans('Start a chat with…') }}</div>
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
                    <div v-if="!coworkerResults.length" class="px-3 py-2 text-xs text-gray-400">{{ ctrans('No matches') }}</div>
                </template>

                <template v-else>
                    <button
                        v-for="conversation in filteredConversations"
                        :key="conversation.ulid"
                        class="w-full flex items-center gap-x-3 px-3 py-2.5 hover:bg-gray-50 text-left border-b border-gray-50"
                        :class="selectedUlid === conversation.ulid ? 'bg-[var(--app-accent-soft)]' : ''"
                        @click="selectConversation(conversation.ulid)">
                        <div v-if="conversation.type === 'group'" class="h-9 w-9 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                            <FontAwesomeIcon icon="fal fa-comments" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
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
                        <span v-if="conversation.unread_count > 0" class="bg-[var(--app-accent)] text-[var(--app-accent-text)] rounded-full h-5 min-w-[1.25rem] px-1.5 flex items-center justify-center text-xxs shrink-0">{{ conversation.unread_count }}</span>
                    </button>
                    <div v-if="!filteredConversations.length" class="px-3 py-6 text-center text-xs text-gray-400">{{ ctrans('No messages yet') }}</div>
                    <Link
                        v-if="taskThreadCount"
                        :href="route('grp.tasks.index')"
                        class="flex items-center gap-x-2 px-3 py-2.5 text-xs text-gray-500 transition duration-200 hover:bg-gray-50 hover:text-gray-700">
                        <FontAwesomeIcon icon="fal fa-tasks" fixed-width aria-hidden="true" />
                        {{ ctrans(':count task chats live with their tasks', { count: String(taskThreadCount) }) }}
                    </Link>
                </template>
            </div>
        </div>

        <!-- RIGHT: conversation -->
        <div class="flex-1 h-full min-w-0" :class="selectedUlid || slack_channel ? 'flex' : 'hidden md:flex'">
            <SlackChannelStream
                v-if="isSlack && slack_channel"
                :key="'slack-' + slack_channel.id"
                :channel="slack_channel"
                :messages="slack_messages ?? undefined"
                :channel-links="slackChannelLinks"
                @back="router.visit(route('grp.chat.staff.slack.index'))" />
            <div v-else-if="isSlack" class="flex-1 flex flex-col items-center justify-center text-gray-400">
                <FontAwesomeIcon icon="fal fa-hashtag" class="text-5xl mb-3" fixed-width aria-hidden="true" />
                <span class="text-sm">{{ ctrans('Select a Slack channel') }}</span>
            </div>
            <MessagingConversation
                v-else-if="selectedConversation"
                :conversation="selectedConversation"
                full-screen
                @back="closeConversation"
                @close="closeConversation" />
            <div v-else class="flex-1 flex flex-col items-center justify-center text-gray-400">
                <FontAwesomeIcon icon="fal fa-comments" class="text-5xl mb-3" fixed-width aria-hidden="true" />
                <span class="text-sm">{{ ctrans('Select a conversation') }}</span>
            </div>
        </div>
    </div>
</template>
