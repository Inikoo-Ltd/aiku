<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 16 Jun 2023 11:39:33 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {library} from '@fortawesome/fontawesome-svg-core';
import { faUserHardHat, faClock,faTerminal} from '@fal';
import { capitalize } from "@/Composables/capitalize"
import PageHeading from '@/Components/Headings/PageHeading.vue';
import { computed, defineAsyncComponent, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import TableEmployees from "@/Components/Tables/Grp/Org/HumanResources/TableEmployees.vue";
import TableGuests from "@/Components/Tables/Grp/SysAdmin/TableGuests.vue";
import type { Navigation } from "@/types/Tabs";
import { PageHeadingTypes } from "@/types/PageHeading";

library.add(
  faUserHardHat,
  faClock,
  faTerminal
)

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes,
    tabs: {
        current: string;
        navigation: Navigation;
    },
    history?: object,
    employees?: object,
    guests?: object,

}>()

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        details: ModelDetails,
        history: TableHistories,
        employees: TableEmployees,
        guests: TableGuests
    };
    return components[currentTab.value];

});

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>

