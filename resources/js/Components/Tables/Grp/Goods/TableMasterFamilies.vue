<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 May 2024 23:30:18 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { RouteParams } from "@/types/route-params";
import { MasterFamily } from "@/types/master-family";
import { trans } from "laravel-vue-i18n";
import { ref } from "vue";
import { faFolderTree } from "@fal";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Dialog from 'primevue/dialog';
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue";
import axios from "axios";

defineProps<{
    data: object,
    tab?: string
}>();

const selectedFamily = ref([])
const _table = ref(null)
const visibleDialog = ref(false);
const selectedParentId = ref(null)
const loadingChangeParent = ref(false)
const tableKey = ref(1)

function familyRoute(masterFamily: MasterFamily) {
    console.log(route().current());
    if (route().current() == "grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.index") {
        return route(
            "grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.show",
            { ...route().params, masterFamily: masterFamily.slug });

    } else if (route().current() == "grp.masters.master_shops.show.master_departments.show.master_families.index") {
        return route(
            "grp.masters.master_shops.show.master_departments.show.master_families.show",
            {
                masterShop: (route().params as RouteParams).masterShop,
                masterDepartment: (route().params as RouteParams).masterDepartment,
                masterFamily: masterFamily.slug
            });
    } else if (route().current() == "grp.masters.master_shops.show.master_families.index") {
        return route(
            "grp.masters.master_shops.show.master_families.show",
            { ...route().params, masterFamily: masterFamily.slug });
    } else if (route().current() == "grp.masters.master_shops.show.master_sub_departments.master_families.index") {
        return route(
            "grp.masters.master_shops.show.master_sub_departments.master_families.show",
            { ...route().params, masterFamily: masterFamily.slug });
    } else {
        return route(
            "grp.masters.master_families.show",
            { masterFamily: masterFamily.slug });
    }
}

function masterDepartmentRoute(masterFamily: MasterFamily) {
    if (route().current() == "grp.masters.master_families.index") {
        return route(
            "grp.masters.master_departments.show",
            { masterDepartment: masterFamily.master_department_slug });
    } else {
        return route(
            "grp.masters.master_shops.show.master_departments.show",
            {
                masterShop: (route().params as RouteParams).masterShop,
                masterDepartment: masterFamily.master_department_slug
            });
    }
}

function masterShopRoute(masterFamily: MasterFamily) {
    return route("grp.masters.master_shops.show",
        {
            masterShop: masterFamily.master_shop_slug
        }
    );
}

function ProductRoute(masterFamily: MasterDepartment) {
    if (route().current() == 'grp.masters.master_shops.show.master_families.index') {
        return route('grp.masters.master_shops.show.master_families.master_products.index',
            {
                masterFamily: masterFamily.slug,
                masterShop: (route().params as RouteParams).masterShop
            }
        )
    }

    return route('grp.masters.master_shops.show.master_families.master_products.index',
        {
            masterShop: (route().params as RouteParams).masterShop,
            masterFamily: masterFamily.slug
        }
    )
}

const onChangeCheked = (checked: boolean, item: {}) => {
    if (!selectedFamily.value) return

    if (checked) {
        if (!selectedFamily.value.includes(item.id)) {
            selectedFamily.value.push(item.id)
        }
    } else {
        selectedFamily.value = selectedFamily.value.filter(id => id != item.id)
    }
}

const onSaveChangeParent = async () => {
    if (!selectedParentId.value) {
        alert("Please choose a parent first.");
        return;
    }

    try {
        loadingChangeParent.value = true;

        const requests = selectedFamily.value.map((familyId: number) => {
            return axios.patch(
                route("grp.models.master_product_category.update", {
                    masterProductCategory: familyId,
                }),
                {
                    master_department_or_master_sub_department_id: selectedParentId.value,
                }
            );
        });

        await Promise.all(requests);

        visibleDialog.value = false;
        selectedFamily.value = [];
        selectedParentId.value = null;
        tableKey.value++

        router.reload()

    } catch (error) {
        console.error("Failed updating parent", error);
    } finally {
        loadingChangeParent.value = false;
    }
};


const onCheckedAll = (handle: { allChecked: boolean, data: Array<{id: number}> }) => {
    if (handle.allChecked) {
        // Set selectedFamily with all data.id values
        selectedFamily.value = handle.data.map(item => item.id);
    } else {
        // Clear selectedFamily
        selectedFamily.value = [];
    }
}


</script>

<template>
    <Table @onCheckedAll="onCheckedAll" :resource="data" :name="tab" class="mt-5" :isCheckBox="true" :key="tableKey"
        @onChecked="(item) => onChangeCheked(true, item)" @onUnchecked="(item) => onChangeCheked(false, item)"
        checkboxKey='id' :isChecked="(item) => selectedFamily.includes(item.id)" ref="_table">
        <template #add-on-button>
            <div v-if="selectedFamily.length != 0">
                <Button :icon="faFolderTree" label="Assign to another" @click="visibleDialog = true" :size="'xs'"
                    type="secondary" />
            </div>
        </template>
        <template #cell(master_shop_code)="{ item: department }">
            <Link v-tooltip="department.master_shop_name" :href="masterShopRoute(department) as string"
                class="secondaryLink">
            {{ department["master_shop_code"] }}
            </Link>
        </template>

        <template #cell(master_department_code)="{ item: department }">
            <Link v-if="department.master_department_slug" v-tooltip="department.master_department_name"
                :href="masterDepartmentRoute(department) as string" class="secondaryLink">
            {{ department["master_department_code"] }}
            </Link>
            <span v-else class="opacity-70  text-red-500">
                {{ trans("No department") }}
            </span>
        </template>

        <template #cell(code)="{ item: family }">
            <Link :href="familyRoute(family)" class="primaryLink">
            {{ family["code"] }}
            </Link>
        </template>

        <template #cell(products)="{ item: family }">
            <Link :href="ProductRoute(family)" class="primaryLink">
            {{ family["products"] }}
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
