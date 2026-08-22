<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, defineAsyncComponent, nextTick, onMounted, onUnmounted, ref, watch } from "vue"
import { usePage } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { formatDistanceToNow } from "date-fns"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faChevronDown, faPaperPlane, faChevronLeft, faQuoteLeft, faPaperclip, faSmile } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Common/Components/Image.vue"
import { useLiveUsers } from "@/Stores/active-users"
import { useStaffMessaging, type StaffConversation, type StaffMessage } from "@/Stores/staff-messaging"
import { useFormatTime } from "@/Composables/useFormatTime"

library.add(faTimes, faChevronDown, faPaperPlane, faChevronLeft, faQuoteLeft, faPaperclip, faSmile)

const GifPicker = defineAsyncComponent(() => import("./GifPicker.vue"))
const EmojiPicker = defineAsyncComponent(() => import("./EmojiPicker.vue"))

const props = defineProps<{
    conversation: StaffConversation
    fullScreen?: boolean
}>()

const emit = defineEmits<{
    close: []
    minimise: []
}>()

const store = useStaffMessaging()
const myId = computed(() => usePage().props?.auth?.user?.id)
const myLanguageId = computed(() => usePage().props?.auth?.user?.language_id ?? null)

const messages = computed(() => store.messagesByUlid[props.conversation.ulid] ?? [])
const isOnline = computed(() =>
    props.conversation.participants.some((p) => p.id !== myId.value && !!useLiveUsers().liveUsers[p.id])
)
const typingUser = computed(() => store.typingByUlid[props.conversation.ulid]?.user_name ?? null)

const otherParticipants = computed(() => props.conversation.participants.filter((p) => p.id !== myId.value))
const displayName = computed(() => props.conversation.name || otherParticipants.value.map((p) => p.name).join(", "))
const displayAvatar = computed(() => otherParticipants.value[0]?.avatar ?? null)
const lastSeenAt = computed(() => props.conversation.type === "dm" && otherParticipants.value[0]?.last_seen_at ? otherParticipants.value[0].last_seen_at : null)

const newMessage = ref("")
const pendingImage = ref<File | null>(null)
const pendingImagePreview = ref<string | null>(null)
const imageInput = ref<HTMLInputElement | null>(null)
const parentMessage = ref<StaffMessage | null>(null)
const showOriginal = ref<Record<number, boolean>>({})
const messagesContainer = ref<HTMLElement | null>(null)
const textarea = ref<HTMLTextAreaElement | null>(null)
const activeReactionFor = ref<number | null>(null)
const showGifPicker = ref(false)
const showEmojiPicker = ref(false)
const REACTION_EMOJIS = ["👍", "✅", "❌", "👀", "🙏", "🔥"]

const quickReplies = [
    trans("On my way"),
    trans("Done"),
    trans("Please check"),
    trans("Can you help?"),
    trans("Where is it?"),
    trans("Call me"),
    trans("OK"),
    trans("Thanks"),
]

const scrollBottom = () =>
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
        }
    })

const autoResize = () => {
    const el = textarea.value
    if (!el) return
    el.style.height = "auto"
    el.style.height = Math.min(el.scrollHeight, 120) + "px"
}

const parentById = (id: number | null) => (id ? messages.value.find((m) => m.id === id) : null)

const pickImage = () => imageInput.value?.click()

const setPendingImage = (file: File | null) => {
    if (pendingImagePreview.value) URL.revokeObjectURL(pendingImagePreview.value)
    pendingImage.value = file
    pendingImagePreview.value = file ? URL.createObjectURL(file) : null
}

const onImageSelect = (event: Event) => {
    const file = (event.target as HTMLInputElement)?.files?.[0]
    if (file) setPendingImage(file)
    if (imageInput.value) imageInput.value.value = ""
}

const clearPendingImage = () => setPendingImage(null)

const onPaste = (event: ClipboardEvent) => {
    const file = Array.from(event.clipboardData?.files ?? []).find((f) => f.type.startsWith("image/"))
    if (file) {
        event.preventDefault()
        setPendingImage(file)
    }
}

const messageText = (message: StaffMessage) => {
    if (!showOriginal.value[message.id] && myLanguageId.value && message.translations?.[myLanguageId.value]) {
        return message.translations[myLanguageId.value]
    }
    return message.body
}

const hasTranslation = (message: StaffMessage) =>
    !!(myLanguageId.value && message.translations?.[myLanguageId.value] && message.translations[myLanguageId.value] !== message.body)

let isNearBottom = true
const onScroll = () => {
    const el = messagesContainer.value
    if (!el) return
    isNearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 80
    if (el.scrollTop < 40) {
        loadOlder()
    }
}

