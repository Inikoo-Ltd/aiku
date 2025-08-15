<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { ref } from "vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { trans } from 'laravel-vue-i18n'
import { routeType } from '@/types/route'


const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: {}
    },
    data: {},
    index?: {}
    sales?: {}
    routes: {
        families_route: routeType
        submit_route: routeType
    }
    is_orphan_products?: boolean
}>()



</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
dsfsdf
   
</template>

