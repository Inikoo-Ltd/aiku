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
        }
    }[]
    customTopSubDepartments: []
    customMenusBottom: {}
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

const styling = {
    ...getStyles(layout?.app?.webpage_layout?.container?.properties),
    ...getStyles(props.containerStyle)
}
function stripImportant(styles) {
    const cleaned = {}
    for (const [key, value] of Object.entries(styles)) {
        cleaned[key] = value.replace(/\s*!important\s*/gi, "")
    }
    return cleaned
}
const stylingWithoutImportant = stripImportant(styling)

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
                <div v-for="(customTopItem, customTopIndex) in customMenusTop" :key="'custom-top-' + customTopIndex"
                    class="p-2 px-4 flex items-center justify-between cursor-pointer"
                    :class="[
                        activeCustomTopIndex === customTopIndex
                            ? `navActive`
                            : 'navInactive'
                    ]"
                    @click="customTopItem.sub_departments && customTopItem.sub_departments.length > 0 ? setActiveCustomTopCategory(customTopIndex) : null">
                    <div>
                        <LinkIris
                            v-if="(!customTopItem.sub_departments || customTopItem.sub_departments.length === 0) && customTopItem.url !== null"
                            :href="internalHref(customTopItem)"
                            class="hover:underline"
                            @success="() => closeSidebar()"
                            :type="customTopItem.type"
                            :target="customTopItem.target"
                        >
                            {{ customTopItem.name }}
                        </LinkIris>
                        <span v-else>{{ customTopItem.name }}</span>
                    </div>
                    <FontAwesomeIcon v-if="customTopItem.sub_departments && customTopItem.sub_departments.length > 0"
                        :icon="faChevronRight" fixed-width class="text-xs" />
                </div>
                <hr class="mt-4 border-gray-200">
            </div>

            <!-- Header -->
            <div class="flex items-center justify-between px-2 py-4 border-b">
                <h3 class="font-semibold text-sm">{{ trans("Departments") }}</h3>
            </div>

            <!-- Product Categories List -->
            <div v-for="(item, index) in sortedProductCategories" :key="index"
                class="p-2 px-4 flex items-center justify-between"
                :class="[
                    activeIndex === index
                        ? ` cursor-pointer navActive`
                        : item.sub_departments?.length
                            ? 'navInactive'
                            : ''
                ]" @click="item.sub_departments?.length ? setActiveCategory(index) : false">
                <LinkIris
                    v-if="item.url"
                    :href="internalHref(item)"
                    class="hover:underline"
                    @success="() => closeSidebar()"
                    :type="item.type"
                    :target="item.target"
                >
                    {{ item.name }}
                </LinkIris>
                <div v-else>
                    {{ item.name }}
                </div>
                <FontAwesomeIcon v-if="item.sub_departments?.length" :icon="faChevronRight" fixed-width class="text-xs" />
            </div>

            <!-- Section: Bottom navigation -->
            <div v-if="customMenusBottom && customMenusBottom.length > 0">
                <hr class="my-4 border-gray-300">
                <div v-for="(customItem, customIndex) in customMenusBottom" :key="'custom-' + customIndex"
                    class="p-2 px-4 flex items-center justify-between cursor-pointer"
                    :class="[
                        activeCustomIndex === customIndex
                            ? 'navActive'
                            : 'navInactive'
                    ]"
                    @click="customItem.sub_departments && customItem.sub_departments.length > 0 ? setActiveCustomCategory(customIndex) : null">
                    <div>
                        <LinkIris
                            v-if="(!customItem.sub_departments || customItem.sub_departments.length === 0) && customItem.url !== null"
                            :href="internalHref(customItem)"
                            class="hover:underline"
                            @success="() => closeSidebar()"
                            :type="customItem.type"
                            :target="customItem.target"
                        >
                            {{ customItem.name }}
                        </LinkIris>
                        <span v-else>{{ customItem.name }}</span>
                    </div>
                    <FontAwesomeIcon v-if="customItem.sub_departments && customItem.sub_departments.length > 0"
                        :icon="faChevronRight" fixed-width class="text-xs" />
                </div>
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
                <!-- Product Categories Subdepartments -->
                <div v-if="activeIndex !== null && sortedSubDepartments?.length">
                    <div v-for="(sub, sIndex) in sortedSubDepartments" :key="sIndex"
                        class="p-2 px-4 flex items-center justify-between cursor-pointer"
                        :class="[
                            activeSubIndex === sIndex
                                ? `navActive`
                                : 'navInactive'
                        ]" @click="changeActiveSubIndex(sIndex)">
                        <LinkIris
                            v-if="sub.url"
                            :href="internalHref(sub)"
                            class="hover:underline"
                            @success="() => closeSidebar()"
                            :type="sub.type"
                            :target="sub.target"
                        >
                            {{ sub.name }}
                        </LinkIris>
                        <div v-else>
                            {{ sub.name }}
                        </div>
                        <FontAwesomeIcon :icon="faChevronRight" fixed-width class="text-xs" />
                    </div>

                    <div class="p-2 px-4 font-bold">
                        <Button 
                            :label="trans('View all')" 
                            :icon="faExternalLink" 
                            size="xs" 
                            :loading="isLoadingProductCategory"
                            @click="handleViewAllProductCategory(sortedProductCategories[activeIndex].url)"
                            class="cursor-pointer"
                        />
                    </div>
                </div>

                <!-- Section: Bottom (Subdepartments) -->
                <div v-if="activeCustomIndex !== null && customSubDepartments?.length">
                    <div v-for="(sub, sIndex) in customSubDepartments" :key="sIndex"
                        class="p-2 px-4 flex items-center justify-between cursor-pointer"
                        :class="[
                            activeCustomSubIndex === sIndex
                            ? 'navActive'
                            : 'navInactive'
                        ]" @click="changeActiveCustomSubIndex(sIndex)">
                        <div>
                            <LinkIris
                                v-if="(!sub.families || sub.families.length === 0) && sub.url !== null"
                                :href="internalHref(sub)"
                                class="hover:underline"
                                @success="() => closeSidebar()"
                                :target="getTarget(sub)"
                                :type="sub.type"
                            >
                                {{ sub.name }}
                            </LinkIris>
                            <span v-else>{{ sub.name }}</span>
                        </div>
                        <FontAwesomeIcon :icon="faChevronRight" fixed-width class="text-xs" />
                    </div>
                </div>

                <!-- Section: Top (Subdepartments) -->
                <div v-if="activeCustomTopIndex !== null && customTopSubDepartments?.length">
                    <div v-for="(sub, sIndex) in customTopSubDepartments" :key="sIndex"
                        class="p-2 px-4 flex items-center justify-between cursor-pointer"
                        :class="[
                            activeCustomTopSubIndex === sIndex
                                ? `navActive`
                                : 'navInactive'
                        ]" @click="changeActiveCustomTopSubIndex(sIndex)">
                        <div>
                            <LinkIris
                                v-if="(!sub.families || sub.families.length === 0) && sub.url !== null"
                                :href="internalHref(sub)"
                                class="hover:underline"
                                @success="() => closeSidebar()"
                                :target="getTarget(sub)"
                                :type="sub.type"
                            >
                                {{ sub.name }}
                            </LinkIris>
                            <span v-else>{{ sub.name }}</span>
                        </div>
                        <FontAwesomeIcon :icon="faChevronRight" fixed-width class="text-xs" />
                    </div>
                </div>

                <!-- No subdepartments message -->
                <div v-if="(activeIndex !== null && !sortedSubDepartments?.length) || (activeCustomIndex !== null && !customSubDepartments?.length) || (activeCustomTopIndex !== null && !customTopSubDepartments?.length)"
                    class="px-4 text-gray-400 italic">
                    {{ trans("No subdepartments available") }}
                </div>
            </div>
        </div>

        <!-- Column 3: Families -->
        <div v-if="activeSubIndex !== null || activeCustomSubIndex !== null || activeCustomTopSubIndex !== null">
            <!-- Header -->
            <div  v-if="activeSubIndex !== null" class="flex items-center justify-between p-4">
                <h3 class="font-semibold text-sm">{{ trans("Families") }}</h3>
            </div>

            <div class="overflow-y-auto">
                <!-- Product Categories Families -->
                <div v-if="activeSubIndex !== null && sortedFamilies.length">
                    <div v-for="(child, cIndex) in sortedFamilies" :key="cIndex"
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

                <!-- Section: Bottom (Families) -->
                <div v-if="activeCustomSubIndex !== null && customFamilies?.length">
                    <div v-for="(child, cIndex) in customFamilies" :key="cIndex"
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

                <!-- Section: Top (Families) -->
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

                <!-- No families message -->
                <div v-if="(activeSubIndex !== null && !sortedFamilies.length) || (activeCustomSubIndex !== null && !customFamilies.length) || (activeCustomTopSubIndex !== null && !customTopFamilies.length)"
                    class="p-2 text-gray-400 italic">
                    {{ trans("No further items") }}
                </div>
            </div>
        </div>
    </div>
</template>

<style lang="scss">
.navActive {
    @apply cursor-pointer;

    color: v-bind('stylingWithoutImportant.background');
    background: v-bind('stylingWithoutImportant.color');
}
.navInactive {
    @apply cursor-pointer;

    &:hover {
        // color: v-bind('stylingWithoutImportant.color');
        background: color-mix(in srgb, v-bind('stylingWithoutImportant.color') 15%, transparent);


    }
}
</style>