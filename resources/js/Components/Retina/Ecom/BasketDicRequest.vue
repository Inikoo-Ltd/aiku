<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 25 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { onMounted, ref } from "vue"
import { router } from "@inertiajs/vue3"
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFileInvoice, faCheck, faClock, faUser } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"
library.add(faFileInvoice, faCheck, faClock, faUser)

const props = defineProps<{
	request: { customer_id: number; dic: string | null }
}>()

const LATER_DAYS = 7
const laterKey = `aiku-dic-later-${props.request.customer_id}`

const isVisible = ref(false)
const dic = ref(props.request.dic ?? "")
const error = ref<string | null>(null)
const isSaving = ref(false)

const laterUntil = () => {
	try {
		return Number(localStorage.getItem(laterKey) ?? 0)
	} catch {
		return 0
	}
}

onMounted(() => {
	isVisible.value = laterUntil() < Date.now()
})

const isSlovakDic = (value: string) => /^[1-9]\d{9}$/.test(value) && Number(value) % 11 === 0

const update = (data: Record<string, unknown>) => {
	isSaving.value = true
	error.value = null
	router.patch(route("retina.models.customer.update", { customer: props.request.customer_id }), data, {
		preserveScroll: true,
		onSuccess: () => (isVisible.value = false),
		onError: (errors) => (error.value = Object.values(errors)[0] ?? ctrans("Could not save, please try again.")),
		onFinish: () => (isSaving.value = false),
	})
}

const save = () => {
	const digits = dic.value.replace(/\D/g, "")
	if (!isSlovakDic(digits)) {
		error.value = ctrans("This is not a valid Slovak DIČ. Please check the number.")
		return
	}
	update({ peppol_dic: digits })
}

const later = () => {
	isVisible.value = false
	try {
		localStorage.setItem(laterKey, String(Date.now() + LATER_DAYS * 24 * 3600 * 1000))
	} catch {
		return
	}
}
</script>

<template>
	<div v-if="isVisible" class="mx-3 mt-3 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm md:mx-6">
		<div class="flex items-start gap-3">
			<FontAwesomeIcon icon="fal fa-file-invoice" class="mt-0.5 text-gray-500" fixed-width aria-hidden="true" />
			<div class="min-w-0 flex-1">
				<div class="font-medium text-gray-900">{{ ctrans("Your DIČ for electronic invoices") }}</div>
				<p class="mt-0.5 text-gray-600">
					{{ ctrans("From 2027 we must send invoices to Slovak companies and sole traders as electronic invoices, to their DIČ. Please add yours.") }}
				</p>

				<div class="mt-2 flex flex-wrap items-center gap-2">
					<label for="basket-dic" class="text-gray-700">{{ ctrans("DIČ") }} <span class="text-red-500">*</span></label>
					<InputText
						id="basket-dic"
						v-model="dic"
						inputmode="numeric"
						maxlength="16"
						placeholder="2120123456"
						class="w-40"
						@update:model-value="error = null"
						@keydown.enter.prevent="save"
					/>
					<Button type="tertiary" :label="ctrans('Save')" :loading="isSaving" @click="save">
						<template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
					</Button>
					<Button type="tertiary" :label="ctrans('Later')" :disabled="isSaving" @click="later">
						<template #icon><FontAwesomeIcon icon="fal fa-clock" class="text-gray-500" fixed-width aria-hidden="true" /></template>
					</Button>
				</div>
				<p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>

				<button type="button" class="mt-2 text-xs text-gray-500 underline hover:text-gray-700" :disabled="isSaving" @click="update({ buys_as_private_person: true })">
					{{ ctrans("I buy as a private person, I have no DIČ") }}
				</button>
			</div>
		</div>
	</div>
</template>
