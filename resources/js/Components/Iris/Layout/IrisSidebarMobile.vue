<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronRight, faExternalLink, faSearch, faTimes, faMapMarkerAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, inject, ref } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { faSignIn, faSignOut, faTimesCircle } from '@fas'
import { faChevronCircleDown } from '@fal'
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SwitchLanguage from "../SwitchLanguage.vue"
import LinkIris from "../LinkIris.vue"
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
import { router } from '@inertiajs/vue3'
import { ProductCategoryMenu } from "@/Composables/Iris/useMenu"
import SidebarMobileNavigation from "./SidebarMobileNavigation.vue"

library.add(faChevronRight, faExternalLink, faSearch, faTimes, faMapMarkerAlt)

const props = defineProps<{
    containerStyle: {
        
    }
    productCategories: {}
    customMenusTop: {
        name: string
        url: string
        type: string
        target: string
        sub_departments: {
            name: string
            url: string
            type: string
            target: string
            families: {
                name: string
                url: string
                type: string
                target: string
            }[]
        }[]
    }[]
    customTopSubDepartments: []
    customMenusBottom: {}[]
    customSubDepartments: []
    activeIndex: {}
    activeCustomIndex: {}
    activeCustomTopIndex: {}
    getTarget: Function
    setActiveCategory: Function
    setActiveCustomCategory: Function
    setActiveCustomTopCategory: Function
    sortedFamilies: {}[]
    customFamilies: {}[]
    customTopFamilies: {}[]
    sortedProductCategories: ProductCategoryMenu[]
    sortedSubDepartments: {}[]
    activeSubIndex: number
    activeCustomSubIndex: {}
    activeCustomTopSubIndex: {}
    changeActiveSubIndex: Function
    changeActiveCustomSubIndex: Function
    changeActiveCustomTopSubIndex: Function
    internalHref: Function
    fieldValue: {
        additional_items: {
            items_list: {
                icon: any
                text: string
                url: {
                    href: string
                    type: string
                    target: string
                }
            }[]
        }
    }
}>()

const emit = defineEmits<{
    closeMobileMenu: []
}>()

const layout = inject('layout', retinaLayoutStructure)
const screenType: string = inject('screenType', 'desktop')

const isLoggedIn = inject('isPreviewLoggedIn', false)
const onLogout = inject('onLogout', () => console.log('Logout function not injected'))

const isOpenMenuMobile = inject('isOpenMenuMobile', ref(false));
const closeSidebar = () => {
    isOpenMenuMobile.value = false;
}

const borderWidth = computed(() => {
    return props.containerStyle?.border?.width ? `${props.containerStyle?.border?.width.value}${props.containerStyle?.border?.width.unit}` : '1px';
})


// Loading states for View all buttons
const isLoadingProductCategory = ref(false)
const isLoadingSubDepartment = ref(false)

// Handle navigation with loading state
const handleViewAllProductCategory = (url: string) => {
    isLoadingProductCategory.value = true
    console.log('url', url)
    router.visit(url, {
        onFinish: () => {
            isLoadingProductCategory.value = false
            // Emit event to close mobile drawer
            emit('closeMobileMenu')
        },
        onError: () => {
            isLoadingProductCategory.value = false
            // Emit event to close mobile drawer
            emit('closeMobileMenu')
        }
    })
}

const handleViewAllSubDepartment = (url: string) => {
    isLoadingSubDepartment.value = true
    router.visit(url, {
        onFinish: () => {
            isLoadingSubDepartment.value = false
            // Emit event to close mobile drawer
            emit('closeMobileMenu')
        },
        onError: () => {
            isLoadingSubDepartment.value = false
            // Emit event to close mobile drawer
            emit('closeMobileMenu')
        }
    })
}
</script>

