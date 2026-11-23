<!--
  - Author Louis Perez
  - Created on 23-11-2026-11h-05m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableStaticWebsites from "@/Components/Tables/Grp/Org/Web/TableStaticWebsites.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGlobe } from "@fal"

library.add(faGlobe)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: object
}>()
</script>

<template>
    <div>
        <Head :title="capitalize(title)" />
        <PageHeading :data="pageHead" />
        <div class="px-4">
            <TableStaticWebsites :data="data" />
        </div>
    </div>
</template>
