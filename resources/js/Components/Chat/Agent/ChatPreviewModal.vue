<script setup lang="ts">
import { computed, inject, nextTick, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStoreAlt, faEnvelope, faGlobe, faArrowRight, faChevronUp, faChevronDown } from "@fal"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import BubbleChat from "@/Components/Chat/BubbleChat.vue"
import ChatTimelineEvent from "@/Components/Chat/ChatTimelineEvent.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { layoutStructure } from "@/Composables/useLayoutStructure"

type PreviewContact = {
    ulid: string | number
    name: string
    channel?: string
    status?: string
    shop?: { name?: string } | null
}

const props = defineProps<{
    contact: PreviewContact | null
}>()

const emit = defineEmits<{
    (e: "close"): void
    (e: "open", contact: PreviewContact): void
}>()

const layout = inject("layout", layoutStructure)
const baseUrl = layout?.appUrl ?? ""

const PREVIEW_MESSAGES = 30

const isVisible = computed({
    get: () => props.contact !== null,
    set: (visible: boolean) => {
        if (!visible) {
            emit("close")
        }
    },
})

const isWhatsapp = computed(() => props.contact?.channel === "whatsapp")
const isLoading = ref(false)
const loadError = ref("")
const messages = ref<any[]>([])
const events = ref<any[]>([])
const scroller = ref<HTMLElement | null>(null)
const canScrollUp = ref(false)
const canScrollDown = ref(false)

const updateScrollArrows = () => {
    const element = scroller.value

    if (!element) {
        canScrollUp.value = false
        canScrollDown.value = false

        return
    }

    canScrollUp.value = element.scrollTop > 4
    canScrollDown.value = element.scrollTop + element.clientHeight < element.scrollHeight - 4
}

const jumpTo = (end: "oldest" | "newest") => {
    scroller.value?.scrollTo({ top: end === "oldest" ? 0 : scroller.value.scrollHeight, behavior: "smooth" })
}

const groupedTimeline = computed(() => {
    const entries = [
        ...messages.value.map((message) => ({ kind: "message" as const, at: +new Date(message.created_at), key: `m-${message.id}`, message })),
        ...events.value.map((event) => ({ kind: "event" as const, at: +new Date(event.created_at), key: `e-${event.id}`, event })),
    ].sort((a, b) => a.at - b.at)

    const groups: Record<string, typeof entries> = {}

    entries.forEach((entry) => {
        const label = new Intl.DateTimeFormat("id-ID", { day: "2-digit", month: "long", year: "numeric" }).format(new Date(entry.at))
        ;(groups[label] ??= []).push(entry)
    })

    return groups
})

const loadPreview = async (contact: PreviewContact) => {
    isLoading.value = true
    loadError.value = ""
    messages.value = []
    events.value = []

    const url = isWhatsapp.value
        ? `${baseUrl}/app/api/chats/meta/sessions/${contact.ulid}/messages`
        : `${baseUrl}/app/api/chats/sessions/${contact.ulid}/messages`

    try {
        const { data } = await axios.get(url, { params: { limit: PREVIEW_MESSAGES, preview: 1 } })

        if (props.contact?.ulid !== contact.ulid) {
            return
        }

        messages.value = data?.data?.messages ?? data?.messages ?? []
        events.value = data?.data?.events ?? []
    } catch (error) {
        loadError.value = ctrans("The conversation could not be loaded")
    } finally {
        isLoading.value = false
    }

    await nextTick()
    scroller.value?.scrollTo({ top: scroller.value.scrollHeight })
    updateScrollArrows()
}

watch(() => props.contact, (contact) => {
    if (contact) {
        loadPreview(contact)
    }
}, { immediate: true })

const openChat = () => {
    if (props.contact) {
        emit("open", props.contact)
    }
}
</script>

