<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import { Head, router, useForm } from "@inertiajs/vue3"
import { debounce } from "lodash-es"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Pagination from "@/Components/Table/Pagination.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import HrRequestCard from "@/Components/HumanResources/HrRequestCard.vue"
import HrRequestFilters from "@/Components/HumanResources/HrRequestFilters.vue"
import HrConfirmDialog from "@/Components/HumanResources/HrConfirmDialog.vue"
import EmployeeAvatar from "@/Components/HumanResources/EmployeeAvatar.vue"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import { useConfirm } from "primevue/useconfirm"
import { notify } from "@kyvg/vue3-notification"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { faCheck, faTimes, faClock, faPaperclip, faCalendarAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faCheck, faTimes, faClock, faPaperclip, faCalendarAlt)

interface Adjustment {
	id: number
	employee_name: string
	employee_avatar: string | null
	date: string
	original_start_time_local: string | null
	original_end_time_local: string | null
	requested_start_time_local: string | null
	requested_end_time_local: string | null
	timezone: string | null
	reason: string | null
	status: string
	status_label: string
	approver_name: string | null
	approver_avatar: string | null
	approved_at: string | null
	approval_comment: string | null
	attachments: { id: number; name: string; url: string }[] | null
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	adjustments: any
	status_options: Record<string, string>
	can_edit?: boolean
	filters?: {
		search: string
		status: string | null
		from: string | null
		to: string | null
	}
}>()

const locale = useLocaleStore()
const confirm = useConfirm()
const confirmGroup = "clocking-adjustments"
const processingAdjustmentId = ref<number | null>(null)
const selectedAdjustment = ref<Adjustment | null>(null)

const rejectForm = useForm({
	approval_comment: "",
})

const filterState = reactive({
	search: props.filters?.search ?? "",
	status: props.filters?.status ?? "all",
	from: props.filters?.from ?? "",
	to: props.filters?.to ?? "",
})
const isLoadingList = ref(false)

const statusFilterOptions = computed(() => [
	{ label: ctrans("All"), value: "all" },
	{ label: ctrans("Pending"), value: "pending", tone: "amber" as const },
	{ label: ctrans("Approved"), value: "approved", tone: "emerald" as const },
	{ label: ctrans("Rejected"), value: "rejected", tone: "red" as const },
])

const rows = computed<Adjustment[]>(() => props.adjustments?.data ?? [])
const paginationMeta = computed(() => props.adjustments?.meta ?? props.adjustments ?? {})
const totalRequests = computed<number>(() => paginationMeta.value?.total ?? rows.value.length)
const hasActiveFilters = computed(
	() => filterState.search.trim() !== "" || filterState.status !== "all" || filterState.from !== "" || filterState.to !== ""
)

const listVisitOptions = {
	preserveState: true,
	preserveScroll: true,
	replace: true,
	only: ["adjustments", "filters"],
	onStart: () => {
		isLoadingList.value = true
	},
	onFinish: () => {
		isLoadingList.value = false
	},
}

const organisationParameter = () => (route().params as Record<string, any>).organisation

const reloadList = (extraQuery: Record<string, any> = {}) => {
	const query: Record<string, any> = { ...extraQuery }
	const search = filterState.search.trim()

	if (search) query.filter = { global: search }
	if (filterState.status !== "all") query.status = filterState.status
	if (filterState.from) query.from = filterState.from
	if (filterState.to) query.to = filterState.to

	router.get(route("grp.org.hr.adjustments.index", { organisation: organisationParameter() }), query, listVisitOptions)
}

const reloadListDebounced = debounce(() => reloadList(), 350)

watch(
	() => filterState.search,
	() => reloadListDebounced()
)

watch(
	() => [filterState.status, filterState.from, filterState.to],
	() => {
		reloadListDebounced.cancel()
		reloadList()
	}
)

const clearFilters = () => {
	filterState.search = ""
	filterState.status = "all"
	filterState.from = ""
	filterState.to = ""
}

