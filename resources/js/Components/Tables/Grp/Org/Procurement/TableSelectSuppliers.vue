<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 20:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

interface Supplier {
    id: number
    code: string
    name: string
    location: object
}

const props = defineProps<{
    data: {}
    organisation_id: number
}>()

function onAdd(supplier: Supplier) {
    router.post(
        route("grp.models.org.org_supplier.store", [props.organisation_id, supplier.id]),
        {},
        { preserveScroll: true }
    )
}
</script>

<template>
    <Table :resource="data" class="mt-5">
        <template #cell(location)="{ item: supplier }">
            <AddressLocation :data="supplier['location']" />
        </template>
        <template #cell(add)="{ item: supplier }">
            <Button style="secondary" label="Add" @click="onAdd(supplier)" />
        </template>
    </Table>
</template>
