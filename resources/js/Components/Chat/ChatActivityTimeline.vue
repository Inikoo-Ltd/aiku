<script setup lang="ts">
import { ref, onMounted, watch, computed } from "vue"
import axios from "axios"
import { capitalize } from "@/Composables/capitalize"

const props = defineProps<{
	sessionUlid: string
	baseUrl?: string
}>()

const loading = ref(false)
const error = ref<string | null>(null)
const activities = ref<any[]>([])
const chatSession = ref<any | null>(null)

const buildUrl = computed(() => {
	const base = (props.baseUrl || "").replace(/\/+$/, "")
	return `${base}/app/api/chats/sessions/${props.sessionUlid}/activity`
})

const getInitials = (name: string | undefined): string => {
	if (!name) return "?"
	const trimmedName = name.trim()
	if (!trimmedName) return "?"
	const parts = trimmedName.split(/\s+/)
	if (parts.length === 1) return parts[0].charAt(0).toUpperCase()
	return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase()
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

const getRandomColor = (name: string | undefined): string => {
	if (!name) return "#6B7280"
	let hash = 0
	for (let i = 0; i < name.length; i++) {
		hash = name.charCodeAt(i) + ((hash << 5) - hash)
	}
	const colors = [
		"#EF4444",
		"#F97316",
		"#EAB308",
		"#22C55E",
		"#3B82F6",
		"#8B5CF6",
		"#EC4899",
		"#6366F1",
		"#10B981",
		"#F59E0B",
		"#06B6D4",
		"#8B5CF6",
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

const load = async () => {
	loading.value = true
	error.value = null
	try {
		const res = await axios.get(buildUrl.value, {
			headers: { Accept: "application/json" },
			withCredentials: true,
		})
		const json = res.data
		if (!json?.success) throw new Error(json?.message || "Error loading activity")
		const data = json.data || {}
		chatSession.value = data.chat_session || null
		activities.value = Array.isArray(data.activities) ? data.activities : []
	} catch (e: any) {
		error.value = e?.response?.data?.message || e?.message || String(e)
	} finally {
		loading.value = false
	}
}

onMounted(load)
watch(
	() => props.sessionUlid,
	async (nv, ov) => {
		if (nv && nv !== ov) await load()
	}
)
</script>

<template>
	<div v-if="loading" class="space-y-3">
		<div class="h-4 bg-gray-200 rounded animate-pulse w-1/3"></div>
		<div class="h-4 bg-gray-200 rounded animate-pulse w-2/3"></div>
		<div class="h-4 bg-gray-200 rounded animate-pulse w-1/2"></div>
	</div>

	<div v-else-if="error" class="text-red-600 text-sm">
		{{ error }}
	</div>

	<div v-else>
		<div class="mb-3 mt-5">
			<div class="text-sm text-gray-500">Activity Log</div>
		</div>

		<ul class="space-y-3">
			<li
				v-for="activity in activities"
				:key="activity.id"
				class="flex flex-col items-start gap-10">
				<div class="flex item-start gap-3">
					<div
						class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-semibold text-white select-none"
						:style="{ backgroundColor: avatarAgent(activity?.actor?.name).color }">
						{{ avatarAgent(activity?.actor?.name).initials }}
					</div>

					<div class="flex-1">
						<div class="text-sm text-gray-800">
							{{ activity.details?.description || activity.event_label }}
						</div>

						<div class="flex flex-col items-start gap-1 text-[12px]">
							<span class="text-xs text-gray-500">{{
								activity.created_at_relative || activity.created_at_formatted
							}}</span>
							<span class="font-semibold text-gray-800">
								<span class="font-normal">by</span>
								{{ capitalize(activity.actor?.name || "-") }}</span
							>
						</div>

						<div
							v-if="
								activity?.details?.from_agent_name ||
								activity?.details?.to_agent_name
							"
							class="mt-2 flex items-center gap-1">
							<div
								v-if="activity?.details?.from_agent_name"
								class="px-2 py-1 text-xs font-medium bg-gray-50 text-gray-700 border border-gray-50">
								{{ activity.details.from_agent_name }}
							</div>

							<div
								v-if="
									activity?.details?.from_agent_name &&
									activity?.details?.to_agent_name
								"
								class="px-2 py-1.5 text-gray-500">
								→
							</div>

							<div
								v-if="activity?.details?.to_agent_name"
								class="px-2 py-1 text-xs font-medium bg-blue-50 text-blue-700 border border-blue-50">
								{{ activity.details.to_agent_name }}
							</div>
						</div>

						<div
							v-if="
								activity?.event_type === 'priority' &&
								(activity?.details?.priority_previous ||
									activity?.details?.priority_current)
							"
							class="mt-2 flex items-center gap-1">
							<div
								v-if="activity?.details?.priority_previous"
								class="px-2 py-1 text-xs font-medium border rounded"
								:class="'bg-gray-50 text-gray-700 border-gray-50'">
								{{ activity.details.priority_previous }}
							</div>

							<div
								v-if="
									activity?.details?.priority_previous &&
									activity?.details?.priority_current
								"
								class="px-2 py-1.5 text-gray-500">
								→
							</div>

							<div
								v-if="
									activity?.details?.priority_current ||
									activity?.details?.priority
								"
								class="px-2 py-1 text-xs font-medium border rounded"
								:class="
									priorityClass(
										activity?.details?.priority_current ||
											activity?.details?.priority
									)
								">
								{{ activity.details.priority_current || activity.details.priority }}
							</div>
						</div>
					</div>
				</div>
			</li>
		</ul>
	</div>
</template>

<style scoped></style>
