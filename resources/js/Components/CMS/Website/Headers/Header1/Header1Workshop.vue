<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import { checkVisible, textReplaceVariables } from "@/Composables/Workshop"
import { inject, ref, computed } from "vue"
import Image from "@common/Components/Image.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";
import { faPresentation, faCube, faText, faPaperclip } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from "@/Iris/Components/IrisButton.vue";
import {
    faChevronRight,
    faSignOutAlt,
    faShoppingCart,
    faSearch,
    faChevronDown,
    faTimes,
    faPlusCircle,
    faBars,
    faUserCircle,
    faImage,
    faSignInAlt,
    faFileAlt,
    faUser,
    faEnvelope,
    faHeart
} from "@fas"
import LuigiSearch from "@/Components/CMS/LuigiSearch.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { ctrans } from "@/Composables/useTrans";
import { router } from "@inertiajs/vue3";
import { notify } from "@kyvg/vue3-notification"
import { set } from "lodash"
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
import { faUserPlus } from "@far";

library.add(
    faPresentation,
    faCube,
    faText,
    faImage,
    faPaperclip,
    faChevronRight,
    faSignOutAlt,
    faShoppingCart,
    faHeart,
    faSearch,
    faChevronDown,
    faTimes,
    faPlusCircle,
    faBars,
    faUserCircle,
    faSignInAlt,
    faFileAlt
)


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
const loadingRedirect = ref(false)
const toggle = (event) => {_menu.value.toggle(event)};
const layout = inject('layout', {})
const isLoggedIn = inject("isPreviewLoggedIn", true)

</script>

<template>
    <div id="header_1_iris" class="bg-white border-t border-sky-200" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        margin: 0,
        padding: 0,
        ...getStyles(modelValue.container?.properties, screenType)
    }">
        <div class="max-w-[1800px] mx-auto h-[110px] px-6 flex items-center gap-8">
            <!-- Logo -->
            <div class="shrink-0 w-[180px]">
                <div class="relative aspect-[3/1.5]">
                    <div v-if="loadingRedirect"
                        class="absolute inset-0 z-10 flex items-center justify-center bg-white/60 rounded">
                        <LoadingIcon class="w-10 h-10" />
                    </div>

                    <component v-if="modelValue?.logo?.image?.source"
                        :is="modelValue?.logo?.image?.source ? LinkIris : 'div'" @start="loadingRedirect = true"
                        @finish="loadingRedirect = false" :canonical_url="modelValue?.logo?.link?.canonical_url"
                        :href="modelValue?.logo?.link?.href" :type="modelValue?.logo?.link?.type"
                        :target="modelValue?.logo?.link?.target || '_self'" class="block h-full">
                        <Image :src="modelValue?.logo?.image?.source" :alt="modelValue?.logo?.alt" imageCover
                            class="w-full h-full object-contain" />
                    </component>
                </div>
            </div>

            <!-- Search -->
            <div class="flex-1 flex justify-center">
                <div class="w-full max-w-[760px]">
                    <div class="w-full relative group">
                        <input :value="''"
                            class="h-12 min-w-28 focus:border-transparent focus:ring-2 focus:ring-gray-700 w-full md:min-w-0 md:w-full rounded-full border border-[#d1d5db] disabled:bg-gray-200 disabled:cursor-not-allowed pl-10"
                            :placeholder="ctrans('Search')" />
                        <FontAwesomeIcon icon="far fa-search"
                            class="group-focus-within:text-gray-700 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2"
                            fixed-width aria-hidden="true" />
                    </div>
                </div>
            </div>

            <!-- Right Menu -->
            <div class="shrink-0 h-full flex items-center gap-6">
                <!-- Mail -->

                <button v-if="isLoggedIn" class="flex items-center gap-2 text-gray-600 hover:text-gray-900 transition-colors"
                    v-tooltip="ctrans('Reminder back in stock')">
                    <FontAwesomeIcon :icon="faEnvelope" class="text-[20px]" />
                    <span class="text-sm font-medium">
                        {{ layout.iris_variables?.back_in_stock_count }}
                    </span>
                </button>


                <!-- Wishlist -->


                <button v-if="isLoggedIn" class="flex items-center gap-2 text-gray-600 hover:text-red-500 transition-colors"
                    v-tooltip="ctrans('Favourites')">
                    <FontAwesomeIcon :icon="faHeart" class="text-[20px]" />
                    <span class="text-sm font-medium">
                        <div class="button">
                            {{ layout.iris_variables?.favourites_count }}
                        </div>
                    </span>
                </button>



                <!-- Cart -->


                <button v-if="isLoggedIn" class="flex items-center gap-2 text-gray-600 hover:text-gray-900 transition-colors"
                    v-tooltip="ctrans('Cart count and amount')">
                    <span class="button whitespace-nowrap"
                        v-html="textReplaceVariables(`({{ cart_count }})`, layout.iris_variables)">
                    </span>
                    <FontAwesomeIcon :icon="faShoppingCart" class="text-[20px]" />
                    <span class="button whitespace-nowrap"
                        v-html="textReplaceVariables(`{{ cart_products_amount }}`, layout.iris_variables)">
                    </span>
                </button>


                <!-- Divider -->
                <div v-if="isLoggedIn" class="h-8 w-px bg-gray-200" />

                <!-- Logged In -->
                <template v-if="isLoggedIn">
                    <div class="flex items-center gap-1">
                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                            <FontAwesomeIcon :icon="faUser" class="text-gray-500 text-lg" />
                        </div>

                        <div class="leading-tight">
                            <div class="font-medium text-sm">
                                {{ layout?.user?.username }}
                            </div>

                            <!-- <div class="text-[11px] text-amber-500 whitespace-nowrap">
								Gold Reward until 26th Jan 2026
							</div> -->
                        </div>
                    </div>

                    <button
                        class="text-[20px] rounded-full bg-gray-100 text-gray-600 hover:text-gray-900 flex items-center justify-center"
                        v-tooltip="ctrans('Logout')">
                        <FontAwesomeIcon :icon="faSignOutAlt" />
                    </button>
                </template>

                <!-- Guest -->
                <template v-else>
                    <div class="flex items-center gap-3">
                        <!-- Login -->

                        <Button @click="() => urlLoginWithRedirect()" :label="ctrans('Login')"
                            :icon="faSignInAlt"></Button>


                        <!-- Register -->

                        <Button :label="ctrans('Register')" :icon="faUserPlus" type="secondary"></Button>

                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped></style>