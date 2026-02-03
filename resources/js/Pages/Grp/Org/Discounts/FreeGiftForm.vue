<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 9 September 2024 16:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'

import { library } from "@fortawesome/fontawesome-svg-core"
import { faCommentDollar, faInfoCircle } from '@fal'
library.add( faCommentDollar, faInfoCircle )


const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
}>()


</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
   free gift
</template>
