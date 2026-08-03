
<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 06 Jun 2026 09:22:41 Indochina Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { faDatabase } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faDatabase)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
}>()


</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />


</template>
