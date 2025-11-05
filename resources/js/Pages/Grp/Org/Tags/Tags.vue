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
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHeading" />
    <Table :resource="data" :name="title" />
</template>
