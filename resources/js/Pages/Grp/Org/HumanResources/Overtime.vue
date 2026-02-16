<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
}>()
</script>

<template>
    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead" />

    <div class="mt-4 text-sm text-gray-500">
        Halaman Overtime. Gunakan sub navigation di atas untuk mengelola Overtime
        dan Overtime types.
    </div>
</template>