<template>
    <div class="flex flex-col h-full">
        <Transition name="slide-absolute-to-right">
            <!-- 3: Families -->
            <div v-if="activeSubIndex !== null || activeCustomSubIndex !== null || activeCustomTopSubIndex !== null">
                <div @click="changeActiveSubIndex(null), changeActiveCustomTopSubIndex(null), changeActiveCustomSubIndex(null)" class="py-1">
                    <FontAwesomeIcon icon="fal fa-chevron-left" class="text-xs" fixed-width aria-hidden="true" />
                    {{ sortedSubDepartments?.[activeSubIndex]?.name }}
                    {{ customTopSubDepartments?.[activeCustomTopSubIndex]?.name }}
                    {{ customSubDepartments?.[activeCustomSubIndex]?.name }}
                </div>

                <!-- Header -->
                <!-- <div  v-if="activeSubIndex !== null" class="flex items-center justify-between pt-4 pb-2 px-4">
                    <h3 class="font-semibold">{{ trans("Families") }}</h3>
                </div> -->

                <div class="overflow-y-auto">
                    <!-- 3: Families: Top -->
                    <div v-if="activeCustomTopSubIndex !== null && customTopFamilies?.length">
                        <div v-for="(child, cIndex) in customTopFamilies" :key="cIndex"
                            class="p-2 px-4">
                            <LinkIris
                                v-if="child.url !== null"
                                :href="internalHref(child)"
                                class="hover:underline"
                                @success="() => closeSidebar()"
                                :target="getTarget(child)"
                                :type="child.type"
                            >
                                {{ child.name }}
                            </LinkIris>
                            <span v-else>{{ child.name }}</span>
                        </div>
                    </div>

                    <!-- 3: Families: Product Categories -->
                    <Transition name="slide-to-right">
                        <div v-if="activeSubIndex !== null && sortedFamilies.length">
                            <div class="mt-1 pt-1 pb-2 px-4">
                                <LinkIris :href="sortedSubDepartments[activeSubIndex].url">
                                    <template #default="{ isLoading }">
                                        <Button
                                            :label="trans('View all')"
                                            :icon="faExternalLink"
                                            :size="screenType === 'mobile' ? 'm' : 'xs'"
                                            :loading="isLoading"
                                            xclick="handleViewAllSubDepartment(sortedSubDepartments[activeSubIndex].url)"
                                            class="cursor-pointer"
                                        />
                                    </template>
                                </LinkIris>
                            </div>

                            <SidebarMobileNavigation
                                v-for="(sub, sIndex) in sortedFamilies" :key="sIndex"
                                :nav="sub"
                                :class="[
                                    activeCustomTopSubIndex === sIndex
                                        ? `navActive`
                                        : 'navInactive'
                                ]"
                                aclick="changeActiveCustomTopSubIndex(sIndex)"
                                :internalHref
                                :activeSubIndex
                                :closeSidebar
                            />

                            <template v-for="(sub, sIndex) in sortedSubDepartments?.[activeSubIndex]?.collections" :key="sIndex">
                                <SidebarMobileNavigation
                                    :nav="sub"
                                    :activeSubIndex
                                    :closeSidebar
                                    :internalHref
                                />
                            </template>

                        </div>
                    </Transition>

                    <!-- 3: Families: Bottom -->
                    <div v-if="activeCustomSubIndex !== null && customFamilies?.length">
                        <div v-for="(child, cIndex) in customFamilies" :key="cIndex" class="p-2 px-4">
                            <LinkIris
                                v-if="child.url !== null"
                                :href="child.type === 'internal' ? internalHref(child) : child.url"
                                class="hover:underline"
                                @success="() => closeSidebar()"
                                :target="getTarget(child)"
                                :type="child.type"
                            >
                                {{ child.name }}
                            </LinkIris>
                            <span v-else>{{ child.name }}</span>
                        </div>
                    </div>


                    <!-- Collections: from Sub Department -->
                    <!-- <template v-if="sortedSubDepartments?.[activeSubIndex]?.collections?.length">
                        <div v-if="activeIndex !== null" class="borderTopColorSameAsText flex items-center justify-between mt-2 pt-4 pb-2 px-4">
                            <h3 class="font-semibold">{{ trans("Collections") }}</h3>
                        </div>
                        <div class="">
                            <div>
                                
                            </div>
                        </div>
                    </template> -->
                </div>
            </div>

            <!-- Column 2: Subdepartments -->
            <div v-else-if="activeIndex !== null || activeCustomIndex !== null || activeCustomTopIndex !== null"
                :class="[(activeSubIndex !== null || activeCustomSubIndex !== null || activeCustomTopSubIndex !== null) && 'border-r']">
                <div @click="setActiveCategory(null), setActiveCustomCategory(null), setActiveCustomTopCategory(null)" class="py-1">
                    <FontAwesomeIcon icon="fal fa-chevron-left" class="text-xs" fixed-width aria-hidden="true" />
                    <!-- Back to menu list -->
                    {{ sortedProductCategories?.[activeIndex]?.name }}
                    {{ customMenusTop?.[activeCustomTopIndex]?.name }}
                    {{ customMenusBottom?.[activeCustomIndex]?.name }}
                </div>

                <!-- Header -->
                <!-- <div v-if="activeIndex !== null" class="flex items-center justify-between pt-4 pb-2 px-4">
                    <h3 class="font-semibold">{{ trans("Sub-Departments") }}</h3>
                </div> -->
                <div class="overflow-y-auto">
                    <!-- Section: Subdepartments (Top) -->
                    <div v-if="activeCustomTopIndex !== null && customTopSubDepartments?.length">
                        <SidebarMobileNavigation
                            v-for="(sub, sIndex) in customTopSubDepartments" :key="sIndex"
                            :nav="sub"
                            :class="[
                                activeCustomTopSubIndex === sIndex
                                    ? `navActive`
                                    : 'navInactive'
                            ]"
                            @click="changeActiveCustomTopSubIndex(sIndex)"
                            :internalHref
                            :activeSubIndex
                            :closeSidebar
                            isWithArrowRight
                        />
                    </div>

                    <!-- Section: SubDepartments (Auto Product Categories) -->
                    <div v-if="activeIndex !== null && sortedSubDepartments?.length">
                        
                        <div class="mt-1 pt-1 pb-2 px-4">
                            <LinkIris :href="sortedProductCategories[activeIndex].url">
                                <template #default="{ isLoading }">
                                    <Button
                                        :label="trans('View all')"
                                        :icon="faExternalLink"
                                        :size="screenType === 'mobile' ? 'm' : 'xs'"
                                        :loading="isLoading"
                                        class="cursor-pointer"
                                    />
                                </template>
                            </LinkIris>
                        </div>

                        <template
                            v-for="(sub, sIndex) in sortedSubDepartments" :key="sIndex">
                            <SidebarMobileNavigation
                                :nav="sub"
                                :class="[
                                    activeSubIndex === sIndex
                                        ? `navActive`
                                        : sub?.families?.length
                                            ? 'navInactive'
                                            : ''
                                ]"
                                @click="sub?.families?.length ? changeActiveSubIndex(sIndex) : false"
                                :internalHref
                                :activeSubIndex
                                :closeSidebar
                                :isWithArrowRight="!!sub?.families?.length"
                            />
                        </template>

                        <!-- Collections (from Department) -->
                        <template v-for="(sub, sIndex) in sortedProductCategories[activeIndex]?.collections" :key="sIndex">
                            <SidebarMobileNavigation
                                :nav="sub"
                                xclass="[
                                    activeCustomSubIndex === sIndex
                                        ? `navActive`
                                        : 'navInactive'
                                ]"
                                :internalHref
                                :activeSubIndex
                                :closeSidebar
                            />
                        </template>
                    </div>

                    <!-- Section: Subdepartments (Bottom) -->
                    <div v-if="activeCustomIndex !== null && customSubDepartments?.length">
                        <SidebarMobileNavigation
                            v-for="(sub, sIndex) in customSubDepartments" :key="sIndex"
                            :nav="sub"
                            :class="[
                                activeCustomSubIndex === sIndex
                                    ? `navActive`
                                    : 'navInactive'
                            ]"
                            @click="changeActiveCustomSubIndex(sIndex)"
                            :internalHref
                            :activeSubIndex
                            :closeSidebar
                            isWithArrowRight
                        />
                    </div>

                    
                    <!-- No subdepartments message -->
                    <div v-if="(activeIndex !== null && !sortedSubDepartments?.length) || (activeCustomIndex !== null && !customSubDepartments?.length) || (activeCustomTopIndex !== null && !customTopSubDepartments?.length)"
                        class="px-4 text-gray-400 italic">
                        {{ trans("No subdepartments available") }}
                    </div>
                </div>

                <!-- Collections: from Department -->
                <!-- <template v-if="sortedProductCategories?.[activeIndex]?.collections.length">
                    <div v-if="activeIndex !== null" class="borderTopColorSameAsText flex items-center justify-between mt-2 pt-4 pb-2 px-4">
                        <h3 class="font-semibold">{{ trans("Collections") }}</h3>
                    </div>
                    <div class="">
                        
                    </div>
                </template> -->
            </div>

            <!-- Section: Navigation links (Departments) -->
            <div v-else class="flex flex-col h-full">
                <div class="flex-1 overflow-y-auto pb-4 mb-4">
                    <!-- Section: Custom Top -->
                    <div v-if="customMenusTop?.length > 0">
                        <div v-for="(customTopItem, customTopIndex) in customMenusTop" :key="'custom-top-' + customTopIndex" class="flex justify-between items-center w-full text-left px-2 py-2 font-semibold borderBottomColorSameAsText">
                            <LinkIris v-if="customTopItem?.url !== null"
                                :href="customTopItem.type === 'internal' ? internalHref(customTopItem) : customTopItem.url"
                                :target="getTarget(customTopItem)"
                                @success="() => closeSidebar()"
                                class="font-bold">
                                {{ customTopItem.name }}
                            </LinkIris>
                            <span v-else
                                class="font-bold">
                                {{ customTopItem.name }}
                            </span>

                            <div v-if="!!customTopItem.sub_departments?.length" @click="setActiveCustomTopCategory(customTopIndex)" class="text-sm">
                                <FontAwesomeIcon :icon="faChevronRight" fixed-width  />
                            </div>
                        </div>
                    </div>

                    <!-- Section: Product Categories (auto) -->
                    <div
                        v-for="(category, index) in sortedProductCategories"
                        :key="'product_categories' + index"
                        class="flex justify-between items-center w-full text-left px-2 py-2 font-semibold borderBottomColorSameAsText"
                        @click="setActiveCategory(index)"
                    >
                        <!-- <LinkIris v-if="category?.url !== null"
                            :href="internalHref(category)"
                            :target="getTarget(category)"
                            @success="() => closeSidebar()"
                            class="font-bold">
                            {{ category.name }}
                        </LinkIris>
                        <span v-else
                            class="font-bold">
                            {{ category.name }}
                        </span> -->
                        <span class="font-bold">
                            {{ category.name }}
                        </span>

                        <div v-if="!!category.sub_departments?.length" class="text-sm">
                            <FontAwesomeIcon :icon="faChevronRight" fixed-width  />
                        </div>
                    </div>

                    
                    <!-- Section: Custom Bottom -->
                    <div v-for="(customBot, customIdxBot) in customMenusBottom" :key="'custom-bot' + customIdxBot" class="flex justify-between items-center w-full text-left px-2 py-2 font-semibold borderBottomColorSameAsText">
                        <LinkIris v-if="customBot?.url !== null"
                            :href="customBot.type === 'internal' ? internalHref(customBot) : customBot.url"
                            :target="getTarget(customBot)"
                            @success="() => closeSidebar()"
                            class="font-bold">
                            {{ customBot.name }}
                        </LinkIris>
                        <span v-else
                            class="font-bold">
                            {{ customBot.name }}
                        </span>

                        <div v-if="!!customBot.sub_departments?.length" @click="setActiveCustomCategory(customIdxBot)" class="text-sm">
                            <FontAwesomeIcon :icon="faChevronRight" fixed-width  />
                        </div>
                    </div>
                </div>

                <!-- Section: List additional links -->
                <div v-if="props?.fieldValue?.additional_items?.items_list?.length" class="flex flex-col gap-y-3 mb-8">
                    <LinkIris v-for="item in props?.fieldValue?.additional_items?.items_list"
                        :href="item?.url?.href ?? ''"
                        class="flex gap-x-2 items-center py-1"
                        :type="item.url?.type"
                        :target="item.url?.target"
                    >
                        <FontAwesomeIcon :icon="item.icon" class="text-xl" fixed-width aria-hidden="true" />
                        <div class="text-sm" v-html="item.text">
                        </div>
                    </LinkIris>
                </div>

                <!-- Switch Language -->
                <div v-if="layout.app.environment !== 'production' && Object.values(layout.iris.website_i18n?.language_options || {})?.length" class="borderTopColorSameAsText px-1 mb-1 flex justify-between items-center text-xs">
                    <div>{{ trans("Language") }}:</div>
                    <SwitchLanguage>
                        <template #default="{ isLoadingChangeLanguage }">
                            <div class="underline text-xs py-2">
                                {{ Object.values(layout.iris.website_i18n?.language_options || {})?.find(language => language.code === layout.iris.website_i18n.current_language?.code)?.name }}
                                <img class="inline pr-1 pl-1 h-[1em]" :src="`/flags/${layout.iris.website_i18n.current_language?.flag}`" :alt="layout.iris.website_i18n.current_language?.code" title='capitalize(countryName)'  />
                            </div>
                        </template>
                    </SwitchLanguage>
                </div>
                
                <!-- Login / Logout -->
                <div class="login-section pl-3 pr-5 py-4 borderTopColorSameAsText flex items-center">
                    <LinkIris v-if="!isLoggedIn" :href="urlLoginWithRedirect()" class="w-full" type="internal">
                        <Button
                            :label="trans('Login')"
                            full
                            :icon="faSignIn"
                        />
                    </LinkIris>
                    <div v-else @click="onLogout()" class="w-full">
                        <Button
                            type="negative"
                            :label="trans('Logout')"
                            full
                            :icon="faSignOut"
                        />
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped lang="scss">
// .menu-container-mobile {
//     display: flex;
//     flex-direction: column;
//     height: 100%;
//     // background: #fff;
// }

