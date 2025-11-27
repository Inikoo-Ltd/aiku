<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 May 2024 23:30:18 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import {RouteParams} from "@/types/route-params";
import {Link, router} from '@inertiajs/vue3'
import { ref } from 'vue';
import { faFolderTree } from "@fal";
import Button from "@/Components/Elements/Buttons/Button.vue";
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue";
import Dialog from 'primevue/dialog';
import axios from "axios";

defineProps<{
    data: object,
    tab?: string
}>()


const selectedSubDepartment = ref([])
const tableKey = ref(1)
const visibleDialog = ref(false);
const selectedParentId = ref(null)
const loadingChangeParent = ref(false)

function masterSubDepartmentRoute(subDepartment: {}) {
    switch (route().current()) {
        case 'grp.masters.master_shops.show.master_sub_departments.index':
            return route(
                'grp.masters.master_shops.show.master_sub_departments.show',
                [
                    (route().params as RouteParams).masterShop,
                    subDepartment.slug
                ]);
        case 'grp.masters.master_departments.show.master_sub_departments.index':
            return route(
                'grp.masters.master_departments.show.master_sub_departments.show',
                [
                    (route().params as RouteParams).masterDepartment,
                    subDepartment.slug
                ]);
        case 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.index':
            return route(
                'grp.masters.master_shops.show.master_departments.show.master_sub_departments.show',
                [
                    (route().params as RouteParams).masterShop,
                    (route().params as RouteParams).masterDepartment,
                    subDepartment.slug
                ]);
        default:
            return route(
                'grp.masters.master_shops.show.master_sub_departments.show',
                [
                    (route().params as RouteParams).masterShop,
                    subDepartment.slug
                ]
            );
    }
}

const onSaveChangeParent = async () => {
    if (!selectedParentId.value) {
        alert("Please choose a parent first.");
        return;
    }

    try {
        loadingChangeParent.value = true;

        const requests = selectedSubDepartment.value.map((subDepartmentId: number) => {
            return axios.patch(
                route("grp.models.master_product_category.update", {
                    masterProductCategory: subDepartmentId,
                }),
                {
                    master_department_or_master_sub_department_id: selectedParentId.value,
                }
            );
        });

        await Promise.all(requests);

        visibleDialog.value = false;
        selectedSubDepartment.value = [];
        selectedParentId.value = null;
        tableKey.value++

        router.reload()

    } catch (error) {
        console.error("Failed updating parent", error);
    } finally {
        loadingChangeParent.value = false;
    }
};

const onChangeCheked = (checked: boolean, item: { id: number }) => {
    if (!selectedSubDepartment.value) return

    if (checked) {
        if (!selectedSubDepartment.value.includes(item.id)) {
            selectedSubDepartment.value.push(item.id)
        }
    } else {
        selectedSubDepartment.value = selectedSubDepartment.value.filter(id => id != item.id)
    }
}

const onCheckedAll = (handle: { allChecked: boolean, data: Array<{id: number}> }) => {
    if (handle.allChecked) {
        // Set selectedSubDepartment with all data.id values
        selectedSubDepartment.value = handle.data.map(item => item.id);
    } else {
        // Clear selectedSubDepartment
        selectedSubDepartment.value = [];
    }
}

</script>

<template>
    <Table @onCheckedAll="onCheckedAll" :resource="data" :name="tab" class="mt-5" :isCheckBox="true" :key="tableKey"
    @onChecked="(item) => onChangeCheked(true, item)" @onUnchecked="(item) => onChangeCheked(false, item)"
        checkboxKey='id' :isChecked="(item) => selectedSubDepartment.includes(item.id)" ref="_table">
           <template #add-on-button>
            <div v-if="selectedSubDepartment.length != 0">
                <Button :icon="faFolderTree" label="Assign to another" @click="visibleDialog = true" :size="'xs'"
                    type="secondary" />
            </div>
        </template>

        <template #cell(code)="{ item: subDepartment }">
            <Link v-if="masterSubDepartmentRoute(subDepartment)" :href="masterSubDepartmentRoute(subDepartment)"
                  class="primaryLink">
                {{ subDepartment["code"] }}
            </Link>
        </template>
    </Table>

     <Dialog v-model:visible="visibleDialog" modal header="Edit Parent" :style="{ width: '25rem' }"
        :content-style="{ overflow: 'visible', paddingLeft: '20px', paddingRight: '20px', }">
        <div>
            <PureMultiselectInfiniteScroll :fetchRoute="{
                name: 'grp.json.master_shop.master_departments_and_sub_departments',
                parameters: {
                    masterShop: (route().params as RouteParams).masterShop
                }
            }" :required="true" valueProp="id" type_label="department-and-sub-department" labelProp="code"
            v-model="selectedParentId" />
        </div>

        <div class="flex justify-end gap-2 mt-4">
            <Button type="tertiary" label="Cancel" @click="visibleDialog = false" />
            <Button type="save" label="Save" @click="onSaveChangeParent"  :loading="loadingChangeParent"/>
        </div>
    </Dialog>
</template>
