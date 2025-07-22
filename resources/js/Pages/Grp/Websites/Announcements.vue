<!--
  * Author: Vika Aqordi
  * Created on: 18-02-2025-09h-16m
  * Github: https://github.com/aqordeon
  * Copyright: 2025
-->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import TableAnnouncements from '@/Components/Tables/Grp/Websites/TableAnnouncements.vue'

const props = defineProps<{
  pageHead: {}
  title: string
  data: {}
}>()

</script>

<template layout="CustomerApp">
  <!-- <pre>{{ props }}</pre> -->

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead"></PageHeading>

  <TableAnnouncements :data="data" />
</template>
