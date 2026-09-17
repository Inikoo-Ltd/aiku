<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:55:18 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import Table from "@/Components/Table/Table.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeadingTypes } from "@/types/PageHeading";
import Icon from "@/Components/Icon.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faChair, faHandPaper, faBoxCheck} from "@fal";
import { computed, ref, watch } from "vue";
import type { Tabs as TSTabs } from "@/types/Tabs";

library.add(faChair, faHandPaper, faBoxCheck);

const props = defineProps<{
  data: any
  title: string
  pageHead: PageHeadingTypes
  tabs: TSTabs
}>();

const currentTab = ref(props.tabs?.current ?? "dropshipping");

watch(
  () => props.tabs?.current,
  (val) => {
    if (val) currentTab.value = val;
  },
  { immediate: true }
);

function handleTabUpdate(tab: string) {
  if (tab === currentTab.value) {
    return;
  }

  const url = new URL(window.location.href);
  url.searchParams.set("tab", tab);

  router.visit(url.toString(), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      currentTab.value = tab;
    },
  });
}

function referenceRoute(item: any) {
  const params: any = route().params as any

  if (item.type === "fulfilment") {
    return route(
      "grp.org.warehouses.show.dispatching.picking_sessions.fulfilment.show",
      [params["organisation"], params["warehouse"], item.slug]
    )
  }

  return route(
    "grp.org.warehouses.show.dispatching.picking_sessions.show",
    [params["organisation"], params["warehouse"], item.slug]
  )
}

</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead" />
  <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />

  <Table :resource="data" class="mt-5">
    <template #cell(state)="{ item }">
      <Icon :data="item.state_icon" class="px-1" />
    </template>

    <template #cell(reference)="{ item }">
      <Link v-if="item.reference" :href="referenceRoute(item)" class="secondaryLink">
        {{ item.reference }}
      </Link>
    </template>
  </Table>
</template>
