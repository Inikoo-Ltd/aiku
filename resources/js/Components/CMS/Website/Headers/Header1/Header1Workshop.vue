<script setup lang="ts">
import { ref } from 'vue'
import MobileMenu from '@/Components/MobileMenu.vue'
import Menu from 'primevue/menu'
import { getStyles } from "@/Composables/styles";

import { faPresentation, faCube, faText, faPaperclip } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronRight, faSignOutAlt, faShoppingCart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faUserCircle, faImage, faSignInAlt, faFileAlt } from '@fas';
import { faHeart } from '@far';
import Image from "@/Components/Image.vue"
import { checkVisible, textReplaceVariables } from '@/Composables/Workshop'

library.add(faPresentation, faCube, faText, faImage, faPaperclip, faChevronRight, faSignOutAlt, faShoppingCart, faHeart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faUserCircle, faSignInAlt, faFileAlt)

const props = defineProps<{
    modelValue: {
        headerText: string
        chip_text: string
    }
    loginMode: boolean
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
    <div class="shadow-sm" :style="getStyles(modelValue.container.properties)">
        <div class="flex flex-col justify-between items-center py-4 px-6 hidden lg:block">
            <div class="w-full grid grid-cols-3 items-center gap-6">
                <!-- Logo -->
             
                    <Image 
                        :style="getStyles(modelValue.logo.properties)"
                        :alt="modelValue?.logo?.alt" 
                         :imageCover="true"
                        :src="modelValue?.logo?.image?.source" 
                        :imgAttributes="modelValue?.logo.image?.attributes"
                        @click="() => emits('setPanelActive', 'logo')"
                        class="hover-dashed">
                    </Image>
               

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
                        :style="getStyles(modelValue?.button_1?.container?.properties)">
                        <span v-html="modelValue?.button_1.text" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile view (hidden on desktop) -->
        <div class="block md:hidden p-3">
            <div class="flex justify-between items-center">
                <MobileMenu :header="modelValue" :menu="modelValue" />

                <!-- Logo for Mobile -->
                <Image v-if="modelValue?.logo?.image?.source" :src="modelValue?.logo?.image?.source" class="h-10 mx-2"></Image>

                <!-- Profile Icon with Dropdown Menu -->
                <div @click="toggle" class="flex items-center cursor-pointer text-white">
                    <FontAwesomeIcon icon="fas fa-user-circle" class="text-2xl" />
                    <Menu ref="_menu" id="overlay_menu" :model="items" :popup="true">
                        <template #itemicon="{ item }">
                            <FontAwesomeIcon :icon="item.icon" />
                        </template>
                    </Menu>
                </div>
            </div>

            <!-- Mobile Search Bar -->
            <div class="relative mt-2">
                <input type="text" placeholder="Search Products"
                    class="border border-gray-300 py-2 px-4 rounded-md w-full shadow-inner focus:outline-none focus:border-gray-500">
                <FontAwesomeIcon icon="fas fa-search" class="absolute top-1/2 -translate-y-1/2 right-4 text-gray-500"
                    fixed-width />
            </div>
        </div>
    </div>
</template>

<style scoped></style>