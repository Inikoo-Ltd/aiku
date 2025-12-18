<script setup lang="ts">
import { ref, type Ref, inject, onMounted, watch, computed, nextTick } from "vue"
import { trans } from "laravel-vue-i18n"
import { Link } from "@inertiajs/vue3"
import axios from "axios"
import MessageHistory from "@/Components/Chat/MessageHistory.vue"
import Button from "../Elements/Buttons/Button.vue"
import type { SessionAPI } from "@/types/Chat/chat"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import SelectQuery from "@/Components/SelectQuery.vue"
import {
	faUser,
	faClose,
	faSync,
	faAngleUp,
	faAngleDown,
	faAngleDoubleUp,
	faEquals,
	faEnvelopeCircleCheck,
	faHourglassHalf,
} from "@fortawesome/free-solid-svg-icons"
import { capitalize } from "@/Composables/capitalize"
import AlertMessage from "@/Components/Utils/AlertMessage.vue"

interface AlertType {
	status: "success" | "danger" | "warning" | "info"
	title?: string
	description?: string
}

const props = defineProps<{
	session: SessionAPI | null
	initialTab?: "history" | "profile" | "message-details"
}>()

const selectedHistory = ref<{
	ulid: string
	contact_name?: string
	guest_identifier?: string
} | null>(null)

const emit = defineEmits<{
	(e: "close"): void
	(e: "sync-success"): void
	(e: "transfer-agent-success"): void
}>()

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

const activeTab = ref<"history" | "profile" | "message-details">(props.initialTab ?? "history")
const syncEmail = ref(props.session?.guest_profile?.email || "")
const syncEmailAlert = ref<AlertType | null>(null)
const priorityAlert = ref<AlertType | null>(null)

const showAlert = (
	target: Ref<AlertType | null>,
	data: AlertType | null,
	timeout = 3000
): Promise<void> => {
	target.value = data
	return new Promise<void>((resolve) => {
		if (data === null || timeout <= 0) {
			resolve()
			return
		}
		setTimeout(() => {
			target.value = null
			resolve()
		}, timeout)
	})
}

const isSyncing = ref(false)
const isUpdatingPriority = ref(false)
const currentPriority = ref(props.session?.priority || "")
const isLoadingHistory = ref(false)
const historyError = ref<string | null>(null)
const userSessions = ref<SessionAPI[]>([])
const isEditingPriority = ref(false)
const agentAlert = ref<AlertType | null>(null)
const isEditingAgent = ref(false)
const isAssigningAgent = ref(false)

const loadUserSessions = async () => {
	if (!props.session?.web_user?.id) return
	try {
		isLoadingHistory.value = true
		historyError.value = null
		const res = await axios.get(`${baseUrl}/app/api/chats/sessions`, {
			params: { web_user_id: props.session?.web_user?.id, limit: 50 },
		})
		userSessions.value = res.data?.data?.sessions ?? []
	} finally {
		isLoadingHistory.value = false
	}
}

const priorityClass = (p?: string) => {
	const key = String(p || "").toLowerCase()
	switch (key) {
		case "low":
			return "bg-blue-50 border-blue-500 text-blue-500"
		case "normal":
			return "bg-gray-50 border-gray-400 text-gray-400"
		case "high":
			return "bg-yellow-50 border-yellow-500 text-yellow-500"
		case "urgent":
			return "bg-red-50 border-red-500 text-red-500"
		default:
			return "bg-gray-50 border-gray-300 text-gray-300"
	}
}

const statusClass = (p?: string) => {
	const key = String(p || "").toLowerCase()
	switch (key) {
		case "waiting":
			return "bg-yellow-50 border-yellow-500 text-yellow-500"
		case "active":
			return "bg-green-50 border-green-400 text-green-400"
		case "closed":
			return "bg-red-50 border-red-500 text-red-500"
		default:
			return "bg-gray-50 border-gray-300 text-gray-300"
	}
}

const priorityIcon = (p?: string) => {
	const key = String(p || "").toLowerCase()
	switch (key) {
		case "urgent":
			return faAngleDoubleUp
		case "high":
			return faAngleUp
		case "normal":
			return faEquals
		case "low":
			return faAngleDown
		default:
			return faEquals
	}
}

const statusIcon = (p?: string) => {
	const key = String(p || "").toLowerCase()
	switch (key) {
		case "waiting":
			return faHourglassHalf
		case "active":
			return faEnvelopeCircleCheck
		case "closed":
			return faClose
		default:
			return faUser
	}
}
const getInitials = (name: string | undefined): string => {
	if (!name) return "?"

	const trimmedName = name.trim()
	if (!trimmedName) return "?"

	const nameParts = trimmedName.split(/\s+/)

	if (nameParts.length === 1) {
		return nameParts[0].charAt(0).toUpperCase()
	}

	return (nameParts[0].charAt(0) + nameParts[1].charAt(0)).toUpperCase()
}

