<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { useTabChange } from "@/Composables/tab-change";
import { capitalize } from "@/Composables/capitalize";
import { computed, ref } from "vue";
import type { Component } from "vue";
import EmailPreview from "@/Components/Showcases/Org/Mailshot/EmailPreview.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import { Tabs as TSTabs } from "@/types/Tabs";
import MailshotShowcase from "@/Components/Showcases/Org/Mailshot/MailshotShowcase.vue";
import { faEnvelope, faStop } from "@fas";
import { faDraftingCompass } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";

library.add(faEnvelope, faDraftingCompass, faStop);


const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: string
    email_preview: Object
}>();

console.log(props);

const currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
    const components: Component = {
        showcase: MailshotShowcase,
        email_preview: EmailPreview,
        history: TableHistories
    };
    return components[currentTab.value];
});

</script>


<template>
    <Head :title="capitalize(pageHead.title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
</template>
