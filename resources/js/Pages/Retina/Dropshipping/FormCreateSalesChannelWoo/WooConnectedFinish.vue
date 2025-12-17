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

const closeCreateWooModal = inject("closeCreateWooModal");

const {props} = defineProps({props: {}});
// Section: ebay

</script>

<template>
    <div class="flex flex-col gap-2">
        <span class="text-lg font-semibold">{{ trans("Connected!") }}</span>
        <span class="text-sm">{{ trans("This is the last step.") }}</span>
    </div>
    <form class="flex flex-col gap-6">
        <div class="flex items-center gap-2 w-full md:w-80">
			{{ trans('Your store already connected, now you can upload portfolio and receive orders.') }}
        </div>

        <hr class="w-full border-t"/>

        <div class="flex md:justify-end gap-4">
            <Button type="success" size="sm" @click="closeCreateWooModal">{{ trans("OK") }}</Button>
        </div>
    </form>
</template>
