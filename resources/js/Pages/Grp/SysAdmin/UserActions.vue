<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Wed, 07 Sept 2022 23:27:32 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import TableUsers from "@/Components/Tables/Grp/SysAdmin/TableUsers.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { computed, ref } from "vue";
import type { Component } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import { faRoad, faTerminal, faUserSlash, faBlender } from "@fal";
import TableUserRequestLogs from "@/Components/Tables/Grp/SysAdmin/TableUserRequestLogs.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { capitalize } from "@/Composables/capitalize";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import { PageHeadingTypes } from "@/types/PageHeading"
import PageHeading from '@/Components/Headings/PageHeading.vue'

library.add(faRoad, faTerminal, faUserSlash, faBlender);

const props = defineProps<{
  pageHead: PageHeadingTypes
  data: object
  title: string
}>();

</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead"></PageHeading>
  <TableHistories :data="data" />
</template>
