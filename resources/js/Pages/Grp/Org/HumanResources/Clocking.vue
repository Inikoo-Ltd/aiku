<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 08 Sept 2022 00:38:38 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {library} from '@fortawesome/fontawesome-svg-core';
import { faEnvelope, faIdCard, faPhone, faSignature, faUser, faBirthdayCake, faVenusMars, faHashtag, faHeading, faHospitalUser, faClock, faPaperclip, faTimes, faCameraRetro} from '@fal';
import {faCheckCircle} from '@fas';
import { router } from '@inertiajs/vue3'
import { capitalize } from "@/Composables/capitalize"

import PageHeading from '@/Components/Headings/PageHeading.vue';

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
    faCameraRetro
)
import { computed, defineAsyncComponent, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { PageHeadingTypes } from "@/types/PageHeading";


const createClocking = () =>{
    router.post(route('grp.org.hr.clockings.store',props['clocking'].data.id), {})
}

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes,
    tabs: {
        current: string;
        navigation: object;
    },
    history: object

}>()

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        details: ModelDetails,
        history: ModelChangelog,
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

