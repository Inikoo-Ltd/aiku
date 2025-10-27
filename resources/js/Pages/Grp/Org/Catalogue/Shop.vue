<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 23 Sept 2024 22:02:13 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n"
import Dashboard from "@/Components/DataDisplay/Dashboard/Dashboard.vue";

const props = defineProps<{
    dashboard: any
}>();

</script>

<template>
    <Head :title="trans('Shop')" />
    <Dashboard :dashboard="props.dashboard" />
</template>
