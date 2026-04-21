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
import Image from "@/Components/Image.vue";
import { faTrash } from "@fas";

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

const removeTradeUnit = (id: number) => {
    valueTradeUnit.value = valueTradeUnit.value.filter(
        (item: any) => item.id !== id
    );
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
    <Dialog v-model:visible="modalVisible" modal header="Add Trade Unit" :style="{ width: '400px' }"
        :contentStyle="{ maxHeight: '500px', overflowY: 'visible' }">
        <div class="flex flex-col gap-3">
            <label class="text-sm font-medium">Trade Unit</label>

            <PureMultiselectInfiniteScroll v-model="valueTradeUnit"
                :fetch-route="{ name: 'grp.json.master_product_category.all_trade_units', parameters: {} }"
                :object="true" labelProp="name" value-prop="id" ref="_pureMultiselectInfiniteScroll" mode="multiple"
                :showClear="false" :required="true">
            </PureMultiselectInfiniteScroll>

            <div v-if="valueTradeUnit.length" class="max-h-[200px] overflow-y-auto flex flex-col gap-1 border rounded p-1">
                <div v-for="item in valueTradeUnit" :key="item.id"
                    class="flex items-center justify-between py-1 px-2 border rounded">
                    <!-- Left: Image + Info -->
                    <div class="flex items-center gap-2">
                        <Image :src="item?.image?.thumbnail"
                            class="w-10 h-10 object-cover rounded border border-gray-200" />

                        <div class="flex flex-col leading-tight">
                            <div class="text-sm font-semibold text-gray-800">
                                {{ item.code }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ item.name }}
                            </div>
                        </div>
                    </div>

                    <!-- Right: Delete -->
                    <Button :icon="faTrash" type="negative" size="xs" @click="removeTradeUnit(item.id)" />
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" type="gray" @click="closeModal" />
                <Button label="Save" @click="addTradeUnit" :loading="loading" />
            </div>
        </div>
    </Dialog>
</template>