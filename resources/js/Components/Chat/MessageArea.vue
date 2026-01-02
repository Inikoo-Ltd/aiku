<script setup lang="ts">
import { ref, inject, onMounted, watch } from "vue"
import Button from "../Elements/Buttons/Button.vue"
// import { faPaperPlane, faSpinner } from "@fas"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStar, faPlus, faSpinner, faPaperPlane } from "@fortawesome/free-solid-svg-icons"
import GuestProfileForm from "@/Components/Chat/GuestProfileForm.vue"
import MessageHistory from "@/Components/Chat/MessageHistory.vue"

const props = defineProps({
	messages: {
		type: Array as () => any[],
		default: () => [],
	},
	session: {
		type: Object as () => any,
		default: null,
	},
	loading: {
		type: Boolean,
		default: false,
	},
	isInitialLoad: Boolean,
	isLoadingMore: Boolean,
	isRating: Boolean,
	rating: Number,
	isLoggedIn: Boolean,
})

console.log("🚀 MessageArea props:", props.messages)

const emit = defineEmits(["send-message", "reload", "mounted", "new-session", "open-session"])

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""
const input = ref("")
const isSending = ref(false)
const messagesContainer = ref<HTMLElement | null>(null)
const selectedRating = ref<number | null>(null)
const hoverRating = ref<number | null>(null)
const starPop = ref<number | null>(null)
const activeMenu = ref<"chat" | "history">("chat")
const isLoadingHistory = ref(false)
const historyError = ref<string | null>(null)
const userSessions = ref<any[]>([])
const selectedHistory = ref<{
	ulid: string
	contact_name?: string
	guest_identifier?: string
} | null>(null)

const getGuestProfileSubmitted = (): boolean => {
	try {
		const raw = localStorage.getItem("chat")
		if (!raw) return false
		const data = JSON.parse(raw)
		return data?.guest_profile_submitted === true
	} catch (e) {
		return false
	}
}
const guestProfileSubmitted = ref<boolean>(getGuestProfileSubmitted())
const onGuestProfileSubmitted = () => {
	guestProfileSubmitted.value = true
}
watch(
	() => props.session?.ulid,
	() => {
		guestProfileSubmitted.value = getGuestProfileSubmitted()
	}
)

const formatTime = (timestamp: string) => {
	if (!timestamp) return ""

	const date = new Date(timestamp)
	return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
}

const formatDate = (timestamp: string) => {
	if (!timestamp) return ""

	const date = new Date(timestamp)
	const today = new Date()
	const yesterday = new Date(today)
	yesterday.setDate(yesterday.getDate() - 1)

	if (date.toDateString() === today.toDateString()) {
		return trans("Today")
	} else if (date.toDateString() === yesterday.toDateString()) {
		return trans("Yesterday")
	} else {
		return date.toLocaleDateString()
	}
}

const loadUserSessions = async () => {
	if (!layout?.user?.id) return
	try {
		isLoadingHistory.value = true
		historyError.value = null
		const res = await axios.get(`${baseUrl}/app/api/chats/sessions`, {
			params: { web_user_id: layout.user.id, limit: 50 },
		})
		userSessions.value = res.data?.data?.sessions ?? []
	} finally {
		isLoadingHistory.value = false
	}
}

const updateRating = async (r: number) => {
	starPop.value = r

	selectedRating.value = r

	if (props.session?.ulid) {
		await axios.put(`${baseUrl}/app/api/chats/sessions/${props.session.ulid}/update`, {
			rating: r,
		})
	}

	setTimeout(() => {
		starPop.value = null
	}, 300)
}

const groupedMessages = () => {
	const groups: Record<string, any[]> = {}

	props.messages.forEach((message) => {
		const date = formatDate(message.created_at)
		if (!groups[date]) {
			groups[date] = []
		}
		groups[date].push(message)
	})

	return groups
}

/**
 * Send message handler
 */
const sendMessage = async () => {
	if (!props.isLoggedIn && !guestProfileSubmitted.value) return
	if (props.isRating) return
	const text = input.value.trim()
	if (!text || !props.session?.ulid) return

	isSending.value = true
	input.value = ""

	try {
		emit("send-message", text)
	} catch (error) {
		console.error("❌ Error sending message:", error)
	} finally {
		isSending.value = false
	}
}

