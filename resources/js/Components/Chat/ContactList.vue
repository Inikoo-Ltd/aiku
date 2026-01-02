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
import { faUser, faUserAlien } from "@far"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "../Image.vue"

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
		// just a moment code hours
		const PLUS_8_HOURS = layout.app?.environment === "local" ? 8 * 60 * 60 * 1000 : 0

		const res = await axios.get(`${baseUrl}/app/api/chats/sessions`, { params })
		contacts.value = res.data.data.sessions.map(
			(s: SessionAPI): Contact => ({
				id: s.id,
				ulid: s.ulid,
				name: s.contact_name || s.guest_identifier || "",
				avatar: s.image,
				lastMessage: s.last_message?.message ?? "",
				lastMessageTime: s.last_message?.created_at
					? formatTime(new Date(s.last_message.created_at).getTime() + PLUS_8_HOURS)
					: undefined,
				unread: s.unread_count,
				status: s.status,
				webUser: s.web_user,
				priority: s.priority,
				guest_profile: s.guest_profile,
				agent: s.assigned_agent,
				shop : s.shop,
				organisation : s.organisation
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
		shop : c.shop,
		organisation : c.organisation
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
			sender_type: "agent",
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
	console.log(c)
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

const onTransferAgentSuccess = async () => {
	sidePanelVisible.value = false
	selectedSession.value = null
	await reloadContacts()
}

const formatLastMessage = (msg: string) => {
	if (!msg) return ""
	return msg.length > 10 ? msg.substring(0, 10) + "..." : msg
}

const tabClass = (tab: string) => {
	return activeTab.value === tab
		? "tabPrimary"
		: "tabInactive"
}

</script>

<template>
	<div class="w-full h-full flex flex-col bg-white">
		<!-- Header -->
		<div class="px-3 py-2 border-b text-sm font-semibold text-gray-700">
			Contacts
		</div>

		<!-- Tabs -->
		<div class="flex border-b text-xs">
			<div class="tabItem" :class="tabClass('waiting')" @click="activeTab = 'waiting'">
				Waiting
			</div>
			<div class="tabItem" :class="tabClass('active')" @click="activeTab = 'active'">
				Active
			</div>
			<div class="tabItem" :class="tabClass('closed')" @click="activeTab = 'closed'">
				Closed
			</div>
		</div>

		<!-- Content -->
		<div class="flex-1">
			<div v-if="!selectedSession" class="overflow-y-auto h-[calc(100vh-140px)]">
				<div v-if="filteredContacts.length === 0"
					class="h-full flex flex-col items-center justify-center gap-2 text-center px-4">
					<div class="text-2xl font-semibold" :style="{ color: 'var(--theme-color-4)' }">
						💬
					</div>

					<div class="text-sm font-medium text-gray-700">
						{{ trans('No conversations') }}
					</div>

					<div class="text-xs text-gray-500">
						{{ trans('There are no chats at the moment') }}
					</div>
				</div>

				<!-- LIST -->
				<div v-else>
					<div v-for="c in filteredContacts" :key="c.id">
						<div class="relative flex items-center gap-3 px-3 py-2 border-b hover:bg-gray-50 cursor-pointer"
							@click="handleClickContact(c)">
							<!-- Loading overlay -->
							<div v-if="isAssigning[c.ulid]"
								class="absolute inset-0 bg-black/30 flex items-center justify-center z-10">
								<LoadingIcon class="w-10 h-10 text-white" />
							</div>

							<!-- Avatar -->
							<div
								class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 bg-gray-100 text-gray-500">
								<Image v-if="c.avatar" :src="c.avatar" class="w-full h-full rounded-full object-cover" />

								<FontAwesomeIcon v-else :icon="faUser" class="text-sm" />
							</div>

							<!-- Main content -->
							<div class="flex-1 min-w-0">
								<!-- Name + badges -->
								<div class="flex items-center gap-1.5">
									<span class="text-sm font-medium text-gray-800 truncate">
										{{ capitalize(c.name) }}
									</span>

									<span class="text-[10px] px-1.5 py-0.5 rounded border"
										:class="priorityClass(c.priority)">
										{{ capitalize(c.priority) }}
									</span>

									<span class="text-[10px] px-1.5 py-0.5 rounded" :class="c.webUser?.id
										? 'bg-green-100 text-green-700'
										: 'bg-blue-100 text-blue-700'">
										{{ c.webUser?.id ? trans('Customer') : trans('Guest') }}
									</span>
								</div>

								<!-- Last message -->
								<div class="text-xs text-gray-500 truncate leading-snug">
									{{ formatLastMessage(c.lastMessage) }}
								</div>
							</div>

							<!-- Time + unread -->
							<div class="flex flex-col items-end gap-0.5 shrink-0">
								<span class="text-[10px] text-gray-400">
									{{ c.lastMessageTime }}
								</span>

								<span v-if="c.unread && activeTab !== 'closed'"
									class="min-w-[16px] px-1.5 text-[10px] leading-4 text-white rounded-full text-center"
									:style="{ backgroundColor: 'var(--theme-color-4)' }">
									{{ c.unread }}
								</span>
							</div>
						</div>

						<!-- Error -->
						<div v-if="errorPerContact[c.ulid]" class="px-3 py-1 text-xs text-red-600 bg-red-50 border-b">
							{{ errorPerContact[c.ulid] }}
						</div>
					</div>

				</div>
			</div>

			<!-- Chat view tetap -->
			<div v-else class="relative h-[calc(100vh-140px)]">
				<div v-if="sidePanelVisible" class="absolute z-[9999] right-[420px] bottom-0 w-[350px]">
					<ChatSidePanel :session="selectedSession" :initialTab="sidePanelInitialTab" @close="closeSidePanel"
						@sync-success="onSyncSuccess" @transfer-agent-success="onTransferAgentSuccess" />
				</div>

				<div class="h-full">
					<MessageAreaAgent :messages="messages" :session="selectedSession" @back="back"
						@send-message="handleSendMessage" @close-session="closeSession" @view-history="showHistoryPanel"
						@view-user-profile="showProfilePanel" @view-message-details="showMessageDetailsPanel" />
				</div>
			</div>
		</div>
	</div>
</template>


<style>
/* Tabs */
.tabItem {
	padding: 6px 12px;
	cursor: pointer;
	border-bottom: 2px solid transparent;
	transition: color 0.15s ease, border-color 0.15s ease;
}

.tabPrimary {
	color: var(--theme-color-4);
	border-bottom-color: var(--theme-color-4);
	font-weight: 600;
}

.tabInactive {
	color: #6b7280;
}

.tabInactive:hover {
	color: var(--theme-color-4);
}

/* Contact item */
.contactItem {
	position: relative;
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 8px 12px;
	border-bottom: 1px solid #e5e7eb;
	cursor: pointer;
	transition: background 0.15s ease;
}

.contactItem:hover {
	background: color-mix(in srgb, var(--theme-color-4) 6%, white);
}

.badge {
	font-size: 10px;
	padding: 2px 6px;
	border-radius: 4px;
	line-height: 1;
}

.badgeCustomer {
	background: color-mix(in srgb, var(--theme-color-4) 15%, white);
	color: var(--theme-color-4);
}

.badgeGuest {
	background: #eff6ff;
	color: #2563eb;
}

/* Unread */
.unreadBadge {
	background: var(--theme-color-4);
	color: white;
	font-size: 10px;
	padding: 2px 6px;
	border-radius: 999px;
}
</style>