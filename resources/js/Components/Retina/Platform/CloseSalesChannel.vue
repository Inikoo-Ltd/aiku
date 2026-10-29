<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faExclamationTriangle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
library.add(faTimes, faExclamationTriangle)

const props = defineProps<{
	customerSalesChannel: {
		name?: string | null
		reference?: string | null
		platform_code?: string | null
		number_portfolios?: number | null
		delete_route: routeType
	}
}>()

const isOpen = ref(false)
const typedName = ref("")
const isClosing = ref(false)
const errors = ref<Record<string, string>>({})

const channelName = computed(() => props.customerSalesChannel.name || props.customerSalesChannel.reference || "")
const isTypedNameRight = computed(() => typedName.value.trim().toLowerCase() === channelName.value.trim().toLowerCase())
const platformCode = computed(() => props.customerSalesChannel.platform_code)
const numberPortfolios = computed(() => props.customerSalesChannel.number_portfolios ?? 0)

const open = () => {
	typedName.value = ""
	errors.value = {}
	isOpen.value = true
}

const closeChannel = () => {
	if (!isTypedNameRight.value || isClosing.value) {
		return
	}

	router.delete(route(props.customerSalesChannel.delete_route.name, props.customerSalesChannel.delete_route.parameters), {
		data: { confirmation: typedName.value },
		preserveScroll: true,
		onStart: () => {
			isClosing.value = true
			errors.value = {}
		},
		onSuccess: () => {
			isOpen.value = false
		},
		onError: (responseErrors) => {
			errors.value = responseErrors
		},
		onFinish: () => {
			isClosing.value = false
		},
	})
}
</script>

<template>
	<div>
		<slot :open="open" />

		<Dialog
			v-model:visible="isOpen"
			modal
			:draggable="false"
			:header="ctrans('Close this channel?')"
			class="w-full max-w-md mx-4">
			<div class="space-y-3 text-sm text-gray-600">
				<p class="font-semibold text-gray-800 break-all">{{ channelName }}</p>

				<ul class="list-disc pl-5 space-y-1">
					<li v-if="numberPortfolios > 0">
						{{ ctrans("Its :count products come off this channel.", { count: numberPortfolios }) }}
					</li>
					<li v-if="platformCode !== 'manual'">
						{{ ctrans("We stop updating stock and prices, and new orders from it no longer come to us.") }}
					</li>
					<li v-if="platformCode === 'ebay'">
						{{ ctrans("Your listings stay live on eBay: end them there if you stop selling them.") }}
					</li>
					<li>{{ ctrans("Your past orders and invoices are kept.") }}</li>
				</ul>

				<p v-if="platformCode === 'ebay'">
					{{ ctrans("If you close it by mistake, connect the same eBay account again from Add Sales Channel to get it back with its products.") }}
				</p>

				<div>
					<label for="close-channel-name" class="block mb-1">
						{{ ctrans("Type :name to confirm", { name: channelName }) }}
					</label>
					<InputText
						id="close-channel-name"
						v-model="typedName"
						fluid
						autocomplete="off"
						:invalid="!!errors.confirmation"
						:disabled="isClosing"
						@keydown.enter="closeChannel" />
					<p v-if="errors.confirmation" class="mt-1 text-xs text-red-600">{{ errors.confirmation }}</p>
				</div>

				<p v-if="errors.message" class="text-xs text-red-600">{{ errors.message }}</p>
			</div>

			<template #footer>
				<div class="flex justify-end gap-2">
					<Button type="tertiary" :label="ctrans('Cancel')" :disabled="isClosing" @click="isOpen = false">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button
						type="tertiary"
						:label="ctrans('Close channel')"
						:loading="isClosing"
						:disabled="!isTypedNameRight"
						@click="closeChannel">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-amber-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
			</template>
		</Dialog>
	</div>
</template>
