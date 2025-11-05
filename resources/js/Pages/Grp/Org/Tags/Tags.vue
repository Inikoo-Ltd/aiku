<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Tue, 04 Nov 2025 10:00:04 Western Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
    import { Head } from "@inertiajs/vue3";
    import PageHeading from "@/Components/Headings/PageHeading.vue";
    import Table from "@/Components/Table/Table.vue";

    defineProps<{
        title: string;
        pageHeading: [];
        data: any;
    }>();

    function editRoute(tag: any) {
        return route('grp.org.tags.edit', [route().params.organisation, tag.slug]);
    }
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHeading" />
    <Table :resource="data" :name="title">
        <template #cell(action)="{ item }">
            <div class="flex gap-2">
                <a :href="editRoute(item)" class="primaryLink">
                    Edit
                </a>
            </div>
        </template>
    </Table>
</template>