// .menu-content {
//     flex: 1;
//     overflow-y: auto;
//     padding-bottom: 1rem;
// }

.login-section {
    // flex-shrink: 0;
    // padding: 1rem 1.25rem;
    // border-top: 1px solid #e5e5e5;
    // display: flex;
    // align-items: center;
    // justify-content: flex-start;

    a,
    div {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        font-size: 0.95rem;
        transition: color 0.25s ease;
    }

    a:hover {
        color: #2563eb;
        /* primary hover */
    }

    div:hover {
        color: #dc2626;
        /* logout hover */
    }
}

.borderTopColorSameAsText {
    border-top: v-bind('`${borderWidth} solid rgba(0, 0, 0, 0.5)`'); /* fallback */
    border-color: v-bind('props.containerStyle?.border?.color || "color-mix(in srgb, currentColor 30%, transparent)"');
}

.borderBottomColorSameAsText {
    border-bottom: v-bind('`${borderWidth} solid rgba(0, 0, 0, 0.5)`'); /* fallback */
    border-color: v-bind('props.containerStyle?.border?.color || "color-mix(in srgb, currentColor 30%, transparent)"');
}

.disclosure-panel {
    padding: 0.75rem 1rem 1rem;
    @apply borderBottomColorSameAsText;

}

.disclosure-panel a {
    display: block;
    transition: color 0.2s ease;
}

.disclosure-panel a:hover {
    text-decoration: underline;
    color: #2563eb;
}
</style>