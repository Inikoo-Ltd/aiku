<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sun, 11 Sept 2022 12:17:26 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import Stats from '@/Components/DataDisplay/Stats.vue';
import { capitalize } from "@/Composables/capitalize"

defineProps(['title', 'pageHead', 'stats']);
</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <stats :stats="stats" class="m-4"/>
</template>
