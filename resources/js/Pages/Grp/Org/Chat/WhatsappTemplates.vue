<!--
  - Author: Artha <artha@aw-advantage.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import WhatsappTemplatesTable from "@/Components/Chat/WhatsappTemplatesTable.vue"
import { capitalize } from "@/Composables/capitalize"

defineProps<{
    data: object
    title: string
    pageHead: object
    mergeTags: { name: string; value: string; example: string; group: string }[]
    variablesRoute: { name: string; parameters: Record<string, any> }
    editRouteName: string
    deleteRouteName: string
    refreshRouteName: string
    draftRouteName: string
    languageRouteName: string
    routeParameters: Record<string, any>
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <WhatsappTemplatesTable
        :data="data"
        :mergeTags="mergeTags"
        :variablesRoute="variablesRoute"
        :editRouteName="editRouteName"
        :deleteRouteName="deleteRouteName"
        :refreshRouteName="refreshRouteName"
        :draftRouteName="draftRouteName"
        :languageRouteName="languageRouteName"
        :routeParameters="routeParameters" />
</template>
