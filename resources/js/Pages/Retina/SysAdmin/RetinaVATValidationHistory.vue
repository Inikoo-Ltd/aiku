<!--
  - Author: Raul Perusquia <raul@inikoo.com>  
  - Created: Wed, 08 Jan 2025 22:09:39 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import Table from "@/Components/Table/Table.vue"
import Icon from '@/Components/Icon.vue'
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faReceipt,faRoad, faUserCircle, faUserSlash, faBlender, faTimes, faCheck, faYinYang, faCrown } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faDesktopAlt, faQuestionCircle } from '@far'
import { faWindows } from '@fortawesome/free-brands-svg-icons'
import { faServer } from '@fas'
import Image from '@/Components/Image.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'

library.add(faReceipt, faCrown,faRoad, faQuestionCircle ,faServer ,faDesktopAlt, faWindows,faUserCircle, faUserSlash, faBlender, faTimes, faCheck, faYinYang)


defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    data: {}
}>()
</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <component :is="TableHistories" :data="data"/>
</template>