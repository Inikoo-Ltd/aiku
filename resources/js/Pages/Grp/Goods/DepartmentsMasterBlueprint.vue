<script setup lang="ts">
import PageHeading from '@/Components/Headings/PageHeading.vue'
import SetVisibleList from '@/Components/Departement&Family/SetVisibleList.vue';

const props = defineProps<{
    pageHead: {},
    departments: {},
}>()


console.log(props)

</script>


<template>
    <PageHeading :data="pageHead">
        <!--  <template #button-save="{ action }">
            <Button type="save" @click="() => confirmSave(action.route)" />
        </template> -->
    </PageHeading>
    <div class="grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 mt-4 px-4 ">
        <SetVisibleList title="Departments List" :list_data="departments.data" />
    </div>

</template>
