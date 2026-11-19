<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 15 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue"
import { Link, router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import axios from "axios"
import { faCheck, faTimes, faGavel, faExclamationTriangle } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"

library.add(faCheck, faTimes, faGavel, faExclamationTriangle)

type SupplierLink = { code: string; name: string; route: routeType | null }

export interface SupplierChangeRequestRow {
	id: number
	type: "deactivate" | "merge"
	type_label: string
	state: "pending" | "approved" | "rejected"
	state_label: string
	supplier: SupplierLink
	target: SupplierLink | null
	reason: string
	requested_by: string | null
	requested_at: string
	decided_by: string | null
	decided_at: string | null
	decision_note: string | null
	decide_route: routeType
	preview_route: routeType
	progress_route: routeType
	progress: DecisionProgress | null
}

type DecisionProgress = { step: string; done: number; total: number; message?: string | null; started_at?: string }

type SupplierSummary = {
	code: string
	name: string
	products: number
	active_products: number
	purchase_orders: number
	open_purchase_orders: number
	stock_deliveries: number
}

type PreviewOrganisation = { code: string; name: string; active: boolean; joins?: boolean }

type DecisionPreview = {
	type: "deactivate" | "merge"
	supplier: SupplierSummary
	target?: SupplierSummary
	products?: { moved: number; merged: string[]; refused: { code: string; reason: string }[] }
	agent_orders?: { folded: string[]; blocked: string[] }
	organisations: PreviewOrganisation[]
	blockers: string[]
}

const props = defineProps<{
	requests: SupplierChangeRequestRow[]
	canDecide: boolean
}>()

const href = (target: routeType) => route(target.name, target.parameters)
const pendingCount = computed(() => props.requests.filter((request) => request.state === "pending").length)

const deciding = ref<{ request: SupplierChangeRequestRow; approve: boolean } | null>(null)
const note = ref("")
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const preview = ref<DecisionPreview | null>(null)
const previewLoading = ref(false)
const previewError = ref<string | null>(null)

const decisionVisible = computed({
	get: () => !!deciding.value,
	set: (value: boolean) => {
		if (!value) {
			deciding.value = null
		}
	},
})

function openDecision(request: SupplierChangeRequestRow, approve: boolean) {
	deciding.value = { request, approve }
	note.value = ""
	errors.value = {}
	preview.value = null
	previewError.value = null
	if (approve) {
		loadPreview(request)
	}
}

async function loadPreview(request: SupplierChangeRequestRow) {
	previewLoading.value = true
	try {
		const response = await axios.get(route(request.preview_route.name, request.preview_route.parameters))
		if (deciding.value?.request.id === request.id) {
			preview.value = response.data
		}
	} catch {
		previewError.value = ctrans("The preview could not be loaded")
	} finally {
		previewLoading.value = false
	}
}

const approveBlocked = computed(() => !!deciding.value?.approve && (previewLoading.value || !!preview.value?.blockers.length))

const progressById = ref<Record<number, DecisionProgress>>({})
const now = ref(Date.now())
const pollTimers = new Map<number, ReturnType<typeof setTimeout>>()
const clock = setInterval(() => (now.value = Date.now()), 1000)

const isFinished = (progress: DecisionProgress | null | undefined) => !progress || progress.step === "done" || progress.step === "failed"

const progressSteps = computed<Record<string, { label: string; percent: number }>>(() => ({
	starting: { label: ctrans("Starting"), percent: 2 },
	queued: { label: ctrans("Waiting in the queue"), percent: 3 },
	checking: { label: ctrans("Checking both suppliers"), percent: 5 },
	products: { label: ctrans("Merging products"), percent: 10 },
	moving: { label: ctrans("Moving purchase orders, containers and messages"), percent: 75 },
	deactivating: { label: ctrans("Archiving the supplier"), percent: 50 },
	refreshing: { label: ctrans("Updating supplier totals"), percent: 85 },
	saving: { label: ctrans("Saving the decision"), percent: 92 },
	done: { label: ctrans("Reloading the page"), percent: 98 },
}))

function progressLabel(progress: DecisionProgress): string {
	const label = (progressSteps.value[progress.step] ?? progressSteps.value.starting).label
	return progress.step === "products" && progress.total ? `${label} (${Math.min(progress.done + 1, progress.total)}/${progress.total})` : label
}

function progressPercent(progress: DecisionProgress): number {
	if (progress.step === "products" && progress.total) {
		return 10 + Math.round((progress.done / progress.total) * 60)
	}
	return (progressSteps.value[progress.step] ?? progressSteps.value.starting).percent
}

function elapsedLabel(progress: DecisionProgress): string {
	if (!progress.started_at) {
		return ""
	}
	const seconds = Math.max(0, Math.floor((now.value - new Date(progress.started_at).getTime()) / 1000))
	return seconds < 60 ? ctrans(":seconds s", { seconds }) : ctrans(":minutes m :seconds s", { minutes: Math.floor(seconds / 60), seconds: seconds % 60 })
}

const runningProgress = (request: SupplierChangeRequestRow) => {
	const progress = progressById.value[request.id]
	return progress && progress.step !== "failed" ? progress : null
}

const failedProgress = (request: SupplierChangeRequestRow) => {
	const progress = progressById.value[request.id]
	return progress?.step === "failed" ? progress : null
}

async function pollProgress(request: SupplierChangeRequestRow) {
	pollTimers.delete(request.id)
	const response = await axios.get(route(request.progress_route.name, request.progress_route.parameters)).catch(() => null)
	const result: DecisionProgress | null = response?.data.progress ?? null
	if (result) {
		progressById.value = { ...progressById.value, [request.id]: result }
	}
	if (result && isFinished(result)) {
		router.reload({ only: ["dashboard"] })
		return
	}
	pollTimers.set(request.id, setTimeout(() => pollProgress(request), 1500))
}

function trackApproval(request: SupplierChangeRequestRow, progress: DecisionProgress) {
	progressById.value = { ...progressById.value, [request.id]: progress }
	if (!pollTimers.has(request.id)) {
		pollTimers.set(request.id, setTimeout(() => pollProgress(request), 1000))
	}
}

watch(
	() => props.requests,
	(requests) => {
		const next: Record<number, DecisionProgress> = {}
		for (const request of requests) {
			if (request.state === "pending" && request.progress) {
				next[request.id] = request.progress
			}
		}
		progressById.value = next
		for (const request of requests) {
			if (request.state === "pending" && request.progress && !isFinished(request.progress)) {
				trackApproval(request, request.progress)
			}
		}
	},
	{ immediate: true },
)

onBeforeUnmount(() => {
	clearInterval(clock)
	pollTimers.forEach((timer) => clearTimeout(timer))
	pollTimers.clear()
})

function decide() {
	if (!deciding.value) {
		return
	}
	const request = deciding.value.request
	const approve = deciding.value.approve
	router.patch(route(request.decide_route.name, request.decide_route.parameters), {
		approve,
		decision_note: note.value.trim() || null,
	}, {
		only: approve ? ["errors"] : ["dashboard"],
		preserveScroll: true,
		onStart: () => {
			saving.value = true
			errors.value = {}
		},
		onFinish: () => (saving.value = false),
		onError: (error) => (errors.value = error),
		onSuccess: () => {
			if (approve) {
				trackApproval(request, { step: "queued", done: 0, total: 0, started_at: new Date().toISOString() })
			}
			deciding.value = null
		},
	})
}

const stateClasses: Record<string, string> = {
	pending: "bg-amber-50 text-amber-700",
	approved: "bg-green-50 text-green-700",
	rejected: "bg-gray-100 text-gray-600",
}
</script>

<template>
	<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
		<header class="flex items-center gap-1.5 border-b border-gray-200 px-4 py-3">
			<FontAwesomeIcon icon="fal fa-gavel" class="text-gray-400" fixed-width aria-hidden="true" />
			<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Suggestions for the directors") }}</h2>
			<span v-if="pendingCount" class="rounded bg-amber-50 px-1.5 text-xs text-amber-700">{{ ctrans(":count waiting", { count: pendingCount }) }}</span>
			<InformationIcon :information="ctrans('Suppliers suggested for deactivation or merging. Nothing changes until a director approves; decisions from the last 90 days stay listed with who decided and why.')" class="text-xs text-gray-500" />
		</header>
		<p v-if="!requests.length" class="px-4 py-6 text-center text-sm text-gray-400">{{ ctrans("No suggestions yet") }}</p>
		<ul v-else class="divide-y divide-gray-100">
			<li v-for="request in requests" :key="request.id" class="px-4 py-3 text-sm">
				<div class="flex flex-wrap items-center justify-between gap-2">
					<span class="flex flex-wrap items-center gap-1.5">
						<span class="font-medium text-gray-700">{{ request.type_label }}</span>
						<Link v-if="request.supplier.route" :href="href(request.supplier.route)" class="font-medium text-gray-700 hover:underline">{{ request.supplier.code }}</Link>
						<span class="text-gray-500">{{ request.supplier.name }}</span>
						<template v-if="request.target">
							<span class="text-gray-400">→</span>
							<Link v-if="request.target.route" :href="href(request.target.route)" class="font-medium text-gray-700 hover:underline">{{ request.target.code }}</Link>
							<span class="text-gray-500">{{ request.target.name }}</span>
						</template>
					</span>
					<span class="rounded px-2 py-0.5 text-xs font-medium" :class="stateClasses[request.state]">{{ request.state_label }}</span>
				</div>
				<p class="mt-1 text-gray-600">{{ request.reason }}</p>
				<p class="mt-0.5 text-xs text-gray-400">{{ ctrans("Suggested by :user, :date", { user: request.requested_by ?? "", date: useFormatTime(request.requested_at) }) }}</p>
				<p v-if="request.decided_at" class="mt-0.5 text-xs text-gray-500">
					{{ ctrans("Decided by :user, :date", { user: request.decided_by ?? "", date: useFormatTime(request.decided_at) }) }}<span v-if="request.decision_note">: {{ request.decision_note }}</span>
				</p>
				<div v-if="request.state === 'pending' && runningProgress(request)" class="mt-2 rounded border border-gray-200 px-3 py-2" role="status" aria-live="polite">
					<div class="flex items-center justify-between gap-2 text-xs">
						<span class="font-medium text-gray-700">{{ ctrans("Approving:") }} {{ progressLabel(runningProgress(request)!) }}</span>
						<span class="whitespace-nowrap text-gray-400">{{ elapsedLabel(runningProgress(request)!) }}</span>
					</div>
					<div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100">
						<div class="h-full rounded-full bg-[var(--app-accent)] transition-all duration-500" :style="{ width: progressPercent(runningProgress(request)!) + '%' }" />
					</div>
				</div>
				<p v-if="request.state === 'pending' && failedProgress(request)" class="mt-2 text-xs text-red-600">
					{{ ctrans("The approval failed:") }} {{ failedProgress(request)!.message }}
				</p>
				<div v-if="canDecide && request.state === 'pending' && !runningProgress(request)" class="mt-2 flex gap-2">
					<Button type="tertiary" size="xs" :label="ctrans('Approve')" @click="openDecision(request, true)">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button type="tertiary" size="xs" :label="ctrans('Keep the supplier')" @click="openDecision(request, false)">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
			</li>
		</ul>

		<Dialog v-model:visible="decisionVisible" modal :header="deciding?.approve ? ctrans('Approve: :type :code', { type: deciding.request.type_label, code: deciding.request.supplier.code }) : ctrans('Keep :code', { code: deciding?.request.supplier.code ?? '' })" :style="{ width: deciding?.approve ? '38rem' : '30rem' }" :draggable="false">
			<div class="space-y-3 text-sm">
				<template v-if="deciding?.approve">
					<div v-if="previewLoading" class="space-y-2">
						<div class="skeleton h-16 rounded" />
						<div class="skeleton h-24 rounded" />
					</div>
					<p v-else-if="previewError" class="text-xs text-red-600">{{ previewError }}</p>
					<template v-else-if="preview">
						<div v-if="preview.blockers.length" class="flex gap-2 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">
							<FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
							<ul class="space-y-1">
								<li v-for="blocker in preview.blockers" :key="blocker">{{ blocker }}</li>
							</ul>
						</div>

						<div class="grid gap-2" :class="preview.target ? 'grid-cols-[1fr_auto_1fr]' : 'grid-cols-1'">
							<div class="rounded border border-gray-200 px-3 py-2">
								<div class="flex items-center justify-between gap-2">
									<span class="whitespace-nowrap font-medium text-gray-700">{{ preview.supplier.code }}</span>
									<span class="rounded bg-gray-100 px-1.5 text-xs text-gray-600">{{ ctrans("Archived") }}</span>
								</div>
								<p class="truncate text-xs text-gray-500">{{ preview.supplier.name }}</p>
								<p class="mt-1 text-xs text-gray-500">
									{{ ctrans(":products products, :orders purchase orders, :deliveries containers", { products: preview.supplier.products, orders: preview.supplier.purchase_orders, deliveries: preview.supplier.stock_deliveries }) }}
								</p>
							</div>
							<template v-if="preview.target">
								<span class="self-center text-gray-400">→</span>
								<div class="rounded border border-green-200 bg-green-50 px-3 py-2">
									<div class="flex items-center justify-between gap-2">
										<span class="whitespace-nowrap font-medium text-gray-700">{{ preview.target.code }}</span>
										<span class="rounded bg-green-100 px-1.5 text-xs text-green-700">{{ ctrans("Kept") }}</span>
									</div>
									<p class="truncate text-xs text-gray-500">{{ preview.target.name }}</p>
									<p class="mt-1 text-xs text-gray-500">
										{{ ctrans(":products products, :orders purchase orders, :deliveries containers", { products: preview.target.products, orders: preview.target.purchase_orders, deliveries: preview.target.stock_deliveries }) }}
									</p>
								</div>
							</template>
						</div>

						<ul v-if="preview.type === 'merge' && preview.target && preview.products && preview.agent_orders" class="space-y-2 text-gray-600">
							<li v-if="preview.products.moved">
								{{ ctrans(":count products move to :target", { count: preview.products.moved, target: preview.target.code }) }}
							</li>
							<li v-if="preview.products.merged.length">
								{{ ctrans(":count products with the same code are merged into the :target product; the :code copy is discontinued", { count: preview.products.merged.length, target: preview.target.code, code: preview.supplier.code }) }}
								<div class="mt-1 flex max-h-20 flex-wrap gap-1 overflow-y-auto">
									<span v-for="code in preview.products.merged" :key="code" class="whitespace-nowrap rounded bg-gray-100 px-1.5 text-xs text-gray-600">{{ code }}</span>
								</div>
							</li>
							<li v-if="preview.products.refused.length" class="text-amber-700">
								{{ ctrans("Cannot be merged automatically:") }}
								<ul class="mt-1 space-y-0.5 text-xs">
									<li v-for="product in preview.products.refused" :key="product.code"><span class="whitespace-nowrap font-medium">{{ product.code }}</span>: {{ product.reason }}</li>
								</ul>
							</li>
							<li v-if="preview.supplier.purchase_orders || preview.supplier.stock_deliveries">
								{{ ctrans(":orders purchase orders (:open open) and :deliveries containers move to :target", { orders: preview.supplier.purchase_orders, open: preview.supplier.open_purchase_orders, deliveries: preview.supplier.stock_deliveries, target: preview.target.code }) }}
							</li>
							<li v-if="preview.agent_orders.folded.length">
								{{ ctrans("Agent orders with both suppliers are combined under :target:", { target: preview.target.code }) }}
								<div class="mt-1 flex max-h-20 flex-wrap gap-1 overflow-y-auto">
									<span v-for="reference in preview.agent_orders.folded" :key="reference" class="max-w-full truncate rounded bg-gray-100 px-1.5 text-xs text-gray-600" :title="reference">{{ reference }}</span>
								</div>
							</li>
							<li>
								{{ ctrans("Messages, invoices and attachments move to :target", { target: preview.target.code }) }}
							</li>
						</ul>

						<ul v-else class="space-y-2 text-gray-600">
							<li>{{ ctrans(":code is archived and switched off in every organisation; it can be unarchived later", { code: preview.supplier.code }) }}</li>
							<li>{{ ctrans("Its :count products stay as they are", { count: preview.supplier.products }) }}</li>
						</ul>

						<div v-if="preview.organisations.length">
							<p class="text-xs font-medium text-gray-500">{{ ctrans("Organisations") }}</p>
							<ul class="mt-1 divide-y divide-gray-100 rounded border border-gray-200">
								<li v-for="organisation in preview.organisations" :key="organisation.code" class="flex items-center justify-between gap-2 px-3 py-1.5 text-xs">
									<span class="truncate text-gray-700">{{ organisation.name }}</span>
									<span class="whitespace-nowrap text-gray-500">
										<template v-if="preview.type === 'merge'">
											{{ organisation.joins ? ctrans("joins its :target supplier", { target: preview.target?.code ?? "" }) : ctrans("becomes :target", { target: preview.target?.code ?? "" }) }}
										</template>
										<template v-else>
											{{ organisation.active ? ctrans("switched off") : ctrans("already off") }}
										</template>
									</span>
								</li>
							</ul>
						</div>
					</template>
				</template>

				<label class="flex flex-col gap-1">
					<span class="text-xs text-gray-500">
						<template v-if="deciding?.approve">{{ ctrans("Note (optional)") }}</template>
						<template v-else>{{ ctrans("Why the supplier is kept") }} <span class="text-red-500">*</span></template>
					</span>
					<Textarea v-model="note" rows="2" autoResize :invalid="!!(errors.decision_note || errors.approve)" />
					<span v-if="errors.decision_note || errors.approve || errors.supplier || errors.target_supplier_id" class="text-xs text-red-600">
						{{ errors.decision_note ?? errors.approve ?? errors.supplier ?? errors.target_supplier_id }}
					</span>
				</label>
			</div>
			<template #footer>
				<div class="flex w-full gap-2">
					<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" :disabled="saving" @click="deciding = null">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button type="primary" full :label="deciding?.approve ? ctrans('Approve') : ctrans('Keep the supplier')" :loading="saving" :disabled="approveBlocked || (!deciding?.approve && note.trim().length < 3)" @click="decide">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
			</template>
		</Dialog>
	</section>
</template>
