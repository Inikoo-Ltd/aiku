<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Wed, 07 Sept 2022 23:27:32 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import TableUsers from "@/Components/Tables/Grp/SysAdmin/TableUsers.vue";
import { faRoad, faUserCircle, faUserSlash, faBlender } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { capitalize } from "@/Composables/capitalize";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import PageHeading from '@/Components/Headings/PageHeading.vue'

library.add(faRoad, faUserCircle, faUserSlash, faBlender);

defineProps<{
  pageHead: PageHeadingTypes
  data: object
  title: string
}>();

</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead"></PageHeading>
  <TableUsers :data="data" />
</template>
