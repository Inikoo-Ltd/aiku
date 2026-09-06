<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Mon, 17 Nov 2025 16:57:42 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { inject, ref } from "vue";
import { faInfoCircle } from "@fal";
import { trans } from "laravel-vue-i18n";
import { useForm } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { notify } from "@kyvg/vue3-notification";
import axios from "axios";
import PureInputWithAddOn from "@/Components/Pure/PureInputWithAddOn.vue"

library.add(faInfoCircle);

const goNext = inject("goNext");
const closeCreateWooModal = inject("closeCreateWooModal");

const isLoadingStep = ref(false)
const errors = ref({})

const {props} = defineProps({props: {}});
// Section: ebay

const wooCommerceInput = useForm({
	url: ""
});

const onSubmitWoocommerce = async () => {
	try {
		isLoadingStep.value = true;
		errors.value = {};

		const response = await axios.post(
			route(props.type_woocommerce?.connectRoute?.name, props.type_woocommerce.connectRoute.parameters),
			wooCommerceInput.data());

		isLoadingStep.value = false;
		const authWindow = window.open(response.data, '_blank');
		if (!authWindow) {
			window.location.assign(response.data);
		}
	} catch (err: any) {
		isLoadingStep.value = false;
		errors.value = err.response?.data?.errors;
	}
}

const manualKeys = useForm({
	consumer_key: "",
	consumer_secret: ""
});
const showManualKeys = ref(false)

const onSubmitManualKeys = async () => {
	try {
		isLoadingStep.value = true;
		errors.value = {};
		await axios.post(route('retina.models.dropshipping.woocommerce.tmp_user.store'), {
			...manualKeys.data(),
			...(wooCommerceInput.url ? { url: wooCommerceInput.url.trim().replace(/\/+$/, '') } : {})
		});
		await submitForm();
	} catch (err: any) {
		isLoadingStep.value = false;
		errors.value = err.response?.data?.errors;
	}
}

const submitForm = async () => {
	isLoadingStep.value = true
	errors.value = {};

	try {
		const {data} = await axios.get(route('retina.models.dropshipping.woocommerce.tmp_user_keys_check'));
		goNext();
		isLoadingStep.value = false
	} catch (err: any) {
		isLoadingStep.value = false;
		errors.value = err.response?.data?.errors;
	}
}

</script>

<template>
    <div class="flex flex-col gap-2">
        <span class="text-lg font-semibold">{{ trans("Authentication Settings") }}</span>
        <span class="text-sm">{{ trans("This is where you need to auth your store to our system.") }}</span>
    </div>
    <form @submit.prevent="submitForm" class="flex flex-col gap-6">
        <div class="flex items-center gap-2 w-full md:w-80">
			<PureInputWithAddOn v-model="wooCommerceInput.url" :leftAddOn="{
                    icon: 'fal fa-globe'
                }" :placeholder="'e.g https://storeurlexample.com'"
								@keydown.enter="() => onSubmitWoocommerce()"/>
            <Button size="sm" :loading="isLoadingStep" @click="onSubmitWoocommerce">{{ trans("Auth Store") }}</Button>
            <FontAwesomeIcon
                v-tooltip="trans('Requests a token from Woocommerce so we can sync without you entering your account details each time')"
                icon="fal fa-info-circle" class="hidden md:block size-5 text-black"/>
        </div>
        <p v-if="errors?.url" class="text-sm text-red-600 mt-1">{{ errors?.url?.[0] }}</p>

        <button type="button" class="text-sm text-left underline text-gray-600 w-fit" @click="showManualKeys = !showManualKeys">
            {{ trans("My store could not send the keys, let me paste them") }}
        </button>
        <div v-if="showManualKeys" class="flex flex-col gap-2 w-full md:w-96">
            <p class="text-sm text-gray-600">{{ trans("In WooCommerce go to Settings, Advanced, REST API and create a key with Read/Write permissions, then paste it here.") }}</p>
            <PureInputWithAddOn v-model="manualKeys.consumer_key" :leftAddOn="{ icon: 'fal fa-key' }" :placeholder="'ck_...'" />
            <PureInputWithAddOn v-model="manualKeys.consumer_secret" :leftAddOn="{ icon: 'fal fa-lock' }" :placeholder="'cs_...'" />
            <Button size="sm" :loading="isLoadingStep" :disabled="!manualKeys.consumer_key || !manualKeys.consumer_secret" @click="onSubmitManualKeys">{{ trans("Use these keys") }}</Button>
        </div>

        <hr class="w-full border-t"/>

        <div class="flex md:justify-end gap-4">
            <Button type="secondary" size="sm" @click="closeCreateWooModal">{{ trans("Cancel") }}</Button>
            <Button size="sm" :loading="isLoadingStep" @click="submitForm">{{ trans("Next") }}</Button>
        </div>
    </form>
</template>
