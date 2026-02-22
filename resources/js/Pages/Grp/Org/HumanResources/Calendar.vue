<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'

const props = defineProps<{
    data: object
    title: string
    pageHead: PageHeadingTypes
}>()
</script>

<template layout="Grp">
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
</template>
