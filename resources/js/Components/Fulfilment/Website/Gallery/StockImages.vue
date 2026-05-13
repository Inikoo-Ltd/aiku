<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref, onBeforeMount } from "vue"
import axios from 'axios'
import Image from "@common/Components/Image.vue"
import { notify } from '@kyvg/vue3-notification'
import { faSpinnerThird } from '@fad'

library.add(faSpinnerThird)

const props = defineProps<{
    imagesUploadedRoutes : routeType,
    stockImageRoutes : routeType
}>()

const stockImages = ref([])
const loading = ref(false)

const emits = defineEmits<{
    (e: 'pick', value: Object): void
    (e: 'onUpload', value: Object): void
}>()

const getStockImages = async () => {
    try {
        loading.value = true
        const response = await axios.get(route(props.stockImageRoutes.name,props.stockImageRoutes.parameters));
        loading.value = false
        stockImages.value = response.data.data
    } catch (error: any) {
        loading.value = false
        console.log('error', error);
        notify({
            title: 'Failed',
            text: 'cannot show stock images',
            type: 'error'
        })
    }
}

onBeforeMount(() => {
    getStockImages()
});


</script>

<template>
    <div  class="max-w-full p-4">
        <div v-if="!loading" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div v-for="image in stockImages" :key="image.id" class="overflow-hidden  duration-300" >
                <div class="border-2 border-gray-200 rounded-lg shadow-md hover:shadow-lg transition-shadow aspect-h-1 aspect-w-1 w-full bg-gray-200" @click="()=>emits('pick',image)">
                    <Image :src="image.thumbnail" class="w-full object-cover object-center group-hover:opacity-75" />
                </div>
                <span class="font-bold text-xs">{{ image.name }}</span>
            </div>
        </div>
        <div v-else class="flex justify-center">
            <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin' fixed-width  aria-hidden="true"/>
        </div>
    </div>
</template>