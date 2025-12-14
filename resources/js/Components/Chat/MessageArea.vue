<script setup lang="ts">
import { ref, inject, onMounted, watch } from "vue"
import Button from "../Elements/Buttons/Button.vue"
// import { faPaperPlane, faSpinner } from "@fas"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStar, faPlus, faSpinner, faPaperPlane } from "@fortawesome/free-solid-svg-icons"
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
	<div class="flex flex-col h-full">
		<div
			class="px-4 py-3 border-b border-gray-200 font-semibold text-gray-700 bg-white shadow-sm flex justify-between items-center">
			<span>{{ trans("Chat Support") }}</span>

			<div v-if="isLoggedIn" class="flex items-center gap-2">
				<button
					:class="[
						'px-3 py-1 rounded-md text-sm',
						activeMenu === 'chat'
							? 'buttonPrimary text-white'
							: 'bg-gray-100 text-gray-700',
					]"
					@click="activeMenu = 'chat'">
					Chat
				</button>

				<button
					:class="[
						'px-3 py-1 rounded-md text-sm',
						activeMenu === 'history'
							? 'buttonPrimary text-white'
							: 'bg-gray-100 text-gray-700',
					]"
					@click="activeMenu = 'history'">
					History
				</button>
			</div>
		</div>

		<div v-if="activeMenu === 'history'" class="flex-1 overflow-y-auto">
			<template v-if="!selectedHistory">
				<div v-if="isLoadingHistory" class="text-sm text-gray-500">Loading...</div>
				<div v-else>
					<div
						v-for="s in userSessions"
						:key="s.ulid"
						class="flex items-start gap-3 px-3 py-3 hover:bg-gray-50 cursor-pointer"
						@click="
							selectedHistory = {
								ulid: s.ulid,
								contact_name: s.contact_name,
								guest_identifier: s.guest_identifier,
							}
						">
						<div class="flex-1">
							<div class="flex items-center justify-between">
								<div class="text-sm">
									{{ s.contact_name || s.guest_identifier }}
								</div>
								<div class="text-xs text-gray-400">
									{{ s.last_message?.created_at }}
								</div>
							</div>
							<div class="text-xs text-gray-600 truncate">
								{{ s.last_message?.message }}
							</div>
						</div>
					</div>
				</div>
			</template>

			<MessageHistory
				v-else
				:sessionUlid="selectedHistory.ulid"
				:session="selectedHistory"
				@back="selectedHistory = null" />
		</div>

		<!-- Messages Area -->
		<div
			ref="messagesContainer"
			@scroll="onScroll"
			class="messages-container flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50"
			v-if="activeMenu === 'chat' && messages.length > 0">
			<template v-for="(groupMessages, date) in groupedMessages()" :key="date">
				<!-- Date Separator -->
				<div class="flex justify-center">
					<div class="px-3 py-1 bg-gray-200 text-gray-600 text-xs rounded-full">
						{{ date }}
					</div>
				</div>

				<!-- Messages -->
				<div v-for="message in groupMessages" :key="message.id" class="message-item">
					<div
						class="flex"
						:class="isUserMessage(message) ? 'justify-end' : 'justify-start'">
						<div class="max-w-[80%]">
							<div
								v-if="!isUserMessage(message)"
								class="text-xs text-gray-500 mb-1 ml-1">
								{{ getSenderName(message) }}
							</div>

							<div
								class="flex items-end gap-2"
								:class="isUserMessage(message) ? 'flex-row-reverse' : ''">
								<div
									class="px-3 py-2 rounded-lg text-sm break-words whitespace-normal max-w-full"
									:class="[
										isUserMessage(message)
											? 'user-bubble rounded-br-none'
											: message.sender_type === 'agent'
											? 'agent-bubble rounded-bl-none'
											: 'system-bubble rounded-bl-none',
									]">
									{{ message.message_text }}

									<div
										class="text-xs mt-1 text-right"
										:class="
											isUserMessage(message)
												? 'text-white/70'
												: 'text-gray-500'
										">
										{{ formatTime(message.created_at) }}
										<span v-if="isUserMessage(message)" class="ml-1">
											{{ message.is_read ? "✓✓" : "✓" }}
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</template>

			<!-- Loading indicator saat messages loading -->
			<div v-if="activeMenu === 'chat' && loading" class="flex justify-center py-4">
				<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
			</div>
		</div>

		<!-- Empty State -->
		<div
			v-else-if="activeMenu === 'chat'"
			class="flex-1 flex flex-col items-center justify-center p-8 text-center text-gray-500 bg-gray-50">
			<div class="mb-4">
				<svg
					class="w-16 h-16 mx-auto text-gray-300"
					fill="none"
					stroke="currentColor"
					viewBox="0 0 24 24">
					<path
						stroke-linecap="round"
						stroke-linejoin="round"
						stroke-width="1"
						d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
				</svg>
			</div>
			<h3 class="text-lg font-medium mb-2">{{ trans("No messages yet") }}</h3>
			<p class="text-sm mb-4">
				{{ trans("Start the conversation by sending a message") }}
			</p>
		</div>
		<div v-if="activeMenu === 'chat' && isRating">
			<div
				class="p-3 border-t border-gray-200 bg-white flex items-center justify-between gap-2">
				<div class="flex items-center gap-1">
					<button
						v-for="n in 5"
						:key="n"
						class="p-1"
						@click="updateRating(n)"
						@mouseover="hoverRating = n"
						@mouseleave="hoverRating = null">
						<FontAwesomeIcon
							:icon="faStar"
							class="text-lg"
							:class="[
								n <= (hoverRating ?? selectedRating ?? props.rating ?? 0)
									? 'text-yellow-400'
									: 'text-gray-300',
								starPop === n ? 'star-pop' : '',
							]" />
					</button>
				</div>
				<button
					class="px-3 py-2 rounded-md border bg-white hover:bg-gray-50 flex items-center gap-2"
					@click="$emit('new-session')">
					<FontAwesomeIcon :icon="faPlus" class="text-sm" />
					<span>New Chat</span>
				</button>
			</div>
		</div>
		<div v-if="activeMenu === 'chat' && !isRating">
			<!-- Input Area -->
			<div class="p-3 border-t border-gray-200 bg-white flex items-center gap-2">
				<textarea
					v-model="input"
					@keydown="handleKeyDown"
					:placeholder="trans('Type your message...')"
					:disabled="!session || isSending || isRating"
					class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-200 focus:border-blue-500 outline-none resize-none"
					rows="1"
					@input="autoResize"
					ref="textArea" />
				<Button
					:icon="isSending ? faSpinner : faPaperPlane"
					:loading="isSending"
					@click="sendMessage"
					:disabled="!input.trim() || !session || isSending || isRating"
					class="px-4" />
			</div>
		</div>

		<!-- Session Info (debug) -->
		<div v-if="false" class="text-xs text-gray-500 p-2 border-t border-gray-100 bg-gray-50">
			<div v-if="session">Session: {{ session.ulid?.substring(0, 12) }}...</div>
			<div v-else>No active session</div>
		</div>
	</div>