const visitPage = (url?: string | null) => {
	if (!url) return
	router.get(url, {}, listVisitOptions)
}

const changePerPage = (perPage: number) => reloadList({ perPage })

const isPending = (adjustment: Adjustment): boolean => adjustment.status === "pending"

const hasRecordedTimes = (adjustment: Adjustment): boolean =>
	Boolean(adjustment.original_start_time_local || adjustment.original_end_time_local)

const requestedStart = (adjustment: Adjustment): string | null =>
	adjustment.requested_start_time_local ?? adjustment.original_start_time_local

const requestedEnd = (adjustment: Adjustment): string | null =>
	adjustment.requested_end_time_local ?? adjustment.original_end_time_local

const startChanged = (adjustment: Adjustment): boolean =>
	!!adjustment.requested_start_time_local && adjustment.requested_start_time_local !== adjustment.original_start_time_local

const endChanged = (adjustment: Adjustment): boolean =>
	!!adjustment.requested_end_time_local && adjustment.requested_end_time_local !== adjustment.original_end_time_local

const changedTimeClass = "rounded bg-amber-100 px-1.5 py-0.5 font-semibold text-amber-900"

const adjustmentUrl = (routeName: string, adjustment: Adjustment): string =>
	route(routeName, { organisation: organisationParameter(), adjustment: adjustment.id })

const confirmApprove = (adjustment: Adjustment) => {
	confirm.require({
		group: confirmGroup,
		header: ctrans("Approve clocking adjustment"),
		message: ctrans("Approve the clocking adjustment of :name for :date? The timesheet for that day will be changed to the requested times.", {
			name: adjustment.employee_name,
			date: useFormatTime(adjustment.date, { localeCode: locale.language.code }),
		}),
		rejectProps: { label: ctrans("Cancel") },
		acceptProps: { label: ctrans("Yes, approve") },
		accept: () => {
			router.post(
				adjustmentUrl("grp.org.hr.adjustments.approve", adjustment),
				{},
				{
					preserveScroll: true,
					onStart: () => {
						processingAdjustmentId.value = adjustment.id
					},
					onFinish: () => {
						processingAdjustmentId.value = null
					},
					onError: (errors: Record<string, string>) => {
						notify({
							title: ctrans("Something went wrong"),
							text: errors?.status ?? ctrans("Failed to approve the clocking adjustment"),
							type: "error",
						})
					},
				}
			)
		},
	})
}

const isRejectDialogVisible = computed({
	get: () => !!selectedAdjustment.value,
	set: (visible: boolean) => {
		if (!visible) {
			closeRejectDialog()
		}
	},
})

const openRejectDialog = (adjustment: Adjustment) => {
	rejectForm.reset()
	rejectForm.clearErrors()
	selectedAdjustment.value = adjustment
}

const closeRejectDialog = () => {
	selectedAdjustment.value = null
	rejectForm.reset()
	rejectForm.clearErrors()
}

