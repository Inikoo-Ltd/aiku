<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"

defineProps<{
    data: {}
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(contact_name)="{ item }">
            <Link
                :href="route('grp.org.shops.show.crm.appointments.staff.edit', { organisation: route().params.organisation, shop: route().params.shop, user: item.id })"
                class="primaryLink">
                {{ item.contact_name }}
            </Link>
        </template>
    </Table>
</template>
