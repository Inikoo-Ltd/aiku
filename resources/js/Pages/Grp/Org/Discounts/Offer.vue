<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 9 September 2024 16:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'

const props = defineProps<{
    data: {}
    title: string
    pageHead: PageHeadingTypes
}>()

// console.log('offer', props);


</script>
<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <div class="p-5">
        <h-2>
            Work In Progress
        </h-2>
    </div>
</template>