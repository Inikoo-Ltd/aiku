<script setup lang="ts">
import { ref, inject, onMounted, nextTick, computed } from "vue"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowLeft, faStar } from "@fortawesome/free-solid-svg-icons"
import type { ChatMessage, SessionAPI } from "@/types/Chat/chat"

const props = defineProps<{
	sessionUlid: string
	session?: Partial<SessionAPI> | null
}>()

const emit = defineEmits<{
	(e: "back"): void
}>()

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

const messages = ref<ChatMessage[]>([])
const isLoading = ref(false)
const isLoadingMore = ref(false)
const canLoadMore = ref(false)
const nextCursor = ref<string | null>(null)
const rating = ref<number | null>(null)
const isClosed = ref(false)
const messagesContainer = ref<HTMLElement | null>(null)

const statusLabel = computed(() => (isClosed.value ? "Closed" : "Active"))
const statusClass = computed(() =>
	isClosed.value
		? "bg-red-100 text-red-700 border border-red-200"
		: "bg-green-100 text-green-700 border border-green-200"
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
	const yesterday = new Date()
	yesterday.setDate(today.getDate() - 1)
	const isSameDay = (d1: Date, d2: Date) =>
		d1.getFullYear() === d2.getFullYear() &&
		d1.getMonth() === d2.getMonth() &&
		d1.getDate() === d2.getDate()
	if (isSameDay(date, today)) return "Today"
	if (isSameDay(date, yesterday)) return "Yesterday"
	return date.toLocaleDateString("id-ID", { day: "2-digit", month: "long", year: "numeric" })
}

const groupedMessages = computed(() => {
	const groups: Record<string, ChatMessage[]> = {}
	const sorted = [...messages.value].sort(
		(a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
	)
	sorted.forEach((m) => {
		const label = formatDate(m.created_at)
		if (!groups[label]) groups[label] = []
		groups[label].push(m)
	})
	return groups
})

const scrollBottom = () => {
	nextTick(() => {
		if (messagesContainer.value) {
			messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
		}
	})
}

const getMessages = async (loadMore = false) => {
	if (!props.sessionUlid) return
	try {
		if (loadMore) isLoadingMore.value = true
		else isLoading.value = true

		let url = `${baseUrl}/app/api/chats/sessions/${props.sessionUlid}/messages`
		if (loadMore) {
			if (!nextCursor.value) {
				canLoadMore.value = false
			} else {
				url += `?cursor=${nextCursor.value}&limit=50`
			}
		} else {
			url += `?limit=20`
		}

		const res = await axios.get(url)
		const fetched: ChatMessage[] = (res.data?.data?.messages ?? res.data?.messages ?? []).map(
			(msg: any) => ({ ...msg })
		)

		if (!loadMore) messages.value = fetched
		else messages.value = [...fetched, ...messages.value]

		const page = res.data?.data?.pagination ?? res.data?.pagination ?? null
		const hasMore = !!(page && page.has_more)
		canLoadMore.value = hasMore
		nextCursor.value = hasMore ? page.next_cursor : null

		isClosed.value = (res.data?.data?.session_status ?? res.data?.session_status) === "closed"
		const r = res.data?.data?.rating ?? res.data?.rating
		rating.value = r ?? rating.value
	} finally {
		isLoading.value = false
		isLoadingMore.value = false
	}
}

const updateRating = async (r: number) => {
	rating.value = r
	try {
		await axios.put(`${baseUrl}/app/api/chats/sessions/${props.sessionUlid}/update`, {
			rating: r,
		})
	} catch (e) {}
}

onMounted(async () => {
	await getMessages(false)
	scrollBottom()
})
</script>

<template>
	<div class="flex flex-col h-full bg-white">
		<div class="flex-none flex items-center justify-between px-4 py-1 bg-gray-50">
			<button class="p-1" @click="emit('back')">
				<FontAwesomeIcon
					:icon="faArrowLeft"
					class="text-base text-gray-500 hover:text-blue-600" />
			</button>

			<span class="px-2 py-0.5 text-xs rounded-full" :class="statusClass">{{
				statusLabel
			}}</span>
		</div>

		<div ref="messagesContainer" class="flex-1 overflow-y-auto px-4 py-3 space-y-4">
			<div class="flex justify-center mb-2">
				<button
					v-if="canLoadMore && messages.length && !isLoadingMore"
					@click="getMessages(true)"
					class="px-2 py-1 text-xs border rounded">
					Load more
				</button>
				<span v-if="isLoadingMore" class="text-xs text-gray-500 ml-2">Loading...</span>
			</div>

			<template v-for="(group, label) in groupedMessages" :key="label">
				<div class="flex justify-center">
					<div class="px-3 py-1 bg-gray-200 text-gray-600 text-xs rounded-full">
						{{ label }}
					</div>
				</div>
				<div
					v-for="m in group"
					:key="m.id"
					class="flex"
					:class="{
						'justify-end': m.sender_type === 'agent',
						'justify-start': m.sender_type !== 'agent',
					}">
					<div
						class="max-w-[70%] px-3 py-2 rounded-lg break-words"
						:class="{
							'bg-blue-500 text-white': m.sender_type === 'agent',
							'bg-gray-100 text-gray-900': m.sender_type !== 'agent',
						}">
						<div class="text-sm whitespace-pre-line">{{ m.message_text }}</div>
						<span
							class="text-xs block mt-1"
							:class="{
								'text-white/80': m.sender_type === 'agent',
								'text-gray-500': m.sender_type !== 'agent',
							}"
							>{{ formatTime(m.created_at) }}</span
						>
					</div>
				</div>
			</template>
			<div
				v-if="!messages.length && !isLoading"
				class="text-sm text-gray-500 text-center py-8">
				No messages
			</div>
		</div>

		<div class="flex-none border-t bg-white px-4 py-3">
			<div class="flex items-center justify-between">
				<div class="text-sm font-medium">Rate this chat</div>
				<div class="flex items-center gap-1">
					<button v-for="n in 5" :key="n" class="p-1" @click="updateRating(n)">
						<FontAwesomeIcon
							:icon="faStar"
							class="text-lg"
							:class="n <= (rating ?? 0) ? 'text-yellow-400' : 'text-gray-300'" />
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped>
.user-bubble {
	background-color: v-bind("layout?.app?.theme[4]") !important;
	color: v-bind("layout?.app?.theme[5]") !important;
	border: v-bind("`1px solid color-mix(in srgb, ${layout?.app?.theme[4]} 80%, black)`");
}
.agent-bubble {
	background-color: #f3f4f6;
	color: #1f2937;
	border: 1px solid #e5e7eb;
}
.system-bubble {
	background-color: #fef3c7;
	color: #92400e;
	border: 1px solid #fbbf24;
	font-style: italic;
}
</style>
