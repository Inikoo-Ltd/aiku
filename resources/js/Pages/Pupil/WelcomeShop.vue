<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import PupilRetina from '@/Layouts/PupilRetina.vue'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    shop: string
    shopUrl: string
}>()

// defineOptions({ layout: PupilRetina });


const openWebsite = (url: string) => {
    window.open(url, '_blank');
};
</script>

<template>
    <div class="relative isolate overflow-hidden px-6 py-8 text-center sm:rounded-3xl sm:px-12">
        <h2 class="mx-auto max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">
            {{ trans(`Welcome to ${props.shop}!`) }}
        </h2>

        <p class="mx-auto mt-6 max-w-xl text-lg leading-8 text-gray-500">
            {{ trans("You can manage your orders and products in our fulfilment website.") }}
        </p>

        <div class="flex justify-center">
            <img class="w-1/2 h-1/2 object-cover" src="https://i.ibb.co.com/CxTbCRf/undraw-factory-4d61.png" :alt="`${props.shop}`">
        </div>

        <div class="mt-10 flex flex-col items-center justify-center gap-x-6">
            <Button @click="openWebsite(props.shopUrl)" size="l" :label="`Open ${props.shop}`" />
        </div>
    </div>
</template>