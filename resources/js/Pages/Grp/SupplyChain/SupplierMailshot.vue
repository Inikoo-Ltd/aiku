<script setup lang="ts">
import { ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMailBulk, faPencil, faPaperPlane, faPersonDolly } from "@fal"

library.add(faMailBulk, faPencil, faPaperPlane, faPersonDolly)

interface RouteTarget {
	name: string
	parameters: unknown[]
}

interface RecipientRow {
	email: string
	supplier_code: string
	supplier_name: string
	supplier_slug: string
	state: string
	first_read_at: string | null
	number_reads: number
	number_clicks: number
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	summary: {
		state: string
		state_label: string
		sent_as: string | null
		countries: string
		is_draft: boolean
		is_published: boolean
		will_reach: number | null
		sent_at: string | null
		emails: number
		read: number
		clicked: number
		unsubscribed: number
	}
	can_send: boolean
	routes: { workshop: RouteTarget; send: RouteTarget }
	recipients?: object | null
	errors?: Record<string, string>
}>()

const isConfirming = ref(false)
const isSending = ref(false)

const send = () => {
	isSending.value = true
	router.post(route(props.routes.send.name, props.routes.send.parameters), {}, {
		preserveScroll: true,
		onFinish: () => {
			isSending.value = false
			isConfirming.value = false
		},
	})
}

const deliveryLabel = (state: string) =>
	({ opened: ctrans("Read"), clicked: ctrans("Read"), delivered: ctrans("Delivered"), sent: ctrans("Sent"), sent_to_provider: ctrans("Sending"), ready: ctrans("Sending"), hard_bounce: ctrans("Bounced"), soft_bounce: ctrans("Bounced"), unsubscribed: ctrans("Unsubscribed"), error: ctrans("Failed") })[state] ?? state
const deliveryClass = (state: string) =>
	["opened", "clicked"].includes(state) ? "text-green-700" : ["hard_bounce", "soft_bounce", "error"].includes(state) ? "text-red-600" : "text-gray-500"
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<dl class="m-4 grid max-w-3xl grid-cols-[auto_1fr] gap-x-8 gap-y-2.5 text-sm">
		<dt class="text-gray-500">{{ ctrans("Status") }}</dt>
		<dd class="font-medium" :class="summary.state === 'sent' ? 'text-green-700' : 'text-gray-900'">{{ summary.state_label }}</dd>
		<dt class="text-gray-500">{{ ctrans("Suppliers in") }}</dt>
		<dd class="text-gray-900">{{ summary.countries || "—" }}</dd>
		<dt class="text-gray-500">{{ ctrans("Send as") }}</dt>
		<dd class="text-gray-900">{{ summary.sent_as ?? "—" }}</dd>
		<template v-if="summary.is_draft">
			<dt class="text-gray-500">{{ ctrans("Supplier emails") }}</dt>
			<dd class="tabular-nums text-gray-900">{{ summary.will_reach ?? 0 }}</dd>
		</template>
		<template v-else>
			<dt class="text-gray-500">{{ ctrans("Sent") }}</dt>
			<dd class="text-gray-900">{{ summary.sent_at ? useFormatTime(summary.sent_at, { formatTime: "hm" }) : "—" }}</dd>
			<dt class="text-gray-500">{{ ctrans("Emails") }}</dt>
			<dd class="tabular-nums text-gray-900">{{ summary.emails }}</dd>
			<dt class="text-gray-500">{{ ctrans("Read") }}</dt>
			<dd class="tabular-nums text-gray-900">{{ summary.read }}<span v-if="summary.emails" class="text-gray-400"> · {{ Math.round((summary.read / summary.emails) * 100) }}%</span></dd>
			<dt class="text-gray-500">{{ ctrans("Clicked") }}</dt>
			<dd class="tabular-nums text-gray-900">{{ summary.clicked }}</dd>
			<dt class="text-gray-500">{{ ctrans("Unsubscribed") }}</dt>
			<dd class="tabular-nums text-gray-900">{{ summary.unsubscribed }}</dd>
		</template>
	</dl>

	<div v-if="summary.is_draft && can_send" class="m-4 max-w-3xl space-y-2">
		<div class="flex flex-wrap items-center gap-2">
			<Link :href="route(routes.workshop.name, routes.workshop.parameters)">
				<Button type="tertiary" size="xs" :label="ctrans('Write the email')">
					<template #icon><FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" /></template>
				</Button>
			</Link>
			<Button v-if="!isConfirming" type="tertiary" size="xs" :label="ctrans('Send')" :disabled="!summary.will_reach" @click="isConfirming = true">
				<template #icon><FontAwesomeIcon icon="fal fa-paper-plane" class="text-green-600" fixed-width aria-hidden="true" /></template>
			</Button>
			<template v-else>
				<span class="text-sm text-gray-700">{{ ctrans("Send it now to :count supplier emails?", { count: String(summary.will_reach ?? 0) }) }}</span>
				<Button type="tertiary" size="xs" :label="ctrans('Yes, send')" :loading="isSending" @click="send" />
				<Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="isConfirming = false" />
			</template>
		</div>
		<p v-if="!summary.is_published" class="text-xs text-gray-500">{{ ctrans("Write the email and publish it in the editor (Ctrl+S) before sending.") }}</p>
		<p v-if="errors?.send || errors?.subject" class="text-xs text-red-600">{{ errors?.send ?? errors?.subject }}</p>
	</div>

	<Table v-if="recipients" :resource="recipients" name="recipients" class="mt-4">
		<template #cell(supplier_code)="{ item }: { item: RecipientRow }">
			<Link :href="route('grp.supply-chain.suppliers.show', [item.supplier_slug])" class="primaryLink whitespace-nowrap">{{ item.supplier_code }}</Link>
			<span class="text-gray-400"> · {{ item.supplier_name }}</span>
		</template>
		<template #cell(state)="{ item }: { item: RecipientRow }">
			<span class="whitespace-nowrap" :class="deliveryClass(item.state)">{{ deliveryLabel(item.state) }}</span>
		</template>
		<template #cell(first_read_at)="{ item }: { item: RecipientRow }">
			<span v-if="item.first_read_at" class="whitespace-nowrap">{{ useFormatTime(item.first_read_at, { formatTime: "hm" }) }}<span v-if="item.number_reads > 1" class="text-gray-400"> · {{ item.number_reads }}×</span></span>
			<span v-else class="text-gray-400">{{ ctrans("Not read") }}</span>
		</template>
		<template #cell(number_clicks)="{ item }: { item: RecipientRow }">
			<span class="tabular-nums">{{ item.number_clicks }}</span>
		</template>
	</Table>
</template>