let isLoadingOlder = false
const loadOlder = async () => {
    if (isLoadingOlder || !messages.value.length) return
    isLoadingOlder = true
    const el = messagesContainer.value
    const prevHeight = el?.scrollHeight ?? 0
    await store.loadMessages(props.conversation.ulid, messages.value[0].id)
    nextTick(() => {
        if (el) el.scrollTop = el.scrollHeight - prevHeight
    })
    isLoadingOlder = false
}

watch(
    () => messages.value.length,
    () => {
        if (isNearBottom) scrollBottom()
    }
)

watch(
    () => store.notesByUlid[props.conversation.ulid],
    () => {
        if (isNearBottom) scrollBottom()
    },
    { deep: true }
)

const emojiPickerContainer = ref<HTMLElement | null>(null)

const closePickers = () => {
    showGifPicker.value = false
    showEmojiPicker.value = false
}

const handleEscapeKey = (event: KeyboardEvent) => {
    if (event.key === "Escape") {
        closePickers()
    }
}

const handleClickOutside = (event: MouseEvent) => {
    if (!emojiPickerContainer.value?.contains(event.target as Node)) {
        showEmojiPicker.value = false
    }
}

onMounted(() => {
    scrollBottom()
    window.addEventListener("keydown", handleEscapeKey)
    document.addEventListener("click", handleClickOutside)
})

onUnmounted(() => {
    window.removeEventListener("keydown", handleEscapeKey)
    document.removeEventListener("click", handleClickOutside)
})

let typingTimeout: ReturnType<typeof setTimeout> | null = null
const handleTypingInput = () => {
    autoResize()
    if (typingTimeout) return
    window.Echo.join("grp.live.users").whisper("staffTyping", {
        conversation_ulid: props.conversation.ulid,
        user_id: myId.value,
        user_name: usePage().props?.auth?.user?.username,
    })
    typingTimeout = setTimeout(() => {
        typingTimeout = null
    }, 2000)
}

const setReply = (message: StaffMessage) => {
    parentMessage.value = message
    textarea.value?.focus()
}

const clearReply = () => {
    parentMessage.value = null
}

const sendCurrent = async () => {
    const body = newMessage.value.trim()
    if (!body && !pendingImage.value) return
    newMessage.value = ""
    const image = pendingImage.value
    clearPendingImage()
    nextTick(autoResize)
    await store.send(props.conversation.ulid, body, parentMessage.value?.id ?? null, image)
    parentMessage.value = null
}

const pickGif = async (url: string) => {
    showGifPicker.value = false
    await store.send(props.conversation.ulid, url)
}

const pickEmoji = (emoji: string) => {
    if (!textarea.value) return
    const start = textarea.value.selectionStart
    const end = textarea.value.selectionEnd
    newMessage.value = newMessage.value.slice(0, start) + emoji + newMessage.value.slice(end)
    nextTick(() => {
        textarea.value?.focus()
        const newPos = start + emoji.length
        textarea.value!.setSelectionRange(newPos, newPos)
    })
}

const sendQuickReply = async (text: string) => {
    await store.send(props.conversation.ulid, text, parentMessage.value?.id ?? null)
    parentMessage.value = null
}

const onEnter = (event: KeyboardEvent) => {
    if (event.shiftKey) return
    event.preventDefault()
    sendCurrent()
}

const toggleReaction = async (message: StaffMessage, emoji: string) => {
    activeReactionFor.value = null
    await store.toggleReaction(message.id, emoji)
}

const reactionEntries = (message: StaffMessage) => Object.entries(message.reactions ?? {})
const hasMyReaction = (message: StaffMessage, emoji: string) =>
    (message.reactions?.[emoji] ?? []).includes(myId.value)
</script>

