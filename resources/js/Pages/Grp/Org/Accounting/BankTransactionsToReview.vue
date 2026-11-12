<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 12 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Textarea from "primevue/textarea"
import Checkbox from "primevue/checkbox"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUniversity, faCheck, faTimes, faArchive, faReceipt, faUndo } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faUniversity, faCheck, faTimes, faArchive, faReceipt, faUndo)

interface BankLine {
	id: number
	booked_at: string
	bank: string
	amount: number
	currency_code: string
	counterparty_name: string | null
	reference: string | null
	reason: string | null
	owed: number | null
	order_reference: string | null
	order_slug: string | null
	shop_slug: string | null
	is_dismissed: boolean
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	canEdit: boolean
	organisationId: number
	scope: "review" | "unmatched" | "dismissed"
	data: { data: BankLine[] }
}>()

const locale = useLocaleStore()

const sentenceCase = (text: string) => text.charAt(0).toUpperCase() + text.slice(1)

const orderRoute = (line: BankLine) =>
	line.order_slug && line.shop_slug
		? route("grp.org.shops.show.ordering.orders.show", [route().params["organisation"], line.shop_slug, line.order_slug])
		: null

const scopeOptions = [
	{ label: ctrans("To review"), value: "review" },
	{ label: ctrans("No order number"), value: "unmatched" },
	{ label: ctrans("Set aside"), value: "dismissed" },
]
const scope = computed({
	get: () => props.scope,
	set: (value: string) => router.get(route("grp.org.accounting.bank_review.index", [route().params["organisation"]]), { scope: value }, { preserveState: false }),
})

const restoreError = ref<{ id: number; message: string } | null>(null)
const restore = (line: BankLine) => {
	router.post(route("grp.models.org.bank_transaction.restore", [props.organisationId, line.id]), {}, {
		preserveScroll: true,
		onError: (errors: Record<string, string>) => (restoreError.value = { id: line.id, message: Object.values(errors ?? {})[0] ?? ctrans("Something went wrong") }),
	})
}

const selected = ref<BankLine | null>(null)
const action = ref<"pay" | "dismiss" | null>(null)
const orderReference = ref("")
const absorbBankFee = ref(true)
const note = ref("")
const error = ref<string | null>(null)
const isSaving = ref(false)

const visible = computed({
	get: () => action.value !== null,
	set: (value: boolean) => {
		if (!value) {
			action.value = null
		}
	},
})

const header = computed(() =>
	action.value === "pay" ? ctrans("Record this bank payment on an order") : ctrans("Set this bank payment aside")
)

const canSubmit = computed(() => (action.value === "pay" ? orderReference.value.trim() !== "" : note.value.trim() !== ""))

const open = (line: BankLine, kind: "pay" | "dismiss") => {
	selected.value = line
	action.value = kind
	orderReference.value = line.order_reference ?? ""
	absorbBankFee.value = true
	note.value = ""
	error.value = null
}

const submit = () => {
	if (!selected.value || !canSubmit.value) {
		return
	}
	const organisationId = props.organisationId
	const url =
		action.value === "pay"
			? route("grp.models.org.bank_transaction.pay_order", [organisationId, selected.value.id])
			: route("grp.models.org.bank_transaction.dismiss", [organisationId, selected.value.id])
	const payload =
		action.value === "pay"
			? { order_reference: orderReference.value.trim(), absorb_bank_fee: absorbBankFee.value }
			: { note: note.value.trim() }

	router.post(url, payload, {
		preserveScroll: true,
		onStart: () => (isSaving.value = true),
		onFinish: () => (isSaving.value = false),
		onSuccess: () => (action.value = null),
		onError: (errors: Record<string, string>) => (error.value = Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")),
	})
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="px-4 pt-3">
		<SegmentedToggle v-model="scope" :options="scopeOptions" :aria-label="ctrans('Which bank payments')" />
	</div>

	<Table :resource="data" class="mt-5">
		<template #cell(booked_at)="{ item }">
			<span class="whitespace-nowrap">{{ useFormatTime(item.booked_at) }}</span>
		</template>

		<template #cell(amount)="{ item }">
			<span class="whitespace-nowrap tabular-nums">{{ locale.currencyFormat(item.currency_code, item.amount) }}</span>
		</template>

		<template #cell(reference)="{ item }">
			<span class="text-gray-600">{{ item.reference ?? "—" }}</span>
		</template>

		<template #cell(reason)="{ item }">
			<span class="text-sm text-gray-600">{{ item.reason ? sentenceCase(ctrans(item.reason)) : "—" }}</span>
		</template>

		<template #cell(order_reference)="{ item }">
			<Link v-if="orderRoute(item)" :href="orderRoute(item)" class="primaryLink whitespace-nowrap">
				{{ item.order_reference }}
			</Link>
			<span v-else class="text-gray-400">—</span>
		</template>

		<template #cell(actions)="{ item }">
			<div v-if="canEdit && item.is_dismissed" class="flex flex-col items-end gap-1 whitespace-nowrap">
				<Button type="tertiary" size="xs" :label="ctrans('Bring back')" @click="restore(item)">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-undo" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<span v-if="restoreError?.id === item.id" class="text-xs text-red-600">{{ restoreError.message }}</span>
			</div>
			<div v-else-if="canEdit" class="flex justify-end gap-2 whitespace-nowrap">
				<Button type="tertiary" size="xs" :label="ctrans('Pay order')" @click="open(item, 'pay')">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" size="xs" :label="ctrans('Set aside')" @click="open(item, 'dismiss')">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-archive" class="text-amber-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Table>

	<Dialog
		v-model:visible="visible"
		modal
		:header="header"
		:style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div v-if="selected" class="space-y-4 text-sm">
			<div class="rounded border border-gray-200 px-3 py-2">
				<div class="font-semibold tabular-nums">{{ locale.currencyFormat(selected.currency_code, selected.amount) }}</div>
				<div class="text-gray-600">{{ selected.counterparty_name ?? "—" }} · {{ selected.bank }}</div>
				<div v-if="selected.reference" class="text-gray-500">{{ selected.reference }}</div>
			</div>

			<template v-if="action === 'pay'">
				<label class="block">
					<span class="mb-1 block font-medium">{{ ctrans("Order") }} <span class="text-red-500">*</span></span>
					<InputText v-model="orderReference" class="w-full" :placeholder="ctrans('Order number')" @keyup.enter="submit" />
				</label>
				<label class="flex items-start gap-2">
					<Checkbox v-model="absorbBankFee" binary input-id="absorb-bank-fee" />
					<span>
						{{ ctrans("If it arrived a little short, absorb the bank fee so the order is paid") }}
						<span class="block text-xs text-gray-500">{{ ctrans("Only up to the bank fee limit; anything more stays owed on the order") }}</span>
					</span>
				</label>
			</template>

			<label v-else class="block">
				<span class="mb-1 block font-medium">{{ ctrans("Why") }} <span class="text-red-500">*</span></span>
				<Textarea v-model="note" rows="3" class="w-full" :placeholder="ctrans('e.g. marketplace payout, already recorded by hand')" />
			</label>

			<p v-if="error" class="text-xs text-red-600">{{ error }}</p>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button
					v-if="action === 'pay'"
					type="primary"
					full
					:label="ctrans('Record payment')"
					:loading="isSaving"
					:disabled="!canSubmit"
					@click="submit"
				>
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button
					v-else
					type="tertiary"
					full
					:label="ctrans('Set aside')"
					:loading="isSaving"
					:disabled="!canSubmit"
					@click="submit"
				>
					<template #icon>
						<FontAwesomeIcon icon="fal fa-archive" class="text-amber-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
