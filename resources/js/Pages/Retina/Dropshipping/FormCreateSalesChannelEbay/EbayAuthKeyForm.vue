<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Mon, 17 Nov 2025 16:57:42 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
    import { inject } from "vue";
    import { faInfoCircle } from "@fal";
    import { trans } from "laravel-vue-i18n";
    import { useForm } from "@inertiajs/vue3";
    import { library } from "@fortawesome/fontawesome-svg-core";
    import Button from "@/Components/Elements/Buttons/Button.vue";
    import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";

    library.add(faInfoCircle);

    const goNext = inject("goNext");
    const closeCreateEbayModal = inject("closeCreateEbayModal");

    const form = useForm({});

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
        <div class="flex items-center gap-2 w-full md:w-80">
            <Button size="sm" @click="closeCreateEbayModal">{{ trans("AuthKey") }}</Button>
            <FontAwesomeIcon v-tooltip="trans('Requests a token from eBay so we can sync without you entering your account details each time')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
        </div>

        <hr class="w-full border-t" />

        <div class="flex md:justify-end gap-4">
            <Button type="secondary" size="sm" @click="closeCreateEbayModal">{{ trans("Cancel") }}</Button>
            <Button size="sm" @click="submitForm">{{ trans("Next") }}</Button>
        </div>
    </form>
</template>
