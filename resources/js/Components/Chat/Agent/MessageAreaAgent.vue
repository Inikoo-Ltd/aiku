<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, inject, computed, nextTick } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
	faPaperPlane,
	faArrowLeft,
	faEllipsisVertical,
	faTimesCircle,
	faMessage,
} from "@fortawesome/free-solid-svg-icons"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import type { ChatMessage, SessionAPI } from "@/types/Chat/chat"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@/Components/Image.vue"
import { faUser } from "@far"
import BubbleChat from "@/Components/Chat/BubbleChat.vue"

type LocalMessageStatus = "sending" | "sent" | "failed"

type LocalChatMessage = ChatMessage & {
	_status?: LocalMessageStatus
	_tempId?: string
}

const props = defineProps<{
	messages: ChatMessage[]
	session: SessionAPI | null
}>()

const emit = defineEmits([
	"send-message",
	"back",
	"close-session",
	"view-history",
	"view-user-profile",
	"view-message-details",
])

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

const messagesLocal = ref<LocalChatMessage[]>([])
const newMessage = ref("")

const fileInput = ref<HTMLInputElement>()
const messageInput = ref<HTMLTextAreaElement>()
const messagesContainer = ref<HTMLDivElement>()

const isMenuOpen = ref(false)
const isLoadingMore = ref(false)
const canLoadMore = ref(false)
const nextCursor = ref<string | null>(null)

const chatSession = computed(() => props.session)
const isClosed = computed(() => chatSession.value?.status === "closed")
const menuRef = ref<HTMLElement | null>(null)



const scrollBottom = () =>
	nextTick(() => {
		if (messagesContainer.value) {
			messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
		}
	})

const autoResize = () => {
	if (!messageInput.value) return
	messageInput.value.style.height = "auto"
	messageInput.value.style.height =
		Math.min(messageInput.value.scrollHeight, 120) + "px"
}


const sendMessage = async () => {
	if (!newMessage.value.trim()) return

	const tempId = `tmp-${Date.now()}`

	const optimisticMessage: LocalChatMessage = {
		id: tempId as any,
		_tempId: tempId,
		message_text: newMessage.value,
		sender_type: "agent",
		created_at: new Date().toISOString(),
		_status: "sending",
	}

	messagesLocal.value.push(optimisticMessage)
	scrollBottom()

	const text = newMessage.value
	newMessage.value = ""
	autoResize()

	try {
		await emit("send-message", text)
		const msg = messagesLocal.value.find(m => m._tempId === tempId)
		if (msg) msg._status = "sending"
	} catch {
		const msg = messagesLocal.value.find(m => m._tempId === tempId)
		if (msg) msg._status = "failed"
	}
}


const resendMessage = async (msg: LocalChatMessage) => {
	if (msg._status === "sending") return

	msg._status = "sending"

	try {
		await emit("send-message", msg.message_text)
		msg._status = "sent"
	} catch {
		msg._status = "failed"
	}
}



const getMessages = async (loadMore = false) => {
	if (!chatSession.value?.ulid || (loadMore && !canLoadMore.value)) return

	isLoadingMore.value = loadMore

	const cursor =
		loadMore && nextCursor.value ? `?cursor=${nextCursor.value}&limit=50` : "?limit=10"

	const { data } = await axios.get(
		`${baseUrl}/app/api/chats/sessions/${chatSession.value.ulid}/messages${cursor}`
	)

	const messages = data?.data?.messages ?? data?.messages ?? []

	if (!loadMore) {
		messagesLocal.value = messages.map((m: ChatMessage) => ({
			...m,
			_status: "sent",
		}))
	} else {
		messagesLocal.value.unshift(
			...messages.map((m: ChatMessage) => ({ ...m, _status: "sent" }))
		)
	}

	const page = data?.data?.pagination ?? data?.pagination
	canLoadMore.value = !!page?.has_more
	nextCursor.value = page?.next_cursor ?? null

	isLoadingMore.value = false
	scrollBottom()
}



const groupedMessages = computed(() => {
	const groups: Record<string, LocalChatMessage[]> = {}

	messagesLocal.value
		.slice()
		.sort((a, b) => +new Date(a.created_at) - +new Date(b.created_at))
		.forEach((msg) => {
			const label = new Intl.DateTimeFormat("id-ID", {
				day: "2-digit",
				month: "long",
				year: "numeric",
			}).format(new Date(msg.created_at))

			;(groups[label] ??= []).push(msg)
		})

	return groups
})



let chatChannel: any = null

const stopSocket = () => {
	chatChannel?.stopListening(".message")
	chatChannel = null
}

const initSocket = () => {
	if (!chatSession.value?.ulid || !window.Echo) return
	stopSocket()

	chatChannel = window.Echo.channel(`chat-session.${chatSession.value.ulid}`)

	chatChannel.listen(".message", ({ message }: any) => {
		const index = messagesLocal.value.findIndex(
			m =>
				m._status === "sending" &&
				m.message_text === message.message_text &&
				m.sender_type === "agent"
		)

		if (index !== -1) {
			messagesLocal.value[index] = { ...message, _status: "sent" }
		} else {
			messagesLocal.value.push({ ...message, _status: "sent" })
		}

		scrollBottom()
	})
}