const submitReject = () => {
	if (!selectedAdjustment.value) return

	rejectForm.post(adjustmentUrl("grp.org.hr.adjustments.reject", selectedAdjustment.value), {
		preserveScroll: true,
		onSuccess: closeRejectDialog,
	})
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<HrRequestFilters
		v-model:search="filterState.search"
		v-model:status="filterState.status"
		v-model:from="filterState.from"
		v-model:to="filterState.to"
		:total="totalRequests"
		:countLabel="ctransChoice('clocking adjustment|clocking adjustments', totalRequests)"
		:loading="isLoadingList"
		:statusOptions="statusFilterOptions"
		:searchPlaceholder="ctrans('Search employee or reason')"
		@clear="clearFilters" />

	<div class="px-4 py-4 transition-opacity" :class="{ 'pointer-events-none opacity-50': isLoadingList }" :aria-busy="isLoadingList">
		<div v-if="rows.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
			<FontAwesomeIcon :icon="faClock" class="text-3xl text-gray-300" aria-hidden="true" />
			<p class="mt-3 text-sm font-medium text-gray-900">
				{{ hasActiveFilters ? ctrans("No clocking adjustments match these filters") : ctrans("No clocking adjustments yet") }}
			</p>
			<p v-if="hasActiveFilters" class="mt-1 text-sm text-gray-500">
				{{ ctrans("Try a different search, status or date range.") }}
			</p>
			<div v-if="hasActiveFilters" class="mt-4 flex justify-center">
				<Button type="tertiary" :label="ctrans('Clear filters')" @click="clearFilters">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</div>

		<div v-else class="flex flex-col gap-4">
			<HrRequestCard v-for="adjustment in rows" :key="adjustment.id" :status="adjustment.status" :statusLabel="adjustment.status_label">
				<template #avatar>
					<EmployeeAvatar :name="adjustment.employee_name" :image="adjustment.employee_avatar" size="md" />
				</template>
				<template #title>{{ adjustment.employee_name }}</template>
				<template #meta>
					<span class="inline-flex items-center gap-1 rounded bg-white/70 px-1.5 py-0.5 ring-1 ring-inset ring-gray-200">
						<FontAwesomeIcon :icon="faCalendarAlt" class="text-gray-400" fixed-width aria-hidden="true" />
						{{ useFormatTime(adjustment.date, { localeCode: locale.language.code }) }}
					</span>
				</template>

				<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
					<div class="rounded-md bg-gray-50 px-3 py-2">
						<div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Recorded") }}</div>
						<div v-if="hasRecordedTimes(adjustment)" class="mt-1 text-sm font-medium tabular-nums text-gray-900">
							{{ adjustment.original_start_time_local ?? "—" }} → {{ adjustment.original_end_time_local ?? "—" }}
						</div>
						<div v-else class="mt-1 text-sm text-gray-400">{{ ctrans("No clockings") }}</div>
					</div>

					<div class="rounded-md bg-gray-50 px-3 py-2">
						<div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Requested") }}</div>
						<div class="mt-1 flex flex-wrap items-center gap-1.5 text-sm tabular-nums text-gray-600">
							<span :class="startChanged(adjustment) ? changedTimeClass : ''">{{ requestedStart(adjustment) ?? "—" }}</span>
							<span class="text-gray-400">→</span>
							<span :class="endChanged(adjustment) ? changedTimeClass : ''">{{ requestedEnd(adjustment) ?? "—" }}</span>
						</div>
					</div>
				</div>
				<p v-if="adjustment.timezone" class="mt-1 text-xs text-gray-400">
					{{ ctrans("Times in :timezone", { timezone: adjustment.timezone }) }}
				</p>

				<dl class="mt-4 space-y-2 text-sm empty:hidden">
					<div v-if="adjustment.reason">
						<dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Reason") }}</dt>
						<dd class="whitespace-pre-line break-words text-gray-700">{{ adjustment.reason }}</dd>
					</div>
					<div v-if="adjustment.attachments?.length">
						<dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Attachments") }}</dt>
						<dd class="mt-1 flex flex-wrap gap-1.5">
							<a
								v-for="attachment in adjustment.attachments"
								:key="attachment.id"
								:href="attachment.url"
								target="_blank"
								rel="noopener"
								class="inline-flex items-center gap-1 rounded bg-[--app-accent-soft] px-2 py-0.5 text-xs font-medium text-[--app-accent-strong] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[--app-accent]">
								<FontAwesomeIcon :icon="faPaperclip" fixed-width aria-hidden="true" />
								{{ attachment.name }}
							</a>
						</dd>
					</div>
					<div v-if="!isPending(adjustment) && (adjustment.approver_name || adjustment.approved_at || adjustment.approval_comment)">
						<dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Decision") }}</dt>
						<dd class="text-gray-700">
							<span v-if="adjustment.approver_name" class="inline-flex items-center gap-1.5 align-middle">
								<EmployeeAvatar :name="adjustment.approver_name" :image="adjustment.approver_avatar" size="xs" />
								{{ adjustment.approver_name }}
							</span>
							<span v-if="adjustment.approver_name && adjustment.approved_at" class="text-gray-300"> · </span>
							<span v-if="adjustment.approved_at" class="tabular-nums">
								{{ useFormatTime(adjustment.approved_at, { formatTime: "hm", localeCode: locale.language.code }) }}
							</span>
							<p
								v-if="adjustment.approval_comment"
								class="mt-1 whitespace-pre-line break-words"
								:class="adjustment.status === 'rejected' ? 'rounded border border-red-200 bg-red-50 px-3 py-2 text-red-900' : 'text-gray-600'">
								{{ adjustment.approval_comment }}
							</p>
						</dd>
					</div>
				</dl>

				<template v-if="isPending(adjustment) ? can_edit : true" #actions>
					<template v-if="isPending(adjustment)">
						<Button
							type="tertiary"
							size="xs"
							:label="ctrans('Approve')"
							:loading="processingAdjustmentId === adjustment.id"
							@click="() => confirmApprove(adjustment)">
							<template #icon>
								<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
							</template>
						</Button>
						<Button
							type="tertiary"
							size="xs"
							:label="ctrans('Reject')"
							:disabled="processingAdjustmentId === adjustment.id"
							@click="() => openRejectDialog(adjustment)">
							<template #icon>
								<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
							</template>
						</Button>
					</template>
					<span v-else class="whitespace-nowrap text-xs text-gray-400">
						{{ ctrans("Processed") }}
					</span>
				</template>
			</HrRequestCard>
		</div>

		<Pagination
			v-if="rows.length > 0 && Number(paginationMeta?.total ?? 0) > 15"
			:on-click="visitPage"
			:has-data="true"
			:meta="paginationMeta"
			:on-per-page-change="changePerPage"
			custom-wrapper-class="mt-4 rounded-lg border border-gray-200" />
	</div>

	<Dialog
		v-model:visible="isRejectDialogVisible"
		modal
		:draggable="false"
		:header="ctrans('Reject clocking adjustment')"
		:style="{ width: '30rem', maxWidth: 'calc(100vw - 2rem)' }">
		<form v-if="selectedAdjustment" class="space-y-4" @submit.prevent="submitReject">
			<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
				<div class="font-medium text-gray-900">{{ selectedAdjustment.employee_name }}</div>
				<div class="tabular-nums text-gray-600">
					{{ useFormatTime(selectedAdjustment.date, { localeCode: locale.language.code }) }}
					<span class="text-gray-300">·</span>
					{{ requestedStart(selectedAdjustment) ?? "—" }} → {{ requestedEnd(selectedAdjustment) ?? "—" }}
				</div>
			</div>

			<div>
				<label for="adjustment-rejection-comment" class="mb-1 block text-sm font-medium text-gray-700">
					{{ ctrans("Reason for rejection") }} <span class="text-red-500">*</span>
				</label>
				<Textarea
					v-model="rejectForm.approval_comment"
					id="adjustment-rejection-comment"
					rows="3"
					autoResize
					fluid
					maxlength="500"
					:invalid="!!rejectForm.errors.approval_comment"
					:placeholder="ctrans('Please provide a reason for rejection')"
					class="focus:!border-[--app-accent]" />
				<p v-if="rejectForm.errors.approval_comment" class="mt-1 text-sm text-red-500">
					{{ rejectForm.errors.approval_comment }}
				</p>
				<p v-if="(rejectForm.errors as Record<string, string>).status" class="mt-1 text-sm text-red-500">
					{{ (rejectForm.errors as Record<string, string>).status }}
				</p>
			</div>

			<div class="flex justify-end gap-2 pt-2">
				<Button type="tertiary" :label="ctrans('Cancel')" @click.prevent="closeRejectDialog">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button
					type="tertiary"
					nativeType="submit"
					:label="ctrans('Reject request')"
					:loading="rejectForm.processing"
					:disabled="!rejectForm.approval_comment.trim()">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</form>
	</Dialog>

	<HrConfirmDialog :group="confirmGroup" />
</template>
