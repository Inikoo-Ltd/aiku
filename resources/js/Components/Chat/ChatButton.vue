<script setup lang="ts">
import { ref, inject, onMounted, onBeforeUnmount } from "vue"
import MessageArea from "@/Components/Chat/MessageArea.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faMessage } from "@fortawesome/free-solid-svg-icons"
import axios from "axios"

interface ChatMessage {
	id: number
	chat_session_id: number
	message_type: string
	sender_type: "guest" | "agent" | "system"
	sender_id?: number
	message_text: string
	media_id?: number
	is_read: boolean
	delivered_at?: string
	read_at?: string
	created_at: string
	updated_at: string
}

interface ChatSessionData {
	ulid: string
	guest_identifier: string
	session_id?: number
	language?: number
	priority?: string
	saved_at?: string
}

const layout: any = inject("layout", {})

const open = ref(false)
const buttonRef = ref<HTMLElement | null>(null)
const panelRef = ref<HTMLElement | null>(null)
const loading = ref(false)

const isInitialLoad = ref(true)
const isLoadingMore = ref(false)
const isSending = ref(false)

const baseUrl = layout?.appUrl ?? ""

const chatSession = ref<{
	ulid: string
	guest_identifier: string
	session_id?: number
} | null>(null)

const messages = ref<ChatMessage[]>([])

/**
 * Save chat session into localStorage
 */
const saveChatSession = (sessionData: {
	ulid: string
	guest_identifier: string
	session_id?: number
}) => {
	const data = {
		ulid: sessionData.ulid,
		guest_identifier: sessionData.guest_identifier,
		session_id: sessionData.session_id,
		language: 64,
		priority: "normal",
		saved_at: new Date().toISOString(),
	}

	localStorage.setItem("chat", JSON.stringify(data))
	console.log("✅ Session saved:", sessionData.ulid)
}

/**
 * Load chat info from localStorage
 */
const loadChatSession = () => {
	const raw = localStorage.getItem("chat")
	if (!raw) return null

	try {
		const data = JSON.parse(raw)
		if (!data?.ulid || !data?.guest_identifier) {
			console.warn("❌ Invalid session data in localStorage")
			return null
		}

		return data
	} catch {
		return null
	}
}

/**
 * Create a new chat session
 */
const createSession = async (): Promise<ChatSessionData | null> => {
	const existingSession = loadChatSession()
	if (existingSession) {
		console.log("🔄 Using existing session:", existingSession.ulid)
		chatSession.value = existingSession
		return chatSession.value
	}

	loading.value = true
	console.log("🆕 Creating new session...")

	try {
		const response = await axios.post<{ data: ChatSessionData }>(
			`${baseUrl}/app/api/chats/sessions`,
			{
				language_id: 64,
				priority: "normal",
			}
		)

		if (response.data?.data) {
			const sessionData = response.data.data

			saveChatSession(sessionData)

			chatSession.value = {
				ulid: sessionData.ulid,
				guest_identifier: sessionData.guest_identifier,
				session_id: sessionData.session_id,
			}

			return chatSession.value
		}
		return null
	} catch (e) {
		console.error("❌ Error creating session", e)
		return null
	} finally {
		loading.value = false
	}
}

/**
 * Fetch messages for current session
 */
const getMessages = async (loadMore = false) => {
	if (!chatSession.value?.ulid) return

	try {
		if (loadMore) isLoadingMore.value = true

		let url = `${baseUrl}/app/api/chats/sessions/${chatSession.value.ulid}/messages`

		if (loadMore && messages.value.length > 0) {
			const cursor = messages.value[0].created_at
			url += `?cursor=${cursor}&limit=100`
		}

		const response = await axios.get(url)
		const fetched = response.data?.data?.messages.map((msg: ChatMessage) => ({
			...msg,
		}))
		console.log(fetched)

		if (!loadMore) {
			messages.value = fetched
		} else {
			messages.value = [...fetched, ...messages.value]
		}
	} catch (e) {
		console.error("❌ Error loading messages:", e)
	} finally {
		if (loadMore) {
			isLoadingMore.value = false
		}
	}
}

const toggle = () => {
	open.value = !open.value
	if (open.value) {
		initChat()
	}
}

