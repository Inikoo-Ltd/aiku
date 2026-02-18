<script setup lang="ts">
import { ref, computed } from "vue"
import { Link, router, useForm } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { faPlus, faCheckCircle, faTimesCircle, faClock } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faPlus, faCheckCircle, faTimesCircle, faClock)

const props = defineProps<{
	data: {}
	tab?: string
	organisation?: string | null
}>()

const locale = useLocaleStore()
const isCreateModalOpen = ref(false)
const isSubmitting = ref(false)

const adjustmentForm = useForm({
	date: "",
	requested_start_at: "",
	requested_end_at: "",
	reason: "",
	attachments: [] as File[],
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

const submitAdjustment = () => {
	isSubmitting.value = true
	adjustmentForm
		.transform((data) => ({
			...data,
			organisation: props.organisation ?? undefined,
		}))
		.post(route("grp.clocking_employees.adjustments.store"), {
			preserveScroll: true,
			forceFormData: true,
			onSuccess: () => {
				isCreateModalOpen.value = false
				adjustmentForm.reset()
			},
			onError: () => {
				isSubmitting.value = false
			},
			onFinish: () => {
				isSubmitting.value = false
			},
		})
}

const closeCreateModal = () => {
	isCreateModalOpen.value = false
	adjustmentForm.reset()
}
</script>

<template>
	<div class="px-4 py-4 space-y-4">
		<div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
			<p class="text-sm text-gray-600">
				{{
					trans(
						"Request attendance corrections for dates where your clock-in/out times need adjustment. All requests require manager approval."
					)
				}}
			</p>
		</div>

		<div class="flex justify-end">
			<Button
				@click="isCreateModalOpen = true"
				:label="trans('Request Adjustment')"
				icon="fal fa-plus"
				type="create" />
		</div>

		<Table :resource="data" :name="tab">
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
				<div class="max-w-xs">
					<span class="text-gray-600 text-sm truncate block">{{
						adjustment.reason
					}}</span>
					<span
						v-if="adjustment.status === 'rejected' && adjustment.approval_comment"
						class="text-red-600 text-xs truncate block mt-1">
						{{ adjustment.approval_comment }}
					</span>
				</div>
			</template>
		</Table>

		<Modal :isOpen="isCreateModalOpen" @onClose="closeCreateModal" width="w-full max-w-lg">
			<div class="p-6">
				<h3 class="text-lg font-semibold text-gray-900 mb-4">
					{{ trans("Request Attendance Adjustment") }}
				</h3>

				<form @submit.prevent="submitAdjustment" class="space-y-4">
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("Date")
						}}</label>
						<input
							v-model="adjustmentForm.date"
							type="date"
							class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
						<p v-if="adjustmentForm.errors.date" class="text-sm text-red-500 mt-1">
							{{ adjustmentForm.errors.date }}
						</p>
					</div>

					<div class="grid grid-cols-2 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1">{{
								trans("New Check-in Time")
							}}</label>
							<input
								v-model="adjustmentForm.requested_start_at"
								type="time"
								class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
							<p
								v-if="adjustmentForm.errors.requested_start_at"
								class="text-sm text-red-500 mt-1">
								{{ adjustmentForm.errors.requested_start_at }}
							</p>
						</div>

						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1">{{
								trans("New Check-out Time")
							}}</label>
							<input
								v-model="adjustmentForm.requested_end_at"
								type="time"
								class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
							<p
								v-if="adjustmentForm.errors.requested_end_at"
								class="text-sm text-red-500 mt-1">
								{{ adjustmentForm.errors.requested_end_at }}
							</p>
						</div>
					</div>

					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("Reason for Adjustment")
						}}</label>
						<textarea
							v-model="adjustmentForm.reason"
							rows="3"
							class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
							:placeholder="trans('Please explain why this adjustment is needed')" />
						<p v-if="adjustmentForm.errors.reason" class="text-sm text-red-500 mt-1">
							{{ adjustmentForm.errors.reason }}
						</p>
					</div>

					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("Supporting Document (Optional)")
						}}</label>
						<input
							type="file"
							@change="
								(e: Event) => {
									const files = (e.target as HTMLInputElement).files
									if (files) adjustmentForm.attachments = Array.from(files)
								}
							"
							class="w-full border border-gray-300 rounded-lg px-3 py-2"
							accept=".pdf,.jpg,.jpeg,.png" />
						<p class="text-xs text-gray-500 mt-1">
							{{ trans("Upload supporting documentation (PDF, JPG, PNG)") }}
						</p>
					</div>

					<div class="flex justify-end gap-3 pt-4">
						<Button
							@click="closeCreateModal"
							:label="trans('Cancel')"
							type="tertiary" />
							<Button
								type="primary"
								nativeType="submit"
								:label="trans('Submit Request')"
								:loading="isSubmitting" />
					</div>
				</form>
			</div>
		</Modal>
	</div>
</template>
