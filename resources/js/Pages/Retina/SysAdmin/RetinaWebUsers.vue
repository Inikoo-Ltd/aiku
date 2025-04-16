<!--
  - Author: Raul Perusquia <raul@inikoo.com>  
  - Created: Wed, 08 Jan 2025 22:09:39 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
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

library.add(faReceipt, faCrown,faRoad, faQuestionCircle ,faServer ,faDesktopAlt, faWindows,faUserCircle, faUserSlash, faBlender, faTimes, faCheck, faYinYang)


defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    data: {}

}>()


function webUserRoute(webUser: {}) {
    switch (route().current()) {
        default:
            return route(
                'retina.sysadmin.web-users.show',
                [
                webUser.slug
                ])
    }
}
</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Table :resource="data" class="mt-5">
        <!-- Image -->
        <template #cell(image)="{ item: webUser }">
            <div class="rounded-full overflow-hidden h-5 w-5 border border-gray-300 mx-auto">
                <Image :src="webUser.image" :alt="webUser.username" v-tooltip="webUser.username" />
            </div>
        </template>

        <!-- Status -->
        <template #cell(status)="{ item: webUser }">
            <Icon :data="webUser.status_icon" class="px-1" />
        </template>

        <!-- Username -->
        <template #cell(username)="{ item: webUser }">
            <Link :href="webUserRoute(webUser)" :class="'primaryLink py-0.5 ' + (!webUser.status ? 'line-through' : '')">
                {{ webUser.username }}
            </Link>
            <Icon v-if="webUser?.root_icon" :data="webUser.root_icon" class="px-1" />
        </template>

        <template #cell(last_location)="{ item: webUser }">
            <div class="flex space-x-2 text-lg text-gray-700">
                <AddressLocation v-if="webUser?.last_location != null && !webUser.server" :data="webUser.last_location" />
                <FontAwesomeIcon v-else-if="webUser?.last_location != null" :icon="['fas', 'server']" v-tooltip="'server'" fixed-width aria-hidden="true"/>
                <FontAwesomeIcon v-else :icon="['far', 'fa-question-circle']" v-tooltip="'Unknown location'" fixed-width aria-hidden="true" />
            </div>
        </template>

        <template #cell(last_device)="{ item: webUser }">
            <div class="flex space-x-2 text-lg text-gray-700">
                <Icon v-if="webUser.last_device?.[0]" :data="webUser.last_device[0]"></Icon>
                <Icon v-if="webUser.last_device?.[1]" :data="webUser.last_device[1]"></Icon>
                <FontAwesomeIcon v-else :icon="['far', 'fa-question-circle']" v-tooltip="'Unknown device & os'" fixed-width aria-hidden="true" />
            </div>
        </template>

        <template #cell(type)="{ item }">
            <div class="text-center">
                <FontAwesomeIcon :icon='item.type?.icon?.icon' v-tooltip="item.type?.icon?.tooltip" :class='item.type?.icon?.class' fixed-width aria-hidden='true' />
            </div>
        </template>

    </Table>
</template>