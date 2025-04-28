<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue";
import { useLocaleStore } from "@/Stores/locale";

const props = defineProps<{
    data: object,
    tab?: string
}>();

const locale = useLocaleStore();

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
    </Table>
</template>