</template>

<style scoped>
.buttonPrimary {
	background-color: v-bind("layout?.app?.theme[4]") !important;
	color: v-bind("layout?.app?.theme[5]") !important;
	border: v-bind("`1px solid color-mix(in srgb, ${layout?.app?.theme[4]} 80%, black)`");

	&:hover {
		background-color: v-bind(
			"`color-mix(in srgb, ${layout?.app?.theme[4]} 85%, black)`"
		) !important;
	}

	&:focus {
		box-shadow: 0 0 0 2px v-bind("layout?.app?.theme[4]") !important;
	}
}
/* User message bubble */
.user-bubble {
	background-color: v-bind("layout?.app?.theme[4]") !important;
	color: v-bind("layout?.app?.theme[5]") !important;
	border: v-bind("`1px solid color-mix(in srgb, ${layout?.app?.theme[4]} 80%, black)`");
}

/* Agent message bubble */
.agent-bubble {
	background-color: #f3f4f6;
	color: #1f2937;
	border: 1px solid #e5e7eb;
}

/* System message bubble */
.system-bubble {
	background-color: #fef3c7;
	color: #92400e;
	border: 1px solid #fbbf24;
	font-style: italic;
}

/* Auto-resize textarea */
textarea {
	min-height: 40px;
	max-height: 120px;
	transition: height 0.2s;
}

/* Loading spinner animation */
@keyframes spin {
	from {
		transform: rotate(0deg);
	}
	to {
		transform: rotate(360deg);
	}
}

.animate-spin {
	animation: spin 1s linear infinite;
}

.star-pop {
	animation: pop 0.3s ease-out;
}

@keyframes pop {
	0% {
		transform: scale(1);
	}
	40% {
		transform: scale(1.6);
	}
	100% {
		transform: scale(1);
	}
}
</style>
