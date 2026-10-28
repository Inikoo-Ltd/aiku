<script setup lang="ts">
import { ref } from "vue"
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableSupplierPortalVisits from "@/Components/Tables/Grp/SupplyChain/TableSupplierPortalVisits.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLink, faEnvelope, faInfoCircle, faEye } from "@fal"

library.add(faLink, faEnvelope, faInfoCircle, faEye)

interface Fact {
	label: string
	type: "text" | "number" | "date" | "state" | "from"
	value: any
	note?: string | null
	empty?: string
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	tabs: {
		current: string
		navigation: object
	}
	overview?: Fact[]
	visits?: object
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const states: Record<string, { label: string; class: string }> = {
	active: { label: "Active", class: "text-green-700" },
	expired: { label: "Expired", class: "text-gray-400" },
	stopped: { label: "Stopped", class: "text-red-600" },
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

	<dl v-if="currentTab === 'overview' && overview" class="m-4 grid max-w-3xl grid-cols-[auto_1fr] gap-x-8 gap-y-2.5 text-sm">
		<template v-for="fact in overview" :key="fact.label">
			<dt class="whitespace-nowrap text-gray-500">{{ fact.label }}</dt>
			<dd class="text-gray-900">
				<span v-if="fact.type === 'state'" class="font-medium" :class="states[fact.value]?.class">{{ ctrans(states[fact.value]?.label ?? fact.value) }}</span>
				<template v-else-if="fact.type === 'date'">
					<span v-if="fact.value">{{ useFormatTime(fact.value, { formatTime: "hm" }) }}</span>
					<span v-else class="text-gray-400">{{ fact.empty ?? "—" }}</span>
					<span v-if="fact.note" class="text-gray-400"> · {{ fact.note }}</span>
				</template>
				<span v-else-if="fact.type === 'number'" class="tabular-nums">{{ fact.value }}</span>
				<span v-else-if="fact.type === 'from'" class="flex items-center gap-1.5">
					<template v-if="fact.value">
						<img v-if="fact.value.country_code" :src="`/flags/${fact.value.country_code.toLowerCase()}.png`" class="h-3 w-4 object-cover" :alt="fact.value.country_code" />
						<span v-if="fact.value.city">{{ fact.value.city }}</span>
						<span class="font-mono text-xs text-gray-400">{{ fact.value.ip }}</span>
					</template>
					<span v-else class="text-gray-400">—</span>
				</span>
				<span v-else>{{ fact.value ?? "—" }}</span>
			</dd>
		</template>
	</dl>
	<TableSupplierPortalVisits v-else-if="currentTab === 'visits' && visits" :data="visits" tab="visits" />
</template>
