<script setup lang="ts">
import { ref, computed, watch, onUnmounted, onMounted, inject } from "vue"
import { router } from "@inertiajs/vue3"
import { Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild } from "@headlessui/vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ProgressSpinner from "primevue/progressspinner"

const props = defineProps<{
	channels: Array<{
		customer_sales_channel_id: number
		customer_sales_channel_name: string
		platform_name: string
		is_used: boolean
	}>
	productCategory: number
}>()

const emit = defineEmits<{
	(e: "submitted", ids: number[]): void
}>()

const isModalOpen = ref(false)
const isRunning = ref(false)

const layout = inject("layout")

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

const progress = ref(0)

const channel = ref(null)
const initSocketListener = () => {
	if (!window.Echo) {
		console.error("Echo not found!")
		return
	}

	const socketEvent = `retina.pc-clone.${layout.user.customer_id}`
	const socketAction = ".action-progress"

	channel.value = window.Echo.private(socketEvent).listen(socketAction, (eventData: any) => {
		if (typeof eventData.number_percentage === "number") {
			progress.value = eventData.number_percentage
			isRunning.value = true
		}

		if (eventData.number_percentage >= 100) {
			isRunning.value = false
			progress.value = 100
			isModalOpen.value = false
			stopSocketListener()
		}
	})
}

const stopSocketListener = () => {
	progress.value = 0
	if (channel.value) {
		channel.value = null
		channel.value = null
	}
}

const onSubmit = () => {
	if (!selectedChannels.value.length) return

	isRunning.value = true
	progress.value = 0

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
				initSocketListener()

				emit("submitted", selectedChannels.value)

				selectedChannels.value = []
				selectAll.value = false
			},
			onError: () => {
				isRunning.value = false

				notify({
					title: trans("Failed"),
					text: trans("Failed to add portfolio"),
					type: "error",
				})
			},
		}
	)
}

onMounted(() => {
	initSocketListener()
})

onUnmounted(() => {
	stopSocketListener()
})
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

						<div
							v-if="isRunning"
							class="mt-4 flex flex-col items-center justify-center">
							<ProgressSpinner style="width: 60px; height: 60px" />
							<div class="mt-2 text-sm font-semibold text-gray-700">
								{{ progress }}%
							</div>

							<p class="mt-1 text-xs text-gray-500 text-center">
								Processing portfolio…<br />
								Please don’t close this window
							</p>

							<div class="mt-3 w-full h-2 bg-gray-200 rounded overflow-hidden">
								<div
									class="h-full bg-indigo-600 transition-all duration-300"
									:style="{ width: progress + '%' }" />
							</div>
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
								:disabled="isRunning || !selectedChannels.length"
								@click="onSubmit" />
						</div>
					</DialogPanel>
				</TransitionChild>
			</div>
		</Dialog>
	</TransitionRoot>
</template>
