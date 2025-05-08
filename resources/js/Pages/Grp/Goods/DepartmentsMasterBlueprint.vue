<script setup lang="ts">
import PageHeading from '@/Components/Headings/PageHeading.vue'
import SetVisibleList from '@/Components/Departement&Family/SetVisibleList.vue';
import { routeType } from '@/types/route';

const props = defineProps<{
    pageHead: {},
    departments: {},
    update_route: routeType
}>()


</script>


<template>
    <PageHeading :data="pageHead" />
    <div class="grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 mt-4 px-4 ">
        <SetVisibleList title="Departments List" :list_data="departments.data" :updateRoute="update_route" />
    </div>
</template>
