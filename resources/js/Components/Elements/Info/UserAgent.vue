<!--
  - Author: Artha <artha@aw-advantage.com>
  - Created: Wed, 07 Jun 2023 09:03:33 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { capitalize } from "@/Composables/capitalize"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {faRoad} from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import {faChrome, faWindows} from "@fortawesome/free-brands-svg-icons";
import {faDesktopAlt} from "@far";

const props = defineProps<{
    data: [
        string,
        string,
        string
    ]
}>()


library.add(
    faChrome,
    faDesktopAlt,
    faWindows
)

</script>

<template>
    <div class="flex text-gray-700">
        <FontAwesomeIcon :icon='(JSON.parse(data[0])).icon' class='w-6' fixed-width aria-hidden='true' :title='capitalize((JSON.parse(data[0])).title)' />
        <FontAwesomeIcon :icon='(JSON.parse(data[1])).icon' class='w-6' fixed-width aria-hidden='true' :title='capitalize((JSON.parse(data[1])).title)' />
        <FontAwesomeIcon :icon='(JSON.parse(data[2])).icon' class='w-6' fixed-width aria-hidden='true' :title='capitalize((JSON.parse(data[2])).title)' />
    </div>
</template>