const getRandomColor = (name: string | undefined): string => {
	if (!name) return "#6B7280"

	let hash = 0
	for (let i = 0; i < name.length; i++) {
		hash = name.charCodeAt(i) + ((hash << 5) - hash)
	}

	const colors = [
		"#EF4444", // red-500
		"#F97316", // orange-500
		"#EAB308", // yellow-500
		"#22C55E", // green-500
		"#3B82F6", // blue-500
		"#8B5CF6", // violet-500
		"#EC4899", // pink-500
		"#6366F1", // indigo-500
		"#10B981", // emerald-500
		"#F59E0B", // amber-500
		"#06B6D4", // cyan-500
		"#8B5CF6", // purple-500
	] as const

	const index = Math.abs(hash) % colors.length
	return colors[index]
}

const avatarAgent = computed(() => {
	return (name: string | undefined) => {
		return {
			initials: getInitials(name),
			color: getRandomColor(name),
		}
	}
})

const assignAgent = async (opt: any) => {
	if (!props.session?.ulid || !opt?.agent_id) return
	if (isAssigningAgent.value) return
	if (String(props.session?.assigned_agent?.id ?? "") === String(opt.agent_id)) return

	try {
		isAssigningAgent.value = true
		const organisation = route().params?.organisation ?? "aw"
		const parameters = [organisation, props.session.ulid]
		const body = {
			agent_id: opt.agent_id,
		}

		const response = await axios.patch(route("grp.org.crm.agents.assign", parameters), body, {
			withCredentials: true,
		})

		await showAlert(
			agentAlert,
			{
				status: "success",
				title: "Success",
				description: response.data?.message || "Agent updated",
			},
			2000
		)

		if (!props.session.assigned_agent) {
			props.session.assigned_agent = {
				id: opt.agent_id,
				name: opt.label || opt.name || "",
			}
		} else {
			props.session.assigned_agent.id = opt.agent_id
			props.session.assigned_agent.name = opt.label || opt.name || ""
		}

		isEditingAgent.value = false
		emit("transfer-agent-success")
	} catch (e: any) {
		await showAlert(
			agentAlert,
			{
				status: "danger",
				title: "Error",
				description: e.response?.data?.message || e.message,
			},
			2000
		)
	} finally {
		isAssigningAgent.value = false
	}
}

const priorityOptions = ["urgent", "high", "normal", "low"]
const displayName = computed(() => {
	return props.session?.contact_name || props.session?.guest_identifier || ""
})
const avatarUrl = computed(() => {
	return `https://i.pravatar.cc/100?u=${props.session?.ulid}`
})

const onSyncByEmail = async () => {
	if (!syncEmail.value || !props.session?.ulid) return
	try {
		isSyncing.value = true
		const response = await axios.put(
			`${baseUrl}/app/api/chats/sessions/${props.session.ulid}/sync-by-email`,
			{
				email: syncEmail.value,
			}
		)
		await showAlert(syncEmailAlert, {
			status: "success",
			title: "Success",
			description: response.data?.message || "Email synced",
		})

		emit("sync-success")
	} catch (e: any) {
		await showAlert(
			syncEmailAlert,
			{
				status: "danger",
				title: "Error",
				description: e.response?.data?.message || e.message,
			},
			3000
		)
	} finally {
		isSyncing.value = false
	}
}
const updatePriority = async (val: string) => {
	if (!props.session?.ulid) return
	try {
		isUpdatingPriority.value = true
		const response = await axios.put(
			`${baseUrl}/app/api/chats/sessions/${props.session.ulid}/update`,
			{ priority: val }
		)
		currentPriority.value = val
		isEditingPriority.value = false
		await showAlert(
			priorityAlert,
			{
				status: "success",
				title: "Success",
				description: response.data?.message || "Priority updated",
			},
			2000
		)

		emit("sync-success")
	} catch (e: any) {
		await showAlert(
			priorityAlert,
			{
				status: "danger",
				title: "Error",
				description: e.response?.data?.message || e.message,
			},
			3000
		)
	} finally {
		isUpdatingPriority.value = false
	}
}

watch(
	() => activeTab.value,
	async (tab) => {
		if (tab === "history") {
			await loadUserSessions()
		}
	}
)

onMounted(async () => {
	if (activeTab.value === "history") {
		await loadUserSessions()
	}
})
</script>

