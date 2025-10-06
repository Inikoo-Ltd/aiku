<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronRight, faExternalLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import Button from '@/Components/Elements/Buttons/Button.vue'
library.add(faChevronRight, faExternalLink)

const props = defineProps<{
    productCategories: {}
    customMenusTop: {}
    customTopSubDepartments: []
    customMenusBottom: {}
    customSubDepartments: []
    activeIndex: {}
    activeCustomIndex: {}
    activeCustomTopIndex: {}
    getHref: Function
    getTarget: Function
    setActiveCategory: Function
    setActiveCustomCategory: Function
    setActiveCustomTopCategory: Function
    sortedFamilies: {}
    customFamilies: {}
    customTopFamilies: {}
    sortedProductCategories: {}[]
    sortedSubDepartments: {}[]
    activeSubIndex: {}
    activeCustomSubIndex: {}
    activeCustomTopSubIndex: {}
    changeActiveSubIndex: Function
    changeActiveCustomSubIndex: Function
    changeActiveCustomTopSubIndex: Function
}>()


const layout = inject('layout', retinaLayoutStructure)

</script>

<template>
    <div class="grid h-full bg-white" :class="[
        (activeIndex !== null || activeCustomIndex !== null || activeCustomTopIndex !== null) && 'grid-cols-2',
        (activeSubIndex !== null || activeCustomSubIndex !== null || activeCustomTopSubIndex !== null) && 'grid-cols-3']"
    >
   
        <!-- Column 1: Categories + Custom Menus -->
        <div :class="[(activeIndex !== null || activeCustomIndex !== null || activeCustomTopIndex !== null) && 'border-r', 'overflow-y-auto']">
             
            <!-- Sidebar: Top for Desktop -->
            <div v-if="customMenusTop && customMenusTop.length > 0">
                <div v-for="(customTopItem, customTopIndex) in customMenusTop" :key="'custom-top-' + customTopIndex"
                    class="p-2 px-4 flex items-center justify-between cursor-pointer"
                    :class="[
                        activeCustomTopIndex === customTopIndex
                            ? `bg-gray-100 font-semibold text-[${layout.iris.theme?.color[0]}]`
                            : ' hover:bg-gray-50'
                    ]"
                    @click="customTopItem.sub_departments && customTopItem.sub_departments.length > 0 ? setActiveCustomTopCategory(customTopIndex) : null">
                    <div>
                        <a v-if="(!customTopItem.sub_departments || customTopItem.sub_departments.length === 0) && customTopItem.url !== null"
                            :href="getHref(customTopItem)" :target="getTarget(customTopItem)" class="block">
                            {{ customTopItem.name }}
                        </a>
                        <span v-else>{{ customTopItem.name }}</span>
                    </div>
                    <FontAwesomeIcon v-if="customTopItem.sub_departments && customTopItem.sub_departments.length > 0"
                        :icon="faChevronRight" class="text-xs" />
                </div>
                <hr class="mt-4 border-gray-200">
            </div>

            <!-- Header -->
            <div class="flex items-center justify-between px-2 py-4 border-b">
                <h3 class="font-semibold text-sm">{{ trans("Departments") }}</h3>
            </div>

            <!-- Product Categories List -->
            <div v-for="(item, index) in sortedProductCategories" :key="index"
                class="p-2 px-4 flex items-center justify-between cursor-pointer"
                :class="[
                    activeIndex === index
                        ? `bg-gray-100 font-semibold text-[${layout.iris.theme?.color[0]}]`
                        : ' hover:bg-gray-50'
                ]" @click="setActiveCategory(index)">
                <div>{{ item.name }}</div>
                <FontAwesomeIcon :icon="faChevronRight" class="text-xs" />
            </div>

            <!-- Custom Menus Section for Desktop -->
            <div v-if="customMenusBottom && customMenusBottom.length > 0">
                <hr class="my-4 mx-4 border-gray-300">
                <div v-for="(customItem, customIndex) in customMenusBottom" :key="'custom-' + customIndex"
                    class="p-2 px-4 flex items-center justify-between cursor-pointer"
                    :class="[
                        activeCustomIndex === customIndex
                            ? `bg-gray-100 font-semibold text-[${layout.iris.theme?.color[0]}]`
                            : ' hover:bg-gray-50'
                    ]"
                    @click="customItem.sub_departments && customItem.sub_departments.length > 0 ? setActiveCustomCategory(customIndex) : null">
                    <div>
                        <a v-if="(!customItem.sub_departments || customItem.sub_departments.length === 0) && customItem.url !== null"
                            :href="getHref(customItem)" :target="getTarget(customItem)" class="block">
                            {{ customItem.name }}
                        </a>
                        <span v-else>{{ customItem.name }}</span>
                    </div>
                    <FontAwesomeIcon v-if="customItem.sub_departments && customItem.sub_departments.length > 0"
                        :icon="faChevronRight" class="text-xs" />
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
                <div v-if="activeIndex !== null && sortedSubDepartments.length">
                    <div v-for="(sub, sIndex) in sortedSubDepartments" :key="sIndex"
                        class="p-2 px-4 flex items-center justify-between cursor-pointer"
                        :class="[
                            activeSubIndex === sIndex
                                ? `bg-gray-100 font-semibold text-[${layout.iris.theme?.color[0]}]`
                                : 'hover:bg-gray-50 text-gray-700'
                        ]" @click="changeActiveSubIndex(sIndex)">
                        <div>{{ sub.name }}</div>
                        <FontAwesomeIcon :icon="faChevronRight" fixed-width class="text-xs" />
                    </div>

                    <div class="p-2 px-4 font-bold">
                        <a :href="'/' + sortedProductCategories[activeIndex].url" class="cursor-pointer">
                            <Button :label="trans('View all')" :icon="faExternalLink" size="xs" />
                        </a>
                    </div>
                </div>

                <!-- Custom Menus Subdepartments -->
                <div v-if="activeCustomIndex !== null && customSubDepartments.length">
                    <div v-for="(sub, sIndex) in customSubDepartments" :key="sIndex"
                        class="p-2 px-4 flex items-center justify-between cursor-pointer"
                        :class="[
                            activeCustomSubIndex === sIndex
                                ? `bg-gray-100 font-semibold text-[${layout.iris.theme?.color[0]}]`
                                : 'hover:bg-gray-50 text-gray-700'
                        ]" @click="changeActiveCustomSubIndex(sIndex)">
                        <div>
                            <a v-if="(!sub.families || sub.families.length === 0) && sub.url !== null"
                                :href="getHref(sub)" :target="getTarget(sub)" class="block">
                                {{ sub.name }}
                            </a>
                            <span v-else>{{ sub.name }}</span>
                        </div>
                        <FontAwesomeIcon :icon="faChevronRight" fixed-width class="text-xs" />
                    </div>
                    <!-- <div class="p-2 px-4  cursor-pointer font-bold">
                        <a :href="'/' + customMenus[activeCustomIndex].url">
                            <Button label="View all" :icon="faExternalLink" size="xs" />
                        </a>
                    </div> -->
                </div>

                <!-- Custom Top Menus Subdepartments -->
                <div v-if="activeCustomTopIndex !== null && customTopSubDepartments?.length">
                    <div v-for="(sub, sIndex) in customTopSubDepartments" :key="sIndex"
                        class="p-2 px-4 flex items-center justify-between cursor-pointer"
                        :class="[
                            activeCustomTopSubIndex === sIndex
                                ? `bg-gray-100 font-semibold text-[${layout.iris.theme?.color[0]}]`
                                : 'hover:bg-gray-50 text-gray-700'
                        ]" @click="changeActiveCustomTopSubIndex(sIndex)">
                        <div>
                            <a v-if="(!sub.families || sub.families.length === 0) && sub.url !== null"
                                :href="getHref(sub)" :target="getTarget(sub)" class="block">
                                {{ sub.name }}
                            </a>
                            <span v-else>{{ sub.name }}</span>
                        </div>
                        <FontAwesomeIcon :icon="faChevronRight"
                            class="text-xs" />
                    </div>
                </div>

                <!-- No subdepartments message -->
                <div v-if="(activeIndex !== null && !sortedSubDepartments?.length) || (activeCustomIndex !== null && !customSubDepartments?.length) || (activeCustomTopIndex !== null && !customTopSubDepartments?.length)"
                    class="p-2 text-gray-400 italic">
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
                        class="p-2 px-4  cursor-pointer hover:bg-gray-50">
                        <a :href="'/' + child.url">{{ child.name }}</a>
                    </div>
                    <div class="p-2 px-4  cursor-pointer hover:bg-gray-50 font-bold">
                        <a :href="'/' + sortedSubDepartments[activeSubIndex].url">
                            <Button :label="trans('View all')" :icon="faExternalLink" size="xs" />
                        </a>
                    </div>
                </div>

                <!-- Custom Menus Families -->
                <div v-if="activeCustomSubIndex !== null && customFamilies?.length">
                    <div v-for="(child, cIndex) in customFamilies" :key="cIndex"
                        class="p-2 px-4  cursor-pointer hover:bg-gray-50">
                        <a v-if="child.url !== null" :href="getHref(child)" :target="getTarget(child)" >
                            {{ child.name }}
                        </a>
                        <span v-else>{{ child.name }}</span>
                    </div>
                </div>

                <!-- Custom Top Menus Families -->
                <div v-if="activeCustomTopSubIndex !== null && customTopFamilies?.length">
                    <div v-for="(child, cIndex) in customTopFamilies" :key="cIndex"
                        class="p-2 px-4  cursor-pointer hover:bg-gray-50">
                        <a v-if="child.url !== null" :href="getHref(child)" :target="getTarget(child)">
                            {{ child.name }}
                        </a>
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