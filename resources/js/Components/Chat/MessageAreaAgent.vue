<script setup lang="ts">
import { ref, watch, onMounted, nextTick } from "vue"
import { ChatMessage, SessionAPI } from "@/types/Chat/chat"

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

const sendMessage = () => {
	if (!newMessage.value.trim()) return
	emit("send-message", newMessage.value)
	newMessage.value = ""
}

const sendFile = () => {
	if (fileInput.value?.files?.length) {
		const file = fileInput.value.files[0]
		console.log("Send file:", file)
		// emit ke parent jika mau handle upload
	}
}

const scrollBottom = () => {
	nextTick(() => {
		if (messagesContainer.value) {
			messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
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

onMounted(() => {
	scrollBottom()
})
</script>

<template>
	<div class="flex flex-col h-full border rounded-md overflow-hidden bg-white">
		<!-- Header -->
		<div class="flex items-center justify-between px-4 py-2 border-b bg-gray-100">
			<button @click="$emit('back')" class="p-1 rounded hover:bg-gray-200">←</button>

			<div class="flex items-center gap-2">
				<img
					:src="'https://i.pravatar.cc/100?u=' + props.session?.ulid"
					class="w-10 h-10 rounded-full object-cover" />
				<span class="font-semibold">{{ props.session?.guest_identifier }}</span>
			</div>

			<button class="p-1 rounded hover:bg-gray-200">
				<!-- Option icon -->
				⋮
			</button>
		</div>

		<!-- Messages Area -->
		<div ref="messagesContainer" class="flex-1 overflow-y-auto px-4 py-3 space-y-2 bg-gray-50">
			<div
				v-for="msg in props.messages"
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
					{{ msg.message_text }}
				</div>
			</div>
		</div>

		<!-- Footer -->
		<div class="flex items-center gap-2 px-4 py-2 border-t bg-gray-100">
			<button @click="fileInput?.click()" class="p-2 rounded hover:bg-gray-200">📎</button>
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
				Send
			</button>
		</div>
	</div>
</template>

<style scoped>
/* Scrollbar style for chat messages */
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
