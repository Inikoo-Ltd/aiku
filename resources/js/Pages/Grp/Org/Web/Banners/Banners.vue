<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 02 Oct 2023 03:20:36 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGlobe, faImage, faSign } from '@fal'
import TableBanners from "@/Components/Tables/Grp/Websites/TableBanner.vue"


library.add(faGlobe, faImage, faSign)

const props = defineProps<{
    pageHead: object
    title: string
    data: object
    firstBanner?: {
        text: string
        createRoute: {
            name: string
            parameters?: any[]
        }
    }
}>()

const isOpen = ref(false)
</script>

<template>
    <!-- <pre>{{ props }}</pre> -->

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>

    <TableBanners :data="data" />
</template>