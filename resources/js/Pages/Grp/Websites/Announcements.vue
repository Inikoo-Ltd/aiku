<!--
  * Author: Vika Aqordi
  * Created on: 18-02-2025-09h-16m
  * Github: https://github.com/aqordeon
  * Copyright: 2025
-->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import TableAnnouncements from '@/Components/Tables/Grp/Websites/TableAnnouncements.vue'
import AnnouncementSimulator from '@/Components/Websites/Announcement/AnnouncementSimulator.vue'
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
  pageHead: PageHeadingTypes
  title: string
  data: {}
}>()

</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead"></PageHeading>

  <div class="px-4 mb-2">
    <AnnouncementSimulator />
  </div>

  <TableAnnouncements :data="data" />
</template>
