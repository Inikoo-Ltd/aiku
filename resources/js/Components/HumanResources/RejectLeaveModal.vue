<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { routeType } from '@/types/route'
import Modal from '@/Components/Utils/Modal.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExclamationTriangle } from '@fal'
import { notify } from '@kyvg/vue3-notification'

library.add(faExclamationTriangle)

const props = defineProps<{
	isOpen: boolean
	leave: any
	route: routeType
}>()

const emit = defineEmits<{
	(e: 'close'): void
}>()

const rejectionReason = ref('')
const isLoading = ref(false)

watch(() => props.isOpen, () => {
	rejectionReason.value = ''
})

const submitReject = () => {
	if (!rejectionReason.value.trim()) {
		notify({
			title: trans('Validation Error'),
			text: trans('Please enter a rejection reason'),
			type: 'error',
		})
		return
	}

	isLoading.value = true

	router.post(
		route(props.route.name, props.route.parameters),
		{ rejection_reason: rejectionReason.value },
		{
			onError: (e) => {
				notify({
					title: trans('Something went wrong'),
					text: e.message || '',
					type: 'error',
				})
			},
			onSuccess: () => {
				emit('close')
			},
			onFinish: () => {
				isLoading.value = false
			},
		}
	)
}

const closeModal = () => {
	emit('close')
}
</script>

<template>
	<Modal :isOpen="isOpen" @onClose="closeModal" width="w-full max-w-md">
		<div class="flex items-start gap-4">
			<div
				class="flex shrink-0 items-center justify-center rounded-full bg-amber-100 border border-amber-300 size-12">
				<FontAwesomeIcon icon='fal fa-exclamation-triangle' class='text-amber-600' fixed-width aria-hidden='true' />
			</div>
			<div class="flex-1">
				<h3 class="text-lg font-semibold text-gray-900">
					{{ trans('Reject Leave Request') }}
				</h3>
				<p class="mt-2 text-sm text-gray-500">
					{{ trans('Are you sure you want to reject this leave request?') }}
				</p>
			</div>
		</div>

		<div class="mt-4">
			<label class="flex items-start text-sm text-gray-700 leading-none mb-1 font-medium">
				{{ trans('Rejection Reason') }}
				<span class="text-red-500 ml-1">*</span>
			</label>
			<PureTextarea
				v-model="rejectionReason"
				:placeholder="trans('Enter the reason for rejection')"
				:maxLength="255"
				:counter="true"
				rows="3"
			/>
		</div>

		<div class="mt-6 flex justify-end gap-2">
			<Button
				:label="trans('Cancel')"
				type="tertiary"
				@click="closeModal"
				:disabled="isLoading" />
			<Button
				:label="trans('Reject')"
				type="warning"
				:loading="isLoading"
				:disabled="!rejectionReason.trim()"
				@click="submitReject" />
		</div>
	</Modal>
</template>
