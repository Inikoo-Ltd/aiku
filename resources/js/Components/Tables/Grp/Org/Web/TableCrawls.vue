<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 15 May 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"

defineProps<{
    data: object
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
    </Table>
</template>
