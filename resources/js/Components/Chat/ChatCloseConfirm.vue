<script setup lang="ts">
import { ref, inject } from "vue"
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"

const props = defineProps<{
	sessionUlid: string
	title?: string
	yesLabel?: string
	noLabel?: string
}>()

const emit = defineEmits<{
	(e: "success"): void
	(e: "onNo"): void
	(e: "onYes"): void
}>()

const isOpen = ref(false)
const isLoading = ref(false)

const changeModel = () => {
	isOpen.value = !isOpen.value
}
const close = () => {
	isOpen.value = false
}

const closeSession = async () => {
	console.log(props.sessionUlid)
	if (!props.sessionUlid) {
		close()
		return
	}
	isLoading.value = true
	try {
		const organisation = route().params?.organisation ?? "aw"

		const assignRoute: routeType = {
			name: "grp.org.crm.agents.sessions.close",
			parameters: [props.sessionUlid, organisation],
			method: "patch",
		}

		await axios.patch(
			route(assignRoute.name, assignRoute.parameters),
			{},
			{ withCredentials: true }
		)

		emit("success")
		close()
	} finally {
		isLoading.value = false
	}
}
</script>

<template>
	<div>
		<slot
			name="default"
			:isOpenModal="isOpen"
			:changeModel="changeModel"
			:isLoading="isLoading" />
		<TransitionRoot as="template" :show="isOpen">
			<Dialog
				class="relative z-50"
				@close="
					() => {
						emit('onNo')
						close()
					}
				">
				<TransitionChild
					as="template"
					enter="ease-out duration-150"
					enter-from="opacity-0"
					enter-to="opacity-100"
					leave="ease-in duration-100"
					leave-from="opacity-100"
					leave-to="opacity-0">
					<div class="fixed inset-0 bg-gray-500/75" />
				</TransitionChild>
				<div class="fixed inset-0 z-50 w-screen overflow-y-auto">
					<div class="flex min-h-full items-center justify-center p-4">
						<TransitionChild
							as="template"
							enter="ease-out duration-150"
							enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
							enter-to="opacity-100 translate-y-0 sm:scale-100"
							leave="ease-in duration-100"
							leave-from="opacity-100 translate-y-0 sm:scale-100"
							leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
							<DialogPanel
								class="relative transform overflow-hidden rounded-lg bg-white px-6 pt-6 pb-6 text-left shadow-2xl transition-all sm:w-full sm:max-w-sm">
								<DialogTitle class="text-base font-semibold">
									{{ props.title ?? trans("Close chat session?") }}
								</DialogTitle>
								<p class="mt-2 text-sm text-gray-600">
									{{
										trans(
											"This action will close the chat and move it to Resolved."
										)
									}}
								</p>
								<div class="mt-5 flex gap-2 justify-end">
									<button
										type="button"
										class="px-3 py-2 text-sm rounded-md border bg-white hover:bg-gray-50"
										@click="
											() => {
												emit('onNo')
												close()
											}
										">
										{{ props.noLabel ?? trans("Cancel") }}
									</button>
									<button
										type="button"
										class="px-3 py-2 text-sm rounded-md bg-red-600 text-white hover:bg-red-700 disabled:opacity-50"
										:disabled="isLoading"
										@click="closeSession">
										{{ props.yesLabel ?? trans("Close") }}
									</button>
								</div>
							</DialogPanel>
						</TransitionChild>
					</div>
				</div>
			</Dialog>
		</TransitionRoot>
	</div>
</template>
