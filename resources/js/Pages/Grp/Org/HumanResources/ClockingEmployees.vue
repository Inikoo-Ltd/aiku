<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 17 Sept 2022 00:32:56 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableClockings from "@/Components/Tables/Grp/Org/HumanResources/TableClockings.vue";
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { computed, defineAsyncComponent, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";

import { library } from '@fortawesome/fontawesome-svg-core';
import { faEnvelope, faIdCard, faPhone, faSignature, faUser, faBuilding, faBirthdayCake, faVenusMars, faHashtag, faHeading, faHospitalUser, faClock, faPaperclip, faTimes, faCameraRetro, faQrcode } from '@fal';

library.add(faEnvelope, faIdCard, faPhone, faSignature, faUser, faBuilding, faBirthdayCake, faVenusMars, faHashtag, faHeading, faHospitalUser, faClock, faPaperclip, faTimes, faCameraRetro, faQrcode);



const props = defineProps<{
    data: object
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string;
        navigation: Record<string, any>;
    }
    timesheets?: Record<string, any>;
    scan_qr_code?: Record<string, any>;
}>()


let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        timesheets: "",
        scan_qr_code: "",
    };
    return components[currentTab.value as keyof typeof components];

});

const currentData = computed(() => {
    const key = currentTab.value;
    return (props as Record<string, any>)[key];
});
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="currentData" :tab="currentTab"></component>
</template>
