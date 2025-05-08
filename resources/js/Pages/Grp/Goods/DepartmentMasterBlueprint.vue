<script setup lang="ts">
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { routeType } from '@/types/route'
import SetVisibleList from '@/Components/Departement&Family/SetVisibleList.vue';
import EditPreviewBluprintData from "@/Components/Departement&Family/EditPreviewBluprintData.vue";

const props = defineProps<{
    pageHead: {},
    department: {},
    update_route: routeType
    upload_image_route: routeType
    families: { data: Array<{ id: number, name: string }> },
    web_block_types: Object
    web_block_types_families: {
        data: Array
    }
}>()
</script>


<template>
    <PageHeading :data="pageHead"/>
    <div class="grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 px-4 pb-8 m-5 ">
        <EditPreviewBluprintData title="Department" :data="department.data" :update_route="update_route"
            :web_block_types="web_block_types" :upload_image_route="upload_image_route">
        </EditPreviewBluprintData>
        <SetVisibleList title="Departments List" :list_data="families.data" :updateRoute="update_route" />
    </div>
</template>
