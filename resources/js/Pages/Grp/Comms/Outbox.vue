<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, ref } from 'vue'
import type { Component } from 'vue'

import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import { Tabs as TSTabs } from "@/types/Tabs";

import { library } from "@fortawesome/fontawesome-svg-core";
import { faBars, faInboxOut, faMailBulk, faRabbitFast } from "@fal";
import TableMailshots from "@/Components/Tables/TableMailshots.vue";
import OutboxShowcase from "@/Components/Showcases/Grp/OutboxShowcase.vue";
import TableEmailBulkRuns from "@/Components/Tables/TableEmailBulkRuns.vue";
import TableDispatchedEmails from "@/Components/Tables/TableDispatchedEmails.vue"
import { faPaperPlane } from "@far"

library.add(faInboxOut, faMailBulk, faRabbitFast, faPaperPlane, faBars);

const props = defineProps<{
  title: string,
  pageHead: PageHeadingTypes
  tabs: TSTabs
  history: {}
  mailshots: {}
  email_bulk_runs?: {}
  dispatched_emails?: {}
  showcase: any
}>();



const currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
  const components: Component = {
    history: TableHistories,
    mailshots: TableMailshots,
    showcase: OutboxShowcase,
    // email_bulk_runs: TableEmailBulkRuns
    dispatched_emails: TableDispatchedEmails
  };

  return components[currentTab.value];
});

</script>


<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead" />
  <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
  <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
</template>


<style lang="scss" scoped>
.card {
  padding: 1rem;
  border-radius: 8px;

  @media (max-width: 768px) {
    padding: 0.5rem;
  }
}

.grid-cols-7 {
  display: grid;
  grid-template-columns: repeat(7, 1fr);

  @media (max-width: 768px) {
    grid-template-columns: repeat(2, 1fr);
  }
}

.text-xl {
  font-size: 1.25rem;

  @media (max-width: 640px) {
    font-size: 1rem;
  }
}
</style>
