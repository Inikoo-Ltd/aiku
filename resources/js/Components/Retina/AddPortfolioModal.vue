<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from "vue"
import { router } from "@inertiajs/vue3"
import { Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild } from "@headlessui/vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"

const props = defineProps<{
	channels: Array<{
		customer_sales_channel_id: number
		customer_sales_channel_name: string
		platform_name: string
	}>
	productCategory: number
}>()

const isModalOpen = ref(false)
const isRunning = ref(false)
const progress = ref(0)

const selectedChannels = ref<number[]>([])
const selectAll = ref(false)

const channelCount = computed(() => props.channels.length)

watch(selectAll, (val) => {
	selectedChannels.value = val ? props.channels.map((c) => c.customer_sales_channel_id) : []
})

watch(selectedChannels, (val) => {
	selectAll.value = val.length === channelCount.value && channelCount.value > 0
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

	// console.log("SUBMIT PORTFOLIO", {
	// 	productCategory: props.productCategory,
	// 	customer_sales_channel_ids: selectedChannels.value,
	// })
	// return
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
	<button
		v-if="channelCount > 0"
		@click="isModalOpen = true"
		class="inline-flex items-center gap-x-2 px-3 py-2 text-sm font-medium rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
		<FontAwesomeIcon icon="plus" fixed-width />
		Add Portfolio
	</button>

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
								<input type="checkbox" v-model="selectAll" :disabled="isRunning" />
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
									class="flex gap-3 p-3 border rounded cursor-pointer">
									<input
										type="checkbox"
										:value="c.customer_sales_channel_id"
										v-model="selectedChannels"
										:disabled="isRunning" />
									<div>
										<div class="font-medium truncate">
											{{ c.customer_sales_channel_name }}
										</div>
										<div class="text-xs text-gray-500">
											{{ c.platform_name }}
										</div>
									</div>
								</label>
							</div>
						</div>

						<!-- ACTIONS -->
						<div class="mt-6 flex justify-end gap-2">
							<button @click="isModalOpen = false" :disabled="isRunning">
								Cancel
							</button>
							<button
								@click="onSubmit"
								:disabled="!selectedChannels.length || isRunning"
								class="px-4 py-2 bg-indigo-600 text-white rounded disabled:opacity-50">
								Submit
							</button>
						</div>
					</DialogPanel>
				</TransitionChild>
			</div>
		</Dialog>
	</TransitionRoot>
</template>
