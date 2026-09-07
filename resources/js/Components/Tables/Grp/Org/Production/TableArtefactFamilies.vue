<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 02 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"

defineProps<{
    data: object
    tab?: string
}>()

const familyRoute = (family: { slug: string }) =>
    route("grp.org.productions.show.crafts.artefact_families.show", [route().params["organisation"], route().params["production"], family.slug])
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: family }">
            <Link :href="familyRoute(family)" class="primaryLink">{{ family.code }}</Link>
        </template>
    </Table>
</template>
