<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Fri, 16 Sept 2022 12:56:59 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';


import {library} from '@fortawesome/fontawesome-svg-core';
import { faEnvelope, faIdCard, faPhone, faSignature, faUser, faBuilding, faBirthdayCake, faVenusMars, faHashtag, faHeading, faHospitalUser, faClock, faPaperclip, faTimes, faCameraRetro} from '@fal';
import Tabs from "@/Components/Navigation/Tabs.vue";
import { computed, defineAsyncComponent, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ClockingMachineShowcase from "./ClockingMachinesShowcase.vue";
import TableClockings from "@/Components/Tables/Grp/Org/HumanResources/TableClockings.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";

import { capitalize } from "@/Composables/capitalize"
import {faCheckCircle} from '@fas';
import { PageHeadingTypes } from "@/types/PageHeading";
library.add(
    faIdCard,
    faUser,
    faCheckCircle,
    faSignature,
    faEnvelope,
    faPhone,
    faIdCard,
    faBirthdayCake,
    faVenusMars,
    faHashtag,
    faHeading,
    faHospitalUser,
    faClock,
    faPaperclip,
    faTimes,
    faCameraRetro,
    faBuilding
);


const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes,
    tabs: {
        current: string;
        navigation: object;
    }
    clockings?: object;
    history?: object;
    showcase?: object;
}>()

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        clockings: TableClockings,
        showcase: ClockingMachineShowcase,
        history: TableHistories,
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

