<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Mon, 17 Nov 2025 14:54:41 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { inject, ref, watch, onMounted, provide } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import PureInput from "@/Components/Pure/PureInput.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { trans } from "laravel-vue-i18n";
import { notify } from "@kyvg/vue3-notification";
import axios from "axios";
import PureInputWithAddOn from "@/Components/Pure/PureInputWithAddOn.vue"

const goNext = inject("goNext");
const closeCreateWooModal = inject("closeCreateWooModal");

const isLoadingStep = ref(false)
const errors = ref({
	name: {}
})

const wooCommerceInput = useForm({
    name: ""
});

const submitForm = async () => {
	isLoadingStep.value = true
	try {
		const {data} = await axios.post(route('retina.models.dropshipping.woocommerce.tmp_user.store'), wooCommerceInput.data());
		goNext();
		isLoadingStep.value = false
	} catch (err: any) {
		isLoadingStep.value = false;
		errors.value = err.response?.data?.errors;
	}
}
</script>

<template>
    <form @submit.prevent="submitForm" class="flex flex-col gap-6">
        <div class="flex flex-col gap-2 w-full md:w-80">
            <label class="font-semibold">{{ trans("Woocommerce Account Name") }}</label>
			<div class="flex flex-col gap-y-2">
				<PureInput v-model="wooCommerceInput.name" :placeholder="trans('Your store name')"></PureInput>
			</div>
        </div>

        <hr class="w-full border-t"/>

        <div class="flex md:justify-end gap-4">
            <Button type="secondary" size="sm" @click="closeCreateWooModal">{{ trans("Cancel") }}</Button>
            <Button size="sm" :loading="isLoadingStep" @click="submitForm">{{ trans("Next") }}</Button>
        </div>
    </form>
</template>