<template>
    <div class="flex flex-col bg-white text-gray-900 text-left h-full w-full" :class="fullScreen ? '' : 'rounded-t-lg border border-gray-200 shadow-lg'">
        <!-- Header -->
        <div class="flex items-center gap-x-2 px-3 py-2 border-b" :class="fullScreen ? 'shrink-0 border-gray-200 bg-gray-50' : 'rounded-t-lg border-[#44475a] bg-[#282a36]'">
            <button v-if="fullScreen" class="p-2 -ml-2 text-gray-600" @click="emit('close')">
                <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" />
            </button>
            <div class="relative h-7 w-7 rounded-full overflow-hidden shrink-0" :class="fullScreen ? 'bg-gray-200' : 'bg-[#44475a]'">
                <Image v-if="displayAvatar" :src="displayAvatar" :alt="displayName" image-cover />
                <span v-else class="flex items-center justify-center h-full text-xs" :class="fullScreen ? 'text-gray-600' : 'text-[#f8f8f2]'">{{ displayName?.[0] }}</span>
                <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full ring-1" :class="[fullScreen ? 'ring-white' : 'ring-[#282a36]', isOnline ? (fullScreen ? 'bg-green-500' : 'bg-[#50fa7b]') : (fullScreen ? 'bg-gray-400' : 'bg-[#6272a4]')]" />
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate" :class="fullScreen ? '' : 'text-[#f8f8f2]'">{{ displayName }}</div>
                <div class="text-xs h-4" :class="fullScreen ? 'text-gray-500' : 'text-[#6272a4]'">
                    <span v-if="typingUser">{{ typingUser }} {{ trans('is typing…') }}</span>
                    <span v-else-if="lastSeenAt && !isOnline">{{ trans('Last seen') }} {{ formatDistanceToNow(new Date(lastSeenAt), { addSuffix: true }) }}</span>
                    <a v-else-if="conversation.context_url" :href="conversation.context_url" :class="fullScreen ? 'text-indigo-600 hover:underline' : 'text-[#bd93f9] hover:underline'">{{ conversation.context_label }}</a>
                </div>
            </div>
            <button v-if="!fullScreen" v-tooltip="trans('Minimize')" class="p-2 text-[#6272a4] hover:text-[#f8f8f2]" @click="emit('minimise')">
                <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width aria-hidden="true" />
            </button>
            <button v-tooltip="conversation.type === 'dm' ? trans('Done talking to :name', { name: displayName }) : trans('Leave this conversation for now')" class="p-2" :class="fullScreen ? 'text-gray-500 hover:text-gray-800' : 'text-[#6272a4] hover:text-[#f8f8f2]'" @click="emit('close')">
                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
            </button>
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto px-3 py-2 space-y-2" @scroll="onScroll">
            <div
                v-for="message in messages"
                :key="message.id"
                class="group flex flex-col"
                :class="message.user_id === myId ? 'items-end' : 'items-start'"
            >
                <div v-if="parentById(message.parent_id)" class="max-w-[80%] mb-0.5 px-2 py-1 rounded bg-gray-100 text-xxs text-gray-500 border-l-2 border-gray-300">
                    <FontAwesomeIcon icon="fal fa-quote-left" class="mr-1" fixed-width aria-hidden="true" />
                    {{ parentById(message.parent_id)?.body }}
                </div>

                <div class="flex items-end gap-x-1" :class="message.user_id === myId ? 'flex-row-reverse' : ''">
                    <div
                        class="relative max-w-[80%] rounded-lg text-sm"
                        :class="[message.gif_url ? 'p-1' : 'px-3 py-2', message.user_id === myId ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-900']"
                        @mouseenter="!fullScreen && (activeReactionFor = message.id)"
                        @mouseleave="!fullScreen && (activeReactionFor = null)"
                        @click="fullScreen && (activeReactionFor = activeReactionFor === message.id ? null : message.id)"
                    >
                        <div v-if="conversation.type === 'group' && message.user_id !== myId" class="text-xxs opacity-70 mb-0.5">
                            {{ message.user_name }}
                        </div>
                        <Image v-if="message.image" :src="message.image" alt="" image-cover class="max-w-[220px] rounded mb-1" />
                        <img v-if="message.gif_url" :src="message.gif_url" loading="lazy" class="max-w-full rounded max-h-[240px]" />
                        <div v-else-if="messageText(message)" class="whitespace-pre-wrap break-words">{{ messageText(message) }}</div>
                        <button
                            v-if="!message.gif_url && hasTranslation(message)"
                            class="text-xxs underline opacity-70 mt-1"
                            :class="message.user_id === myId ? 'text-indigo-100' : 'text-gray-500'"
                            @click="showOriginal[message.id] = !showOriginal[message.id]"
                        >
                            {{ showOriginal[message.id] ? trans('translated') : trans('original') }}
                        </button>

                        <div v-if="activeReactionFor === message.id" class="absolute -top-9 flex gap-x-1 bg-white border border-gray-200 rounded-full shadow px-2 py-1 z-10" :class="message.user_id === myId ? 'right-0' : 'left-0'">
                            <button v-for="emoji in REACTION_EMOJIS" :key="emoji" class="text-base leading-none p-1" @click="toggleReaction(message, emoji)">
                                {{ emoji }}
                            </button>
                        </div>
                    </div>

                    <button class="opacity-0 group-hover:opacity-100 text-xxs text-gray-400 px-1" @click="setReply(message)">
                        {{ trans('reply') }}
                    </button>
                </div>

                <div v-if="reactionEntries(message).length" class="flex gap-x-1 mt-0.5">
                    <span
                        v-for="[emoji, userIds] in reactionEntries(message)"
                        :key="emoji"
                        class="text-xxs px-1.5 py-0.5 rounded-full border flex items-center gap-x-0.5"
                        :class="hasMyReaction(message, emoji) ? 'bg-indigo-50 border-indigo-300' : 'bg-gray-50 border-gray-200'"
                    >
                        {{ emoji }} {{ (userIds as number[]).length }}
                    </span>
                </div>

                <div class="text-xxs text-gray-400 mt-0.5">{{ useFormatTime(message.created_at, { formatTime: 'hm' }) }}</div>
            </div>

            <div
                v-for="note in store.notesByUlid[conversation.ulid]"
                :key="note.id"
                class="text-center text-xxs text-gray-500 italic py-1 opacity-60"
            >
                {{ note.text }}
            </div>
        </div>

        <!-- Composer -->
        <div class="border-t border-gray-200 px-2 pt-2 shrink-0" :style="fullScreen ? { paddingBottom: 'env(safe-area-inset-bottom)' } : {}">
            <div v-if="parentMessage" class="flex items-center justify-between px-2 py-1 mb-1 rounded bg-gray-100 text-xs text-gray-600">
                <span class="truncate">{{ trans('Replying to') }}: {{ parentMessage.body }}</span>
                <button class="ml-2 shrink-0" @click="clearReply">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div class="flex items-start gap-1.5 pb-1.5">
                <div class="flex flex-wrap gap-1.5 flex-1">
                    <button
                        v-for="reply in quickReplies"
                        :key="reply"
                        class="px-2 py-0.5 rounded-full border border-gray-300 text-xxs text-gray-700 hover:bg-gray-100 whitespace-nowrap"
                        @click="sendQuickReply(reply)"
                    >
                        {{ reply }}
                    </button>
                </div>
                <div class="flex items-center gap-x-1 shrink-0">
                    <input ref="imageInput" type="file" accept="image/*" class="hidden" @change="onImageSelect" />
                    <button
                        v-tooltip="trans('Send a GIF')"
                        class="h-7 w-7 rounded-full border border-gray-300 text-gray-500 flex items-center justify-center hover:bg-gray-100"
                        @click.stop="showGifPicker = !showGifPicker; showEmojiPicker = false"
                    >
                        <span class="text-xxs font-medium">GIF</span>
                    </button>
                    <button
                        v-tooltip="trans('Emoji')"
                        class="h-7 w-7 rounded-full border border-gray-300 text-gray-500 flex items-center justify-center hover:bg-gray-100"
                        @click.stop="showEmojiPicker = !showEmojiPicker; showGifPicker = false"
                    >
                        <FontAwesomeIcon icon="fal fa-smile" fixed-width aria-hidden="true" />
                    </button>
                    <button
                        class="h-7 w-7 rounded-full border border-gray-300 text-gray-500 flex items-center justify-center hover:bg-gray-100"
                        @click="pickImage"
                    >
                        <FontAwesomeIcon icon="fal fa-paperclip" fixed-width aria-hidden="true" />
                    </button>
                </div>
            </div>

            <div v-if="pendingImagePreview" class="relative inline-block mb-1.5">
                <img :src="pendingImagePreview" class="h-16 w-16 object-cover rounded border border-gray-200" />
                <button class="absolute -top-1.5 -right-1.5 h-5 w-5 rounded-full bg-gray-800 text-white flex items-center justify-center" @click="clearPendingImage">
                    <FontAwesomeIcon icon="fal fa-times" size="xs" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div class="relative flex items-end gap-x-2 pb-2">
                <div v-if="showGifPicker" class="absolute bottom-full left-0 mb-1 z-20" :class="fullScreen ? 'w-full max-w-md' : ''">
                    <GifPicker class="w-full" @pick="pickGif" @close="showGifPicker = false" />
                </div>
                <div v-if="showEmojiPicker" ref="emojiPickerContainer" class="absolute bottom-full left-0 mb-1 z-20">
                    <EmojiPicker @pick="pickEmoji" />
                </div>
                <textarea
                    ref="textarea"
                    v-model="newMessage"
                    rows="1"
                    class="flex-1 resize-none border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    :class="fullScreen ? 'min-h-[44px] text-base' : 'text-sm'"
                    :placeholder="trans('Write a message…')"
                    @input="handleTypingInput"
                    @keydown.enter="onEnter"
                    @paste="onPaste"
                />
                <button
                    class="shrink-0 rounded-full bg-indigo-600 text-white flex items-center justify-center hover:bg-indigo-700 disabled:opacity-40"
                    :class="fullScreen ? 'h-11 w-11' : 'h-9 w-9'"
                    :disabled="!newMessage.trim() && !pendingImage"
                    @click="sendCurrent"
                >
                    <FontAwesomeIcon icon="fal fa-paper-plane" fixed-width aria-hidden="true" />
                </button>
            </div>
        </div>
    </div>
</template>
