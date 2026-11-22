<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import AgentSystemUserDialog from "@/Components/Procurement/AgentSystemUserDialog.vue"
import TableWebUserLogins from "@/Components/Tables/Grp/Org/CRM/TableWebUserLogins.vue"
import TableEmployeeRequests from "@/Components/Tables/Grp/Org/HumanResources/TableEmployeeRequests.vue"
import TableEmployeeSearches from "@/Components/Tables/Grp/Org/HumanResources/TableEmployeeSearches.vue"
import TableEmployeeChats from "@/Components/Tables/Grp/Org/HumanResources/TableEmployeeChats.vue"
import TableEmployeeAiQueries from "@/Components/Tables/Grp/Org/HumanResources/TableEmployeeAiQueries.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import { useTabChange } from "@/Composables/tab-change"
import { useFormatTime } from "@/Composables/useFormatTime"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPencil, faCheckCircle, faTimesCircle, faTerminal, faUser, faSignInAlt, faGlobe, faSearch, faComments, faRobot, faClock } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faTerminal, faUser, faSignInAlt, faGlobe, faSearch, faComments, faRobot, faClock, faPencil)

type Showcase = {
	id: number
	username: string
	contact_name: string | null
	email: string | null
	is_active: boolean
	positions: string[]
	organisations: string[]
	created_at: string | null
	number_logins: number
	last_login_at: string | null
	last_login_ip: string | null
	number_failed_logins: number
	last_failed_login_at: string | null
	last_active: string | null
	has_2fa: boolean
	is_two_factor_required: boolean
}

const props = defineProps<{
	title: string
	pageHead: object
	tabs: { current: string; navigation: object }
	management: { store_route: { name: string; parameters: number[] }; update_route: { name: string; parameters: number[] }; positions: { value: string; label: string }[] } | null
	showcase?: Showcase
	logins?: object
	requests?: object
	searches?: object
	chats?: object
	ai_queries?: object
	changes?: object
	history?: object
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(
	() =>
		({
			logins: TableWebUserLogins,
			requests: TableEmployeeRequests,
			searches: TableEmployeeSearches,
			chats: TableEmployeeChats,
			ai_queries: TableEmployeeAiQueries,
			changes: TableHistories,
			history: TableHistories,
		})[currentTab.value]
)

const dialog = ref<InstanceType<typeof AgentSystemUserDialog> | null>(null)

const formatDate = (date: string | null) => (date ? useFormatTime(date, { formatTime: "aiku" }) : "-")

function openEdit() {
	if (!props.showcase) {
		return
	}
	dialog.value?.openEdit({ id: props.showcase.id, username: props.showcase.username, contact_name: props.showcase.contact_name, email: props.showcase.email, status: { icon: props.showcase.is_active ? "fal fa-check" : "fal fa-times" } })
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other>
			<Button v-if="management && showcase" type="tertiary" :label="ctrans('Edit')" @click="openEdit">
				<template #icon>
					<FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</template>
	</PageHeading>
	<Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

	<div v-if="currentTab === 'showcase' && showcase" class="grid max-w-5xl gap-4 p-4 md:grid-cols-2">
		<section class="rounded-lg border border-gray-200 bg-white">
			<h3 class="border-b border-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-800">{{ ctrans("Account") }}</h3>
			<dl class="divide-y divide-gray-100 text-sm">
				<div class="flex justify-between gap-4 px-4 py-2">
					<dt class="text-gray-500">{{ ctrans("Status") }}</dt>
					<dd :class="showcase.is_active ? 'text-green-600' : 'text-red-500'">
						<FontAwesomeIcon :icon="showcase.is_active ? faCheckCircle : faTimesCircle" fixed-width aria-hidden="true" />
						{{ showcase.is_active ? ctrans("Active, can log in") : ctrans("Suspended, cannot log in") }}
					</dd>
				</div>
				<div class="flex justify-between gap-4 px-4 py-2">
					<dt class="text-gray-500">{{ ctrans("Username") }}</dt>
					<dd class="whitespace-nowrap text-gray-800">{{ showcase.username }}</dd>
				</div>
				<div class="flex justify-between gap-4 px-4 py-2">
					<dt class="text-gray-500">{{ ctrans("Name") }}</dt>
					<dd class="text-gray-800">{{ showcase.contact_name || "-" }}</dd>
				</div>
				<div class="flex justify-between gap-4 px-4 py-2">
					<dt class="text-gray-500">{{ ctrans("Email") }}</dt>
					<dd class="text-gray-800">{{ showcase.email || "-" }}</dd>
				</div>
				<div class="flex justify-between gap-4 px-4 py-2">
					<dt class="text-gray-500">{{ ctrans("Positions") }}</dt>
					<dd class="text-right text-gray-800">{{ showcase.positions.join(", ") || "-" }}</dd>
				</div>
				<div class="flex justify-between gap-4 px-4 py-2">
					<dt class="text-gray-500">{{ ctrans("Organisations") }}</dt>
					<dd class="text-right text-gray-800">{{ showcase.organisations.join(", ") || "-" }}</dd>
				</div>
				<div class="flex justify-between gap-4 px-4 py-2">
					<dt class="text-gray-500">{{ ctrans("Created") }}</dt>
					<dd class="text-gray-800">{{ formatDate(showcase.created_at) }}</dd>
				</div>
			</dl>
		</section>

		<section class="rounded-lg border border-gray-200 bg-white">
			<h3 class="border-b border-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-800">{{ ctrans("Access") }}</h3>
			<dl class="divide-y divide-gray-100 text-sm">
				<div class="flex justify-between gap-4 px-4 py-2">
					<dt class="text-gray-500">{{ ctrans("Last login") }}</dt>
					<dd class="text-gray-800">{{ formatDate(showcase.last_login_at) }}</dd>
				</div>
				<div class="flex justify-between gap-4 px-4 py-2">
					<dt class="text-gray-500">{{ ctrans("Last login IP") }}</dt>
					<dd class="text-gray-800">{{ showcase.last_login_ip || "-" }}</dd>
				</div>
				<div class="flex justify-between gap-4 px-4 py-2">
					<dt class="text-gray-500">{{ ctrans("Logins") }}</dt>
					<dd class="text-gray-800">{{ showcase.number_logins }}</dd>
				</div>
				<div class="flex justify-between gap-4 px-4 py-2">
					<dt class="text-gray-500">{{ ctrans("Last active") }}</dt>
					<dd class="text-gray-800">{{ formatDate(showcase.last_active) }}</dd>
				</div>
				<div class="flex justify-between gap-4 px-4 py-2">
					<dt class="text-gray-500">{{ ctrans("Failed logins") }}</dt>
					<dd :class="showcase.number_failed_logins ? 'text-red-500' : 'text-gray-800'">
						{{ showcase.number_failed_logins }}
						<span v-if="showcase.last_failed_login_at" class="text-gray-500">({{ ctrans("last :date", { date: formatDate(showcase.last_failed_login_at) }) }})</span>
					</dd>
				</div>
				<div class="flex justify-between gap-4 px-4 py-2">
					<dt class="text-gray-500">{{ ctrans("Two-factor authentication") }}</dt>
					<dd :class="showcase.has_2fa ? 'text-green-600' : 'text-gray-500'">
						{{ showcase.has_2fa ? ctrans("Enabled") : showcase.is_two_factor_required ? ctrans("Required, not set up yet") : ctrans("Not enabled") }}
					</dd>
				</div>
			</dl>
		</section>
	</div>

	<component :is="component" v-if="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />

	<AgentSystemUserDialog v-if="management" ref="dialog" :management="management" />
</template>
