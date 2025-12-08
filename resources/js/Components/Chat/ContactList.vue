<script setup lang="ts">
import { ref, inject, computed, watch, onMounted, nextTick } from "vue"
import Button from "../Elements/Buttons/Button.vue"
import { faPaperPlane } from "@fas"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { capitalize } from "@/Composables/capitalize"
import { Contact, SessionAPI, ChatMessage } from "@/types/Chat/chat"
import MessageAreaAgent from "@/Components/Chat/MessageAreaAgent.vue"
import { routeType } from "@/types/route"

const layout: any = inject("layout", {})

const baseUrl = layout?.appUrl ?? ""
const contacts = ref<Contact[]>([])
const selectedSession = ref<SessionAPI | null>(null)
const messages = ref<ChatMessage[]>([])

const activeTab = ref("waiting")
const isAssigning = ref<Record<string, boolean>>({})
const errorMessage = ref<string | null>(null)

const reloadContacts = async () => {
	try {
		const res = await axios.get(`${baseUrl}/app/api/chats/sessions`, {
			params: { statuses: [activeTab.value] },
		})

		contacts.value = res.data.data.sessions.map(
			(s: SessionAPI): Contact => ({
				id: s.id,
				ulid: s.ulid,
				name: s.guest_identifier,
				avatar: `https://i.pravatar.cc/100?u=${s.ulid}`,
				lastMessage: s.last_message?.message ?? "",
				lastMessageTime: s.last_message?.created_at
					? formatTime(s.last_message.created_at)
					: undefined,
				lastMessageTimestamp: s.last_message?.created_at_timestamp,
				unread: s.unread_count,
				status: s.status,
			})
		)
	} catch (e) {
		console.error("Failed to reload contacts:", e)
	}
}

onMounted(() => {
	window.Echo.join("chat-list").listen(".chatlist", (e: any) => {
		console.log("🔥 chat-list update:", e)
		reloadContacts()
	})

	reloadContacts()
})

const formatTime = (timestamp: string) => {
	if (!timestamp) return ""

	const date = new Date(timestamp)
	return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
}

watch(activeTab, () => {
	selectedSession.value = null
	messages.value = []
	reloadContacts()
})

const filteredContacts = computed(() => contacts.value.filter((c) => c.status === activeTab.value))

const openChat = (c: Contact) => {
	selectedSession.value = {
		ulid: String(c.ulid),
		guest_identifier: c.name,
	} as SessionAPI
	messages.value = c.messages ?? []
}

const back = () => {
	selectedSession.value = null
}

const handleSendMessage = (text: string) => {
	messages.value.push({
		sender_type: "agent",
		message_text: text,
	} as ChatMessage)
}

const assignToSelf = async (ulid: string) => {
	if (isAssigning.value[ulid]) return
	isAssigning.value[ulid] = true
	errorMessage.value = null

	try {
		const assignRoute: routeType = {
			name: "grp.org.crm.agents.assign.self",
			parameters: [route().params.organisation, ulid],
			method: "post",
		}
		await axios.post(
			route(assignRoute.name, assignRoute.parameters),
			{},
			{ withCredentials: true }
		)
	} catch (error: any) {
		errorMessage.value = error?.response?.data?.message ?? trans("Failed to assign chat")
	} finally {
		isAssigning.value[ulid] = false
	}
}

const handleClickContact = async (c: Contact) => {
	openChat(c)
	if (activeTab.value === "waiting") {
		await assignToSelf(String(c.ulid))
		await nextTick()
		reloadContacts()
	}
}
</script>

<template>
	<div class="w-full h-full flex flex-col border bg-white">
		<div class="px-4 py-3 border-b font-semibold text-gray-700">Contacts</div>

		<div class="flex border-b text-sm">
			<div
				class="px-4 py-2 cursor-pointer"
				:class="[
					activeTab === 'waiting'
						? 'text-blue-600 border-b-2 border-blue-600 font-semibold'
						: 'text-gray-600',
				]"
				@click="activeTab = 'waiting'">
				Waiting
			</div>

			<div
				class="px-4 py-2 cursor-pointer"
				:class="[
					activeTab === 'active'
						? 'text-blue-600 border-b-2 border-blue-600 font-semibold'
						: 'text-gray-600',
				]"
				@click="activeTab = 'active'">
				Active
			</div>

			<div
				class="px-4 py-2 cursor-pointer"
				:class="[
					activeTab === 'resolved'
						? 'text-blue-600 border-b-2 border-blue-600 font-semibold'
						: 'text-gray-600',
				]"
				@click="activeTab = 'resolved'">
				Resolved
			</div>
		</div>

		<div class="flex-1 overflow-y-auto">
			<div v-if="!selectedSession">
				<div
					v-for="c in filteredContacts"
					:key="c.id"
					class="flex items-center gap-4 px-4 py-3 border-b hover:bg-gray-50 cursor-pointer"
					@click="handleClickContact(c)">
					<img :src="c.avatar" class="w-12 h-12 rounded-full object-cover" />
					<div class="flex-1">
						<div class="font-semibold text-gray-800">{{ capitalize(c.name) }}</div>
						<div class="text-sm text-gray-500 truncate">{{ c.lastMessage }}</div>
					</div>
					<span class="text-xs text-gray-400">{{ c.lastMessageTime }}</span>
					<div
						v-if="c.unread"
						class="px-2 py-1 bg-red-500 text-white text-xs rounded-full">
						{{ c.unread }}
					</div>
				</div>
			</div>

			<MessageAreaAgent
				v-else
				:messages="messages"
				:session="selectedSession"
				@back="back"
				@send-message="handleSendMessage" />
		</div>
		<div v-if="errorMessage" class="px-4 py-2 text-xs text-red-600">{{ errorMessage }}</div>
	</div>
</template>
