<script setup lang="ts">
import { ref, computed, watch, onUnmounted, onMounted } from "vue"
import { router } from "@inertiajs/vue3"
import { Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild } from "@headlessui/vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"

const props = defineProps<{
	channels: Array<{
		customer_sales_channel_id: number
		customer_sales_channel_name: string
		platform_name: string
		is_used: boolean
	}>
	productCategory: number
}>()

const isModalOpen = ref(false)
const isRunning = ref(false)
const progress = ref(0)

const selectedChannels = ref<number[]>([])
const selectAll = ref(false)

const channelCount = computed(() => props.channels.length)
const usedCount = computed(() => props.channels.filter((c) => c.is_used).length)

watch(selectAll, (val) => {
	if (val) {
		selectedChannels.value = props.channels
			.filter((c) => !c.is_used)
			.map((c) => c.customer_sales_channel_id)
	} else {
		selectedChannels.value = []
	}
})

watch(selectedChannels, (val) => {
	const selectableCount = props.channels.filter((c) => !c.is_used).length
	selectAll.value = val.length === selectableCount && selectableCount > 0
})

const handleProgress = (event: any) => {
	if (event.detail?.progress?.percentage !== undefined) {
		progress.value = event.detail.progress.percentage
	}
}

const handleFinish = () => {
	progress.value = 100
	setTimeout(() => {
		isRunning.value = false
		progress.value = 0
	}, 300)
}

router.on("progress", handleProgress)
router.on("finish", handleFinish)

const startProgress = () => {
	isRunning.value = true
	progress.value = 0

	setTimeout(() => {
		if (progress.value === 0 && isRunning.value) {
			progress.value = 15
		}
	}, 300)
}

const onSubmit = () => {
	if (!selectedChannels.value.length) return

	startProgress()

	router.post(
		route("retina.models.portfolio.store_to_multi_channels", {
			productCategory: props.productCategory,
		}),
		{
			customer_sales_channel_ids: selectedChannels.value,
		},
		{
			preserveScroll: true,
			onSuccess: () => {
				notify({
					title: trans("Success"),
					text: trans("Portfolio added"),
					type: "success",
				})
				isModalOpen.value = false
				selectedChannels.value = []
				selectAll.value = false
			},
			onError: () => {
				notify({
					title: trans("Failed"),
					text: trans("Failed to add portfolio"),
					type: "error",
				})
			},
		}
	)
}
</script>

<template>
	<Button
		v-if="channelCount > 0"
		style="indigo"
		icon="plus"
		label="Add Portfolio"
		@click="isModalOpen = true" />

	<TransitionRoot appear :show="isModalOpen" as="template">
		<Dialog as="div" class="relative z-50" @close="isModalOpen = false">
			<TransitionChild
				as="template"
				enter="duration-200 ease-out"
				enter-from="opacity-0"
				enter-to="opacity-100">
				<div class="fixed inset-0 bg-black/40" />
			</TransitionChild>

			<div class="fixed inset-0 flex items-center justify-center p-4">
				<TransitionChild
					as="template"
					enter="duration-200 ease-out"
					enter-from="opacity-0 scale-95"
					enter-to="opacity-100 scale-100">
					<DialogPanel class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
						<DialogTitle class="text-lg font-semibold"> Select Channel </DialogTitle>

						<div v-if="isRunning" class="mt-4 h-2 bg-gray-200 rounded overflow-hidden">
							<div
								class="h-full bg-indigo-600 transition-all duration-200"
								:style="{ width: progress + '%' }" />
						</div>

						<div class="mt-4 space-y-3">
							<label
								class="flex items-center gap-3 p-3 border rounded cursor-pointer">
								<input
									type="checkbox"
									v-model="selectAll"
									:disabled="isRunning || channelCount === usedCount" />
								<div>
									<div class="font-medium">All Channels</div>
									<div class="text-xs text-gray-500">
										{{ channelCount }} channels
									</div>
								</div>
							</label>

							<div class="border-t pt-3 space-y-2 max-h-64 overflow-auto">
								<label
									v-for="c in props.channels"
									:key="c.customer_sales_channel_id"
									class="flex items-start gap-3 p-3 rounded border transition"
									:class="{
										'opacity-50 cursor-not-allowed bg-gray-50': c.is_used,
										'cursor-pointer hover:bg-gray-50': !c.is_used,
									}">
									<input
										type="checkbox"
										class="mt-1"
										:value="c.customer_sales_channel_id"
										v-model="selectedChannels"
										:disabled="isRunning || c.is_used" />

									<div class="flex-1 min-w-0">
										<div class="flex items-center justify-between gap-2">
											<div class="font-medium truncate">
												{{ c.customer_sales_channel_name }}
											</div>

											<span
												v-if="c.is_used"
												class="shrink-0 text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-600">
												Already In
											</span>
										</div>

										<div class="text-xs text-gray-500 mt-0.5 truncate">
											{{ c.platform_name }}
										</div>
									</div>
								</label>
							</div>
						</div>

						<div class="mt-6 flex justify-end gap-2">
							<Button
								type="transparent"
								label="Cancel"
								:disabled="isRunning"
								@click="isModalOpen = false" />

							<Button
								type="primary"
								label="Submit"
								:loading="isRunning"
								:disabled="!selectedChannels.length"
								@click="onSubmit" />
						</div>
					</DialogPanel>
				</TransitionChild>
			</div>
		</Dialog>
	</TransitionRoot>
</template>