const handleClickOutside = (e: MouseEvent) => {
	if (!open.value) return

	const target = e.target as Node

	const clickedOutside =
		panelRef.value &&
		!panelRef.value.contains(target) &&
		buttonRef.value &&
		!buttonRef.value.contains(target)

	if (clickedOutside) {
		open.value = false
	}
}

onMounted(() => {
	document.addEventListener("mousedown", handleClickOutside)
})

onBeforeUnmount(() => {
	document.removeEventListener("mousedown", handleClickOutside)
})

const sendMessage = async (messageText: string): Promise<any> => {
	if (!chatSession.value?.ulid) {
		console.error("❌ No active session for sending message")
		throw new Error("No active session")
	}

	if (isSending.value) {
		return
	}

	isSending.value = true

	try {
		const payload = {
			message_text: messageText,
			message_type: "text",
		}

		const response = await axios.post(
			`${baseUrl}/app/api/chats/messages/${chatSession.value.ulid}/send`,
			payload
		)

		console.log("✅ Message sent:", response.data)
		return response.data
	} catch (error) {
		console.error("❌ Error sending message:", error)
		throw error
	} finally {
		isSending.value = false
	}
}

/**
 * Initialize WebSocket connection for realtime chat
 */
let chatChannel: any = null

const stopChatWebSocket = () => {
	if (chatChannel) {
		console.log("🔌 Disconnecting from chat WebSocket...")

		chatChannel.stopListening("message")

		chatChannel = null
	}
}

const initWebSocket = () => {
	if (!chatSession.value?.ulid) {
		console.warn("⚠️ Cannot init WebSocket: No session data")
		return
	}

	const channelName = `chat-session.${chatSession.value.ulid}`
	console.log(`🔌 Subscribing with Echo to: ${channelName}`)

	if (!window.Echo) {
		console.error("❌ Echo is not initialized")
		return
	}

	stopChatWebSocket()

	// Subscribe with Echo
	chatChannel = window.Echo.channel(channelName)

	chatChannel.listen(".message", (eventData: { message: any }) => {
		const msg = eventData.message
		if (msg) {
			messages.value.push({
				...msg,
				created_at: new Date(msg.created_at),
			})
			forceScrollBottom()
		}
	})

	console.log("✅ Livechat WebSocket Ready")
}

/**
 * Initialize chat session and load messages
 */
const initChat = async () => {
	const session = await createSession()
	if (!session) {
		console.error("❌ Failed to initialize chat session")
		return
	}

	console.log("✅ Session ready:", {
		ulid: session.ulid,
		guest_identifier: session.guest_identifier,
	})

	await getMessages()
	isInitialLoad.value = true

	setTimeout(() => {
		if (isInitialLoad.value) {
			forceScrollBottom()
			isInitialLoad.value = false
		}
	}, 200)

	initWebSocket()
}

const forceScrollBottom = () => {
	setTimeout(() => {
		const container = document.querySelector(".messages-container")
		if (container) {
			container.scrollTop = container.scrollHeight
		}
	}, 150)
}

defineExpose({
	messages,
	sendMessage,
	chatSession,
	loading,
	isInitialLoad,
	isLoadingMore,
})
</script>

<template>
	<div>
		<button
			ref="buttonRef"
			@click="toggle"
			class="fixed bottom-20 right-5 z-[60] flex items-center gap-2 px-4 py-4 rounded-xl shadow-lg buttonPrimary">
			<FontAwesomeIcon :icon="faMessage" class="text-base" />
		</button>

		<transition
			enter-active-class="transition duration-150"
			enter-from-class="opacity-0 scale-95"
			enter-to-class="opacity-100 scale-100"
			leave-active-class="transition duration-150"
			leave-from-class="opacity-100 scale-100"
			leave-to-class="opacity-0 scale-95">
			<div
				v-if="open"
				ref="panelRef"
				class="fixed bottom-[9rem] right-5 z-[70] w-[350px] h-[350px] bg-white rounded-md overflow-hidden border">
				<MessageArea
					:messages="messages"
					:session="chatSession"
					:loading="loading"
					@send-message="sendMessage"
					@reload="(loadMore: any) => getMessages(loadMore)"
					@mounted="forceScrollBottom" />
			</div>
		</transition>
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
</style>
