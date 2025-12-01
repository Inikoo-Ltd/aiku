<script setup lang="ts">
import { ref, inject, onMounted, watch } from "vue"
import Button from "../Elements/Buttons/Button.vue"
import { faPaperPlane, faSpinner } from "@fas"
import { trans } from "laravel-vue-i18n"

// Props dari parent component
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
})

console.log("🚀 MessageArea props:", props)

// Emit events ke parent
const emit = defineEmits(["send-message", "reload"])

const layout: any = inject("layout", {})
const input = ref("")
const isSending = ref(false)
const messagesContainer = ref<HTMLElement | null>(null)

/**
 * Format timestamp
 */
const formatTime = (timestamp: string) => {
	if (!timestamp) return ""

	const date = new Date(timestamp)
	return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
}

/**
 * Format date untuk message
 */
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

/**
 * Group messages by date
 */
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
	const text = input.value.trim()
	if (!text || !props.session?.ulid) return

	isSending.value = true
	input.value = "" // Clear input immediately for better UX

	try {
		// Emit ke parent component untuk handle sending
		emit("send-message", text)
	} catch (error) {
		console.error("❌ Error sending message:", error)
		// Optional: Show error message to user
	} finally {
		isSending.value = false
	}
}

/**
 * Handle Enter key dengan Shift untuk new line
 */
const handleKeyDown = (event: KeyboardEvent) => {
	if (event.key === "Enter" && !event.shiftKey) {
		event.preventDefault()
		sendMessage()
	}
}

/**
 * Auto-scroll ke bottom saat messages berubah
 */
const scrollToBottom = () => {
	if (messagesContainer.value) {
		setTimeout(() => {
			messagesContainer.value!.scrollTop = messagesContainer.value!.scrollHeight
		}, 50)
	}
}

/**
 * Check jika message dari user/guest
 */
const isUserMessage = (message: any) => {
	return message.sender_type === "guest"
}

/**
 * Get message bubble class
 */
const getBubbleClass = (message: any) => {
	if (isUserMessage(message)) {
		return "user-bubble"
	} else if (message.sender_type === "agent") {
		return "agent-bubble"
	} else {
		return "system-bubble"
	}
}

/**
 * Get sender display name
 */
const getSenderName = (message: any) => {
	switch (message.sender_type) {
		case "guest":
			return trans("You")
		case "agent":
			return message.sender?.name || trans("Support Agent")
		case "system":
			return trans("System")
		default:
			return message.sender_type
	}
}

// Auto-scroll saat messages berubah
watch(
	() => props.messages,
	() => {
		scrollToBottom()
	},
	{ deep: true }
)

// Scroll ke bottom saat component mounted
onMounted(() => {
	scrollToBottom()
})
</script>

<template>
	<div class="flex flex-col h-full">
		<!-- Header -->
		<div
			class="px-4 py-3 border-b border-gray-200 font-semibold text-gray-700 bg-white shadow-sm flex justify-between items-center">
			<span>{{ trans("Chat Support") }}</span>
		</div>

		<!-- Messages Area -->
		<div
			ref="messagesContainer"
			class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50"
			v-if="messages.length > 0">
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
							<!-- Sender Name (hanya untuk non-user messages) -->
							<div
								v-if="!isUserMessage(message)"
								class="text-xs text-gray-500 mb-1 ml-1">
								{{ getSenderName(message) }}
							</div>

							<!-- Message Bubble -->
							<div
								class="flex items-end gap-2"
								:class="isUserMessage(message) ? 'flex-row-reverse' : ''">
								<!-- Message Content -->
								<div
									class="px-3 py-2 rounded-lg text-sm break-words"
									:class="[
										isUserMessage(message)
											? 'user-bubble rounded-br-none'
											: message.sender_type === 'agent'
											? 'agent-bubble rounded-bl-none'
											: 'system-bubble rounded-bl-none',
									]">
									{{ message.message_text }}

									<!-- Message Status/Timestamp -->
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
			<div v-if="loading" class="flex justify-center py-4">
				<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
			</div>
		</div>

		<!-- Empty State -->
		<div
			v-else
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
			<p class="text-sm mb-4">{{ trans("Start the conversation by sending a message") }}</p>
		</div>

		<!-- Input Area -->
		<div class="p-3 border-t border-gray-200 bg-white flex items-center gap-2">
			<textarea
				v-model="input"
				@keydown="handleKeyDown"
				:placeholder="trans('Type your message...')"
				:disabled="!session || isSending"
				class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-200 focus:border-blue-500 outline-none resize-none"
				rows="1"
				@input="autoResize"
				ref="textArea" />
			<Button
				:icon="isSending ? faSpinner : faPaperPlane"
				:loading="isSending"
				@click="sendMessage"
				:disabled="!input.trim() || !session || isSending"
				class="px-4" />
		</div>

		<!-- Session Info (debug) -->
		<div v-if="false" class="text-xs text-gray-500 p-2 border-t border-gray-100 bg-gray-50">
			<div v-if="session">Session: {{ session.ulid?.substring(0, 12) }}...</div>
			<div v-else>No active session</div>
		</div>
	</div>
</template>

<style scoped>
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
</style>
