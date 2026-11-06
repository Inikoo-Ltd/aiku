<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import ConfirmDialog from "primevue/confirmdialog"
import { useConfirm } from "primevue/useconfirm"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowLeft, faExclamationTriangle, faMoneyCheckAlt, faPlus, faTimes } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TableAgentMoneyRequests, { MoneyRequestRow } from "@/Components/Tables/Grp/Org/Procurement/TableAgentMoneyRequests.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

library.add(faArrowLeft, faExclamationTriangle, faMoneyCheckAlt, faPlus, faTimes)

defineProps<{
	title: string
	pageHead: PageHeadingTypes
	create_route: routeType | null
	data: object
}>()

const confirm = useConfirm()

const cancelRequest = (row: MoneyRequestRow) =>
	confirm.require({
		group: "money-requests",
		header: ctrans("Cancel :reference", { reference: row.reference }),
		message: ctrans("The client will no longer be asked for this money, and its lines can be asked for again."),
		rejectLabel: ctrans("Keep it"),
		acceptLabel: ctrans("Cancel request"),
		accept: () =>
			router.patch(route(row.cancel_route!.name, row.cancel_route!.parameters), {}, {
				preserveScroll: true,
				onSuccess: () => notify({ title: ctrans("Request cancelled"), type: "success" }),
				onError: (errors) => notify({ title: ctrans("Something went wrong"), text: Object.values(errors ?? {})[0] as string, type: "error" }),
			}),
	})
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other>
			<Link v-if="create_route" :href="route(create_route.name, create_route.parameters)">
				<Button type="tertiary" :label="ctrans('New request')">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-plus" class="text-gray-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</Link>
		</template>
	</PageHeading>

	<TableAgentMoneyRequests :data="data" @cancel="cancelRequest" />

	<ConfirmDialog group="money-requests" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }">
		<template #container="{ message, acceptCallback, rejectCallback }">
			<div class="rounded-lg bg-white p-5">
				<div class="flex items-center gap-2 font-semibold text-gray-900">
					<FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-amber-500" fixed-width aria-hidden="true" />
					{{ message.header }}
				</div>
				<p class="mt-3 text-sm text-gray-600">{{ message.message }}</p>
				<div class="mt-5 flex justify-end gap-2">
					<Button type="tertiary" :label="message.rejectLabel" @click="rejectCallback">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-arrow-left" class="text-gray-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button type="negative" :label="message.acceptLabel" @click="acceptCallback">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
			</div>
		</template>
	</ConfirmDialog>
</template>
