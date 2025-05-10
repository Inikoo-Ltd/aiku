<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as TSPageHeading } from '@/types/PageHeading'



const props = defineProps<{
    title: string,
    pageHead: TSPageHeading



}>()



</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

</template>