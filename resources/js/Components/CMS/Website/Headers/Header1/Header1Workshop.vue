<script setup lang="ts">
import { ref } from 'vue'
import { getStyles } from "@/Composables/styles";
import MobileHeader from '@/Components/CMS/Website/Headers/MobileHeader.vue';

import { faPresentation, faCube, faText, faPaperclip } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronRight, faSignOutAlt, faShoppingCart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faUserCircle, faImage, faSignInAlt, faFileAlt } from '@fas';
import { faHeart } from '@far';
import Image from "@/Components/Image.vue"
import { checkVisible } from '@/Composables/Workshop'

library.add(faPresentation, faCube, faText, faImage, faPaperclip, faChevronRight, faSignOutAlt, faShoppingCart, faHeart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faUserCircle, faSignInAlt, faFileAlt)

const props = defineProps<{
    modelValue: {
        headerText: string
        logo: {
            alt: string,
            image: {
                source: object
            },
        }
        container: {
            properties: Object
        }
        button_1: {
            visible: boolean
            text: string
            container: {
                properties: Object
            }
        }
    }
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()


const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
    (e: 'setPanelActive', value: string | number): void
}>()

const _menu = ref();

const toggle = (event) => {
    _menu.value.toggle(event)
};


</script>

<template>
    <div class="shadow-sm" :style="getStyles(modelValue.container.properties, screenType)">
        <div class="flex flex-col justify-between items-center py-4 px-6 hidden lg:block">
            <div class="w-full grid grid-cols-3 items-center gap-6">
                <!-- Logo -->
                <component v-if="modelValue?.logo?.image?.source" :is="modelValue?.logo?.image?.source ? 'a' : 'div'"
                    target="_blank" rel="noopener noreferrer" class="block w-full h-full"
                    @click="() => emits('setPanelActive', 'logo')">
                    <Image :style="getStyles(modelValue.logo.properties, screenType)" :alt="modelValue?.logo?.alt"
                        :imageCover="true" :src="modelValue?.logo?.image?.source"
                        :imgAttributes="modelValue?.logo.image?.attributes"
                        @click="() => emits('setPanelActive', 'logo')" class="hover-dashed" />
                </component>

                <div v-else @click="() => emits('setPanelActive', 'logo')"
                    class="flex items-center justify-center w-[200px] h-[100px] bg-gray-200 rounded-lg aspect-square transition-all duration-300 hover:bg-gray-300 hover:shadow-lg hover:scale-105 cursor-pointer">
                    <font-awesome-icon :icon="['fas', 'image']"
                        class="text-gray-500 text-4xl transition-colors duration-300 group-hover:text-gray-700" />
                </div>

                <!-- Search Bar -->
                <div class="relative justify-self-center w-full max-w-md">
                    <!-- <input type="text" placeholder="Search Products"
                        class="border border-gray-300 py-2 px-4 rounded-md text-sm w-full shadow-inner focus:outline-none focus:border-gray-500">
                    <FontAwesomeIcon icon="fas fa-search"
                        class="absolute top-1/2 -translate-y-1/2 right-4 text-gray-500" fixed-width /> -->
                </div>

                <!-- Gold Member Button -->
                <div class="justify-self-end w-fit hover-dashed">
                    <div v-if="checkVisible(modelValue?.button_1?.visible || null, isLoggedIn)"
                        :href="modelValue?.button_1?.visible" class="space-x-1.5 cursor-pointer whitespace-nowrap" id=""
                        @click="() => emits('setPanelActive', 'button-1')"
                        :style="getStyles(modelValue?.button_1?.container?.properties, screenType)">
                        <span v-html="modelValue?.button_1.text" />
                    </div>
                </div>
            </div>
        </div>

        <MobileHeader :header-data="modelValue" :menu-data="{}" :screenType="screenType" />
    </div>
</template>

<style scoped></style>