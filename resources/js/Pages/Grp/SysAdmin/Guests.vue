<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Wed, 14 Sept 2022 02:00:03 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableGuests from "@/Components/Tables/Grp/SysAdmin/TableGuests.vue";
import { capitalize } from "@/Composables/capitalize";
import { faUserSlash } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";

library.add(faUserSlash);

defineProps<{
  data: object
  title: string
  pageHead: PageHeadingTypes
}>();

</script>
<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead"></PageHeading>
  <TableGuests :data="data" />

</template>


