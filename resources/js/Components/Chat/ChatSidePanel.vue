<script setup lang="ts">
import { ref, type Ref, inject, onMounted, watch, computed, nextTick } from "vue"
import { trans } from "laravel-vue-i18n"
import { Link } from "@inertiajs/vue3"
import axios from "axios"
import MessageHistory from "@/Components/Chat/MessageHistory.vue"
import ChatActivityTimeline from "@/Components/Chat/ChatActivityTimeline.vue"
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
import Image from "../Image.vue"
import { useFormatTime } from "@/Composables/useFormatTime";

interface AlertType {
	status: "success" | "danger" | "warning" | "info"
	title?: string
	description?: string
}

const props = defineProps<{
	session: SessionAPI | null
	initialTab?: "history" | "profile" | "message-details"
}>()

const selectedHistory = ref<SessionAPI | null>(null)

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
		const organisation = (route().params as Record<string, any>)?.organisation ?? "aw"
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
	return (props.session as any)?.image || ""
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
	const user_id = layout.user?.id || null
	try {
		isUpdatingPriority.value = true
		const response = await axios.put(
			`${baseUrl}/app/api/chats/sessions/${props.session.ulid}/update`,
			{ priority: val, user_id }
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

const sentimentClass = (val: string) => {
	switch (val) {
		case 'positive':
			return 'bg-green-100 text-green-700'
		case 'neutral':
			return 'bg-yellow-100 text-yellow-700'
		case 'negative':
			return 'bg-red-100 text-red-700'
		default:
			return 'bg-gray-100 text-gray-600'
	}
}

const priorityClass = (p?: string) => {
	return "primary-outline"
}

const statusClass = (p?: string) => {
	return "primary-outline"
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
	<Teleport to="body">
		<div class="fixed inset-0" @click="emit('close')" />

		<div
			class="fixed right-[25rem] top-[120px] z-[9999] w-[380px] h-[calc(100vh-180px)] bg-white flex flex-col rounded-xl shadow-2xl ring-1 ring-gray-200 overflow-hidden">
			<div class="px-4 py-3 border-b relative">
				<button class="absolute right-4 top-3 p-1 rounded hover:bg-gray-100" @click="emit('close')">
					<FontAwesomeIcon :icon="faClose" class="text-base text-gray-400" />
				</button>
				<div class="flex flex-col items-center gap-2 text-center">
					<div
						class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 bg-gray-100 text-gray-500">
						<Image v-if="avatarUrl" :src="avatarUrl" class="w-full h-full rounded-full object-cover" />

						<FontAwesomeIcon v-else :icon="faUser" class="text-sm" />
					</div>

					<div class="leading-tight">
						<div class="font-semibold text-sm">
							{{ capitalize(displayName) }}
						</div>
						<span
							class="inline-flex items-center justify-center px-2 py-0.5 mt-1 rounded-sm text-[11px] font-medium "
							:class="props.session?.web_user ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'">
							{{ props.session?.web_user ? trans('Customer') : trans('Guest') }}
						</span>
					</div>
				</div>
			</div>

			<div class="flex border-b text-sm">
				<button class="px-4 py-2" :class="activeTab === 'history' ? 'tab-active' : 'tab-inactive'"
					@click="activeTab = 'history'">
					{{ trans("History") }}
				</button>

				<button class="px-4 py-2" :class="activeTab === 'profile' ? 'tab-active' : 'tab-inactive'"
					@click="activeTab = 'profile'">
					{{ trans("Profile") }}
				</button>

				<button class="px-4 py-2" :class="activeTab === 'message-details' ? 'tab-active' : 'tab-inactive'"
					@click="activeTab = 'message-details'">
					{{ trans("Message Details") }}
				</button>
			</div>

			<div class="flex-1 overflow-y-auto">
				<div v-if="activeTab === 'history'" class="h-full">
					<template v-if="!selectedHistory">
						<div v-if="isLoadingHistory" class="text-sm px-4 py-2 text-center text-gray-500">
							Loading...
						</div>
						<div v-else class="px-3 py-3 space-y-3">
							<div v-for="s in userSessions" :key="s.ulid"
								class="border border-gray-200 rounded-xl p-4 bg-white shadow-lg hover:shadow-xl transition cursor-pointer"
								@click="selectedHistory = s">
								<div class="flex justify-start mb-3">
									<span class="text-xs font-semibold px-2 py-1 rounded-xl capitalize" :class="s.status === 'closed'
										? 'bg-red-50 text-red-600'
										: 'bg-green-50 text-green-600'">
										{{ s.status }}
									</span>
								</div>

								<!-- Time Info -->
								<div class="grid grid-cols-3 gap-2 text-xs text-gray-600 mb-3">
									<div>
										<div class="text-gray-400">{{ trans("Created") }}</div>
										<div class="font-medium text-gray-800">
											{{ useFormatTime(s.created_at, { formatTime: 'hms' }) }}
										</div>
									</div>
									<div>
										<div class="text-gray-400">{{ trans("Last Activity") }}</div>
										<div class="font-medium text-gray-800">
											{{ useFormatTime(s.last_message?.created_at, { formatTime: 'hms' })
											}}
										</div>
									</div>
									<div>
										<div class="text-gray-400">{{ trans("Duration") }}</div>
										<div class="font-medium text-gray-800">
											{{ s.duration || '' }}
										</div>
									</div>
								</div>

								<!-- AI Summary -->
								<div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
									<div class="flex items-center justify-between mb-2">
										<span class="text-xs font-bold text-indigo-600">{{ trans("AI Summary") }}</span>
										<span class="text-[10px] text-gray-400">{{ trans("Auto generated") }}</span>
									</div>

									<template v-if="s.ai_summary">
										<!-- Summary -->
										<div class="mb-2">
											<div class="text-[11px] font-bold text-gray-500 mb-1">{{ trans("Summary") }}
											</div>
											<p class="text-xs text-gray-700 leading-relaxed">
												{{ s.ai_summary.summary }}
											</p>
										</div>

										<!-- Key Points -->
										<div class="mb-2">
											<div class="text-[11px] font-bold text-gray-500 mb-1">
												{{ trans("Key Points") }}
											</div>
											<ul class="text-xs text-gray-700 space-y-1 list-disc pl-4">
												<li v-for="(point, i) in s.ai_summary.key_points" :key="i">
													{{ point }}
												</li>
											</ul>
										</div>

										<!-- Sentiment -->
										<div class="flex items-center mt-2 gap-2">
											<span class="text-[11px] font-medium px-2 py-0.5 rounded-full capitalize"
												:class="sentimentClass(s.ai_summary.sentiment)">
												{{ trans("Sentiment :") }} {{ s.ai_summary.sentiment || '' }}
											</span>
										</div>
									</template>

									<!-- Fallback -->
									<div v-else class="text-xs text-gray-400 italic">
										{{ trans("AI summary not available.") }}
									</div>
								</div>
							</div>
						</div>
					</template>
					<MessageHistory v-else :sessionUlid="selectedHistory?.ulid || ''" viewerType="agent"
						:session="selectedHistory || undefined" @back="selectedHistory = null" />
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

					<div v-if="!props.session?.web_user && props.session?.guest_identifier" class="pt-2 space-y-2">
						<div class="text-xs text-gray-500">
							{{ trans("Sync by email (optional)") }}
						</div>
						<input type="email" v-model="syncEmail" disabled placeholder="guest@example.com"
							class="w-full px-3 py-2 border rounded" />
						<button
							class="w-full px-3 py-2 buttonPrimary rounded bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50"
							:disabled="isSyncing || !syncEmail" @click="onSyncByEmail">
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
							<Button :icon="faUser" :full="true" :label="trans('View Customer Profile')" />
						</Link>
					</div>
				</div>

				<div v-if="activeTab === 'message-details'" class="p-4 space-y-3">
					<div class="grid grid-cols-3 gap-2 items-center">
						<div class="text-gray-500 text-sm">{{ trans("Status") }}</div>
						<div class="flex items-center text-sm">
							<FontAwesomeIcon :icon="statusIcon(props.session?.status)" class="mr-1 text-sm"
								:class="statusClass(props.session?.status)" />
							{{ capitalize(props.session?.status || "") }}
						</div>
					</div>
					<div class="grid grid-cols-3 gap-2 items-center">
						<div class="text-gray-500 text-sm">{{ trans("Priority") }}</div>
						<div v-if="!isEditingPriority" class="flex items-center text-sm cursor-pointer"
							@click="isEditingPriority = true">
							<FontAwesomeIcon :icon="priorityIcon(currentPriority)" class="mr-1 text-sm"
								:class="priorityClass(currentPriority)" />
							{{ capitalize(currentPriority || "") }}
						</div>
						<div v-else class="flex flex-wrap items-center gap-2">
							<button v-for="opt in priorityOptions" :key="opt" :disabled="isUpdatingPriority"
								class="items-center justify-center border px-2 py-0.5 rounded-sm text-[11px]"
								:class="priorityClass(opt)" @click="updatePriority(opt)">
								<FontAwesomeIcon :icon="priorityIcon(opt)" class="mr-1 text-xs" />
								{{ capitalize(opt) }}
							</button>
							<button class="px-2 py-1 text-xs border rounded" :disabled="isUpdatingPriority"
								@click="isEditingPriority = false">
								{{ trans("Cancel") }}
							</button>
						</div>
					</div>
					<div class="grid grid-cols-3 gap-2 items-center">
						<div class="text-gray-500 text-sm">{{ trans("Agent") }}</div>

						<div v-if="!isEditingAgent" class="col-span-2 font-medium text-sm cursor-pointer"
							@click="isEditingAgent = true">
							{{ props.session?.assigned_agent?.name || "-" }}
						</div>

						<div v-else class="col-span-2">
							<SelectQuery :urlRoute="`${baseUrl}/app/api/chats/agents`" :label="'label'"
								:valueProp="'agent_id'" :object="true" :searchable="true" :closeOnSelect="true"
								:canClear="true" :onChange="assignAgent">
								<template #option="{ option, isSelected, isPointed }">
									<div class="flex items-center px-2 py-1.5"
										:class="[isPointed(option) ? 'bg-gray-100' : '']">
										<div class="w-7 h-7 rounded-full flex items-center justify-center mr-2 text-xs font-medium text-white"
											:style="{
												backgroundColor: avatarAgent(
													option?.name || option?.label
												).color,
											}">
											{{
												avatarAgent(option?.name || option?.label).initials
											}}
										</div>
										<div class="flex-1">
											<div class="text-sm" :class="[isSelected(option) ? 'font-medium' : '']">
												{{ option?.label || option?.name }}
											</div>
											<span v-if="option.shop_names" class="mx-1 text-gray-400">
												—
											</span>

											<span
												class="text-gray-500 truncate max-w-[200px] inline-block align-bottom text-xs"
												:title="option.shop_names">
												{{ option.shop_names }}
											</span>
										</div>
									</div>
								</template>
							</SelectQuery>

							<div class="flex items-center gap-2 mt-2">
								<button class="px-2 py-1 text-xs border rounded" :disabled="isAssigningAgent"
									@click="isEditingAgent = false">
									{{ trans("Cancel") }}
								</button>
							</div>
						</div>
					</div>
					<AlertMessage v-if="agentAlert" :alert="agentAlert" />
					<AlertMessage v-if="priorityAlert" :alert="priorityAlert" />
					<ChatActivityTimeline :sessionUlid="session.ulid" :baseUrl="baseUrl" />
				</div>
			</div>
		</div>
	</Teleport>
</template>

<style scoped>
.tab-active {
	color: v-bind("layout?.app?.theme[4]") !important;
	border-bottom: 2px solid v-bind("layout?.app?.theme[4]") !important;
	font-weight: 600;
}

.tab-inactive {
	color: #6b7280;
}
</style>
