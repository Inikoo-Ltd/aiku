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
import Coupon from '@/Components/Utils/Coupon.vue'

const props = defineProps<{
    data: {}
    title: string
    pageHead: PageHeadingTypes
    currency_code: string
}>()

// console.log('offer', props);


</script>
<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <div class="p-5">
        <Coupon :first_order_bonus="[data]" :currency_code="currency_code" />
    </div>
</template>