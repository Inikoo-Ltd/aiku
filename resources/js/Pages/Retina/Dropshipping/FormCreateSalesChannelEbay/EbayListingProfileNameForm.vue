<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Tue, 18 Nov 2025 08:51:34 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
    import { inject } from "vue";
    import { faInfoCircle } from "@fal";
    import { trans } from "laravel-vue-i18n";
    import { useForm } from "@inertiajs/vue3";
    import PureInput from "@/Components/Pure/PureInput.vue";
    import { library } from "@fortawesome/fontawesome-svg-core";
    import Button from "@/Components/Elements/Buttons/Button.vue";

    library.add(faInfoCircle);

    const goNext = inject("goNext");
    const closeCreateEbayModal = inject("closeCreateEbayModal");

    const form = useForm({
        profileName: ""
    });

    const submitForm = async () => {
        console.log(form.data());
        goNext();
    }
</script>

<template>
    <div class="flex flex-col gap-2">
        <span class="text-lg font-semibold">{{ trans("Add eBay listing profile") }}</span>
        <hr class="w-full border-t" />
    </div>
    <form @submit.prevent="submitForm" class="flex flex-col gap-6">
        <div class="flex flex-col gap-2 w-full md:w-80">
            <label class="font-semibold">{{ trans("Profile name") }}</label>
            <PureInput
                type="text"
                v-model="form.profileName"
                @update:model-value="form.errors.profileName = null"
            />
        </div>

        <hr class="w-full border-t" />

        <div class="flex md:justify-end gap-4">
            <Button type="secondary" size="sm" @click="closeCreateEbayModal">{{ trans("Cancel") }}</Button>
            <Button size="sm" @click="submitForm">{{ trans("Next") }}</Button>
        </div>
    </form>
</template>
