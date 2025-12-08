<script setup lang="ts">
import { ref, watch, onMounted, computed, onUnmounted, inject, nextTick } from "vue"
import { ChatMessage, SessionAPI } from "@/types/Chat/chat"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import {
	faPaperPlane,
	faPaperclip,
	faArrowLeft,
	faEllipsisVertical,
} from "@fortawesome/free-solid-svg-icons"
import axios from "axios"
import { capitalize } from "@/Composables/capitalize"

const props = defineProps<{
	messages: ChatMessage[]
	session: SessionAPI | null
}>()

const emit = defineEmits<{
	(e: "send-message", message: string): void
	(e: "back"): void
}>()

const newMessage = ref("")
const fileInput = ref<HTMLInputElement | null>(null)
const messagesContainer = ref<HTMLDivElement | null>(null)

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""
const messagesLocal = ref<ChatMessage[]>([])
const isLoadingMore = ref(false)
const canLoadMore = ref(false)
const nextCursor = ref<string | null>(null)
const chatSession = computed(() => props.session)

const sendMessage = () => {
	if (!newMessage.value.trim()) return
	emit("send-message", newMessage.value)
	newMessage.value = ""
}

const sendFile = () => {
	if (fileInput.value?.files?.length) {
		const file = fileInput.value.files[0]
		console.log("Send file:", file)
	}
}

const scrollBottom = () => {
	nextTick(() => {
		if (messagesContainer.value) {
			messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
		}
	})
}

const getMessages = async (loadMore = false) => {
	if (!chatSession.value?.ulid) return
	try {
		if (loadMore) {
			if (!canLoadMore.value) return
			isLoadingMore.value = true
		}

		let url = `${baseUrl}/app/api/chats/sessions/${chatSession.value.ulid}/messages`
		if (!loadMore) {
			url += `?limit=10`
		} else {
			if (nextCursor.value) {
				url += `?cursor=${nextCursor.value}&limit=50`
			} else {
				canLoadMore.value = false
				return
			}
		}

		const response = await axios.get(url)
		const messages = response.data?.data?.messages ?? response.data?.messages ?? []
		const fetched = messages.map((msg: ChatMessage) => ({ ...msg }))

		if (!loadMore) {
			messagesLocal.value = fetched
		} else {
			messagesLocal.value = [...fetched, ...messagesLocal.value]
		}

		const page = response.data?.data?.pagination ?? response.data?.pagination ?? null
		const hasMore = !!(page && page.has_more)
		canLoadMore.value = hasMore
		nextCursor.value = hasMore ? page.next_cursor : null
	} finally {
		if (loadMore) {
			isLoadingMore.value = false
		}
	}
}

const formatTime = (timestamp: string) => {
	if (!timestamp) return ""
	const date = new Date(timestamp)
	return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
}

const formatDate = (timestamp: string) => {
	if (!timestamp) return ""
	const date = new Date(timestamp)
	const today = new Date()
	const yesterday = new Date()
	yesterday.setDate(today.getDate() - 1)

	const isSameDay = (d1: Date, d2: Date) =>
		d1.getFullYear() === d2.getFullYear() &&
		d1.getMonth() === d2.getMonth() &&
		d1.getDate() === d2.getDate()

	if (isSameDay(date, today)) return trans("Today")
	if (isSameDay(date, yesterday)) return trans("Yesterday")

	const fullDate = date.toLocaleDateString("id-ID", {
		day: "2-digit",
		month: "long",
		year: "numeric",
	})

	return `${fullDate}`
}