const onViewMessageDetails = () => {
	isMenuOpen.value = false
	emit("view-message-details")
}


watch(() => chatSession.value?.ulid, async () => {
	stopSocket()
	messagesLocal.value = []
	await getMessages()
	initSocket()
})

onMounted(async () => {
	await getMessages()
	initSocket()
	document.addEventListener("click", handleClickOutside)
})

onUnmounted(() => {
	stopSocket()
	document.removeEventListener("click", handleClickOutside)
})

const handleClickOutside = (e: MouseEvent) => {
	if (isMenuOpen.value && menuRef.value && !menuRef.value.contains(e.target as Node)) {
		isMenuOpen.value = false
	}
}
</script>



<template>
	<div class="flex flex-col h-full bg-white  overflow-hidden">

		<!-- Header -->
		<header class="flex items-center gap-3 px-3 py-2 border-b bg-gray-50">
			<button @click="$emit('back')">
				<FontAwesomeIcon :icon="faArrowLeft" class="text-gray-400" />
			</button>


			<div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 bg-gray-100 text-gray-500">
				<Image v-if="session.image" :src="session.image" class="w-full h-full rounded-full object-cover" />

				<FontAwesomeIcon v-else :icon="faUser" class="text-sm" />
			</div>

			<span
				class="flex-1 text-sm font-semibold truncate cursor-pointer primary-text hover:primary-text-hover transition-colors"
				@click="onViewMessageDetails">
				{{ session?.guest_identifier || session?.contact_name }}
			</span>

				<div class="relative" ref="menuRef">
				<button @click.stop="isMenuOpen = !isMenuOpen">
					<FontAwesomeIcon :icon="faEllipsisVertical" class="text-gray-400" />
				</button>

				<div v-if="isMenuOpen && !isClosed"
					class="absolute right-0 mt-2 w-56 bg-white border rounded-md shadow-sm z-50">

					<ModalConfirmationDelete :routeDelete="{
						name: 'grp.org.crm.agents.sessions.close',
						parameters: [session.organisation.id, session?.ulid],
						method: 'patch',
					}" :title="trans('Are you sure you want to close this session?')" @success="$emit('close-session')">

						<template #default="{ changeModel }">
							<button @click="changeModel" class="menu-item text-red-600">
								<FontAwesomeIcon :icon="faTimesCircle" />
								{{ trans('Close Chat Session') }}
							</button>
						</template>
					</ModalConfirmationDelete>


					<button class="menu-item" @click="onViewMessageDetails">
						<FontAwesomeIcon :icon="faMessage" /> {{ trans('Message Details') }}
					</button>
				</div>
			</div>
		</header>

		<!-- Messages -->
		<div ref="messagesContainer" class="flex-1 overflow-y-auto px-3 py-2 space-y-3 bg-[#f6f6f7]">
			<template v-for="(msgs, date) in groupedMessages" :key="date">
				<div class="text-center text-xs text-gray-400">{{ date }}</div>

				<div v-for="msg in msgs" :key="msg.id" class="flex"
					:class="msg.sender_type === 'agent' ? 'justify-end' : 'justify-start'">


					<BubbleChat :message="msg" viewerType="agent" />

					<!-- 	<div class="px-3 py-2 rounded-lg max-w-[75%] text-sm flex items-center gap-2 cursor-default" :class="msg.sender_type === 'agent'
						? 'bubble-chat text-white'
						: 'bg-gray-200'">
						<span>{{ msg.message_text }}</span>

						<span v-if="msg._status === 'sending'" class="text-xs opacity-70 animate-pulse">
							<LoadingIcon />
						</span>


						<button v-if="msg._status === 'failed'" @click="resendMessage(msg)"
							class="text-xs text-red-300 hover:text-red-500" title="Click to resend">
							<FontAwesomeIcon :icon="faExclamation" />
						</button>
					</div> -->

				</div>
			</template>
		</div>

		<!-- Footer -->
		<footer v-if="!isClosed" class="flex items-center gap-2 px-3 py-2 border-t bg-white">

			<!-- Attachment -->
			<!-- <button @click="fileInput?.click()"
				class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-gray-100 shrink-0">
				<FontAwesomeIcon :icon="faPaperclip" />
			</button> -->

			<!-- Message Input -->
			<textarea ref="messageInput" v-model="newMessage" @input="autoResize"
				@keydown.enter.exact.prevent="sendMessage" rows="1" placeholder="Type message..." class="flex-1 resize-none border rounded-lg px-3 py-2 text-sm
		       leading-5  focus:outline-none" />

			<!-- Send -->
			<Button @click="sendMessage" :icon="faPaperPlane"></Button>
		</footer>
	</div>
</template>

<style scoped>
.menu-item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 10px 16px;
	width: 100%;
	font-size: 14px;
}

.menu-item:hover {
	background: #f3f4f6;
}

::-webkit-scrollbar {
	width: 5px;
}

::-webkit-scrollbar-thumb {
	background: rgba(0, 0, 0, 0.2);
	border-radius: 4px;
}
</style>