<template>
	<div
		class="w-[380px] h-[calc(100vh-180px)] bg-white flex flex-col rounded-t-lg shadow-xl ring-1 ring-gray-200 overflow-hidden pb-5">
		<div class="px-4 py-3 border-b">
			<div class="flex items-start">
				<div class="w-10"></div>

				<div class="flex-1 flex flex-col items-center gap-2 text-center">
					<img :src="avatarUrl" class="w-12 h-12 rounded-full object-cover" />

					<div class="leading-tight">
						<div class="font-semibold text-sm">
							{{ capitalize(displayName) }}
						</div>

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
					</div>
				</div>

				<div class="w-10 flex justify-end">
					<button class="px-2 py-1 rounded hover:bg-gray-100" @click="emit('close')">
						<FontAwesomeIcon :icon="faClose" class="text-base text-gray-400" />
					</button>
				</div>
			</div>
		</div>

		<div class="flex border-b text-sm">
			<button
				class="px-4 py-2"
				:class="
					activeTab === 'history'
						? 'text-blue-600 border-b-2 border-blue-600 font-semibold'
						: 'text-gray-600'
				"
				@click="activeTab = 'history'">
				{{ trans("History") }}
			</button>
			<button
				class="px-4 py-2"
				:class="
					activeTab === 'profile'
						? 'text-blue-600 border-b-2 border-blue-600 font-semibold'
						: 'text-gray-600'
				"
				@click="activeTab = 'profile'">
				{{ trans("Profile") }}
			</button>
			<button
				class="px-4 py-2"
				:class="
					activeTab === 'message-details'
						? 'text-blue-600 border-b-2 border-blue-600 font-semibold'
						: 'text-gray-600'
				"
				@click="activeTab = 'message-details'">
				{{ trans("Message Details") }}
			</button>
		</div>

		<div class="flex-1 overflow-y-auto">
			<div v-if="activeTab === 'history'" class="h-full">
				<template v-if="!selectedHistory">
					<div
						v-if="isLoadingHistory"
						class="text-sm px-4 py-2 text-center text-gray-500">
						Loading...
					</div>
					<div v-else>
						<div
							v-for="s in userSessions"
							:key="s.ulid"
							class="flex items-start gap-3 px-3 py-3 hover:bg-gray-50 cursor-pointer"
							@click="
								selectedHistory = {
									ulid: s.ulid,
									contact_name: s.contact_name,
									guest_identifier: s.guest_identifier,
									status: s.status,
									web_user: s.web_user,
									guest_profile: s.guest_profile,
								}
							">
							<div class="flex-1">
								<div class="flex items-center justify-between">
									<div class="text-sm">
										{{ s.contact_name || s.guest_identifier }}
									</div>
									<div class="text-xs text-gray-400">
										{{ s.last_message?.created_at }}
									</div>
								</div>
								<div class="text-xs text-gray-600 truncate">
									{{ s.last_message?.message }}
								</div>
							</div>
						</div>
					</div>
				</template>
				<MessageHistory
					v-else
					:sessionUlid="selectedHistory?.ulid || ''"
					:session="selectedHistory || undefined"
					@back="selectedHistory = null" />
			</div>

			<div v-if="activeTab === 'profile'" class="p-4 space-y-3">
				<div class="grid grid-cols-3 gap-2 items-center">
					<div class="text-gray-500 text-sm">{{ trans("Name") }}</div>
					<div class="col-span-2 text-sm">
						{{ displayName || "-" }}
					</div>
				</div>

				<div class="grid grid-cols-3 gap-2 items-center">
					<div class="text-gray-500 text-sm">{{ trans("Email") }}</div>
					<div class="col-span-2 text-sm">
						{{ props.session?.guest_profile?.email || "-" }}
					</div>
				</div>

				<div class="grid grid-cols-3 gap-2 items-center">
					<div class="text-gray-500 text-sm">{{ trans("Phone") }}</div>
					<div class="col-span-2 text-sm">
						{{ props.session?.guest_profile?.phone || "-" }}
					</div>
				</div>

				<div v-if="props.session?.web_user" class="grid grid-cols-3 gap-2 items-center">
					<div class="text-gray-500 text-sm">{{ trans("Organisation") }}</div>
					<div class="col-span-2 text-sm">
						{{ capitalize(props.session?.web_user?.organisation || "-") }}
					</div>
				</div>
				<div v-if="props.session?.web_user" class="grid grid-cols-3 gap-2 items-center">
					<div class="text-gray-500 text-sm">{{ trans("Shop") }}</div>
					<div class="col-span-2 text-sm">
						{{ capitalize(props.session?.web_user?.shop || "-") }}
					</div>
				</div>

				<div
					v-if="!props.session?.web_user && props.session?.guest_identifier"
					class="pt-2 space-y-2">
					<div class="text-xs text-gray-500">{{ trans("Sync by email (optional)") }}</div>
					<input
						type="email"
						v-model="syncEmail"
						disabled
						placeholder="guest@example.com"
						class="w-full px-3 py-2 border rounded" />
					<button
						class="w-full px-3 py-2 buttonPrimary rounded bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50"
						:disabled="isSyncing || !syncEmail"
						@click="onSyncByEmail">
						<FontAwesomeIcon :icon="faSync" class="text-base text-white" />
						{{ isSyncing ? trans("Syncing...") : trans("Sync by email") }}
					</button>
					<AlertMessage v-if="syncEmailAlert" :alert="syncEmailAlert" />
				</div>
				<div v-else class="pt-2 space-y-2">
					<div class="text-xs text-gray-500 mb-1">
						{{ trans("Click to Customer Detail") }}
					</div>
					<Link
						:href="`/org/${props.session.web_user.organisation_slug}/shops/${props.session.web_user.shop_slug}/crm/customers/${props.session.web_user.slug}`">
						<Button
							:icon="faUser"
							:full="true"
							:label="trans('View Customer Profile')" />
					</Link>
				</div>
			</div>

			<div v-if="activeTab === 'message-details'" class="p-4 space-y-3">
				<div class="grid grid-cols-3 gap-2 items-center">
					<div class="text-gray-500 text-sm">{{ trans("Status") }}</div>
					<div class="flex items-center text-sm">
						<FontAwesomeIcon
							:icon="statusIcon(props.session?.status)"
							class="mr-1 text-sm"
							:class="statusClass(props.session?.status)" />
						{{ capitalize(props.session?.status || "") }}
					</div>
				</div>
				<div class="grid grid-cols-3 gap-2 items-center">
					<div class="text-gray-500 text-sm">{{ trans("Priority") }}</div>
					<div
						v-if="!isEditingPriority"
						class="flex items-center text-sm cursor-pointer"
						@click="isEditingPriority = true">
						<FontAwesomeIcon
							:icon="priorityIcon(currentPriority)"
							class="mr-1 text-sm"
							:class="priorityClass(currentPriority)" />
						{{ capitalize(currentPriority || "") }}
					</div>
					<div v-else class="flex flex-wrap items-center gap-2">
						<button
							v-for="opt in priorityOptions"
							:key="opt"
							:disabled="isUpdatingPriority"
							class="items-center justify-center border px-2 py-0.5 rounded-sm text-[11px]"
							:class="priorityClass(opt)"
							@click="updatePriority(opt)">
							<FontAwesomeIcon :icon="priorityIcon(opt)" class="mr-1 text-xs" />
							{{ capitalize(opt) }}
						</button>
						<button
							class="px-2 py-1 text-xs border rounded"
							:disabled="isUpdatingPriority"
							@click="isEditingPriority = false">
							{{ trans("Cancel") }}
						</button>
					</div>
				</div>
				<div class="grid grid-cols-3 gap-2 items-center">
					<div class="text-gray-500 text-sm">{{ trans("Agent") }}</div>

					<div
						v-if="!isEditingAgent"
						class="col-span-2 font-medium text-sm cursor-pointer"
						@click="isEditingAgent = true">
						{{ props.session?.assigned_agent?.name || "-" }}
					</div>

					<div v-else class="col-span-2">
						<SelectQuery
							:urlRoute="`${baseUrl}/app/api/chats/agents`"
							:label="'label'"
							:valueProp="'agent_id'"
							:object="true"
							:searchable="true"
							:closeOnSelect="true"
							:canClear="true"
							:onChange="assignAgent">
							<template #option="{ option, isSelected, isPointed }">
								<div
									class="flex items-center px-2 py-1.5"
									:class="[isPointed(option) ? 'bg-gray-100' : '']">
									<div
										class="w-7 h-7 rounded-full flex items-center justify-center mr-2 text-xs font-medium text-white"
										:style="{
											backgroundColor: avatarAgent(
												option?.name || option?.label
											).color,
										}">
										{{ avatarAgent(option?.name || option?.label).initials }}
									</div>
									<div class="flex-1">
										<div
											class="text-sm"
											:class="[isSelected(option) ? 'font-medium' : '']">
											{{ option?.label || option?.name }}
										</div>
									</div>
								</div>
							</template>
						</SelectQuery>

						<div class="flex items-center gap-2 mt-2">
							<button
								class="px-2 py-1 text-xs border rounded"
								:disabled="isAssigningAgent"
								@click="isEditingAgent = false">
								{{ trans("Cancel") }}
							</button>
						</div>
					</div>
				</div>
				<AlertMessage v-if="agentAlert" :alert="agentAlert" />
				<AlertMessage v-if="priorityAlert" :alert="priorityAlert" />
			</div>
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
</style>
