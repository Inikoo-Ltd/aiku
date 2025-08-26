<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 13 Oct 2022 15:35:22 Central European Summer Plane Malaga - East Midlands UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCubes, faLink, faSeedling, faBooks, faFolderTree, faAlbumCollection } from "@fal"
import { faFireAlt } from "@fad"
import { faCheckCircle, faTimesCircle } from "@fas"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTS } from '@/types/PageHeading'
import { routeType } from '@/types/route'
import StatsBox from '@/Components/Stats/StatsBox.vue'
import { trans } from 'laravel-vue-i18n'
import CopyButton from '@/Components/Utils/CopyButton.vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import BoxApiUrl from '@/Components/Showcases/Retina/Catalouge/BoxApiUrl.vue'


library.add(faCheckCircle, faTimesCircle, faCubes, faLink, faSeedling, faFireAlt, faBooks, faFolderTree, faAlbumCollection)

const props = defineProps<{
    pageHead: PageHeadingTS
    tabs: {
        current: string
        navigation: {}
    },
    title: string
    stats: {
        id: number
        label: string
        value: number
        change: number
        changeType: string
        icon: string
        color: string
        backgroundColor?: string
        is_negative?: boolean
        route: {
            name: string
            parameters: {}
        }
        metaRight?: {
            count: number
            icon: {
                icon: string
                class: string
                tooltip: string
            }
            route: routeType
            tooltip: string
        }
        metas?: {
            count: number
            icon: {
                icon: string
                class: string
                tooltip: string
            }
            route: routeType
            tooltip: string
        }[]
    }[]
}>()


const routeAPI = window.location.origin + '/data-feed.csv'
</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-6">
        <!-- Box: API URL -->
        <BoxApiUrl
            :routeApi="routeAPI"
        />

        <dl class="grid grid-cols-1 gap-2 lg:gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <StatsBox v-for="stat in stats" :stat="stat">
            </StatsBox>
        </dl>
    </div>
</template>
