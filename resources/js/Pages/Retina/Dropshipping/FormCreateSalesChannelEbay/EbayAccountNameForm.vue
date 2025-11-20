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

const goNext = inject("goNext");
const ebayId = inject("ebayId");
const closeCreateEbayModal = inject("closeCreateEbayModal");

const isLoadingStep = ref(false)

const form = useForm({
    name: ""
});

const submitForm = async () => {
    isLoadingStep.value = true
    try {
        const {data} = await axios.post(route('retina.dropshipping.customer_sales_channels.ebay.store'), form.data());
        ebayId.value = data.id;
        goNext();
        isLoadingStep.value = false
    } catch (err) {
        isLoadingStep.value = false;
        notify({
            title: trans("Something went wrong"),
            text: err.message,
            type: "error"
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm" class="flex flex-col gap-6">
        <div class="flex flex-col gap-2 w-full md:w-80">
            <label class="font-semibold">{{ trans("ebay Account Name") }}</label>
            <PureInput
                type="text"
                v-model="form.name"
                @update:model-value="form.errors.name = null"
            />
        </div>

        <hr class="w-full border-t"/>

        <div class="flex md:justify-end gap-4">
            <Button type="secondary" size="sm" @click="closeCreateEbayModal">{{ trans("Cancel") }}</Button>
            <Button size="sm" :loading="isLoadingStep" @click="submitForm">{{ trans("Next") }}</Button>
        </div>
    </form>
</template>
