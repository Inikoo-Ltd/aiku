<script setup lang="ts">
import { ref } from "vue"
import { Head, router, useForm } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { faCheckCircle, faTimesCircle, faClock } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faCheckCircle, faTimesCircle, faClock)

const props = defineProps<{
	title: string
	pageHead: {
		title: string
		icon: string[]
	}
	adjustments: {
		data: any[]
		links: any
		meta: any
	}
	status_options: Record<string, string>
}>()

const locale = useLocaleStore()
const isRejectModalOpen = ref(false)
const selectedAdjustment = ref<any>(null)
const isSubmitting = ref(false)

const rejectForm = useForm({
	approval_comment: "",
})

const statusColors: Record<string, string> = {
	pending: "bg-yellow-100 text-yellow-800 border-yellow-200",
	approved: "bg-green-100 text-green-800 border-green-200",
	rejected: "bg-red-100 text-red-800 border-red-200",
}

const statusIcons: Record<string, string> = {
	pending: "fal fa-clock",
	approved: "fal fa-check-circle",
	rejected: "fal fa-times-circle",
}

const formatTime = (dateString: string | null) => {
	if (!dateString) return "-"
	const date = new Date(dateString)
	return date.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit", hour12: false })
}

const approveAdjustment = (adjustment: any) => {
	router.post(
		route("grp.org.hr.adjustments.approve", {
			organisation: route().params.organisation,
			adjustment: adjustment.id,
		}),
		{},
		{
			preserveScroll: true,
		}
	)
}

const openRejectModal = (adjustment: any) => {
	selectedAdjustment.value = adjustment
	isRejectModalOpen.value = true
}

const submitReject = () => {
	if (!selectedAdjustment.value) return

	isSubmitting.value = true
	rejectForm.post(
		route("grp.org.hr.adjustments.reject", {
			organisation: route().params.organisation,
			adjustment: selectedAdjustment.value.id,
		}),
		{
			preserveScroll: true,
			onSuccess: () => {
				isRejectModalOpen.value = false
				rejectForm.reset()
				selectedAdjustment.value = null
			},
			onFinish: () => {
				isSubmitting.value = false
			},
		}
	)
}

const closeRejectModal = () => {
	isRejectModalOpen.value = false
	rejectForm.reset()
	selectedAdjustment.value = null
}
</script>

<template>
	<Head :title="title" />
	<PageHeading :data="pageHead" />

	<div class="px-4 py-4">
		<Table :resource="adjustments">
			<template #cell(employee_name)="{ item: adjustment }">
				<span class="font-medium text-gray-900">{{ adjustment.employee_name }}</span>
			</template>

			<template #cell(date)="{ item: adjustment }">
				<span class="text-gray-900">{{
					useFormatTime(adjustment.date, { localeCode: locale.language.code })
				}}</span>
			</template>

			<template #cell(original_times)="{ item: adjustment }">
				<span class="text-gray-600 tabular-nums">
					{{ formatTime(adjustment.original_start_at) }} -
					{{ formatTime(adjustment.original_end_at) }}
				</span>
			</template>

			<template #cell(requested_times)="{ item: adjustment }">
				<span class="text-gray-900 tabular-nums font-medium">
					{{ formatTime(adjustment.requested_start_at) }} -
					{{ formatTime(adjustment.requested_end_at) }}
				</span>
			</template>

			<template #cell(status_label)="{ item: adjustment }">
				<span
					class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium border"
					:class="statusColors[adjustment.status]">
					<FontAwesomeIcon :icon="statusIcons[adjustment.status]" fixed-width />
					{{ adjustment.status_label }}
				</span>
			</template>

			<template #cell(reason)="{ item: adjustment }">
				<span class="text-gray-600 text-sm truncate max-w-xs block">{{
					adjustment.reason
				}}</span>
			</template>

			<template #cell(actions)="{ item: adjustment }">
				<div v-if="adjustment.status === 'pending'" class="flex gap-2">
					<Button
						@click="approveAdjustment(adjustment)"
						:label="trans('Approve')"
						size="xs"
						type="primary"
						icon="fal fa-check" />
					<Button
						@click="openRejectModal(adjustment)"
						:label="trans('Reject')"
						size="xs"
						type="delete"
						icon="fal fa-times" />
				</div>
				<span v-else class="text-gray-400 text-xs">{{ trans("Processed") }}</span>
			</template>
		</Table>
	</div>

	<Modal :isOpen="isRejectModalOpen" @onClose="closeRejectModal" width="w-full max-w-md">
		<div class="p-6">
			<h3 class="text-lg font-semibold text-gray-900 mb-4">
				{{ trans("Reject Adjustment Request") }}
			</h3>
			<p class="text-sm text-gray-600 mb-4">
				{{ trans("Are you sure you want to reject this adjustment request from") }}
				<strong>{{ selectedAdjustment?.employee_name }}</strong
				>?
			</p>

			<form @submit.prevent="submitReject" class="space-y-4">
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-1">{{
						trans("Reason for rejection")
					}}</label>
					<textarea
						v-model="rejectForm.approval_comment"
						rows="3"
						class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500"
						:placeholder="trans('Please provide a reason for rejection')" />
					<p v-if="rejectForm.errors.approval_comment" class="text-sm text-red-500 mt-1">
						{{ rejectForm.errors.approval_comment }}
					</p>
				</div>

				<div class="flex justify-end gap-3 pt-4">
					<Button @click="closeRejectModal" :label="trans('Cancel')" type="tertiary" />
					<Button type="submit" :label="trans('Reject')" :loading="isSubmitting" />
				</div>
			</form>
		</div>
	</Modal>
</template>
