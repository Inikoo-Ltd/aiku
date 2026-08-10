<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 10 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableShoppingListItems from "@/Components/Tables/Grp/Org/Procurement/TableShoppingListItems.vue"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"
import { PageHeadingTypes } from "@/types/PageHeading"

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
    dismiss_proposed_count?: number
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div v-if="dismiss_proposed_count" class="mx-4 mt-4 rounded-lg border border-warning-300 bg-warning-50 px-4 py-2 text-warning-700">
        {{ trans("Needs your decision") }}: {{ dismiss_proposed_count }}
    </div>
    <TableShoppingListItems :data="data" />
</template>
