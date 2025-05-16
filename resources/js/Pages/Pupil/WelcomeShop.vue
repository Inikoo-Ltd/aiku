<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { trans } from 'laravel-vue-i18n'
import PupilFulfilmentDashboard from "@/Pages/Pupil/Dashboard/PupilFulfilmentDashboard.vue";

const props = defineProps<{
    data: {}
    shop: string
    shopUrl: string
}>()

console.log('data', props.data)

const openWebsite = (url: string) => {
    window.open(url, '_blank');
};
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
            <PupilFulfilmentDashboard :data :shop />
        </div>
        <div class="relative isolate overflow-hidden px-6 py-8 text-center sm:rounded-3xl sm:px-12">
            <p class="mx-auto mt-6 max-w-xl text-lg leading-8 text-gray-500">
                {{ trans("You can manage your orders and products in our fulfilment website.") }}
            </p>

            <div class="flex justify-center">
                <img class="w-full h-1/2 object-cover" src="https://i.ibb.co.com/CxTbCRf/undraw-factory-4d61.png" :alt="`${props.shop}`">
            </div>

            <div class="mt-10 flex flex-col items-center justify-center gap-x-6">
                <Button @click="openWebsite(props.shopUrl)" size="l" :label="`Open ${props.shop}`" />
            </div>
        </div>
    </div>
</template>
