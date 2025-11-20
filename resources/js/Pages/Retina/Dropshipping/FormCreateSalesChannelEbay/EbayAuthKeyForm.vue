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

library.add(faInfoCircle);

const goNext = inject("goNext");
const closeCreateEbayModal = inject("closeCreateEbayModal");
const ebayId = inject("ebayId");

const isLoadingStep = ref(false)
const {props} = defineProps({props: {}});
// Section: ebay
const onSubmitEbay = async () => {
    isLoadingStep.value = true;

    try {
        const response = await axios.post(
            route(props.type_ebay.connectRoute.name, props.type_ebay.connectRoute.parameters));
        isLoadingStep.value = false;
        window.open(response.data, '_blank')
    } catch (err) {
        isLoadingStep.value = false;
        notify({
            title: trans("Something went wrong"),
            text: err.response?.data?.message,
            type: "error"
        });
    }
};

const form = useForm({});

const submitForm = async () => {
    isLoadingStep.value = true
    try {
        const {data} = await axios.get(route('retina.dropshipping.customer_sales_channels.ebay.auth_check', {
            ebayUser: ebayId.value
        }));
        goNext();
        isLoadingStep.value = false
    } catch (err) {
        isLoadingStep.value = false;
        notify({
            title: trans("Something went wrong"),
            text: err.response?.data?.message,
            type: "error"
        });
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
            <Button size="sm" :loading="isLoadingStep" @click="onSubmitEbay">{{ trans("Auth Store") }}</Button>
            <FontAwesomeIcon
                v-tooltip="trans('Requests a token from eBay so we can sync without you entering your account details each time')"
                icon="fal fa-info-circle" class="hidden md:block size-5 text-black"/>
        </div>

        <hr class="w-full border-t"/>

        <div class="flex md:justify-end gap-4">
            <Button type="secondary" size="sm" @click="closeCreateEbayModal">{{ trans("Cancel") }}</Button>
            <Button size="sm" @click="submitForm">{{ trans("Next") }}</Button>
        </div>
    </form>
</template>
