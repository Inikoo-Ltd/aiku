<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Mon, 17 Nov 2025 16:26:48 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
    import { inject, ref } from "vue";
    import { faInfoCircle } from "@fal";
    import Select from "primevue/select";
    import { trans } from "laravel-vue-i18n";
    import { useForm } from "@inertiajs/vue3";
    import { library } from "@fortawesome/fontawesome-svg-core";
    import Button from "@/Components/Elements/Buttons/Button.vue";
    import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
    import axios from "axios";
    import { notify } from "@kyvg/vue3-notification";
    import PureInput from "@/Components/Pure/PureInput.vue";

    library.add(faInfoCircle);

    const goNext = inject("goNext");
    const closeCreateEbayModal = inject("closeCreateEbayModal");
    const ebayId = inject("ebayId");

    const isLoadingStep = ref(false)
    const errors = ref({})

    const sites = ref([
        { name: "United Kingdom", value: "EBAY_GB" },
        { name: "Spain", value: "EBAY_ES" },
        { name: "Germany", value: "EBAY_DE" },
    ]);

    const form = useForm({
        marketplace: ""
    });

    const submitForm = async () => {
        isLoadingStep.value = true
        try {
            const {data} = await axios.patch(route('retina.dropshipping.customer_sales_channels.ebay.update', {
                ebayUser: ebayId.value
            }), form.data());
            goNext();
            isLoadingStep.value = false
        } catch (err) {
            isLoadingStep.value = false;
            errors.value = err.response?.data?.errors;
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
        <span class="text-lg font-semibold">{{ trans("API Settings") }}</span>
        <span class="text-sm">{{ trans("This is where you need to add your API settings") }}</span>
    </div>
    <form @submit.prevent="submitForm" class="flex flex-col gap-6">
        <div class="flex flex-col gap-2 ">
            <label class="font-semibold">{{ trans("eBay Site") }}</label>
            <div class="flex items-center gap-2 w-full md:w-80">
                <Select v-model="form.marketplace" :options="sites" optionLabel="name" optionValue="value"
                        class="w-full"
                        @update:model-value="errors.marketplace = null "/>
                <FontAwesomeIcon v-tooltip="trans('Select listing duration')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
            </div>
            <p v-if="errors.marketplace" class="text-sm text-red-600 mt-1">{{ errors.marketplace?.[0] }}</p>
        </div>

        <hr class="w-full border-t" />

        <div class="flex md:justify-end gap-4">
            <Button type="secondary" size="sm" @click="closeCreateEbayModal">{{ trans("Cancel") }}</Button>
            <Button size="sm" :loading="isLoadingStep" @click="submitForm">{{ trans("Next") }}</Button>
        </div>
    </form>
</template>
