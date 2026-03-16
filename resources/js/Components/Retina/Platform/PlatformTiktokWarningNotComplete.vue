<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import { CustomerSalesChannel } from "@/types/customer-sales-channel"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { Message } from "primevue"
import { checkVisible } from "@/Composables/Workshop"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { ref } from "vue"
import { useForm } from "@inertiajs/vue3"

const props = defineProps<{
	platform: any
	customer_sales_channel: CustomerSalesChannel
}>()

const isLoadingStep = ref(false)

const tiktokInput = useForm({
	tiktok_shop_id: "",
	tiktok_shop_chiper: "",
})

const errors = ref({
	message: "",
})

const submitForm = async () => {
	isLoadingStep.value = true

	if(!tiktokInput.tiktok_shop_id && !tiktokInput.tiktok_shop_chiper) {
		errors.value = {
			message: trans('Please select at least one shop.')
		}

		isLoadingStep.value = false
		return;
	}

	try {
		const { data } = await axios.patch(
			route("retina.models.dropshipping.tiktok.update", {
				tiktokUser: props.customer_sales_channel?.user?.id,
			}),
			tiktokInput.data()
		)

		window.location.reload()
		isLoadingStep.value = false
	} catch (err: any) {
		isLoadingStep.value = false
		errors.value = err.response?.data?.errors
	}
}
</script>

<template>
	<Message severity="warn" class="my-8">
		<div
			class="ml-2 font-normal flex flex-col gap-x-4 items-center sm:flex-row justify-between w-full">
			<div>
				<FontAwesomeIcon
					icon="fad fa-exclamation-triangle"
					class="text-xl"
					fixed-width
					aria-hidden="true" />
				<div class="inline items-center gap-x-2">
					{{
						trans(
							"Your registration is not complete yet, you can continue choose your shop here"
						)
					}}
				</div>
			</div>
		</div>
	</Message>
	<div class="flex flex-col w-full gap-4">
		<div
			v-for="(shop, index) in customer_sales_channel?.user_data?.authorized_shop"
			:key="index"
			class="flex items-center gap-2">
			<input
				type="radio"
				:id="'shop-' + index"
				:value="shop"
				@change="() => {
						errors.message = '';
						tiktokInput.tiktok_shop_id = shop.id;
						tiktokInput.tiktok_shop_chiper = shop.cipher;
					}"
				class="cursor-pointer" />
			<label :for="'shop-' + index" class="cursor-pointer">{{ shop.name }} ({{ shop.code }})</label>
		</div>
		<div class="flex justify-end">
			<Button type="primary" size="sm" label="Save" @click="submitForm" />
		</div>
	</div>
</template>