const groupedMessages = () => {
	const source = messagesLocal.value.length ? messagesLocal.value : props.messages
	const groups: Record<string, ChatMessage[]> = {}

	const sorted = [...source].sort(
		(a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
	)

	sorted.forEach((message) => {
		const label = formatDate(message.created_at)
		if (!groups[label]) groups[label] = []
		groups[label].push(message)
	})

	return groups
}

let chatChannel: any = null

const stopChatWebSocket = () => {
	if (chatChannel) {
		chatChannel.stopListening("message")
		chatChannel = null
	}
}

const initWebSocket = () => {
	if (!chatSession.value?.ulid) return
	if (!window.Echo) return
	stopChatWebSocket()
	const channelName = `chat-session.${chatSession.value.ulid}`
	console.log("channelName", channelName)

	chatChannel.listen(".message", (eventData: { message: any }) => {
		const msg = eventData.message
		if (msg) {
			messagesLocal.value.push({
				...msg,
				created_at: new Date(msg.created_at),
			} as ChatMessage)
			scrollBottom()
		}
	})
}

watch(
	() => props.messages,
	() => {
		scrollBottom()
	},
	{ deep: true }
)

watch(
	() => messagesLocal.value,
	() => {
		scrollBottom()
	},
	{ deep: true }
)

watch(
	() => props.session?.ulid,
	async () => {
		stopChatWebSocket()
		messagesLocal.value = []
		await getMessages(false)
		initWebSocket()
		scrollBottom()
	}
)

onMounted(async () => {
	await getMessages(false)
	initWebSocket()
	scrollBottom()
})

onUnmounted(() => {
	stopChatWebSocket()
})
</script>

<template>
	<div
		class="flex flex-col max-h-[85vh] sm:max-h-[80vh] md:max-h-[75vh] lg:max-h-[70vh] h-[clamp(420px,70vh,900px)] border rounded-md overflow-hidden bg-white">
		<div class="flex-none flex items-center justify-between px-4 py-2 border-b bg-gray-100">
			<button @click="$emit('back')" class="p-1">
				<FontAwesomeIcon
					:icon="faArrowLeft"
					class="text-base text-gray-400 hover:text-blue-600" />
			</button>

			<div class="flex items-center gap-2">
				<img
					:src="'https://i.pravatar.cc/100?u=' + props.session?.ulid"
					class="w-10 h-10 rounded-full object-cover" />
				<span class="font-semibold">{{ capitalize(props.session?.guest_identifier) }}</span>
			</div>

			<button class="p-1">
				<FontAwesomeIcon
					:icon="faEllipsisVertical"
					class="text-base text-gray-400 hover:text-blue-600" />
			</button>
		</div>

		<!-- Messages Area -->
		<div ref="messagesContainer" class="flex-1 overflow-y-auto px-4 py-3 space-y-4 bg-gray-50">
			<div class="flex justify-center mb-2">
				<button
					v-if="canLoadMore && messagesLocal.length && !isLoadingMore"
					@click="getMessages(true)"
					class="px-2 py-1 text-xs border rounded">
					Load more
				</button>
				<span v-if="isLoadingMore" class="text-xs text-gray-500 ml-2">Loading...</span>
			</div>

			<template v-for="(groupMessages, dateLabel) in groupedMessages()" :key="dateLabel">
				<div class="flex justify-center">
					<div class="px-3 py-1 bg-gray-200 text-gray-600 text-xs rounded-full">
						{{ dateLabel }}
					</div>
				</div>

				<div
					v-for="msg in groupMessages"
					:key="msg.id"
					class="flex"
					:class="{
						'justify-end': msg.sender_type === 'agent',
						'justify-start': msg.sender_type !== 'agent',
					}">
					<div
						class="max-w-[70%] px-3 py-2 rounded-lg break-words"
						:class="{
							'bg-blue-500 text-white': msg.sender_type === 'agent',
							'bg-gray-200 text-gray-900': msg.sender_type !== 'agent',
						}">
						<div class="text-sm">{{ msg.message_text }}</div>
						<span class="text-xs text-gray-400 block mt-1">
							{{ formatTime(msg.created_at) }}
						</span>
					</div>
				</div>
			</template>
		</div>

		<!-- Footer -->
		<div class="flex-none flex items-center gap-2 px-4 py-2 border-t bg-gray-100">
			<button @click="fileInput?.click()" class="p-2 rounded hover:bg-gray-200">
				<FontAwesomeIcon :icon="faPaperclip" class="text-base" />
			</button>
			<input type="file" ref="fileInput" class="hidden" @change="sendFile" />

			<input
				type="text"
				v-model="newMessage"
				placeholder="Type a message..."
				class="flex-1 px-3 py-2 rounded-full border focus:outline-none focus:ring"
				@keydown.enter.prevent="sendMessage" />

			<button
				@click="sendMessage"
				class="px-4 py-2 rounded-full bg-blue-500 text-white hover:bg-blue-600">
				<FontAwesomeIcon :icon="faPaperPlane" class="text-base" />
			</button>
		</div>
	</div>
</template>

<style scoped>
::-webkit-scrollbar {
	width: 6px;
}
::-webkit-scrollbar-track {
	background: transparent;
}
::-webkit-scrollbar-thumb {
	background: rgba(0, 0, 0, 0.2);
	border-radius: 3px;
}
</style>
