<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Feb 2024 12:36:21 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import Button from '@/Components/Elements/Buttons/Button.vue'
import { faExternalLink, faLink, faPencil } from '@fal'
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faExternalLink, faLink)

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

const props = defineProps<{
    data: {
        slug: string
        url: string
        domain: string
        state: string
        status: string
        created_at: string
        updated_at: string
        layout: string
    },
}>()

console.log(props.data)
const links = ref([
    { label: "Edit Header", url: route(props.data.layout.headerRoute.name, props.data.layout.headerRoute.parameters), icon: faPencil },
    { label: "Edit Menu", url: route(props.data.layout.menuRoute.name, props.data.layout.menuRoute.parameters), icon: faPencil },
    { label: "Edit Footer", url: route(props.data.layout.footerRoute.name, props.data.layout.footerRoute.parameters), icon: faPencil }
]);

</script>
<template>
    <!-- Box: Url and Buttons in a single row -->
    <div class="px-6 py-12 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- URL Box -->
            <div
                class="bg-white  h-fit flex items-center cursor-pointer gap-x-3  md:w-96">
                <a :href="props.data.url" target="_blank"
                    class="pl-4 md:pl-5 inline-block text-xxs md:text-base text-gray-400">{{ props.data.url }}
                    <FontAwesomeIcon :icon="faExternalLink" class='pl-1 text-gray-500' v-tooltip="'Go To Website'" aria-hidden='true' />
                </a>
               <!--  <Button :style="'tertiary'" class="" @click="useCopyText(props.data.url)"
                    v-tooltip="trans('Copy url to clipboard')">
                </Button> -->
            </div>


            <!-- Buttons Card (in the right part of the grid) -->
            <div class="bg-white flex justify-end">
                <div class="w-64 border border-gray-300 rounded-md p-2">
                    <div v-for="(item, index) in links" :key="index" class="p-2">
                        <Link :href="item.url">
                        <Button full :icon="item.icon" :label="item.label" class="text-sm py-1 px-3" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
