<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 05 Nov 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import DatePicker from "primevue/datepicker"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import StockDeliveryAgentPayments from "@/Components/Procurement/StockDeliveryAgentPayments.vue"
import StockDeliverySupplierPayments from "@/Components/Procurement/StockDeliverySupplierPayments.vue"
import { ctrans } from "@/Composables/useTrans"
import { useConfirm } from "primevue/useconfirm"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFolderOpen, faUpload, faUndo, faCheckCircle, faCircle, faMinusCircle, faFileAlt, faMagic, faBoxUsd, faCoins, faTimes, faLock, faFilePdf, faFileArchive, faPaperclip, faDownload } from "@fal"

library.add(faFolderOpen, faUpload, faUndo, faCheckCircle, faCircle, faMinusCircle, faFileAlt, faMagic, faBoxUsd, faCoins, faTimes, faLock, faFilePdf, faFileArchive, faPaperclip, faDownload)

type CostingSection = "invoice" | "landed"

type Status = "done" | "pending" | "na"

type Step = {
	key: string
	label: string
	status: Status
	date: string | null
	source: string | null
	is_auto: boolean
	marked: { by: string | null; at: string | null } | null
	scope: string | null
	costing: CostingSection | null
	upload_in_costing: boolean
	files: { id: number; media_id: number; is_applied: boolean; file_name: string | null; caption: string | null; url: string }[]
	money: DepositMoney | BalanceMoney | null
}

type DepositRow = {
	purchase_order: string
	date: string | null
	amount: number
	currency: string | null
	original_amount: number | null
	original_currency: string | null
	percent: number | null
	is_paid: boolean
	bank_statement: string | null
}

type DepositMoney = { rows: DepositRow[]; currency: string | null; total: number | null }

type BalanceMoney = { currency: string; total: number; total_source: "invoice" | "orders"; orders_total?: number | null; deposits: number; payments: number; left: number; scheduled: { amount: number; date: string } | null }

const props = defineProps<{
	data: {
		summary: {
			purchase_orders: { reference: string; route: routeType }[]
			supplier: string | null
			suppliers: string[]
			agent: string | null
			ordered_at: string | null
			incoterm: string | null
			mode: "sea" | "air" | "road" | null
			eta: string | null
			delivered_at: string | null
			days: number | null
		}
		steps: Step[]
		outstanding: string[]
		costs_missing: string[]
		checks: { goods_received: boolean; costed: boolean; documents: boolean }
		is_complete: boolean
		can_edit: boolean
		can_upload: boolean
		update_route: routeType
		attach_route: routeType
		detach_route: routeType
		documents_route: routeType
		documents_count: number
	}
	tab?: string
	costingAvailable?: Record<CostingSection, boolean>
	invoice?: {
		source: string
		currency: string
		total: number
		org_currency?: string | null
		org_exchange?: number | null
		charges_list: unknown[]
		agent?: InstanceType<typeof StockDeliveryAgentPayments>["$props"]["agent"] | null
		supplier?: InstanceType<typeof StockDeliverySupplierPayments>["$props"]["supplier"] | null
	} | null
}>()

const emits = defineEmits<{
	(e: "openCosting", section: CostingSection): void
}>()

const costingButtonLabel = computed<Record<CostingSection, string>>(() => ({
	invoice: ctrans("Adjust costing"),
	landed: ctrans("Landed costs"),
}))

const statusOptions = computed(() => [
	{ label: ctrans("Done"), value: "done" as Status, icon: faCheckCircle, tone: "emerald" as const },
	{ label: ctrans("Pending"), value: "pending" as Status, icon: faCircle, tone: "amber" as const },
	{ label: ctrans("N/A"), value: "na" as Status, icon: faMinusCircle, tone: "gray" as const },
])

const modeLabel = computed(() => ({ sea: ctrans("Sea"), air: ctrans("Air"), road: ctrans("Road") })[props.data.summary.mode ?? "sea"] ?? null)

