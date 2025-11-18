<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Mon, 17 Nov 2025 16:26:48 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
    import { inject } from "vue";
    import { faInfoCircle } from "@fal";
    import Select from "primevue/select";
    import { trans } from "laravel-vue-i18n";
    import { useForm } from "@inertiajs/vue3";
    import { library } from "@fortawesome/fontawesome-svg-core";
    import Button from "@/Components/Elements/Buttons/Button.vue";
    import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";

    library.add(faInfoCircle);

    const goNext = inject("goNext");
    const closeCreateEbayModal = inject("closeCreateEbayModal");

    const sites = [
        { name: "United States", value: "us" },
        { name: "United Kingdom", value: "uk" },
    ];

    const form = useForm({
        site: ""
    });

    const submitForm = async () => {
        console.log(form.data());
        goNext();
    }
</script>

<template>
    <div class="flex flex-col gap-2">
        <span class="text-lg font-semibold">{{ trans("API Settings") }}</span>
        <span class="text-sm">{{ trans("This is where you need to add your API settings") }}</span>
    </div>
    <form @submit.prevent="submitForm" class="flex flex-col gap-6">
        <div class="flex flex-col gap-2 w-64">
            <label class="font-semibold">{{ trans("eBay Site") }}</label>
            <div class="flex items-center gap-2">
                <Select v-model="form.site" :options="sites" optionLabel="name" optionValue="value" class="w-full" />
                <FontAwesomeIcon v-tooltip="trans('Select eBay site name')" icon="fal fa-info-circle" class="size-5 text-black" />
            </div>
        </div>

        <hr class="w-full border-t" />

        <div class="flex justify-end gap-4">
            <Button type="secondary" size="sm" @click="closeCreateEbayModal">{{ trans("Cancel") }}</Button>
            <Button size="sm" @click="submitForm">{{ trans("Next") }}</Button>
        </div>
    </form>
</template>
