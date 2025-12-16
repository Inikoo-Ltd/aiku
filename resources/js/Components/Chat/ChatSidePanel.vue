<script setup lang="ts">
import { ref, inject, onMounted, watch, computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { Link } from "@inertiajs/vue3"
import axios from "axios"
import MessageHistory from "@/Components/Chat/MessageHistory.vue"
import type { SessionAPI } from "@/types/Chat/chat"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faClose, faUser } from "@fortawesome/free-solid-svg-icons"
import { capitalize } from "@/Composables/capitalize"
import AlertMessage from "@/Components/Utils/AlertMessage.vue"

const props = defineProps<{
	session: SessionAPI | null
	initialTab?: "history" | "profile"
}>()

const selectedHistory = ref<{
	ulid: string
	contact_name?: string
	guest_identifier?: string
} | null>(null)

const emit = defineEmits<{
	(e: "close"): void
	(e: "sync-success"): void
}>()

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

const activeTab = ref<"history" | "profile">(props.initialTab ?? "history")
const userProfile = ref<any | null>(null)
const syncEmail = ref(props.session?.guest_profile?.email || "")
const syncEmailAlert = ref<{
	status: string
	title?: string
	description?: string
} | null>(null)
const isSyncing = ref(false)

const isLoadingHistory = ref(false)
const historyError = ref<string | null>(null)
const userSessions = ref<SessionAPI[]>([])

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

const displayName = computed(() => {
	return props.session?.contact_name || props.session?.guest_identifier || ""
})
const avatarUrl = computed(() => {
	return `https://i.pravatar.cc/100?u=${props.session?.ulid}`
})

const fetchUserProfile = async () => {
	userProfile.value = {
		name: displayName.value,
		email: props.session?.guest_profile?.email || null,
		phone: props.session?.guest_profile?.phone || null,
	}
	try {
		const slug = props.session?.web_user?.slug
		if (slug) {
			const res = await axios.get(`${baseUrl}/app/api/web-users/${slug}`)
			userProfile.value = res.data?.data ?? res.data ?? userProfile.value
		}
	} catch (e) {}
}

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
		syncEmailAlert.value = {
			status: "success",
			title: "Success",
			description: response.data.message,
		}

		setTimeout(() => {
			syncEmailAlert.value = null
			emit("close")
		}, 3000)

		emit("sync-success")
		// emit("close")
	} catch (e: any) {
		syncEmailAlert.value = {
			status: "danger",
			title: "Error",
			description: e.response?.data?.message || e.message,
		}
	} finally {
		isSyncing.value = false
	}
}

watch(
	() => props.session?.ulid,
	async () => {
		if (activeTab.value === "profile") {
			await fetchUserProfile()
		}
	}
)

watch(
	() => activeTab.value,
	async (tab) => {
		if (tab === "history") {
			await loadUserSessions()
		}
	}
)

onMounted(async () => {
	if (activeTab.value === "profile") {
		await fetchUserProfile()
	}
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

			<div v-else class="p-4 space-y-3">
				<div class="grid grid-cols-3 gap-2 items-center">
					<div class="text-gray-500">{{ trans("Name") }}</div>
					<div class="col-span-2 font-medium">
						{{ displayName || "-" }}
					</div>
				</div>

				<div class="grid grid-cols-3 gap-2 items-center">
					<div class="text-gray-500">{{ trans("Email") }}</div>
					<div class="col-span-2 font-medium">{{ userProfile?.email || "-" }}</div>
				</div>

				<div class="grid grid-cols-3 gap-2 items-center">
					<div class="text-gray-500">{{ trans("Phone") }}</div>
					<div class="col-span-2 font-medium">{{ userProfile?.phone || "-" }}</div>
				</div>

				<div v-if="props.session?.web_user" class="grid grid-cols-3 gap-2 items-center">
					<div class="text-gray-500">{{ trans("Organisation") }}</div>
					<div class="col-span-2 font-medium">
						{{ props.session?.web_user?.organisation || "-" }}
					</div>
				</div>
				<div v-if="props.session?.web_user" class="grid grid-cols-3 gap-2 items-center">
					<div class="text-gray-500">{{ trans("Shop") }}</div>
					<div class="col-span-2 font-medium">
						{{ props.session?.web_user?.shop || "-" }}
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
						{{ isSyncing ? trans("Syncing...") : trans("Sync by email") }}
					</button>
					<AlertMessage v-if="syncEmailAlert" :alert="syncEmailAlert" />
				</div>
				<div v-else class="pt-2 space-y-2">
					<div class="text-xs text-gray-500">{{ trans("Click to Customer Detail") }}</div>
					<Link
						:href="`/org/${props.session.web_user.organisation_slug}/shops/${props.session.web_user.shop_slug}/crm/customers/${props.session.web_user.slug}`"
						class="w-full px-3 py-2 buttonPrimary rounded bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50"
						as="button">
						{{ trans("View Customer Profile") }}
					</Link>
				</div>
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
