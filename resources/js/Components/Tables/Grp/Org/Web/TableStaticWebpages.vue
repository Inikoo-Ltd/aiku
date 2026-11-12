<!--
  - Author: Rifqi Taufiqurrohman
  - Created: Thu, 12 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faEye } from "@fal"

library.add(faEye)

defineProps<{
    data: object
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(path)="{ item }">
            <Link :href="route(item.route.name, item.route.parameters)" class="whitespace-nowrap font-mono text-sm text-gray-700 hover:text-[var(--app-accent)] hover:underline">
                {{ item.path }}
            </Link>
        </template>
        <template #cell(preview)="{ item }">
            <a :href="item.preview_url" target="_blank" rel="noopener">
                <Button type="tertiary" size="xs" icon="fal fa-eye" :label="ctrans('Preview in Aiku')" />
            </a>
        </template>
    </Table>
</template>
