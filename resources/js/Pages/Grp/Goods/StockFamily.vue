<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  xCreated: Sat, 22 Oct 2022 18:57:31 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->





<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import {useLocaleStore} from '@/Stores/locale';


import {library} from '@fortawesome/fontawesome-svg-core';
import {
    faInventory,
    faBox,
    faDollarSign,
    faPoop,
    faCubes,
    faCube,
    faCameraRetro
} from '@fal';
import { computed, defineAsyncComponent, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { faX } from "@fortawesome/free-solid-svg-icons";
import { capitalize } from "@/Composables/capitalize"
import TableStocks from "@/Components/Tables/Grp/Goods/TableStocks.vue";
import { Link } from "@inertiajs/vue3"
import Button from '@/Components/Elements/Buttons/Button.vue';

library.add(
    faInventory,
    faBox,
    faDollarSign,
    faPoop,
    faCubes,
    faCube,
    faCameraRetro,
    faX
);

const locale = useLocaleStore();

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string;
        navigation: object;
    }
    stocks: object
    createStockRoute: {
        name: string;
        parameters?: {
            stockFamily?: string;
        };
    };
}>()

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        stocks: TableStocks,
        details: ModelDetails,
        history: ModelChangelog,
    };
    return components[currentTab.value];

});

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Link v-if="currentTab === 'stocks'"  :href="route(createStockRoute.name, createStockRoute.parameters)" >
                <Button  label="Create" icon="create"/>
            </Link>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>
