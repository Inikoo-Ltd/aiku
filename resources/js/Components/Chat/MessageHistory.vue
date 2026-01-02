<script setup lang="ts">
import { ref, inject, onMounted, nextTick, computed } from "vue"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowLeft, faStar } from "@fortawesome/free-solid-svg-icons"
import type { ChatMessage, SessionAPI } from "@/types/Chat/chat"
import BubbleChat from "@/Components/Chat/BubbleChat.vue"

type ViewerType = "user" | "agent"

/* ================= PROPS & EMITS ================= */

const props = defineProps<{
	sessionUlid: string
	session?: Partial<SessionAPI> | null
	viewerType: ViewerType
}>()

const emit = defineEmits<{
	(e: "back"): void
}>()

/* ================= GLOBAL ================= */

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

/* ================= STATE ================= */

const messages = ref<ChatMessage[]>([])
const messagesContainer = ref<HTMLElement | null>(null)

const isLoading = ref(false)
const isLoadingMore = ref(false)
const canLoadMore = ref(false)
const nextCursor = ref<string | null>(null)

const rating = ref<number | null>(null)
const isClosed = ref(false)

/* ================= COMPUTED ================= */

const statusLabel = computed(() => (isClosed.value ? "Closed" : "Active"))

const statusClass = computed(() =>
	isClosed.value
		? "bg-red-100 text-red-700 border border-red-200"
		: "bg-green-100 text-green-700 border border-green-200"
)

/* ================= HELPERS ================= */

const formatDate = (timestamp: string) => {
	const date = new Date(timestamp)
	const today = new Date()
	const yesterday = new Date()
	yesterday.setDate(today.getDate() - 1)

	const isSameDay = (a: Date, b: Date) =>
		a.getFullYear() === b.getFullYear() &&
		a.getMonth() === b.getMonth() &&
		a.getDate() === b.getDate()

	if (isSameDay(date, today)) return "Today"
	if (isSameDay(date, yesterday)) return "Yesterday"

	return date.toLocaleDateString("id-ID", {
		day: "2-digit",
		month: "long",
		year: "numeric",
	})
}

/**
 * Apakah message berasal dari viewer aktif
 */
const isFromViewer = (message: ChatMessage) => {
	if (props.viewerType === "agent") {
		return message.sender_type === "agent"
	}

	return ["user", "guest"].includes(message.sender_type)
}

/* ================= GROUPING ================= */

const groupedMessages = computed(() => {
	const groups: Record<string, ChatMessage[]> = {}

	const sorted = [...messages.value].sort(
		(a, b) =>
			new Date(a.created_at).getTime() -
			new Date(b.created_at).getTime()
	)

	for (const message of sorted) {
		const label = formatDate(message.created_at)
		if (!groups[label]) groups[label] = []
		groups[label].push(message)
	}

	return groups
})

/* ================= UI ================= */

const scrollBottom = () => {
	nextTick(() => {
		if (messagesContainer.value) {
			messagesContainer.value.scrollTop =
				messagesContainer.value.scrollHeight
		}
	})
}

/* ================= API ================= */

const getMessages = async (loadMore = false) => {
	if (!props.sessionUlid) return

	try {
		loadMore ? (isLoadingMore.value = true) : (isLoading.value = true)

		let url = `${baseUrl}/app/api/chats/sessions/${props.sessionUlid}/messages`

		if (loadMore && nextCursor.value) {
			url += `?cursor=${nextCursor.value}&limit=50`
		} else {
			url += `?limit=20`
		}

		const res = await axios.get(url)

		const fetched: ChatMessage[] =
			res.data?.data?.messages ?? res.data?.messages ?? []

		messages.value = loadMore
			? [...fetched, ...messages.value]
			: fetched

		const page = res.data?.data?.pagination ?? res.data?.pagination
		canLoadMore.value = !!page?.has_more
		nextCursor.value = page?.next_cursor ?? null

		isClosed.value =
			(res.data?.data?.session_status ??
				res.data?.session_status) === "closed"

		rating.value =
			res.data?.data?.rating ??
			res.data?.rating ??
			rating.value
	} finally {
		isLoading.value = false
		isLoadingMore.value = false
	}
}

const updateRating = async (value: number) => {
	rating.value = value

	try {
		await axios.put(
			`${baseUrl}/app/api/chats/sessions/${props.sessionUlid}/update`,
			{ rating: value }
		)
	} catch {
		// silent fail
	}
}

/* ================= LIFECYCLE ================= */

onMounted(async () => {
	await getMessages()
	scrollBottom()
})
</script>


<template>
	<div class="flex flex-col h-full bg-white">
		<!-- Header -->
		<div class="flex items-center justify-between px-4 py-1 bg-gray-50">
			<button class="p-1" @click="emit('back')">
				<FontAwesomeIcon
					:icon="faArrowLeft"
					class="text-gray-500 hover:text-blue-600" />
			</button>

			<span class="px-2 py-0.5 text-xs rounded-full" :class="statusClass">
				{{ statusLabel }}
			</span>
		</div>

		<!-- Messages -->
		<div ref="messagesContainer" class="flex-1 overflow-y-auto px-4 py-3 space-y-4">
			<div class="flex justify-center mb-2">
				<button
					v-if="canLoadMore && messages.length && !isLoadingMore"
					@click="getMessages(true)"
					class="px-2 py-1 text-xs border rounded">
					Load more
				</button>
				<span v-if="isLoadingMore" class="text-xs text-gray-500 ml-2">
					Loading...
				</span>
			</div>

			<template v-for="(group, label) in groupedMessages" :key="label">
				<div class="flex justify-center">
					<div class="px-3 py-1 bg-gray-200 text-gray-600 text-xs rounded-full">
						{{ label }}
					</div>
				</div>

				<div
					v-for="message in group"
					:key="message.id"
					class="flex"
					:class="isFromViewer(message) ? 'justify-end' : 'justify-start'">

					<BubbleChat
						:message="message"
						:viewerType="viewerType" />
				</div>
			</template>

			<div
				v-if="!messages.length && !isLoading"
				class="text-sm text-gray-500 text-center py-8">
				No messages
			</div>
		</div>

		<!-- Rating -->
		<div class="border-t bg-white px-4 py-3">
			<div class="flex items-center justify-between">
				<div class="text-sm font-medium">Rate this chat</div>
				<div class="flex gap-1">
					<button v-for="n in 5" :key="n" @click="updateRating(n)">
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
