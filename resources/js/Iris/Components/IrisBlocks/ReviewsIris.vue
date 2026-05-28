<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { getStyles } from "@/Composables/styles";
import { faCube, faStar } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"


library.add(faCube, faStar)

const props = defineProps<{
    modelValue: any
    properties: {}
}>()


</script>

<template>
    <div class="w-full py-12 px-8 flex gap-x-10" :style="getStyles(properties)">
        <h2 class=" text-2xl font-bold">Reviews:</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-10">
            <div v-for="index in 3" class="">
                <h3 class="font-bold text-lg">Johnn Smith <span class="ml-6 font-normal">
                        <FontAwesomeIcon icon="fas fa-star"></FontAwesomeIcon>
                        <FontAwesomeIcon icon="fas fa-star"></FontAwesomeIcon>
                        <FontAwesomeIcon icon="fas fa-star"></FontAwesomeIcon>
                        <FontAwesomeIcon icon="fas fa-star"></FontAwesomeIcon>
                        <FontAwesomeIcon icon="fas fa-star"></FontAwesomeIcon> 41
                    </span></h3>
                <p class="mt-2 text-sm text-justify">Thank you so much for taking the time to give Ancient Wisdom
                    Wholesale a fantastic 5-star rating! We truly appreciate your support and are thrilled to hear that
                    you love our products.</p>
                <p class="mt-2 text-sm">From the moment you place your order to the prompt</p>
            </div>
        </div>
    </div>
</template>