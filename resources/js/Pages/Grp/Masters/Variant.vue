<script setup lang="ts">
import { Head, useForm, } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"


const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
    master_products: Array<any>
    product_leader : any
}>()
console.log(props)

</script>


<template>
    <Head :title="capitalize(props.title)" />
    <PageHeading :data="props.pageHead" />
     
</template>

<style scoped>
:deep(.multiselect-wrapper) {
    justify-content: space-between;
}
</style>
