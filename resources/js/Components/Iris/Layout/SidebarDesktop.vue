<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronRight, faExternalLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject, ref } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import Button from '@/Components/Elements/Buttons/Button.vue'
import LinkIris from '../LinkIris.vue'
import { router } from '@inertiajs/vue3'
import { ProductCategoryMenu } from '@/Composables/Iris/useMenu'
import { getStyles } from '@/Composables/styles'
import SidebarDesktopNavigation from './SidebarDesktopNavigation.vue'
library.add(faChevronRight, faExternalLink)

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
}>()

const emit = defineEmits<{
    closeMobileMenu: []
}>()


const layout = inject('layout', retinaLayoutStructure)

// Loading states for View all buttons
const isLoadingProductCategory = ref(false)
const isLoadingSubDepartment = ref(false)

// Handle navigation with loading state
const handleViewAllProductCategory = (url: string) => {
    isLoadingProductCategory.value = true
    router.visit('/' + url, {
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
    router.visit('/' + url, {
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

const isOpenMenuMobile = inject('isOpenMenuMobile', ref(false));
const closeSidebar = () => {
    isOpenMenuMobile.value = false;
}

const combinedStyleSidebarAndWebpage = {
    ...getStyles(layout?.app?.webpage_layout?.container?.properties),
    ...getStyles(props.containerStyle)
}
function removeImportant(styles) {
    const cleaned = {}
    for (const [key, value] of Object.entries(styles)) {
        cleaned[key] = value.replace(/\s*!important\s*/gi, "")
    }
    return cleaned
}
const stylingWithoutImportant = removeImportant(combinedStyleSidebarAndWebpage)
const backgroundColorNoGradient = props.containerStyle.background?.color || layout?.app?.webpage_layout?.container?.properties?.background?.color?.replace(/\s*!important\s*/gi, "") || '#030712'

</script>

<template>
    <div class="grid h-full" :class="[
        (activeIndex !== null || activeCustomIndex !== null || activeCustomTopIndex !== null) && 'grid-cols-2',
        (activeSubIndex !== null || activeCustomSubIndex !== null || activeCustomTopSubIndex !== null) && 'grid-cols-3']"
    >
   
        <!-- Column 1: Categories + Custom Menus -->
        <div :class="[(activeIndex !== null || activeCustomIndex !== null || activeCustomTopIndex !== null) && 'border-r', 'overflow-y-auto']">
            <!-- Sidebar: Top navigation -->
            <div v-if="customMenusTop && customMenusTop.length > 0">
                <SidebarDesktopNavigation
                    v-for="(sub, sIndex) in customMenusTop" :key="sIndex"
                    :nav="sub"
                    :class="[
                        activeCustomTopIndex === sIndex
                            ? `navActive`
                            : 'navInactive'
                    ]"
                    @click="sub.sub_departments && sub.sub_departments.length > 0 ? setActiveCustomTopCategory(sIndex) : null"
                    :internalHref
                    :activeSubIndex
                    :closeSidebar
                    :isWithArrowRight="sub.sub_departments && sub.sub_departments.length > 0"
                />
                <hr class="mt-4 border-gray-200">
            </div>

            
            <!-- Section: Auto Product Categories List -->
            <div class="flex items-center justify-between px-2 py-4 border-b">
                <h3 class="font-semibold text-sm">{{ trans("Departments") }}</h3>
            </div>
            <SidebarDesktopNavigation
                v-for="(sub, sIndex) in sortedProductCategories" :key="sIndex"
                :nav="sub"
                :class="[
                    activeIndex === sIndex
                        ? ` cursor-pointer navActive`
                        : sub.sub_departments?.length
                            ? 'navInactive'
                            : ''
                ]"
                @click="sub.sub_departments?.length ? setActiveCategory(sIndex) : false"
                :internalHref
                :activeSubIndex
                :closeSidebar
                :isWithArrowRight="!!sub.sub_departments?.length"
            />

            <!-- Section: Bottom navigation -->
            <div v-if="customMenusBottom && customMenusBottom.length > 0">
                <hr class="my-4 border-gray-300">
                <SidebarDesktopNavigation
                    v-for="(sub, sIndex) in customMenusBottom" :key="sIndex"
                    :nav="sub"
                    :class="[
                        activeCustomIndex === sIndex
                            ? `navActive`
                            : 'navInactive'
                    ]"
                    @click="sub.sub_departments && sub.sub_departments.length > 0 ? setActiveCustomCategory(sIndex) : null"
                    :internalHref
                    :activeSubIndex
                    :closeSidebar
                    :isWithArrowRight="sub.sub_departments && sub.sub_departments.length > 0"
                />
            </div>
        </div>

        <!-- Column 2: Subdepartments -->
        <div v-if="activeIndex !== null || activeCustomIndex !== null || activeCustomTopIndex !== null"
            :class="[(activeSubIndex !== null || activeCustomSubIndex !== null || activeCustomTopSubIndex !== null) && 'border-r']">
            <!-- Header -->
            <div v-if="activeIndex !== null" class="flex items-center justify-between py-4 px-4">
                <h3 class="font-semibold text-sm">{{ trans("Sub-Departments") }}</h3>
            </div>

            <div class="overflow-y-auto">
                <!-- Section: Subdepartments (Top) -->
                <div v-if="activeCustomTopIndex !== null && customTopSubDepartments?.length">
                    <SidebarDesktopNavigation
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
                    <SidebarDesktopNavigation
                        v-for="(sub, sIndex) in sortedSubDepartments" :key="sIndex"
                        :nav="sub"
                        :class="[
                            activeSubIndex === sIndex
                                ? `navActive`
                                : 'navInactive'
                        ]"
                        @click="changeActiveSubIndex(sIndex)"
                        :internalHref
                        :activeSubIndex
                        :closeSidebar
                        isWithArrowRight
                    />

                    <div class="p-2 px-4">
                        <Button 
                            :label="trans('View all')" 
                            :icon="faExternalLink"
                            size="xs"
                            :loading="isLoadingProductCategory"
                            @click="handleViewAllProductCategory(sortedProductCategories[activeIndex].url)"
                        />
                    </div>
                </div>

                <!-- Section: Subdepartments (Bottom) -->
                <div v-if="activeCustomIndex !== null && customSubDepartments?.length">
                    <SidebarDesktopNavigation
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
            <template v-if="sortedProductCategories?.[activeIndex]?.collections.length">
                <div v-if="activeIndex !== null" class="border-t bodashe border-gray-300 flex items-center justify-between mt-2 pt-4 pb-2 px-4">
                    <h3 class="font-semibold text-sm">{{ trans("Collections") }}</h3>
                </div>
                <div class="">
                    <div>
                        <template v-for="(sub, sIndex) in sortedProductCategories[activeIndex]?.collections" :key="sIndex">
                            <SidebarDesktopNavigation
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
                </div>
            </template>
        </div>

        <!-- Column 3: Families -->
        <div v-if="activeSubIndex !== null || activeCustomSubIndex !== null || activeCustomTopSubIndex !== null">
            <!-- Header -->
            <div  v-if="activeSubIndex !== null" class="flex items-center justify-between p-4">
                <h3 class="font-semibold text-sm">{{ trans("Families") }}</h3>
            </div>

            <div class="overflow-y-auto">
                <!-- Families: Top -->
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

                <!-- Families: Product Categories -->
                <Transition name="slide-to-right">
                    <div v-if="activeSubIndex !== null && sortedFamilies.length">
                        <SidebarDesktopNavigation
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
                        <div class="p-2 px-4">
                            <Button
                                :label="trans('View all')"
                                :icon="faExternalLink"
                                size="xs"
                                :loading="isLoadingSubDepartment"
                                @click="handleViewAllSubDepartment(sortedSubDepartments[activeSubIndex].url)"
                                class="cursor-pointer"
                            />
                        </div>
                    </div>
                </Transition>

                <!-- Families: Bottom -->
                <div v-if="activeCustomSubIndex !== null && customFamilies?.length">
                    <div v-for="(child, cIndex) in customFamilies" :key="cIndex" class="p-2 px-4">
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


                <!-- Collections: from Sub Department -->
                <template v-if="sortedSubDepartments?.[activeSubIndex]?.collections?.length">
                    <div v-if="activeIndex !== null" class="border-t border-gray-300 flex items-center justify-between mt-2 pt-4 pb-2 px-4">
                        <h3 class="font-semibold text-sm">{{ trans("Collections") }}</h3>
                    </div>
                    <div class="">
                        <div>
                            <template v-for="(sub, sIndex) in sortedSubDepartments?.[activeSubIndex]?.collections" :key="sIndex">
                                <SidebarDesktopNavigation
                                    :nav="sub"
                                    :activeSubIndex
                                    :closeSidebar
                                    :internalHref
                                />
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<style lang="scss">
.navInactive {
    @apply cursor-pointer;

    &:hover {
        // color: v-bind('stylingWithoutImportant.color');
        background: color-mix(in srgb, v-bind('stylingWithoutImportant.color || "#030712"') 15%, transparent);
    }
}

.navActive {
    @apply cursor-pointer;

    color: v-bind('backgroundColorNoGradient || "#ffffff"');
    background: v-bind('stylingWithoutImportant.color || "#030712"');
}
</style>