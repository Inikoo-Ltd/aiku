<script setup lang="ts">
import { ref, inject, computed, watch, onMounted, nextTick } from "vue"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { capitalize } from "@/Composables/capitalize"
import { Contact, SessionAPI, ChatMessage } from "@/types/Chat/chat"
import MessageAreaAgent from "@/Components/Chat/MessageAreaAgent.vue"
import { routeType } from "@/types/route"
import ChatSidePanel from "@/Components/Chat/ChatSidePanel.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

const layout: any = inject("layout", {})

const baseUrl = layout?.appUrl ?? ""
const contacts = ref<Contact[]>([])
const selectedSession = ref<SessionAPI | null>(null)
const messages = ref<ChatMessage[]>([])

const activeTab = ref("waiting")
const isAssigning = ref<Record<string, boolean>>({})
const errorPerContact = ref<Record<string, string>>({})

const sidePanelVisible = ref(false)
const sidePanelInitialTab = ref<"history" | "profile" | "message-details">("history")

const showHistoryPanel = () => {
	sidePanelInitialTab.value = "history"
	sidePanelVisible.value = true
}
const showProfilePanel = () => {
	sidePanelInitialTab.value = "profile"
	sidePanelVisible.value = true
}
const showMessageDetailsPanel = () => {
	sidePanelInitialTab.value = "message-details"
	sidePanelVisible.value = true
}

const closeSidePanel = () => {
	sidePanelVisible.value = false
}

const reloadContacts = async () => {
	try {
		let params: any = {}

		params.statuses = [activeTab.value]

		if (["active", "closed"].includes(activeTab.value)) {
			params.assigned_to_me = layout?.user?.id
		}

		const res = await axios.get(`${baseUrl}/app/api/chats/sessions`, { params })
		console.log("✅ Contacts:", res)
		contacts.value = res.data.data.sessions.map(
			(s: SessionAPI): Contact => ({
				id: s.id,
				ulid: s.ulid,
				name: s.contact_name || s.guest_identifier || "",
				avatar: `https://i.pravatar.cc/100?u=${s.ulid}`,
				lastMessage: s.last_message?.message ?? "",
				lastMessageTime: s.last_message?.created_at
					? formatTime(s.last_message.created_at)
					: undefined,
				lastMessageTimestamp: s.last_message?.created_at_timestamp,
				unread: s.unread_count,
				status: s.status,
				webUser: s.web_user,
				priority: s.priority,
				guest_profile: s.guest_profile,
				agent: s.assigned_agent,
			})
		)
	} catch (e) {
		console.error("Failed to reload contacts:", e)
	}
}
const waitEchoReady = (callback: Function) => {
	if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
		callback()
		return
	}

	const interval = setInterval(() => {
		if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
			clearInterval(interval)
			callback()
		}
	}, 300)
}

onMounted(() => {
	waitEchoReady(() => {
		window.Echo.join("chat-list").listen(".chatlist", (e: any) => {
			console.log("🔥 chat-list update:", e)
			reloadContacts()
		})
	})
	reloadContacts()
})

const formatTime = (timestamp: string) => {
	if (!timestamp) return ""

	const date = new Date(timestamp)
	return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
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
		status: c.status,
		priority: c.priority,
		web_user: c.webUser,
		guest_profile: c.guest_profile,
		assigned_agent: c.agent,
	} as SessionAPI
	messages.value = c.messages ?? []
}

const back = () => {
	selectedSession.value = null
}

const handleSendMessage = async (text: string) => {
	if (!selectedSession.value?.ulid) return

	try {
		const organisation = route().params?.organisation ?? "aw"

		const payload = {
			message_text: text,
			message_type: "text",
		}

		const assignRoute: routeType = {
			name: "grp.org.crm.agents.messages.send",
			parameters: [selectedSession.value.ulid, organisation],
			method: "post",
		}

		await axios.post(route(assignRoute.name, assignRoute.parameters), payload, {
			withCredentials: true,
		})
	} catch (error) {
		console.error("Error sending message:", error)
	}
}

const assignToSelf = async (ulid: string) => {
	if (isAssigning.value[ulid]) return { success: false }

	isAssigning.value[ulid] = true

	try {
		const organisation = route().params?.organisation ?? "aw"

		const assignRoute: routeType = {
			name: "grp.org.crm.agents.assign.self",
			parameters: [organisation, ulid],
			method: "post",
		}

		const response = await axios.post(
			route(assignRoute.name, assignRoute.parameters),
			{},
			{ withCredentials: true }
		)
		return { success: true, data: response.data }
	} catch (error: any) {
		return {
			success: false,
			error: error?.response?.data?.message ?? trans("Failed to assign chat"),
		}
	} finally {
		isAssigning.value[ulid] = false
	}
}

