<script setup lang='ts'>
import Button from '@/Components/Elements/Buttons/Button.vue'

const props = defineProps<{
    familyBanner: {
        description: string
        image: string
    }
}>()
</script>

<template>
    <div class="relative overflow-hidden rounded-lg lg:h-96">
        <div class="absolute inset-0">
            <img :src="props.familyBanner.image" alt="" class="h-full w-full object-cover object-center" />
        </div>

        <div aria-hidden="true" class="relative h-96 w-full lg:hidden" />
        <div aria-hidden="true" class="relative h-32 w-full lg:hidden" />

        <div class="absolute inset-x-0 bottom-0 rounded-bl-lg rounded-br-lg bg-white/75 p-6 backdrop-blur backdrop-filter sm:flex sm:items-center sm:justify-between lg:inset-x-auto lg:inset-y-0 lg:w-96 lg:flex-col lg:items-start lg:rounded-br-none lg:rounded-tl-lg">
            <div v-html="props.familyBanner.description" class="text-gray-600 pr-3 overflow-y-auto mb-4" />

            <Button type="indigo" :key="1" label="View the collection" size="xl" full />
        </div>
    </div>
</template>