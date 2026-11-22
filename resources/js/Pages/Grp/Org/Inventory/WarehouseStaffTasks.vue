<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sun, 22 Nov 2026, Kuala Lumpur, Malaysia
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableWarehouseStaffTasks from "@/Components/Tables/Grp/Org/Inventory/TableWarehouseStaffTasks.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTasks } from "@fal"

library.add(faTasks)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tasks: {}
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableWarehouseStaffTasks :data="tasks" tab="warehouse_tasks" class="mt-5" />
</template>
