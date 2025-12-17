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
	faTimesCircle,
	faHistory,
	faAddressCard,
	faExchange,
	faMessage,
} from "@fortawesome/free-solid-svg-icons"
import axios from "axios"
import { capitalize } from "@/Composables/capitalize"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"

const props = defineProps<{
	messages: ChatMessage[]
	session: SessionAPI | null
}>()

const emit = defineEmits<{
	(e: "send-message", message: string): void
	(e: "back"): void
	(e: "close-session"): void
	(e: "view-history"): void
	(e: "view-user-profile"): void
	(e: "view-message-details"): void
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
const isMenuOpen = ref(false)
const ellipsisBtn = ref<HTMLElement | null>(null)
const menuRef = ref<HTMLElement | null>(null)
const toggleMenu = () => {
	isMenuOpen.value = !isMenuOpen.value
}
const isClosed = computed(() => chatSession.value?.status === "closed")
const textareaHeight = ref(50)
const messageInput = ref(null)

const handleKeydown = (event: KeyboardEvent) => {
	if (event.key === "Enter" && !event.shiftKey) {
		event.preventDefault()
		sendMessage()
		messageInput.value.style.height = "auto"
		return
	}

	if (event.key === "Enter" && event.ctrlKey) {
		event.preventDefault()
		messageInput.value.style.height = "auto"
		sendMessage()
	}
}

const autoResize = () => {
	nextTick(() => {
		if (messageInput.value) {
			messageInput.value.style.height = "auto"

			const scrollHeight = messageInput.value.scrollHeight
			const minHeight = 50
			const maxHeight = 120

			let newHeight = Math.max(scrollHeight, minHeight)
			newHeight = Math.min(newHeight, maxHeight)

			messageInput.value.style.height = newHeight + "px"
			messageInput.value.style.overflowY = newHeight >= maxHeight ? "auto" : "hidden"
		}
	})
}

const priorityClass = (p?: string) => {
	const key = String(p || "").toLowerCase()
	switch (key) {
		case "low":
			return "border-blue-500 text-blue-500"
		case "normal":
			return "border-gray-400 text-gray-400"
		case "high":
			return "border-yellow-500 text-yellow-500"
		case "urgent":
			return "border-red-500 text-red-500"
		default:
			return "border-gray-300 text-gray-300"
	}
}

const sendMessage = () => {
	if (!newMessage.value.trim()) return
	emit("send-message", newMessage.value)
	newMessage.value = ""
	messageInput.value.style.height = "auto"
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

const handleClickOutside = (e: MouseEvent) => {
	if (!isMenuOpen.value) return
	if (document.querySelector('[role="dialog"]')) return

	const target = e.target as Node
	if (
		menuRef.value &&
		!menuRef.value.contains(target) &&
		ellipsisBtn.value &&
		!ellipsisBtn.value.contains(target)
	) {
		isMenuOpen.value = false
	}
}

const onViewHistory = () => {
	isMenuOpen.value = false
	emit("view-history")
}

const onViewUserProfile = () => {
	isMenuOpen.value = false
	const slug = chatSession.value?.web_user?.slug || null
	emit("view-user-profile", slug)
}

const onViewMessageDetails = () => {
	isMenuOpen.value = false
	emit("view-message-details")
}

const initWebSocket = () => {
	if (!chatSession.value?.ulid) return
	if (!window.Echo) return
	stopChatWebSocket()
	const channelName = `chat-session.${chatSession.value.ulid}`
	chatChannel = window.Echo.channel(channelName)
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
	document.addEventListener("mousedown", handleClickOutside)
})

onUnmounted(() => {
	stopChatWebSocket()
	document.removeEventListener("mousedown", handleClickOutside)
})
</script>

<template>
	<div
		class="flex flex-col max-h-[85vh] sm:max-h-[80vh] md:max-h-[75vh] lg:max-h-[100vh] h-[clamp(420px,70vh,900px)] overflow-hidden bg-white">
		<div class="flex-none flex items-center justify-between border px-4 py-3 bg-gray-100">
			<button @click="$emit('back')" class="p-1">
				<FontAwesomeIcon
					:icon="faArrowLeft"
					class="text-base text-gray-400 hover:text-blue-600" />
			</button>

			<div class="flex items-center gap-2">
				<img
					:src="'https://i.pravatar.cc/100?u=' + props.session?.ulid"
					class="w-10 h-10 rounded-full object-cover" />
				<div class="flex flex-col justify-start items-start gap-1">
					<span class="font-semibold">{{
						capitalize(props.session?.guest_identifier || props.session?.contact_name)
					}}</span>
					<div class="flex items-start gap-1">
						<span
							v-if="props.session?.web_user"
							class="inline-flex items-center justify-center px-2 py-0.5 mt-1 rounded-sm text-[11px] font-medium bg-green-100 text-green-800">
							{{ trans("Customer") }}
						</span>
						<span
							v-else
							class="inline-flex items-center justify-center px-2 py-0.5 mt-1 rounded-sm text-[11px] font-medium bg-blue-100 text-blue-800">
							{{ trans("Guest") }}
						</span>
						<span
							class="inline-flex items-center justify-center border px-2 py-0.5 rounded-sm text-[11px] font-medium"
							:class="priorityClass(props.session?.priority)">
							{{ capitalize(props.session?.priority || "") }}
						</span>
					</div>
				</div>
			</div>
			<div class="relative">
				<button ref="ellipsisBtn" class="p-1" @click="toggleMenu">
					<FontAwesomeIcon
						:icon="faEllipsisVertical"
						class="text-base text-gray-400 hover:text-blue-600" />
				</button>

				<div
					v-if="isMenuOpen && !isClosed"
					ref="menuRef"
					class="absolute right-0 mt-2 w-56 bg-white border rounded-md shadow-lg z-50">
					<ModalConfirmationDelete
						:routeDelete="{
							name: 'grp.org.crm.agents.sessions.close',
							parameters: [route().params.organisation, props.session?.ulid],
							method: 'patch',
						}"
						:title="trans('Are you sure you want to close this session?')"
						:noLabel="trans('Close')"
						@success="
							() => {
								$emit('close-session')
								isMenuOpen = false
							}
						">
						<template #default="{ changeModel }">
							<div
								@click="changeModel"
								class="flex items-center justify-start gap-2 px-4 py-3 hover:bg-gray-100 group">
								<FontAwesomeIcon
									:icon="faTimesCircle"
									class="text-base text-gray-400 group-hover:text-red-500" />
								{{ trans("Close Chat Session") }}
							</div>
						</template>
					</ModalConfirmationDelete>

					<div
						class="flex items-center justify-center gap-2 px-4 py-3 hover:bg-gray-100 group">
						<FontAwesomeIcon
							:icon="faHistory"
							class="text-base text-gray-400 group-hover:text-blue-500" />
						<button @click="onViewHistory" class="w-full text-left">
							{{ trans("History chat") }}
						</button>
					</div>

					<div
						class="flex items-center justify-center gap-2 px-4 py-3 hover:bg-gray-100 group">
						<FontAwesomeIcon
							:icon="faAddressCard"
							class="text-base text-gray-400 group-hover:text-blue-500" />
						<button @click="onViewUserProfile" class="w-full text-left">
							{{ trans("Detail User Profile") }}
						</button>
					</div>

					<div
						class="flex items-center justify-center gap-2 px-4 py-3 hover:bg-gray-100 group">
						<FontAwesomeIcon
							:icon="faMessage"
							class="text-base text-gray-400 group-hover:text-blue-500" />
						<button @click="onViewMessageDetails" class="w-full text-left">
							{{ trans("Message Details") }}
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Messages Area -->
		<div ref="messagesContainer" class="flex-1 overflow-y-auto px-4 py-3 space-y-4">
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
						<span
							class="text-xs text-gray-400 block mt-1"
							:class="{
								'text-white': msg.sender_type === 'agent',
							}">
							{{ formatTime(msg.created_at) }}
						</span>
					</div>
				</div>
			</template>
		</div>

		<!-- Footer -->

		<div
			v-if="!isClosed"
			class="flex-none flex items-center gap-2 px-4 py-4 border-t bg-gray-10">
			<button @click="fileInput?.click()" class="p-2 rounded hover:bg-gray-200">
				<FontAwesomeIcon :icon="faPaperclip" class="text-base" />
			</button>
			<input type="file" ref="fileInput" class="hidden" @change="sendFile" />

			<textarea
				ref="messageInput"
				v-model="newMessage"
				placeholder="Type a message..."
				class="flex-1 px-3 py-2 rounded-lg border focus:border-blue-500 focus:ring-0 resize-none overflow-hidden min-h-[50px] max-h-[120px]"
				@keydown="handleKeydown"
				@input="autoResize"
				rows="1" />

			<button
				@click="sendMessage"
				class="px-4 py-2 buttonPrimary rounded-full bg-blue-500 text-white hover:bg-blue-600">
				<FontAwesomeIcon :icon="faPaperPlane" class="text-base" />
			</button>
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
