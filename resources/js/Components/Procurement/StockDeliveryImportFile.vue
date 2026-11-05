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
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFolderOpen, faUpload, faUndo, faCheckCircle, faCircle, faMinusCircle, faFileAlt, faMagic } from "@fal"

library.add(faFolderOpen, faUpload, faUndo, faCheckCircle, faCircle, faMinusCircle, faFileAlt, faMagic)

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
	files: { id: number; file_name: string | null; caption: string | null; url: string }[]
}

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
	}
	tab?: string
}>()

const statusOptions = computed(() => [
	{ label: ctrans("Done"), value: "done" as Status, icon: faCheckCircle, tone: "emerald" as const },
	{ label: ctrans("Pending"), value: "pending" as Status, icon: faCircle, tone: "amber" as const },
	{ label: ctrans("N/A"), value: "na" as Status, icon: faMinusCircle, tone: "gray" as const },
])

const modeLabel = computed(() => ({ sea: ctrans("Sea"), air: ctrans("Air"), road: ctrans("Road") })[props.data.summary.mode ?? "sea"] ?? null)

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
				<h3 class="mb-3 text-sm font-semibold text-gray-700">{{ ctrans("Shipment") }}</h3>
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
								<a v-for="file in step.files" :key="file.id" :href="file.url" target="_blank" class="flex max-w-[14rem] items-center gap-1 truncate text-xs text-gray-700 underline decoration-gray-300 hover:decoration-gray-600">
									<FontAwesomeIcon :icon="faFileAlt" class="text-gray-400" aria-hidden="true" />
									<span class="truncate">{{ file.caption || file.file_name }}</span>
								</a>
								<template v-if="data.can_upload && step.scope">
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
			{{ ctrans("Steps with a magic wand are ticked from what aiku already has: purchase orders, files by type, payments, the costing checklist and the customs bills. A mark you set wins over it; clear it to go back to aiku's reading. Invoices are read and booked in the costing panel.") }}
		</p>
	</div>
</template>
