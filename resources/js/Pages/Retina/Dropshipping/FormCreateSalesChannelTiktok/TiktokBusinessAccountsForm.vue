<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Mon, 17 Nov 2025 14:54:41 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { inject, ref, watch, onMounted, provide, onUnmounted } from "vue"
import { router, useForm } from "@inertiajs/vue3"
import PureInput from "@/Components/Pure/PureInput.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import PureInputWithAddOn from "@/Components/Pure/PureInputWithAddOn.vue"

const goNext = inject("goNext")
const closeCreateTiktokModal = inject("closeCreateTiktokModal")
const tiktokUserId = inject("tiktokUserId")

const tiktokShopData = ref([])
const isLoadingStep = ref(false)
const errors = ref({
	message: "",
})

const tiktokInput = useForm({
	tiktok_shop_id: "",
	tiktok_shop_chiper: "",
})

onMounted(async () => {
	try {
		const response = await axios.get(
			route("retina.json.dropshipping.customer_sales_channel.tiktok_user.show", {
				tiktokUser: tiktokUserId.value,
			})
		)

		tiktokShopData.value = response.data?.data?.authorized_shop
	} catch (error) {
		console.log(error)
		notify({
			type: "error",
			text: trans("Failed to save Tiktok user data"),
		})
	}
})

const submitForm = async () => {
	isLoadingStep.value = true

	if(!tiktokInput.tiktok_shop_id && !tiktokInput.tiktok_shop_chiper) {
		errors.value = {
			message: trans('Please select atleast one shop.')
		}

		isLoadingStep.value = false
		return;
	}

	try {
		const { data } = await axios.patch(
			route("retina.models.dropshipping.tiktok.update", {
				tiktokUser: tiktokUserId.value,
			}),
			tiktokInput.data()
		)
		goNext()
		isLoadingStep.value = false
	} catch (err: any) {
		isLoadingStep.value = false
		errors.value = err.response?.data?.errors
	}
}
</script>

<template>
	<form @submit.prevent="submitForm" class="flex flex-col gap-6">
		<div class="flex flex-col gap-2 w-full md:w-80">{{ trans('Select your shop:') }}</div>

		<hr class="w-full border-t" />
		<div v-for="tiktok in tiktokShopData">
			<label class="flex items-center gap-2">
				<input
					type="radio"
					name="tiktok_shop"
					:value="tiktok.id"
					@change="() => {
						errors.message = '';
						tiktokInput.tiktok_shop_id = tiktok.id;
						tiktokInput.tiktok_shop_chiper = tiktok.cipher;
					}"
					class="w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500" />
				<span class="text-sm font-medium text-gray-700">{{ tiktok.name }}</span>
			</label>
			<p v-if="errors" class="text-red-500">{{errors.message}}</p>
		</div>

		<div class="flex md:justify-end gap-4">
			<Button type="secondary" size="sm" @click="closeCreateTiktokModal">{{
				trans("Cancel")
			}}</Button>
			<Button size="sm" :loading="isLoadingStep" @click="submitForm">{{
				trans("Next")
			}}</Button>
		</div>
	</form>
</template>