<template>
    <Dialog v-model:visible="isVisible" modal dismissableMask :draggable="false"
        :style="{ width: '90vw', maxWidth: '720px' }" :breakpoints="{ '640px': '96vw' }"
        :pt="{ header: { class: '!px-4 !py-2.5 border-b border-gray-200' }, content: { class: '!p-0' }, footer: { class: '!px-4 !py-3 border-t border-gray-200' } }">
        <template #header>
            <div class="min-w-0">
                <div class="truncate text-base font-semibold text-gray-800">{{ contact?.name }}</div>
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <FontAwesomeIcon :icon="isWhatsapp ? faWhatsapp : contact?.channel === 'email' ? faEnvelope : faGlobe"
                        :class="isWhatsapp ? 'text-green-600' : contact?.channel === 'email' ? 'text-blue-500' : 'text-gray-500'" fixed-width aria-hidden="true" />
                    <span v-if="contact?.shop?.name" class="flex min-w-0 items-center gap-1 truncate">
                        <FontAwesomeIcon :icon="faStoreAlt" class="text-[10px]" fixed-width aria-hidden="true" />
                        {{ contact.shop.name }}
                    </span>
                    <span class="text-gray-400">{{ ctrans("Preview, nothing is marked as read") }}</span>
                </div>
            </div>
        </template>

        <div class="relative">
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-1"
            leave-active-class="transition duration-200 ease-in" leave-to-class="opacity-0 -translate-y-1">
            <button v-show="canScrollUp" type="button" v-tooltip="ctrans('Go to oldest')" :aria-label="ctrans('Go to oldest')"
                class="absolute right-4 top-2 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-500 shadow-md ring-1 ring-gray-200 hover:text-gray-800"
                @click="jumpTo('oldest')">
                <FontAwesomeIcon :icon="faChevronUp" class="text-sm" fixed-width aria-hidden="true" />
            </button>
        </Transition>
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 translate-y-1"
            leave-active-class="transition duration-200 ease-in" leave-to-class="opacity-0 translate-y-1">
            <button v-show="canScrollDown" type="button" v-tooltip="ctrans('Back to newest')" :aria-label="ctrans('Back to newest')"
                class="absolute bottom-2 right-4 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-500 shadow-md ring-1 ring-gray-200 hover:text-gray-800"
                @click="jumpTo('newest')">
                <FontAwesomeIcon :icon="faChevronDown" class="text-sm" fixed-width aria-hidden="true" />
            </button>
        </Transition>
        <div ref="scroller" class="h-[60vh] space-y-3 overflow-y-auto bg-gray-50 px-4 py-4" @scroll.passive="updateScrollArrows">
            <div v-if="isLoading" class="flex h-full items-center justify-center">
                <LoadingIcon class="h-6 w-6 text-gray-400" />
            </div>
            <p v-else-if="loadError" class="py-10 text-center text-sm text-red-600">{{ loadError }}</p>
            <p v-else-if="!messages.length && !events.length" class="py-10 text-center text-sm text-gray-400">{{ ctrans("No messages yet") }}</p>
            <template v-else v-for="(entries, date) in groupedTimeline" :key="date">
                <div class="text-center text-xs text-gray-400">{{ date }}</div>
                <template v-for="entry in entries" :key="entry.key">
                    <ChatTimelineEvent v-if="entry.kind === 'event'" :event="entry.event" />
                    <div v-else class="flex" :class="entry.message.sender_type === 'agent' ? 'justify-end' : 'justify-start'">
                        <BubbleChat :message="entry.message" viewerType="agent" format-markup :contactName="contact?.name" :canEdit="false" readonly disableSlackForward />
                    </div>
                </template>
            </template>
        </div>
        </div>

        <template #footer>
            <div class="flex w-full items-center justify-between gap-2">
                <span class="text-xs text-gray-400">{{ ctrans("Latest :count messages", { count: String(PREVIEW_MESSAGES) }) }}</span>
                <button type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-[var(--theme-color-4,#4f46e5)] px-4 py-2 text-sm font-medium text-[var(--theme-color-5,#fff)] hover:opacity-90"
                    @click="openChat">
                    {{ ctrans("Open chat") }}
                    <FontAwesomeIcon :icon="faArrowRight" fixed-width aria-hidden="true" />
                </button>
            </div>
        </template>
    </Dialog>
</template>