const locale = useLocaleStore()
const money = (currency: string | null, amount: number) => (currency ? locale.currencyFormat(currency, amount) : amount)
const depositMoney = (step: Step) => (step.key === "deposit" ? (step.money as DepositMoney | null) : null)
const balanceMoney = (step: Step) => (step.key === "balance_payment" ? (step.money as BalanceMoney | null) : null)
const balanceWarning = (step: Step) => {
	const balance = balanceMoney(step)
	if (balance?.scheduled && balance.left > 0) {
		return ctrans("Scheduled in the bank for :date. Aiku cannot see scheduled bank payments, so it counts as paid only from that date: check the bank that it went out.", { date: useFormatTime(balance.scheduled.date) })
	}
	if (!balance || balance.left <= 0 || (step.status !== "done" && step.files.length === 0)) {
		return null
	}
	if (balance.total_source === "orders") {
		return ctrans("Marked as paid, but no payment is recorded in aiku yet.")
	}
	if (balance.total_source === "invoice" && !balance.payments && (props.invoice?.supplier?.can_edit || props.invoice?.agent?.can_edit)) {
		return props.invoice?.agent
			? ctrans("Marked as paid, but no payment is recorded in aiku yet: add it in Agent payments.")
			: ctrans("Marked as paid, but no payment is recorded in aiku yet: add it in Supplier payments.")
	}
	return null
}
const balanceNote = (step: Step) => {
	if (balanceMoney(step)?.total_source !== "orders") {
		return null
	}
	return props.costingAvailable?.invoice && !props.invoice?.agent
		? ctrans("No invoice entered yet, so this uses the purchase orders total. What you owe is the supplier's invoice: if they shipped more or fewer cartons, or changed prices, it is a different amount. Enter it with Adjust costing on the Commercial invoice step and Left to pay will follow it.")
		: ctrans("No invoice entered yet, so this uses the purchase orders total. What you owe is the invoice: if more or fewer cartons were shipped, or prices changed, it is a different amount.")
}

const invoiceVsOrders = (step: Step) => {
	const balance = balanceMoney(step)
	if (balance?.total_source !== "invoice" || balance.orders_total == null || Math.abs(balance.total - balance.orders_total) <= 0.01) {
		return null
	}
	const difference = balance.total - balance.orders_total
	return ctrans("The invoice is :difference :direction than the purchase orders (:orders). What you owe is the invoice: the supplier charges what they sent. Adjust costing on the Commercial invoice step shows the difference product by product.", {
		difference: String(money(balance.currency, Math.abs(difference))),
		direction: difference > 0 ? ctrans("more") : ctrans("less"),
		orders: String(money(balance.currency, balance.orders_total)),
	})
}

