<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 16 Jun 2023 11:39:33 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {library} from '@fortawesome/fontawesome-svg-core';
import { faUserHardHat, faUserAlien, faClock, faTerminal, faTachometerAltFast, faClipboardListCheck } from '@fal';
import { capitalize } from "@/Composables/capitalize"
import PageHeading from '@/Components/Headings/PageHeading.vue';
import { computed, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import Tabs from "@/Components/Navigation/Tabs.vue";
import JobPositionShowcase from "@/Components/HumanResources/JobPositionShowcase.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import TableEmployees from "@/Components/Tables/Grp/Org/HumanResources/TableEmployees.vue";
import TableJobPositionRoles from "@/Components/Tables/Grp/Org/HumanResources/TableJobPositionRoles.vue";
import TableGuests from "@/Components/Tables/Grp/SysAdmin/TableGuests.vue";
import type { Navigation } from "@/types/Tabs";
import { PageHeadingTypes } from "@/types/PageHeading";

library.add(
  faUserHardHat,
  faUserAlien,
  faClock,
  faTerminal,
  faTachometerAltFast,
  faClipboardListCheck
)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes,
    tabs: {
        current: string;
        navigation: Navigation;
    },
    showcase?: object,
    employees?: object,
    guests?: object,
    roles?: object,
    history?: object,
}>()

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const components = {
    showcase: JobPositionShowcase,
    employees: TableEmployees,
    guests: TableGuests,
    roles: TableJobPositionRoles,
    history: TableHistories
};

const component = computed(() => components[currentTab.value]);

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>
