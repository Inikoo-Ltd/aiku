<!-- 
 Author Louis Perez
 Created on 19-06-2026-09h-46m
 GitHub: https://github.com/louis-perez
 Copyright 2026 
-->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeadingTypes } from "@/types/PageHeading";
import { computed, onMounted, ref } from "vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { useTabChange } from "@/Composables/tab-change";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import BarcodeShowcase from "@/Components/Goods/BarcodeShowcase.vue";
import JsBarcode from "jsbarcode";

const props = defineProps<{
    title: string;
    pageHead: PageHeadingTypes;
    tabs: {
        current: string;
        navigation: {};
    };
    showcase?: {};
    history?: {};
}>();

const currentTab = ref<string>(props?.tabs?.current ?? "index");
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
    const components: Record<string, any> = {
        showcase: BarcodeShowcase,
        history: TableHistories,
    };

    return components[currentTab.value];
});
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :key="currentTab" :tab="currentTab" :data="props[currentTab]" />
</template>