const formatDate = (value: string | null) => (value ? useFormatTime(value) : "—")
const toDate = (value: string | null) => (value ? new Date(`${value.substring(0, 10)}T00:00:00`) : null)
const toIso = (date: Date | null) => (date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}` : null)

const saving = ref<string | null>(null)
const errors = ref<Record<string, string>>({})

const save = (step: Step, status: Status | null, date: string | null) => {
	saving.value = step.key
	errors.value = { ...errors.value, [step.key]: "" }
	router.patch(
		route(props.data.update_route.name, props.data.update_route.parameters),
		{ step: step.key, status, date: status ? (date ?? (status === "done" ? toIso(new Date()) : null)) : null },
		{
			preserveScroll: true,
			only: ["import_file"],
			onError: (bag) => (errors.value = { ...errors.value, [step.key]: Object.values(bag).join(" ") }),
			onFinish: () => (saving.value = null),
		}
	)
}

const fileInputs = ref<Record<string, HTMLInputElement | null>>({})

const upload = (step: Step, event: Event) => {
	const files = Array.from((event.target as HTMLInputElement).files ?? [])
	if (!files.length || !step.scope) {
		return
	}
	saving.value = step.key
	router.post(
		route(props.data.attach_route.name, props.data.attach_route.parameters),
		{ attachments: files, scope: step.scope },
		{
			forceFormData: true,
			preserveScroll: true,
			only: ["import_file"],
			onError: (bag) => (errors.value = { ...errors.value, [step.key]: Object.values(bag).join(" ") }),
			onFinish: () => {
				saving.value = null
				;(event.target as HTMLInputElement).value = ""
			},
		}
	)
}

const removingFileId = ref<number | null>(null)
const confirm = useConfirm()

const confirmRemoveFile = (step: Step, file: Step["files"][number]) => {
	confirm.require({
		group: "stock-delivery",
		header: ctrans("Remove this file"),
		message: ctrans(":file is taken off the :step step and off this delivery. Upload it again if you remove it by mistake.", { file: file.caption || file.file_name || "", step: step.label }),
		rejectProps: { label: ctrans("Cancel") },
		acceptProps: { label: ctrans("Remove"), severity: "danger" },
		accept: () => removeFile(step, file),
	})
}

const removeFile = (step: Step, file: Step["files"][number]) => {
	removingFileId.value = file.id
	errors.value = { ...errors.value, [step.key]: "" }
	router.delete(route(props.data.detach_route.name, { ...props.data.detach_route.parameters, attachment: file.media_id }), {
		preserveScroll: true,
		only: ["import_file"],
		onError: (bag) => (errors.value = { ...errors.value, [step.key]: Object.values(bag).join(" ") }),
		onFinish: () => (removingFileId.value = null),
	})
}

const statusIconClass: Record<Status, string> = {
	done: "text-green-600",
	pending: "text-amber-500",
	na: "text-gray-400",
}
</script>

<template>
	<div class="space-y-4 p-4">
		<div class="grid gap-4 lg:grid-cols-3">
			<div class="rounded-md border border-gray-200 bg-white p-4 lg:col-span-2">
				<div class="mb-3 flex flex-wrap items-center justify-between gap-2">
					<h3 class="text-sm font-semibold text-gray-700">{{ ctrans("Shipment") }}</h3>
					<div v-if="data.documents_count" class="flex gap-2">
						<a :href="route(data.documents_route.name, { ...data.documents_route.parameters, format: 'pdf' })" v-tooltip="ctrans('Every document in one PDF to print, with a cover page listing them')">
							<Button type="tertiary" size="xs" :label="ctrans('All documents (PDF)')">
								<template #icon>
									<FontAwesomeIcon :icon="faFilePdf" class="text-red-500" fixed-width aria-hidden="true" />
								</template>
								<template #iconRight>
									<FontAwesomeIcon :icon="faDownload" class="text-gray-500" fixed-width aria-hidden="true" />
								</template>
							</Button>
						</a>
						<a :href="route(data.documents_route.name, { ...data.documents_route.parameters, format: 'zip' })" v-tooltip="ctrans('Every document as the original files in one ZIP')">
							<Button type="tertiary" size="xs" :label="ctrans('All documents (ZIP)')">
								<template #icon>
									<FontAwesomeIcon :icon="faFileArchive" class="text-gray-500" fixed-width aria-hidden="true" />
								</template>
								<template #iconRight>
									<FontAwesomeIcon :icon="faDownload" class="text-gray-500" fixed-width aria-hidden="true" />
								</template>
							</Button>
						</a>
					</div>
				</div>
				<dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
					<div class="col-span-2 sm:col-span-3">
						<dt class="text-xs text-gray-500">{{ ctrans("Purchase orders") }}</dt>
						<dd class="flex flex-wrap gap-x-3 gap-y-1">
							<Link v-for="purchaseOrder in data.summary.purchase_orders" :key="purchaseOrder.reference" :href="route(purchaseOrder.route.name, purchaseOrder.route.parameters)" class="whitespace-nowrap text-gray-700 underline decoration-gray-300 hover:decoration-gray-600">
								{{ purchaseOrder.reference }}
							</Link>
							<span v-if="!data.summary.purchase_orders.length" class="text-gray-400">—</span>
						</dd>
					</div>
					<div class="col-span-2 sm:col-span-3">
						<dt class="text-xs text-gray-500">{{ ctrans("Supplier") }}</dt>
						<dd class="text-gray-700">{{ data.summary.suppliers.length ? data.summary.suppliers.join(", ") : (data.summary.supplier ?? "—") }}</dd>
					</div>
					<div>
						<dt class="text-xs text-gray-500">{{ ctrans("Agent") }}</dt>
						<dd class="text-gray-700">{{ data.summary.agent ?? "—" }}</dd>
					</div>
					<div>
						<dt class="text-xs text-gray-500">{{ ctrans("Order date") }}</dt>
						<dd class="text-gray-700">{{ formatDate(data.summary.ordered_at) }}</dd>
					</div>
					<div>
						<dt class="text-xs text-gray-500">{{ ctrans("Shipping terms") }}</dt>
						<dd class="text-gray-700">{{ data.summary.incoterm ?? "—" }}<span v-if="modeLabel && data.summary.mode" class="text-gray-400"> · {{ modeLabel }}</span></dd>
					</div>
					<div>
						<dt class="text-xs text-gray-500">{{ ctrans("ETA") }}</dt>
						<dd class="text-gray-700">{{ formatDate(data.summary.eta) }}</dd>
					</div>
					<div>
						<dt class="text-xs text-gray-500">{{ ctrans("Delivered") }}</dt>
						<dd class="text-gray-700">{{ formatDate(data.summary.delivered_at) }}</dd>
					</div>
					<div>
						<dt class="text-xs text-gray-500" v-tooltip="ctrans('From the first purchase order submitted to the day the goods arrived')">{{ ctrans("Days PO to delivery") }}</dt>
						<dd class="font-semibold tabular-nums text-gray-700">{{ data.summary.days ?? "—" }}</dd>
					</div>
				</dl>
			</div>

			<div class="rounded-md border p-4" :class="data.is_complete ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'">
				<h3 class="mb-3 text-sm font-semibold" :class="data.is_complete ? 'text-green-800' : 'text-amber-800'">
					{{ data.is_complete ? ctrans("Ready to close") : ctrans("Not ready to close") }}
				</h3>
				<ul class="space-y-1.5 text-sm">
					<li class="flex items-center gap-2">
						<FontAwesomeIcon :icon="data.checks.goods_received ? faCheckCircle : faCircle" :class="data.checks.goods_received ? 'text-green-600' : 'text-amber-500'" fixed-width aria-hidden="true" />
						{{ ctrans("Goods received") }}
					</li>
					<li class="flex items-center gap-2">
						<FontAwesomeIcon :icon="data.checks.costed ? faCheckCircle : faCircle" :class="data.checks.costed ? 'text-green-600' : 'text-amber-500'" fixed-width aria-hidden="true" />
						{{ ctrans("Costs complete and costing finished") }}
					</li>
					<li class="flex items-center gap-2">
						<FontAwesomeIcon :icon="data.checks.documents ? faCheckCircle : faCircle" :class="data.checks.documents ? 'text-green-600' : 'text-amber-500'" fixed-width aria-hidden="true" />
						{{ ctrans("All documents done or N/A") }}
					</li>
				</ul>
				<div v-if="data.outstanding.length || data.costs_missing.length" class="mt-3 border-t border-amber-200 pt-2 text-xs text-amber-900">
					<div class="font-semibold">{{ ctrans("Still missing") }}</div>
					<ul class="mt-1 list-inside list-disc">
						<li v-for="label in data.outstanding" :key="label">{{ label }}</li>
						<li v-for="label in data.costs_missing" :key="`cost-${label}`">{{ ctrans("Cost missing: :cost", { cost: label }) }}</li>
					</ul>
				</div>
			</div>
		</div>

		<div class="overflow-x-auto rounded-md border border-gray-200 bg-white">
			<table class="min-w-full divide-y divide-gray-200 text-sm">
				<thead class="whitespace-nowrap bg-gray-50 text-left text-xs font-semibold text-gray-600">
					<tr>
						<th class="px-3 py-2">#</th>
						<th class="px-3 py-2">{{ ctrans("Step") }}</th>
						<th class="px-3 py-2">{{ ctrans("Status") }}</th>
						<th class="px-3 py-2">{{ ctrans("Date") }}</th>
						<th class="px-3 py-2">{{ ctrans("Files") }}</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					<tr v-for="(step, index) in data.steps" :key="step.key" class="align-top">
						<td class="px-3 py-2 tabular-nums text-gray-400">{{ index + 1 }}</td>
						<td class="px-3 py-2">
							<div class="flex items-center gap-2 font-medium text-gray-800">
								<FontAwesomeIcon :icon="step.status === 'done' ? faCheckCircle : step.status === 'na' ? faMinusCircle : faCircle" :class="statusIconClass[step.status]" fixed-width aria-hidden="true" />
								{{ step.label }}
							</div>
							<div v-if="step.is_auto && step.source" class="ml-6 mt-0.5 text-xs text-gray-500">
								<FontAwesomeIcon :icon="faMagic" class="mr-1 text-gray-400" aria-hidden="true" />{{ step.source }}
							</div>
							<div v-else-if="step.marked" class="ml-6 mt-0.5 text-xs text-gray-500">
								{{ ctrans("Marked by :name", { name: step.marked.by ?? "—" }) }}
								<template v-if="step.source"> · {{ step.source }}</template>
							</div>
							<div v-if="depositMoney(step)?.rows.length" class="ml-6 mt-1.5 space-y-1 text-xs">
								<div v-for="(row, rowIndex) in depositMoney(step)!.rows" :key="rowIndex" class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-gray-600">
									<span class="whitespace-nowrap font-medium text-gray-700">{{ row.purchase_order }}</span>
									<span class="whitespace-nowrap tabular-nums text-gray-900">{{ money(row.currency, row.amount) }}</span>
									<span v-if="row.percent !== null" class="whitespace-nowrap tabular-nums">{{ ctrans(":percent% of the order", { percent: String(row.percent) }) }}</span>
									<span v-if="row.original_amount !== null && row.original_currency" class="whitespace-nowrap">{{ ctrans("sent as :amount", { amount: String(money(row.original_currency, row.original_amount)) }) }}</span>
									<span v-if="row.date" class="whitespace-nowrap text-gray-400">{{ formatDate(row.date) }}</span>
									<span v-if="!row.is_paid" class="whitespace-nowrap rounded bg-amber-50 px-1.5 text-amber-700">{{ ctrans("Requested, not paid yet") }}</span>
									<a v-if="row.bank_statement" :href="route('grp.media.download', { id: row.bank_statement })" target="_blank" class="whitespace-nowrap text-gray-500 underline decoration-gray-300 hover:text-gray-800">
										<FontAwesomeIcon :icon="faPaperclip" aria-hidden="true" />
										{{ ctrans("Bank statement") }}
									</a>
									<span v-else-if="row.is_paid" class="whitespace-nowrap text-amber-600">{{ ctrans("No bank statement") }}</span>
								</div>
								<div v-if="depositMoney(step)!.rows.length > 1 && depositMoney(step)!.total !== null" class="font-medium text-gray-700">
									{{ ctrans("Deposits sent: :amount", { amount: String(money(depositMoney(step)!.currency, depositMoney(step)!.total!)) }) }}
								</div>
							</div>
							<dl v-if="balanceMoney(step)" class="ml-6 mt-1.5 grid w-fit grid-cols-[auto_auto] gap-x-4 gap-y-0.5 text-xs">
								<dt class="whitespace-nowrap text-gray-500">{{ balanceMoney(step)!.total_source === "invoice" ? ctrans("Invoice total") : ctrans("Orders total, no invoice yet") }}</dt>
								<dd class="text-right tabular-nums text-gray-700">{{ money(balanceMoney(step)!.currency, balanceMoney(step)!.total) }}</dd>
								<template v-if="balanceMoney(step)!.deposits">
									<dt class="whitespace-nowrap text-gray-500">{{ ctrans("Deposits sent") }}</dt>
									<dd class="text-right tabular-nums text-gray-700">−{{ money(balanceMoney(step)!.currency, balanceMoney(step)!.deposits) }}</dd>
								</template>
								<template v-if="balanceMoney(step)!.payments">
									<dt class="whitespace-nowrap text-gray-500">{{ ctrans("Paid") }}</dt>
									<dd class="text-right tabular-nums text-gray-700">−{{ money(balanceMoney(step)!.currency, balanceMoney(step)!.payments) }}</dd>
								</template>
								<template v-if="balanceMoney(step)!.scheduled">
									<dt class="whitespace-nowrap text-gray-500">{{ ctrans("Scheduled for :date", { date: useFormatTime(balanceMoney(step)!.scheduled!.date) }) }}</dt>
									<dd class="text-right tabular-nums text-gray-500">{{ money(balanceMoney(step)!.currency, balanceMoney(step)!.scheduled!.amount) }}</dd>
								</template>
								<dt class="whitespace-nowrap font-semibold text-gray-800">{{ balanceMoney(step)!.left < 0 ? ctrans("Overpaid") : ctrans("Left to pay") }}</dt>
								<dd class="text-right font-semibold tabular-nums" :class="balanceMoney(step)!.left < 0 ? 'text-red-600' : 'text-gray-900'">{{ money(balanceMoney(step)!.currency, Math.abs(balanceMoney(step)!.left)) }}</dd>
							</dl>
							<div v-if="invoiceVsOrders(step)" class="ml-6 mt-1.5 max-w-md rounded border border-gray-200 bg-gray-50 px-2 py-1.5 text-xs text-gray-600">
								{{ invoiceVsOrders(step) }}
							</div>
							<div v-if="balanceNote(step)" class="ml-6 mt-1.5 max-w-md rounded border border-gray-200 bg-gray-50 px-2 py-1.5 text-xs text-gray-600">
								{{ balanceNote(step) }}
							</div>
							<div v-if="balanceWarning(step)" class="ml-6 mt-1 max-w-md text-xs text-amber-700">
								{{ balanceWarning(step) }}
							</div>
							<div v-if="errors[step.key]" class="ml-6 mt-1 text-xs text-red-600">{{ errors[step.key] }}</div>
						</td>
						<td class="px-3 py-2">
							<div class="flex items-center gap-2">
								<SegmentedToggle
									v-if="data.can_edit"
									:modelValue="step.status"
									:options="statusOptions"
									:ariaLabel="step.label"
									soft
									@update:modelValue="(value: Status) => save(step, value, step.date)"
								/>
								<span v-else class="whitespace-nowrap text-gray-700">{{ statusOptions.find((option) => option.value === step.status)?.label }}</span>
								<button v-if="data.can_edit && step.marked" type="button" v-tooltip="ctrans('Clear the mark and show what aiku reads')" class="text-gray-400 hover:text-gray-600" @click="save(step, null, null)">
									<FontAwesomeIcon :icon="faUndo" fixed-width />
									<span class="sr-only">{{ ctrans("Clear the mark") }}</span>
								</button>
								<LoadingIcon v-if="saving === step.key" />
							</div>
						</td>
						<td class="px-3 py-2">
							<DatePicker
								v-if="data.can_edit && step.status !== 'na'"
								:modelValue="toDate(step.date)"
								:manualInput="false"
								showIcon
								iconDisplay="input"
								:pt="{ pcInputText: { root: { class: '!w-32 !py-1 !text-xs' } } }"
								class="[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"
								:aria-label="ctrans('Date')"
								@update:modelValue="(value: Date | Date[] | (Date | null)[] | null | undefined) => save(step, step.status, toIso((value as Date) ?? null))"
							/>
							<span v-else class="whitespace-nowrap text-gray-700">{{ step.status === "na" ? "—" : formatDate(step.date) }}</span>
						</td>
						<td class="px-3 py-2">
							<div class="flex flex-wrap items-center gap-2">
								<span v-for="file in step.files" :key="file.id" class="flex max-w-[16rem] items-center gap-1 text-xs">
									<a :href="file.url" target="_blank" class="flex min-w-0 items-center gap-1 text-gray-700 underline decoration-gray-300 hover:decoration-gray-600">
										<FontAwesomeIcon :icon="faFileAlt" class="text-gray-400" aria-hidden="true" />
										<span class="truncate">{{ file.caption || file.file_name }}</span>
									</a>
									<LoadingIcon v-if="removingFileId === file.id" />
									<button
										v-else-if="data.can_upload && !file.is_applied"
										type="button"
										v-tooltip="ctrans('Remove this file')"
										class="text-red-500 hover:text-red-700"
										@click="confirmRemoveFile(step, file)"
									>
										<FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
										<span class="sr-only">{{ ctrans("Remove this file") }}</span>
									</button>
									<FontAwesomeIcon v-else-if="file.is_applied" v-tooltip="ctrans('Booked in the costing: change it with Adjust costing')" :icon="faLock" class="text-gray-400" fixed-width />
								</span>
								<Button
									v-if="step.costing && costingAvailable?.[step.costing]"
									type="tertiary"
									size="xs"
									:label="costingButtonLabel[step.costing]"
									@click="emits('openCosting', step.costing)"
								>
									<template #icon>
										<FontAwesomeIcon :icon="step.costing === 'invoice' ? faBoxUsd : faCoins" aria-hidden="true" />
									</template>
								</Button>
								<template v-if="step.key === 'balance_payment' && invoice">
									<StockDeliveryAgentPayments
										v-if="invoice.agent"
										:agent="invoice.agent"
										:currency="invoice.currency"
										:hasCharges="invoice.charges_list.length > 0"
										:isEstimate="invoice.source === 'estimated'"
									/>
									<StockDeliverySupplierPayments
										v-if="invoice.supplier && (invoice.total > 0 || invoice.supplier.payments.length > 0)"
										:supplier="invoice.supplier"
										:currency="invoice.currency"
										:total="invoice.total"
										:orgCurrency="invoice.org_currency"
										:orgExchange="invoice.org_exchange"
										:isEstimate="invoice.source === 'estimated'"
									/>
								</template>
								<template v-if="data.can_upload && step.scope && !(step.upload_in_costing && step.costing && costingAvailable?.[step.costing])">
									<input :ref="(element) => (fileInputs[step.key] = element as HTMLInputElement | null)" type="file" multiple class="hidden" @change="(event) => upload(step, event)" />
									<Button type="tertiary" size="xs" :label="ctrans('Upload')" @click="fileInputs[step.key]?.click()">
										<template #icon>
											<FontAwesomeIcon :icon="faUpload" aria-hidden="true" />
										</template>
									</Button>
								</template>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<p class="text-xs text-gray-500">
			{{ ctrans("Steps with a magic wand are ticked from what aiku already has: purchase orders, files by type, payments, the costing checklist and the customs bills. A mark you set wins over it; clear it to go back to aiku's reading. The invoice, shipping and customs steps open the costing, where every document uploaded is read and its amounts booked.") }}
		</p>
	</div>
</template>
