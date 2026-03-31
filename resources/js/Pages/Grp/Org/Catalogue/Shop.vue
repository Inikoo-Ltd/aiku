<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 23 Sept 2024 22:02:13 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import Dashboard from "@/Components/DataDisplay/Dashboard/Dashboard.vue";

const props = defineProps<{
    title: string,
    dashboard: any
}>();

</script>

<template>
    <Head :title="title" />
    <Dashboard :dashboard="props.dashboard" />
</template>
