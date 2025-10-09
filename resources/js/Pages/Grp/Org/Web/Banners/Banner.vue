<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 02 Oct 2023 03:20:36 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import {library} from '@fortawesome/fontawesome-svg-core';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import {computed, ref} from "vue";
import {useTabChange} from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import {capitalize} from "@/Composables/capitalize"
//import TableCustomerHistories from "@/Components/Tables/TableCustomerHistories.vue";
import {faSign, faGlobe, faPencil, faSeedling, faPaste, faLayerGroup} from '@fal'
import { faRocketLaunch } from '@far'
import TableSnapshots from '@/Components/Tables/TableSnapshots.vue';
import BannerShowcase from "@/Pages/Grp/Org/Web/Banners/BannerShowcase.vue";
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import { trans } from 'laravel-vue-i18n'

library.add(faSign, faRocketLaunch, faGlobe, faPencil, faSeedling, faPaste, faLayerGroup)

const props = defineProps<{    
    title: string,
    pageHead: object,
    tabs: {
        current: string;
        navigation: object;
    }
    changelog?: object,
    showcase?: object,
    snapshots?: object,
}>()

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        showcase: BannerShowcase,
        snapshots: TableSnapshots,
        details: ModelDetails,
        // changelog: TableCustomerHistories,
    };
    return components[currentTab.value];

});


</script>


<template layout="CustomerApp">
    <Head :title="capitalize(title)"/>
    <!-- <pre>{{ showcase }}</pre> -->
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :tab="currentTab" :data="props[currentTab]">
        <template #banner-snapshot="{ item }">
            <ButtonWithLink
                v-if="item.state_value != 'live'"
                v-tooltip="trans('Publish this snapshot as current active banner')"
                :label="trans('Checkout')"
                size="xs"
                icon="far fa-rocket-launch"
                type="tertiary"
                method="POST"
                :routeTarget="{
                    name: 'grp.models.banner.snapshot_to_banner.store',
                    parameters: {
                        banner: item.parent_id,
                        snapshot: item.id
                    }
                }"
            />
        </template>
    </component>
</template>