const handleKeyDown = (event: KeyboardEvent) => {
	if (event.key === "Enter" && !event.shiftKey) {
		event.preventDefault()
		sendMessage()
	}
}

const onScroll = (e: any) => {
	const el = e.target
	if (el.scrollTop === 0) {
		emit("reload", true)
	}
}

const scrollToBottom = () => {
	if (messagesContainer.value) {
		setTimeout(() => {
			messagesContainer.value!.scrollTop = messagesContainer.value!.scrollHeight
		}, 50)
	}
}

const isUserMessage = (message: any) => {
	return message.sender_type === "guest" || message.sender_type === "user"
}

const getBubbleClass = (message: any) => {
	if (isUserMessage(message)) {
		return "user-bubble"
	} else if (message.sender_type === "agent") {
		return "agent-bubble"
	} else {
		return "system-bubble"
	}
}

const getSenderName = (message: any) => {
	switch (message.sender_type) {
		case "guest":
			return trans("You")
		case "user":
			return props.session?.contact_name || trans("User")
		case "agent":
			return message.sender?.name || trans("Support Agent")
		case "system":
			return trans("System")
		default:
			return message.sender_type
	}
}

watch(
	() => props.messages,
	() => {
		if (props.isLoadingMore) return
		if (props.isInitialLoad) {
			scrollToBottom()
			return
		}

		const el = messagesContainer.value
		if (el) {
			const threshold = 150
			const distanceFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight

			if (distanceFromBottom < threshold) {
				scrollToBottom()
			}
		}
	},
	{ deep: true }
)

watch(activeMenu, (val) => {
	if (val === "history") loadUserSessions()
})

onMounted(() => {
	emit("mounted")
	scrollToBottom()
})
</script>

<template>
	<div class="flex flex-col h-full bg-white">
		<!-- Header -->
		<div class="chat-header">
			<span>{{ trans("Chat Support") }}</span>

			<div v-if="isLoggedIn" class="flex gap-1">
				<button v-for="m in ['chat', 'history']" :key="m" @click="activeMenu = m"
					:class="['tab-btn', activeMenu === m && 'tab-active']">
					{{ capitalize(m) }}
				</button>
			</div>
		</div>

		<!-- History -->
		<div v-if="activeMenu === 'history'" class="flex-1 overflow-y-auto">
			<MessageHistory v-if="selectedHistory" :sessionUlid="selectedHistory.ulid" :session="selectedHistory"
				@back="selectedHistory = null" />

			<div v-else>
				<div v-if="isLoadingHistory" class="p-3 text-sm text-gray-400">
					Loading...
				</div>

				<div v-for="s in userSessions" :key="s.ulid" class="history-item" @click="selectedHistory = s">
					<div class="flex justify-between text-sm">
						<span>{{ s.contact_name || s.guest_identifier }}</span>
						<span class="text-xs text-gray-400">
							{{ s.last_message?.created_at }}
						</span>
					</div>
					<div class="text-xs text-gray-500 truncate">
						{{ s.last_message?.message }}
					</div>
				</div>
			</div>
		</div>

		<!-- Messages -->
		<div ref="messagesContainer" @scroll="onScroll" class="messages-wrap"
			v-if="activeMenu === 'chat' && messages.length">


			<template v-for="(group, date) in groupedMessages()" :key="date">
				<div class="mx-auto flex text-xs text-gray-400 justify-center">{{ date }}</div>

				<div v-for="m in group" :key="m.id"
					:class="['flex', isUserMessage(m) ? 'justify-end' : 'justify-start']">

				<div
	:class="[
		'bubble',
		isUserMessage(m)
			? 'bubble-user'
			: m.sender_type === 'agent'
				? 'bubble-agent'
				: 'bubble-system',
	]"
>
	<div class="bubble-text">
		{{ m.message_text }}
	</div>

	<div class="bubble-meta">
		<span class="bubble-time-text">
			{{ formatTime(m.created_at) }}
		</span>

		<span v-if="isUserMessage(m)" class="bubble-check">
			{{ m.is_read ? "✓✓" : "✓" }}
		</span>
	</div>