const handleClickContact = async (c: Contact) => {
	errorPerContact.value[c.ulid] = ""

	if (activeTab.value === "waiting") {
		const result = await assignToSelf(String(c.ulid))

		if (!result.success) {
			errorPerContact.value[c.ulid] = result.error
			return
		}

		openChat(c)
		await nextTick()
		reloadContacts()
	} else {
		openChat(c)
	}
}

const onSyncSuccess = async () => {
	await reloadContacts()
	if (selectedSession.value?.ulid) {
		const c = contacts.value.find(
			(ct) => String(ct.ulid) === String(selectedSession.value?.ulid)
		)
		if (c) {
			openChat(c)
		}
	}
}

const formatLastMessage = (msg: string) => {
	if (!msg) return ""
	return msg.length > 10 ? msg.substring(0, 10) + "..." : msg
}
</script>

<template>
	<div class="w-full h-full flex flex-col bg-white">
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
					activeTab === 'closed'
						? 'text-blue-600 border-b-2 border-blue-600 font-semibold'
						: 'text-gray-600',
				]"
				@click="activeTab = 'closed'">
				Closed
			</div>
		</div>

		<div class="flex-1">
			<div v-if="!selectedSession">
				<div class="overflow-y-auto h-[calc(100vh-160px)]">
					<div v-for="c in filteredContacts" :key="c.id">
						<div
							class="relative flex items-center gap-4 px-4 py-3 border-b hover:bg-gray-50 cursor-pointer"
							@click="handleClickContact(c)">
							<div
								v-if="isAssigning[c.ulid]"
								class="absolute inset-0 bg-black/30 flex items-center justify-center z-10">
								<LoadingIcon class="w-20 h-20 text-white" />
							</div>
							<img :src="c.avatar" class="w-12 h-12 rounded-full object-cover" />
							<div class="flex-1">
								<div class="font-semibold text-gray-800">
									{{ capitalize(c.name) }}
								</div>
								<div class="flex items-start gap-1">
									<span
										v-if="c.webUser?.id"
										class="inline-flex items-center justify-center px-2 py-0.5 mt-1 rounded-sm text-[11px] font-medium bg-green-100 text-green-800">
										{{ trans("Customer") }}
									</span>
									<span
										v-else
										class="inline-flex items-center justify-center px-2 py-0.5 mt-1 rounded-sm text-[11px] font-medium bg-blue-100 text-blue-800">
										{{ trans("Guest") }}
									</span>

									<span
										class="inline-flex items-center justify-center px-2 py-0.5 mt-1 rounded-sm text-[11px] font-medium border"
										:class="priorityClass(c.priority)">
										{{ capitalize(c.priority) }}
									</span>
								</div>
								<div class="text-sm text-gray-500 truncate">
									{{ formatLastMessage(c.lastMessage) }}
								</div>
							</div>
							<span class="text-xs text-gray-400">{{ c.lastMessageTime }}</span>

							<div
								v-if="c.unread && activeTab !== 'closed'"
								class="px-2 py-1 bg-red-500 text-white text-xs rounded-full">
								{{ c.unread }}
							</div>
						</div>

						<div
							v-if="errorPerContact[c.ulid]"
							class="px-4 py-2 text-xs text-red-600 border-b bg-red-50">
							{{ errorPerContact[c.ulid] }}
						</div>
					</div>
				</div>
			</div>

			<div v-else class="relative h-[calc(100vh-160px)]">
				<div
					v-if="sidePanelVisible"
					class="absolute z-[9999] right-[420px] bottom-0 w-[350px]">
					<ChatSidePanel
						:session="selectedSession"
						:initialTab="sidePanelInitialTab"
						@close="closeSidePanel"
						@sync-success="onSyncSuccess" />
				</div>

				<div class="h-full">
					<MessageAreaAgent
						:messages="messages"
						:session="selectedSession"
						@back="back"
						@send-message="handleSendMessage"
						@close-session="closeSession"
						@view-history="showHistoryPanel"
						@view-user-profile="showProfilePanel"
						@view-message-details="showMessageDetailsPanel" />
				</div>
			</div>
		</div>
	</div>
</template>
