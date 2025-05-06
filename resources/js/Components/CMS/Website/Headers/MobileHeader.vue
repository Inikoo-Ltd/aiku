<script setup lang="ts">
import MobileMenu from '@/Components/MobileMenu.vue'
import { getStyles } from "@/Composables/styles";

import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUserCircle } from '@fas';
import Image from "@/Components/Image.vue"

library.add(faUserCircle)

const props = defineProps<{
    headerData: {
        logo: {
            properties: object
            image: {
                source: Object
            }
            alt: string
        }
    },
    menuData: Object
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()

console.log('mobileHeader', props)

</script>

<template>
    <div class="block md:hidden p-3">
        <div class="flex justify-between items-center">
            <MobileMenu :header="headerData" :menu="menuData" />
            <div :style="getStyles(headerData.logo.properties, screenType)">
                <Image v-if="headerData.logo?.image?.source" :src="headerData.logo?.image?.source" :imageCover="true"
                    :alt="headerData.logo?.alt" />
            </div>

            <div class="flex items-center cursor-pointer">
                <FontAwesomeIcon :icon="faUserCircle" class="text-2xl" />
            </div>
        </div>
    </div>
</template>

<style scoped></style>