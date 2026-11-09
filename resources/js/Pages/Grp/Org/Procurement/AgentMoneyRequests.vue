<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import ConfirmDialog from "primevue/confirmdialog"
import { useConfirm } from "primevue/useconfirm"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowLeft, faExclamationTriangle, faMoneyCheckAlt, faPaperPlane, faPlus, faTimes } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TableAgentMoneyRequests, { MoneyRequestRecipient, MoneyRequestRow } from "@/Components/Tables/Grp/Org/Procurement/TableAgentMoneyRequests.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useTruncate } from "@/Composables/useTruncate"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

library.add(faArrowLeft, faExclamationTriangle, faMoneyCheckAlt, faPaperPlane, faPlus, faTimes)

defineProps<{
	title: string
	pageHead: PageHeadingTypes
	create_route: routeType | null
	data: object
}>()

const confirm = useConfirm()
const isOpeningCreate = ref(false)
const cancelError = ref<string | null>(null)
const remindingId = ref<number | null>(null)
const isReminderDialog = ref(false)
const dialogReference = ref("")
const reminderRecipients = ref<{ label: string; recipients: MoneyRequestRecipient[] }[]>([])

const headerReferenceMaxLength = 40

const headerReference = (row: MoneyRequestRow) => {
	dialogReference.value = row.reference
	return useTruncate(row.reference, headerReferenceMaxLength, 0)
}

const cancelRequest = (row: MoneyRequestRow) => {
	isReminderDialog.value = false
	confirm.require({
		group: "money-requests",
		header: ctrans("Cancel :reference", { reference: headerReference(row) }),
		message: ctrans("The client will no longer be asked for this money, and its lines can be asked for again."),
		rejectLabel: ctrans("Keep it"),
		acceptLabel: ctrans("Cancel request"),
		accept: () => {
			cancelError.value = null
			router.patch(route(row.cancel_route!.name, row.cancel_route!.parameters), {}, {
				preserveScroll: true,
				onError: (errors) => (cancelError.value = `${row.reference}: ${Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")}`),
			})
		},
	})
}

const remindRequest = (row: MoneyRequestRow) => {
	isReminderDialog.value = true
	reminderRecipients.value = [
		{ label: ctrans("To"), recipients: row.reminder_recipients?.to ?? [] },
		{ label: ctrans("Cc"), recipients: row.reminder_recipients?.cc ?? [] },
	]
	confirm.require({
		group: "money-requests",
		header: ctrans("Send a reminder for :reference", { reference: headerReference(row) }),
		message: [
			ctrans("The money request email is sent again to the client's payers and copied users."),
			row.last_reminded_at ? ctrans("Last reminder sent :time by :user.", { time: useFormatTime(row.last_reminded_at, { formatTime: "hm" }), user: row.last_reminded_by ?? "-" }) : "",
		].filter(Boolean).join(" "),
		rejectLabel: ctrans("Back"),
		acceptLabel: ctrans("Send reminder"),
		accept: () => {
			cancelError.value = null
			router.post(route(row.remind_route!.name, row.remind_route!.parameters), {}, {
				preserveScroll: true,
				onStart: () => (remindingId.value = row.id),
				onFinish: () => (remindingId.value = null),
				onError: (errors) => (cancelError.value = `${row.reference}: ${Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")}`),
			})
		},
	})
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other>
			<Link v-if="create_route" :href="route(create_route.name, create_route.parameters)" @start="isOpeningCreate = true" @finish="isOpeningCreate = false">
				<Button type="tertiary" :label="ctrans('New request')" :loading="isOpeningCreate">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-plus" class="text-gray-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</Link>
		</template>
	</PageHeading>

	<p v-if="cancelError" class="mx-4 mt-4 text-xs text-red-600">{{ cancelError }}</p>

	<TableAgentMoneyRequests :data="data" :remindingId="remindingId" @cancel="cancelRequest" @remind="remindRequest" />

	<ConfirmDialog group="money-requests" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }">
		<template #container="{ message, acceptCallback, rejectCallback }">
			<div class="rounded-lg bg-white p-5">
				<div class="flex items-center gap-2 font-semibold text-gray-900" :title="dialogReference">
					<FontAwesomeIcon v-if="isReminderDialog" icon="fal fa-paper-plane" class="text-gray-500" fixed-width aria-hidden="true" />
					<FontAwesomeIcon v-else icon="fal fa-exclamation-triangle" class="text-amber-500" fixed-width aria-hidden="true" />
					{{ message.header }}
				</div>
				<p class="mt-3 text-sm text-gray-600">{{ message.message }}</p>
				<dl v-if="isReminderDialog" class="mt-3 space-y-2 text-sm">
					<div v-for="group in reminderRecipients" :key="group.label" class="flex gap-3">
						<dt class="w-8 shrink-0 font-medium text-gray-500">{{ group.label }}</dt>
						<dd class="min-w-0 flex-1">
							<span v-if="!group.recipients.length" class="text-gray-400">{{ ctrans("Nobody") }}</span>
							<ul v-else class="space-y-1">
								<li v-for="recipient in group.recipients" :key="`${recipient.name}-${recipient.email}`" :class="recipient.by_email ? 'text-gray-900' : 'text-gray-400'">
									<span class="font-medium">{{ recipient.name }}</span>
									<span class="ml-1 break-all text-xs" :class="recipient.by_email ? 'text-gray-500' : ''">{{ recipient.email ?? ctrans("no email") }}</span>
									<span v-if="!recipient.by_email" class="block text-xs">{{ recipient.email ? ctrans("Email off: only notified in aiku") : ctrans("Only notified in aiku") }}</span>
								</li>
							</ul>
						</dd>
					</div>
				</dl>
				<div class="mt-5 flex justify-end gap-2">
					<Button type="tertiary" :label="message.rejectLabel" @click="rejectCallback">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-arrow-left" class="text-gray-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button v-if="isReminderDialog" type="primary" :label="message.acceptLabel" @click="acceptCallback">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-paper-plane" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button v-else type="negative" :label="message.acceptLabel" @click="acceptCallback">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
			</div>
		</template>
	</ConfirmDialog>
</template>
