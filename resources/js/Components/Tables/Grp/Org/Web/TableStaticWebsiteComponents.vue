<!--
  - Author: Rifqi Taufiqurrohman
  - Created: Thu, 12 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"

defineProps<{
    data: object
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item }">
            <Link :href="route(item.route.name, item.route.parameters)" class="whitespace-nowrap text-gray-700 hover:text-[var(--app-accent)] hover:underline">
                {{ item.label }}
            </Link>
        </template>
    </Table>
</template>
