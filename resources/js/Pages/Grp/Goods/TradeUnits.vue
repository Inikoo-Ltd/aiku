<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 24 Mar 2024 21:06:12 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableTradeUnits from "@/Components/Tables/Grp/Goods/TableTradeUnits.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeadingTypes } from "@/types/PageHeading";
import { computed, ref } from "vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { useTabChange } from "@/Composables/tab-change";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Dialog from "primevue/dialog";
import InputText from "primevue/inputtext";
import PickList from 'primevue/picklist';
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";

const props = defineProps<{
    title: string;
    pageHead: PageHeadingTypes;
    tabs: {
        current: string;
        navigation: {};
    };
    index?: {};
    sales?: {};
}>();

const currentTab = ref<string>(props?.tabs?.current ?? "index");
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);
const modalVisible = ref(false);
const valueTradeUnit = ref([])
const loading = ref(false)
const openModal = () => {
    modalVisible.value = true;
};

const closeModal = () => {
    modalVisible.value = false;
};

const component = computed(() => {
    const components: Record<string, any> = {
        index: TableTradeUnits,
        sales: TableTradeUnits,
    };

    return components[currentTab.value];
});

const addTradeUnit = () => {
    /*   router.patch(
          route("trade-units.update"), // adjust to your actual route name
           { data : valueTradeUnit.value},
          {
              onStart : () => {loading.value = true},
              onSuccess: () => {
                  modalVisible.value = false;
              },
              onError: (errors) => {
                  console.log(errors);
              },
              onFinish : () => {loading.value = false}
          }
      ); */
};




</script>

<template>

    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
        <template #button-add-trade-units="{ action }">
            <Button :icon="action.icon" :label="action.label" :style="action.style" @click="openModal" />
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />

    <component :is="component" :key="currentTab" :tab="currentTab" :data="props[currentTab]" />

    <!-- PrimeVue Dialog -->
    <Dialog v-model:visible="modalVisible" modal header="Add Trade Unit" :style="{ width: '400px' }">
        <div class="flex flex-col gap-3">
            <label class="text-sm font-medium">Trade Unit</label>
            <PureMultiselectInfiniteScroll v-model="valueTradeUnit"
                :fetch-route="{ name: 'grp.json.master_product_category.all_trade_units', parameters: {} }"
                :object="true" labelProp="name" value-prop="id" ref="_pureMultiselectInfiniteScroll" mode="tags" />

            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" type="gray" @click="closeModal" />
                <Button label="Save" @click="addTradeUnit" :loading="loading" />
            </div>
        </div>
    </Dialog>
</template>