</div>

				</div>
			</template>

			<div v-if="loading" class="flex justify-center py-3">
				<div class="loader" />
			</div>
		</div>

		<!-- Empty -->
		<div v-if="activeMenu === 'chat' && !messages.length && isLoggedIn"
			class="flex-1 grid place-content-center text-gray-400 text-sm">
			{{ trans("Start the conversation") }}
		</div>

		<!-- Rating -->
		<div v-if="activeMenu === 'chat' && isRating" class="rating-bar">
			<div class="flex gap-1">
				<button v-for="n in 5" :key="n" @click="updateRating(n)">
					<FontAwesomeIcon :icon="faStar"
						:class="n <= (selectedRating ?? rating ?? 0) ? 'text-yellow-400' : 'text-gray-300'" />
				</button>
			</div>

			<button class="new-chat-btn" @click="$emit('new-session')">
				<FontAwesomeIcon :icon="faPlus" />
				New Chat
			</button>
		</div>

		<!-- Input -->
		<div v-if="activeMenu === 'chat' && !isRating" class="border-t p-2 flex gap-2 items-end">

			<GuestProfileForm v-if="!isLoggedIn && !guestProfileSubmitted" :sessionUlid="session?.ulid"
				@submitted="onGuestProfileSubmitted" />

			<template v-else>
				<textarea v-model="input" rows="1" @keydown="handleKeyDown" placeholder="Type a message..."
					class="chat-input" />

				<Button :icon="isSending ? faSpinner : faPaperPlane" :loading="isSending" :disabled="!input.trim()"
					class="send-btn" @click="sendMessage" />
			</template>
		</div>
	</div>
</template>

<style scoped>

.chat-header {
	@apply flex justify-between items-center px-3 py-2 border-b text-sm font-semibold;
}

.tab-btn {
	@apply px-2 py-1 rounded text-xs bg-gray-100 text-gray-600 transition;
}

.tab-active {
	background-color: v-bind("layout.app.theme[4]");
	color: v-bind("layout.app.theme[5]");
}


.history-item {
	@apply px-3 py-2 border-b cursor-pointer hover:bg-gray-50;
}


.bubble {
	@apply flex flex-col gap-0.5 text-sm leading-snug shadow-sm;
	padding: 6px 10px;
	border-radius: 12px;
	max-width: 78%;
}


.bubble-text {
	@apply whitespace-pre-wrap break-words;
}


.bubble-meta {
	@apply flex items-center justify-end gap-1 text-[10px] opacity-70;
	line-height: 1;
}

.bubble-time-text,
.bubble-check {
	@apply leading-none;
}


.bubble-user {
	background-color: v-bind("layout.app.theme[4]");
	color: v-bind("layout.app.theme[5]");
	border-bottom-right-radius: 4px;
}

.bubble-agent {
	@apply bg-white text-gray-800;
	border-bottom-left-radius: 4px;
}

.bubble-system {
	@apply bg-amber-100 text-amber-800 italic text-xs;
}


.chat-input {
	@apply flex-1 resize-none px-3 py-2 border rounded-lg text-sm outline-none;
	border-color: v-bind("layout.app.theme[4]");
}

.send-btn {
	background-color: v-bind("layout.app.theme[4]");
	color: v-bind("layout.app.theme[5]");
}


.loader {
	width: 20px;
	height: 20px;
	border-radius: 9999px;
	border: 2px solid transparent;
	border-top-color: v-bind("layout.app.theme[4]");
	animation: spin 1s linear infinite;
}


.rating-bar {
	@apply flex justify-between items-center border-t px-3 py-2;
}

.new-chat-btn {
	@apply flex items-center gap-2 text-sm px-3 py-1 rounded border;
	border-color: v-bind("layout.app.theme[4]");
	color: v-bind("layout.app.theme[4]");
}

.messages-wrap {
	max-height: calc(100vh - 400px);
	overflow-y: auto;
	@apply bg-gray-50 px-3 py-2 space-y-2;
	scroll-behavior: smooth;
}


@keyframes spin {
	to {
		transform: rotate(360deg);
	}
}

</style>
