<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-13 09:12
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faUndo } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

const props = defineProps<{
	deliveryAddress: string | null
	defaultDeliveryAddress: string | null
	followsDefault: boolean
	updateRoute: routeType
}>()

const visible = defineModel<boolean>("visible", { default: false })

const address = ref("")
const isSaving = ref(false)
const error = ref<string | null>(null)

watch(visible, (isOpen) => {
	if (isOpen) {
		address.value = props.deliveryAddress ?? ""
		error.value = null
	}
})

const isDefault = computed(() => address.value.trim() === (props.defaultDeliveryAddress ?? "").trim())

function save(deliveryAddress: string | null) {
	isSaving.value = true
	error.value = null

	axios
		.patch(route(props.updateRoute.name, props.updateRoute.parameters), { delivery_address: deliveryAddress })
		.then(() => {
			visible.value = false
			router.reload({ only: ["box_stats", "showcase"] })
		})
		.catch((exception) => {
			const errors = exception?.response?.data?.errors ?? {}
			error.value = (Object.values(errors)[0] as string[] | undefined)?.[0] ?? ctrans("Something went wrong")
		})
		.finally(() => (isSaving.value = false))
}
</script>

<template>
	<Dialog v-model:visible="visible" modal :header="ctrans('Delivery address')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
		<div class="flex flex-col gap-3 text-sm">
			<label class="flex flex-col gap-1">
				<span class="font-medium text-gray-700">{{ ctrans("Deliver to") }}</span>
				<Textarea v-model="address" rows="5" autoResize :disabled="isSaving" class="w-full text-sm" />
			</label>

			<div v-if="defaultDeliveryAddress && !isDefault" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
				<div class="mb-1 text-xs text-gray-500">{{ ctrans("Organisation default") }}</div>
				<div class="whitespace-pre-line text-gray-700">{{ defaultDeliveryAddress }}</div>
				<Button type="tertiary" size="xs" class="mt-2" :label="ctrans('Use organisation default')" :disabled="isSaving" @click="save(null)">
					<template #icon>
						<FontAwesomeIcon :icon="faUndo" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>

			<p v-else-if="followsDefault" class="text-xs text-gray-500">
				{{ ctrans("This order follows the organisation's delivery address until it is submitted.") }}
			</p>

			<p v-if="error" class="text-xs text-red-500">{{ error }}</p>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full :label="ctrans('Save')" :loading="isSaving" :disabled="!address.trim()" @click="save(isDefault ? null : address.trim())">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
