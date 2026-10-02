<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 2 Oct 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { computed, onBeforeUnmount, ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLifeRing } from "@fal"
library.add(faLifeRing)

const props = withDefaults(
	defineProps<{
		orgPartnerId: number
		partnerName: string
		size?: string
	}>(),
	{ size: "s" }
)

const steps = [
	"Finding what we are running out of",
	"Checking what :partner can spare without putting their own stock at risk",
	"Working out how much of each to order",
	"Adding the lines to the purchase order",
]

const isWorking = ref(false)
const step = ref(0)
let stepTimer: ReturnType<typeof setInterval> | null = null

const stepText = computed(() => ctrans(steps[step.value], { partner: props.partnerName }))

const stopTimer = () => {
	if (stepTimer) {
		clearInterval(stepTimer)
		stepTimer = null
	}
}

const prepare = () => {
	router.post(
		route("grp.models.org-partner.rescue_purchase_order.store", {
			orgPartner: props.orgPartnerId,
		}),
		{},
		{
			onStart: () => {
				isWorking.value = true
				step.value = 0
				stepTimer = setInterval(() => {
					step.value = Math.min(step.value + 1, steps.length - 1)
				}, 2500)
			},
			onFinish: () => {
				stopTimer()
				isWorking.value = false
			},
			onError: (errors) => {
				notify({
					title: ctrans("No rescue order prepared"),
					text: errors.rescue ?? ctrans("Something went wrong, please try again"),
					type: "error",
				})
			},
		}
	)
}

onBeforeUnmount(stopTimer)
</script>

<template>
	<span class="inline-flex">
		<Button
			:label="ctrans('Prepare rescue order')"
			icon="fal fa-life-ring"
			:size="size"
			:loading="isWorking"
			v-tooltip="
				ctrans(
					'Adds every SKO that would lose sales, and every A/B bestseller, to the purchase order being prepared, or a new one'
				)
			"
			@click="prepare" />

		<Modal
			:isOpen="isWorking"
			width="w-full max-w-md"
			:isClosableInBackground="false"
			@onClose="() => {}">
			<div
				class="flex flex-col items-center gap-3 px-4 py-6 text-center text-sm text-gray-700"
				role="status"
				aria-live="polite">
				<LoadingIcon class="text-3xl text-indigo-500" />
				<div class="font-semibold text-gray-900">{{ ctrans("Crunching the numbers") }}</div>
				<div class="text-gray-500">{{ stepText }}…</div>
				<div class="text-xs text-gray-400">
					{{
						ctrans(
							"This can take a few seconds, the purchase order opens when it is ready"
						)
					}}
				</div>
			</div>
		</Modal>
	</span>
</template>
