<!--
  - Author: eka yudinata (https://github.com/ekayudinata)
  - Created: Wednesday, 20 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, eka yudinata
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue";
import { useFormatTime } from "@/Composables/useFormatTime";
import { inject } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { Link } from "@inertiajs/vue3";
import { RouteParams } from "@/types/route-params";

defineProps<{
    data: object
    tab?: string
}>();

const locale = inject("locale", aikuLocaleStructure);

function watiTemplateRoute(template: { id: number }): string {
    return route("grp.org.shops.show.marketing.wati.templates.show", [
        (route().params as RouteParams).organisation,
        (route().params as RouteParams).shop,
        template.id,
    ]);
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(element_name)="{ item: template }">
            <Link :href="watiTemplateRoute(template)" class="primaryLink font-medium">
                {{ template.element_name }}
            </Link>
        </template>
        <template #cell(category)="{ item: template }">
            <span class="capitalize text-sm">{{ template.category }}</span>
        </template>
        <template #cell(status)="{ item: template }">
            <span class="capitalize text-sm">{{ template.status }}</span>
        </template>
        <template #cell(created_at)="{ item: template }">
            <div class="text-sm">
                {{ useFormatTime(template.created_at, { localeCode: locale.language.code, formatTime: "aiku" }) }}
            </div>
        </template>
    </Table>
</template>
