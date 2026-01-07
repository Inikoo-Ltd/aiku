<script setup lang="ts">
import { ref, inject, onMounted, watch, computed } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStar, faPlus, faSpinner, faPaperPlane } from "@fortawesome/free-solid-svg-icons"
import GuestProfileForm from "@/Components/Chat/Customer/GuestProfileForm.vue"
import BubbleChat from "@/Components/Chat/BubbleChat.vue"

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

const emit = defineEmits(["send-message", "reload", "mounted", "new-session"])

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""
const input = ref("")
const isSending = ref(false)
const messagesContainer = ref<HTMLElement | null>(null)
const selectedRating = ref<number | null>(null)
const starPop = ref<number | null>(null)


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

const groupedMessages = computed(() => {
	const groups: Record<string, LocalChatMessage[]> = {}

	props.messages
		.slice()
		.sort((a, b) => +new Date(a.created_at) - +new Date(b.created_at))
		.forEach((msg) => {
			const label = new Intl.DateTimeFormat("id-ID", {
				day: "2-digit",
				month: "long",
				year: "numeric",
			}).format(new Date(msg.created_at))

				; (groups[label] ??= []).push(msg)
		})

	return groups
})

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


onMounted(() => {
	emit("mounted")
	scrollToBottom()
})

</script>

<template>
	<div class="flex flex-col bg-white">

		<!-- Messages -->
		<div
			v-if="messages.length"
			ref="messagesContainer"
			@scroll="onScroll"
			class="bg-gray-50 px-3 py-2 space-y-2 overflow-y-auto min-h-[350px] max-h-[calc(100vh-400px)]  scroll-smooth"
		>
			<template v-for="(group, date) in groupedMessages" :key="date">
				<div class="mx-auto text-xs text-gray-400 flex justify-center">
					{{ date }}
				</div>

				<div
					v-for="m in group"
					:key="m.id"
					class="flex"
					:class="isUserMessage(m) ? 'justify-end' : 'justify-start'"
				>
					<BubbleChat :message="m" viewerType="user" />
				</div>
			</template>

			<div v-if="loading" class="flex justify-center py-3">
				<div
					class="w-5 h-5 rounded-full border-2 border-transparent animate-spin"
					:style="{ borderTopColor: layout.app.theme[4] }"
				/>
			</div>
		</div>

		<!-- Empty -->
		<div
			v-if="!messages.length && isLoggedIn"
			class="flex-1 grid place-content-center text-gray-400 text-sm"
		>
			{{ trans("Start the conversation") }}
		</div>

		<!-- Rating -->
		<div
			v-if="isRating"
			class="flex justify-between items-center border-t px-3 py-2"
		>
			<div class="flex gap-1">
				<button v-for="n in 5" :key="n" @click="updateRating(n)">
					<FontAwesomeIcon
						:icon="faStar"
						:class="n <= (selectedRating ?? rating ?? 0)
							? 'text-yellow-400'
							: 'text-gray-300'"
					/>
				</button>
			</div>

			<button
				@click="$emit('new-session')"
				class="flex items-center gap-2 text-sm px-3 py-1 rounded-sm border"
				:style="{
					borderColor: layout.app.theme[4],
					color: layout.app.theme[4]
				}"
			>
				<FontAwesomeIcon :icon="faPlus" />
				New Chat
			</button>
		</div>

		<!-- Input -->
		<div
			v-if="!isRating"
			class="border-t p-2 flex gap-2 items-end"
		>
			<GuestProfileForm
				v-if="!isLoggedIn && !guestProfileSubmitted"
				:sessionUlid="session?.ulid"
				@submitted="onGuestProfileSubmitted"
			/>

			<template v-else>
				<textarea
					v-model="input"
					rows="1"
					@keydown="handleKeyDown"
					placeholder="Type a message..."
					class="flex-1 resize-none px-3 py-2 rounded-lg text-sm outline-none border"
					:style="{ borderColor: layout.app.theme[4] }"
				/>

				<Button
					:icon="isSending ? faSpinner : faPaperPlane"
					:loading="isSending"
					:disabled="!input.trim()"
					@click="sendMessage"
				/>
			</template>
		</div>

	</div>
</template>


<style scoped>
@keyframes spin {
	to {
		transform: rotate(360deg);
	}
}
</style>
