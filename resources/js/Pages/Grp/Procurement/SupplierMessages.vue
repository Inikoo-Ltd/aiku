<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableSupplierMessages from "@/Components/Tables/Grp/Org/Procurement/TableSupplierMessages.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"

defineProps<{
    pageHead: object
    title: string
    data: object
    mailbox: string | null
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div v-if="mailbox" class="px-4 pt-3 text-xs text-gray-500">{{ ctrans("Mirrored from") }} <span class="font-medium text-gray-700">{{ mailbox }}</span></div>
    <TableSupplierMessages :data="data" />
</template>
