<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Tue, 28 Feb 2023 10:07:36 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue";
import { computed, ref } from "vue";

import TablePostRooms from "@/Components/Tables/TablePostRooms.vue";
import TableOutboxes from "@/Components/Tables/TableOutboxes.vue";
import TableMailshots from "@/Components/Tables/TableMailshots.vue";
import TableDispatchedEmails from "@/Components/Tables/TableDispatchedEmails.vue";
import { useTabChange } from "@/Composables/tab-change";


const props = defineProps<{
    data: object
    title: string
    pageHead: object
}>()

// let currentTab = ref(props.tabs.current);
// const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        post_rooms: TablePostRooms,
        outboxes: TableOutboxes,
        mailshots: TableMailshots,
        dispatched_emails: TableDispatchedEmails,
    };
    return components[currentTab.value];

});

</script>

<!--suppress HtmlUnknownAttribute -->
<template>
    <!--suppress HtmlRequiredTitleElement -->
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <TablePostRooms :data="data" />
    <!-- <Tabs :current="currentTab" :navigation="tabs['navigation']"  @update:tab="handleTabUpdate"/>
    <component :is="component" :tab="currentTab"  :data="props[currentTab]"></component> -->
</template>

