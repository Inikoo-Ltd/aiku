<script setup lang="ts">
import { ref, inject, computed, watch, onMounted } from "vue"
import Button from "../Elements/Buttons/Button.vue"
import { faPaperPlane } from "@fas"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { capitalize } from "@/Composables/capitalize"

const layout: any = inject("layout", {})

const baseUrl = layout?.appUrl ?? ""

const reloadContacts = async () => {
	try {
		const res = await axios.get(`${baseUrl}/app/api/chats/sessions`, {
			params: { statuses: [activeTab.value] },
		})

		contacts.value = res.data.data.sessions.map((s: any) => ({
			id: s.ulid,
			name: s.guest_identifier,
			avatar: "https://i.pravatar.cc/100",
			lastMessage: s.last_message?.message ?? "",
			lastMessageTime: s.last_message?.created_at,
			lastMessageTimestamp: s.last_message?.created_at_timestamp,
			unread: s.unread_count,
			status: s.status,
		}))
	} catch (e) {
		console.error("Failed to reload contacts:", e)
	}
}

const contacts = ref([])

const activeTab = ref("waiting")

onMounted(() => {
	window.Echo.join("chat-list").listen(".chatlist", (e: any) => {
		console.log("🔥 chat-list update:", e)
		reloadContacts()
	})

	reloadContacts()
})

const userTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone

const formatTimestamp = (timestamp: number) => {
	const date = new Date(timestamp * 1000)
	const now = new Date()

	const isToday =
		date.toLocaleDateString(undefined, { timeZone: userTimeZone }) ===
		now.toLocaleDateString(undefined, { timeZone: userTimeZone })

	const diffHours = (now.getTime() - date.getTime()) / (1000 * 60 * 60)

	if (isToday && diffHours < 24) {
		return date.toLocaleTimeString(undefined, {
			hour: "2-digit",
			minute: "2-digit",
			timeZone: userTimeZone,
		})
	}

	return date.toLocaleDateString(undefined, {
		day: "2-digit",
		month: "short",
		year: "numeric",
		timeZone: userTimeZone,
	})
}

watch(activeTab, () => {
	reloadContacts()
})

const filteredContacts = computed(() => contacts.value.filter((c) => c.status === activeTab.value))
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
			<div
				v-for="c in filteredContacts"
				:key="c.id"
				class="flex items-center gap-4 px-4 py-3 border-b hover:bg-gray-50 cursor-pointer">
				<img :src="c.avatar" class="w-12 h-12 rounded-full object-cover" />

				<div class="flex-1">
					<div class="font-semibold text-gray-800">
						{{ capitalize(c.name) }}
					</div>
					<div class="text-sm text-gray-500 truncate">
						{{ c.lastMessage }}
					</div>
				</div>
				<span class="text-xs text-gray-400">
					{{ formatTimestamp(c.lastMessageTimestamp) }}
				</span>
				<div v-if="c.unread" class="px-2 py-1 bg-red-500 text-white text-xs rounded-full">
					{{ c.unread }}
				</div>
			</div>
		</div>
	</div>
</template>
