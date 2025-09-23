<script setup lang="ts">
import Drawer from 'primevue/drawer';
import { ref, inject, onMounted, onUnmounted, computed } from 'vue';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faSignIn, faSignOut, faTimesCircle } from '@fas';
// import { library } from '@fortawesome/fontawesome-svg-core'
import { faBars, faChevronCircleDown } from '@fal';
import { getStyles } from "@/Composables/styles";
import { isNull } from 'lodash-es';
// import { trans } from 'laravel-vue-i18n';
import Button from './Elements/Buttons/Button.vue';
import { faChevronRight, faExternalLink } from '@far';

const props = defineProps<{
    header: { logo?: { image: { source: string } } },
    screenType: String
    productCategories: Array<any>
    menu?: { data: Array<any> }
    customMenusBottom?: Array<any>
    customMenusTop?: Array<any>
}>();

const layout = inject("layout", {});
const isLoggedIn = inject('isPreviewLoggedIn', false)
const onLogout = inject('onLogout')
const isOpenMenuMobile = inject('isOpenMenuMobile', ref(false));

const isMobile = ref(false);
const activeIndex = ref(null); // active category
const activeSubIndex = ref(null); // active subdepartment
const activeCustomIndex = ref(null); // active custom menu
const activeCustomSubIndex = ref(null); // active custom menu subdepartment
const activeCustomTopIndex = ref(null); // active custom menu top
const activeCustomTopSubIndex = ref(null); // active custom menu top subdepartment

// Computed properties for sorted data
const sortedProductCategories = computed(() => {
    if (!props.productCategories) return [];
    return [...props.productCategories].sort((a, b) =>
        (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base' })
    );
});

// Custom menus without sorting
const customMenusBottom = computed(() => {
    if (!props.customMenusBottom) return [];
    return props.customMenusBottom;
});

const customMenusTop = computed(() => {
    if (!props.customMenusTop) return [];
    return props.customMenusTop;
});

const sortedNavigation = computed(() => {
    if (!props.menu?.navigation) return [];
    return [...props.menu.navigation].sort((a, b) =>
        (a.label || '').localeCompare(b.label || '', undefined, { sensitivity: 'base' })
    );
});

const sortedSubDepartments = computed(() => {
    if (activeIndex.value === null || !sortedProductCategories.value[activeIndex.value]?.sub_departments) return [];
    return [...sortedProductCategories.value[activeIndex.value].sub_departments].sort((a, b) =>
        (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base' })
    );
});

// Custom sub departments without sorting
const customSubDepartments = computed(() => {
    if (activeCustomIndex.value === null || !customMenusBottom.value[activeCustomIndex.value]?.sub_departments) return [];
    return customMenusBottom.value[activeCustomIndex.value].sub_departments;
});

const sortedFamilies = computed(() => {
    if (activeSubIndex.value === null || !sortedSubDepartments.value[activeSubIndex.value]?.families) return [];
    return [...sortedSubDepartments.value[activeSubIndex.value].families].sort((a, b) =>
        (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base' })
    );
});

// Custom families without sorting
const customFamilies = computed(() => {
    if (activeCustomSubIndex.value === null || !customSubDepartments.value[activeCustomSubIndex.value]?.families) return [];
    return customSubDepartments.value[activeCustomSubIndex.value].families;
});

// Custom top sub departments without sorting
const customTopSubDepartments = computed(() => {
    if (activeCustomTopIndex.value === null || !customMenusTop.value[activeCustomTopIndex.value]?.sub_departments) return [];
    return customMenusTop.value[activeCustomTopIndex.value].sub_departments;
});

// Custom top families without sorting
const customTopFamilies = computed(() => {
    if (activeCustomTopSubIndex.value === null || !customTopSubDepartments.value[activeCustomTopSubIndex.value]?.families) return [];
    return customTopSubDepartments.value[activeCustomTopSubIndex.value].families;
});

// reset subdepartment when category changes
const setActiveCategory = (index: number) => {
    activeIndex.value = index;
    activeSubIndex.value = null;
    // Reset custom menu states
    activeCustomIndex.value = null;
    activeCustomSubIndex.value = null;
    activeCustomTopIndex.value = null;
    activeCustomTopSubIndex.value = null;
};

const setActiveCustomCategory = (index: number) => {
    activeCustomIndex.value = index;
    activeCustomSubIndex.value = null;
    // Reset product category states
    activeIndex.value = null;
    activeSubIndex.value = null;
    // Reset custom top menu states
    activeCustomTopIndex.value = null;
    activeCustomTopSubIndex.value = null;
};

const setActiveCustomTopCategory = (index: number) => {
    activeCustomTopIndex.value = index;
    activeCustomTopSubIndex.value = null;
    // Reset product category states
    activeIndex.value = null;
    activeSubIndex.value = null;
    // Reset custom bottom menu states
    activeCustomIndex.value = null;
    activeCustomSubIndex.value = null;
};

const checkMobile = () => {
    isMobile.value = window.innerWidth < 768;
};

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
});
onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
});

const getHref = (item) => {
    if (item.type === 'external' && item.url !== null) {
        if (item.url.startsWith('http://') || item.url.startsWith('https://')) {
            return item.url;
        }
        return `https://${item.url}`;
    }
    return `/${item.url}`;
}

const getTarget = (item) => {
    if (item.target) {
        return item.target;
    }
    if (item.type === 'external') {
        return '_blank';
    }
    return '_self';
}
</script>

<template>
    <div class="mobile-menu editor-class">
        <button @click="isOpenMenuMobile = true">
            <FontAwesomeIcon :icon="props.header?.mobile?.menu?.icon || faBars"
                :style="{ ...getStyles(header?.mobile?.menu?.container?.properties) }" />
        </button>

        <Drawer v-model:visible="isOpenMenuMobile" :header="''" :showCloseIcon="true" :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            margin: 0,
            padding: 0,
            ...getStyles(props.menu?.container?.properties),
            width: isMobile ? null : !isNull(activeIndex) || !isNull(activeCustomIndex) || !isNull(activeCustomTopIndex) ?
                (!isNull(activeSubIndex) || !isNull(activeCustomSubIndex) || !isNull(activeCustomTopSubIndex)) ? '60%' : '40%' : '20%'
        }">
            <template #header>
                <img :src="header?.logo?.image?.source?.original" :alt="header?.logo?.alt" class="h-16" />
            </template>

            <!-- Sidebar Menu: Mobile -->
            <div v-if="isMobile" class="menu-container-mobile">
                <div class="menu-content">
                    <!-- Section: top sidemenu -->
                    <div v-if="customMenusTop && customMenusTop.length > 0">
                        <div v-for="(customTopItem, customTopIndex) in customMenusTop" :key="'custom-top-' + customTopIndex">
                            <!-- Custom Menu Top WITH Sub-departments -->
                            <Disclosure v-if="customTopItem.sub_departments && customTopItem.sub_departments.length > 0"
                                v-slot="{ open }">
                                <DisclosureButton class="w-full text-left p-4 font-semibold text-gray-600 border-b">
                                    <div class="flex justify-between items-center xtext-lg"
                                        :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }">
                                        <span>{{ customTopItem.name }}</span>
                                        <FontAwesomeIcon :icon="faChevronCircleDown"
                                            :class="{ 'rotate-180': open, 'transition-transform duration-300': true }" />
                                    </div>
                                </DisclosureButton>

                                <DisclosurePanel class="disclosure-panel">
                                    <div v-for="(subDept, subDeptIndex) in customTopItem.sub_departments"
                                        :key="subDeptIndex" class="mb-6">
                                        <a v-if="subDept?.url !== null" :href="getHref(subDept)"
                                            :target="getTarget(subDept)"
                                            class="block text-base font-bold text-gray-700 mb-2"
                                            :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation?.properties) }">
                                            {{ subDept.name }}
                                        </a>
                                        <span v-else class="block text-base font-bold text-gray-700 mb-2"
                                            :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation?.properties) }">
                                            {{ subDept.name }}
                                        </span>
                                        <div v-if="subDept.families" class="space-y-2 mt-2 ml-4 pl-4 border-gray-200">
                                            <div v-for="(family, familyIndex) in subDept.families" :key="familyIndex">
                                                <a 
                                                    v-if="family?.url !== null" :href="getHref(family)" :target="getTarget(family)"
                                                    :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation_link?.properties) }"
                                                    class="block text-sm text-gray-700 relative hover:text-primary transition-all">
                                                    <span class="absolute left-0 -ml-4">–</span>
                                                    {{ family.name }}
                                                </a>
                                                <span v-else
                                                    :key="'span-' + familyIndex" v-if="family?.url === null"
                                                    :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation_link?.properties) }"
                                                    class="block text-sm text-gray-700 relative">
                                                    <span class="absolute left-0 -ml-4">–</span>
                                                    {{ family.name }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </DisclosurePanel>
                            </Disclosure>

                            <!-- Custom Menu Top SINGLE LINK -->
                            <div v-else class="py-4 px-5 border-b">
                                <a v-if="customTopItem?.url !== null" :href="getHref(customTopItem)"
                                    :target="getTarget(customTopItem)"
                                    :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }"
                                    class="font-bold text-gray-600 xtext-lg">
                                    {{ customTopItem.name }}
                                </a>

                                <span v-else
                                    :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }"
                                    class="font-bold text-gray-600 xtext-lg">{{ customTopItem.name }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Product Categories Section for Mobile -->
                    <div v-for="(category, index) in sortedProductCategories" :key="index">
                        <!-- Product Category WITH Sub-departments -->
                        <Disclosure v-if="category.sub_departments && category.sub_departments.length > 0"
                            v-slot="{ open }">
                            <DisclosureButton class="w-full text-left p-4 font-semibold text-gray-600 border-b">
                                <div class="flex justify-between items-center xtext-lg"
                                    :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }">
                                    <span>{{ category.name }}</span>
                                    <FontAwesomeIcon :icon="faChevronCircleDown"
                                        :class="{ 'rotate-180': open, 'transition-transform duration-300': true }" />
                                </div>
                            </DisclosureButton>

                            <DisclosurePanel class="disclosure-panel">
                                <div v-for="(subDept, subDeptIndex) in category.sub_departments"
                                    :key="subDeptIndex" class="mb-6">
                                    <a v-if="subDept?.url !== null" :href="'/' + subDept.url"
                                        class="block text-base font-bold text-gray-700 mb-2"
                                        :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation?.properties) }">
                                        {{ subDept.name }}
                                    </a>
                                    <span v-else class="block text-base font-bold text-gray-700 mb-2"
                                        :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation?.properties) }">
                                        {{ subDept.name }}
                                    </span>
                                    <div v-if="subDept.families" class="space-y-2 mt-2 ml-4 pl-4 border-gray-200">
                                        <div v-for="(family, familyIndex) in subDept.families" :key="familyIndex">
                                            <a 
                                                v-if="family?.url !== null" :href="'/' + family.url"
                                                :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation_link?.properties) }"
                                                class="block text-sm text-gray-700 relative hover:text-primary transition-all">
                                                <span class="absolute left-0 -ml-4">–</span>
                                                {{ family.name }}
                                            </a>
                                            <span v-else
                                                :key="'span-' + familyIndex" v-if="family?.url === null"
                                                :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation_link?.properties) }"
                                                class="block text-sm text-gray-700 relative">
                                                <span class="absolute left-0 -ml-4">–</span>
                                                {{ family.name }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </DisclosurePanel>
                        </Disclosure>

                        <!-- Product Category SINGLE LINK -->
                        <div v-else class="py-4 px-5 border-b">
                            <a v-if="category?.url !== null" :href="'/' + category.url"
                                :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }"
                                class="font-bold text-gray-600 xtext-lg">
                                {{ category.name }}
                            </a>

                            <span v-else
                                :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }"
                                class="font-bold text-gray-600 xtext-lg">{{ category.name }}</span>
                        </div>
                    </div>

                    <!-- Section: bottom menu -->
                    <div v-if="customMenusBottom && customMenusBottom.length > 0">
                        <!-- <hr class="my-4 border-gray-300"> -->
                        <div v-for="(customItem, customIndex) in customMenusBottom" :key="'custom-' + customIndex">
                            <!-- Custom Menu WITH Sub-departments -->
                            <Disclosure v-if="customItem.sub_departments && customItem.sub_departments.length > 0"
                                v-slot="{ open }">
                                <DisclosureButton class="w-full text-left p-4 font-semibold text-gray-600 border-b">
                                    <div class="flex justify-between items-center xtext-lg"
                                        :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }">
                                        <span>{{ customItem.name }}</span>
                                        <FontAwesomeIcon :icon="faChevronCircleDown"
                                            :class="{ 'rotate-180': open, 'transition-transform duration-300': true }" />
                                    </div>
                                </DisclosureButton>

                                <DisclosurePanel class="disclosure-panel">
                                    <div v-for="(subDept, subDeptIndex) in customItem.sub_departments"
                                        :key="subDeptIndex" class="mb-6">
                                        <a v-if="subDept?.url !== null" :href="'/' + subDept.url"
                                            class="block text-base font-bold text-gray-700 mb-2"
                                            :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation?.properties) }">
                                            {{ subDept.name }}
                                        </a>
                                        <span v-else class="block text-base font-bold text-gray-700 mb-2"
                                            :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation?.properties) }">
                                            {{ subDept.name }}
                                        </span>
                                        <div v-if="subDept.families" class="space-y-2 mt-2 ml-4 pl-4 border-gray-200">
                                            <div v-for="(family, familyIndex) in subDept.families" :key="familyIndex">
                                                <a 
                                                    v-if="family?.url !== null" :href="getHref(family)" :target="getTarget(family)"
                                                    :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation_link?.properties) }"
                                                    class="block text-sm text-gray-700 relative hover:text-primary transition-all">
                                                    <span class="absolute left-0 -ml-4">–</span>
                                                    {{ family.name }}
                                                </a>
                                                <span v-else
                                                    :key="'span-' + familyIndex" v-if="family?.url === null"
                                                    :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation_link?.properties) }"
                                                    class="block text-sm text-gray-700 relative">
                                                    <span class="absolute left-0 -ml-4">–</span>
                                                    {{ family.name }}
                                                </span>
                                            </div>

                                        </div>
                                    </div>
                                </DisclosurePanel>
                            </Disclosure>

                            <!-- Custom Menu SINGLE LINK -->
                            <div v-else class="py-4 px-5 border-b">
                                <a v-if="customItem?.url !== null" :href="getHref(customItem)"
                                    :target="getTarget(customItem)"
                                    :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }"
                                    class="font-bold text-gray-600 xtext-lg">
                                    {{ customItem.name }}
                                </a>

                                <span v-else
                                    :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }"
                                    class="font-bold text-gray-600 xtext-lg">{{ customItem.name }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="isMobile" class="login-section">
                    <a v-if="!isLoggedIn" href="/app" class="font-bold text-gray-500">
                        <FontAwesomeIcon :icon="faSignIn" class="mr-3" /> Login
                    </a>
                    <div v-else @click="onLogout()" class="font-bold text-red-500 cursor-pointer">
                        <FontAwesomeIcon :icon="faSignOut" class="mr-3" /> Log Out
                    </div>
                </div>
            </div>

            <!-- Sidebar Menu: Desktop -->
            <div v-else
                :class="['menu-container grid h-full', (activeIndex !== null || activeCustomIndex !== null || activeCustomTopIndex !== null) && 'grid-cols-2', (activeSubIndex !== null || activeCustomSubIndex !== null || activeCustomTopSubIndex !== null) && 'grid-cols-3']">
                <!-- Column 1: Categories + Custom Menus -->
                <div :class="[(activeIndex !== null || activeCustomIndex !== null || activeCustomTopIndex !== null) && 'border-r', 'overflow-y-auto']">
                    <!-- Custom Menu Top Section for Desktop -->
                    <div v-if="customMenusTop && customMenusTop.length > 0">
                        <div v-for="(customTopItem, customTopIndex) in customMenusTop" :key="'custom-top-' + customTopIndex"
                            class="p-2 px-4 flex items-center justify-between cursor-pointer transition-colors duration-200"
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
                                :icon="faChevronRight" class="text-xs transition-transform duration-200" />
                        </div>
                        <hr class="mt-4 border-gray-200">
                    </div>

                    <!-- Header -->
                    <div class="flex items-center justify-between px-2 py-4 border-b">
                        <h3 class="font-semibold text-sm">Departments</h3>
                    </div>

                    <!-- Product Categories List -->
                    <div v-for="(item, index) in sortedProductCategories" :key="index"
                        class="p-2 px-4 flex items-center justify-between cursor-pointer transition-colors duration-200"
                        :class="[
                            activeIndex === index
                                ? `bg-gray-100 font-semibold text-[${layout.iris.theme?.color[0]}]`
                                : ' hover:bg-gray-50'
                        ]" @click="setActiveCategory(index)">
                        <div>{{ item.name }}</div>
                        <FontAwesomeIcon :icon="faChevronRight" class="text-xs transition-transform duration-200" />
                    </div>

                    <!-- Custom Menus Section for Desktop -->
                    <div v-if="customMenusBottom && customMenusBottom.length > 0">
                        <hr class="my-4 mx-4 border-gray-300">
                        <div v-for="(customItem, customIndex) in customMenusBottom" :key="'custom-' + customIndex"
                            class="p-2 px-4 flex items-center justify-between cursor-pointer transition-colors duration-200"
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
                                :icon="faChevronRight" class="text-xs transition-transform duration-200" />
                        </div>
                    </div>
                </div>

                <!-- Column 2: Subdepartments -->
                <div v-if="activeIndex !== null || activeCustomIndex !== null || activeCustomTopIndex !== null"
                    :class="[(activeSubIndex !== null || activeCustomSubIndex !== null || activeCustomTopSubIndex !== null) && 'border-r']">
                    <!-- Header -->
                    <div  v-if="activeIndex !== null" class="flex items-center justify-between py-4 px-4">
                        <h3 class="font-semibold text-sm">Sub-Departments</h3>
                    </div>

                    <div class="overflow-y-auto">
                        <!-- Product Categories Subdepartments -->
                        <div v-if="activeIndex !== null && sortedSubDepartments.length">
                            <div v-for="(sub, sIndex) in sortedSubDepartments" :key="sIndex"
                                class="p-2 px-4 flex items-center justify-between cursor-pointer transition-colors duration-200"
                                :class="[
                                    activeSubIndex === sIndex
                                        ? `bg-gray-100 font-semibold text-[${layout.iris.theme?.color[0]}]`
                                        : 'hover:bg-gray-50 text-gray-700'
                                ]" @click="activeSubIndex = sIndex">
                                <div>{{ sub.name }}</div>
                                <FontAwesomeIcon :icon="faChevronRight"
                                    class="transition-transform duration-200 text-xs" />
                            </div>
                            <div class="p-2 px-4  cursor-pointer font-bold">
                                <a :href="'/' + sortedProductCategories[activeIndex].url">
                                    <Button label="View all" :icon="faExternalLink" size="xs" />
                                </a>
                            </div>
                        </div>

                        <!-- Custom Menus Subdepartments -->
                        <div v-if="activeCustomIndex !== null && customSubDepartments.length">
                            <div v-for="(sub, sIndex) in customSubDepartments" :key="sIndex"
                                class="p-2 px-4 flex items-center justify-between cursor-pointer transition-colors duration-200"
                                :class="[
                                    activeCustomSubIndex === sIndex
                                        ? `bg-gray-100 font-semibold text-[${layout.iris.theme?.color[0]}]`
                                        : 'hover:bg-gray-50 text-gray-700'
                                ]" @click="activeCustomSubIndex = sIndex">
                                <div>
                                    <a v-if="(!sub.families || sub.families.length === 0) && sub.url !== null"
                                        :href="getHref(sub)" :target="getTarget(sub)" class="block">
                                        {{ sub.name }}
                                    </a>
                                    <span v-else>{{ sub.name }}</span>
                                </div>
                                <FontAwesomeIcon :icon="faChevronRight"
                                    class="transition-transform duration-200 text-xs" />
                            </div>
                            <!-- <div class="p-2 px-4  cursor-pointer font-bold">
                                <a :href="'/' + customMenus[activeCustomIndex].url">
                                    <Button label="View all" :icon="faExternalLink" size="xs" />
                                </a>
                            </div> -->
                        </div>

                        <!-- Custom Top Menus Subdepartments -->
                        <div v-if="activeCustomTopIndex !== null && customTopSubDepartments.length">
                            <div v-for="(sub, sIndex) in customTopSubDepartments" :key="sIndex"
                                class="p-2 px-4 flex items-center justify-between cursor-pointer transition-colors duration-200"
                                :class="[
                                    activeCustomTopSubIndex === sIndex
                                        ? `bg-gray-100 font-semibold text-[${layout.iris.theme?.color[0]}]`
                                        : 'hover:bg-gray-50 text-gray-700'
                                ]" @click="activeCustomTopSubIndex = sIndex">
                                <div>
                                    <a v-if="(!sub.families || sub.families.length === 0) && sub.url !== null"
                                        :href="getHref(sub)" :target="getTarget(sub)" class="block">
                                        {{ sub.name }}
                                    </a>
                                    <span v-else>{{ sub.name }}</span>
                                </div>
                                <FontAwesomeIcon :icon="faChevronRight"
                                    class="transition-transform duration-200 text-xs" />
                            </div>
                        </div>

                        <!-- No subdepartments message -->
                        <div v-if="(activeIndex !== null && !sortedSubDepartments.length) || (activeCustomIndex !== null && !customSubDepartments.length) || (activeCustomTopIndex !== null && !customTopSubDepartments.length)"
                            class="p-2 text-gray-400 italic">
                            No subdepartments available
                        </div>
                    </div>
                </div>

                <!-- Column 3: Families -->
                <div v-if="activeSubIndex !== null || activeCustomSubIndex !== null || activeCustomTopSubIndex !== null">
                    <!-- Header -->
                    <div  v-if="activeSubIndex !== null" class="flex items-center justify-between p-4">
                        <h3 class="font-semibold text-sm">Families</h3>
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
                                    <Button label="View all" :icon="faExternalLink" size="xs" />
                                </a>
                            </div>
                        </div>

                        <!-- Custom Menus Families -->
                        <div v-if="activeCustomSubIndex !== null && customFamilies.length">
                            <div v-for="(child, cIndex) in customFamilies" :key="cIndex"
                                class="p-2 px-4  cursor-pointer hover:bg-gray-50">
                                <a v-if="child.url !== null" :href="getHref(child)" :target="getTarget(child)">{{
                                    child.name }}</a>
                                <span v-else>{{ child.name }}</span>
                            </div>
                            <!-- <div class="p-2 px-4  cursor-pointer hover:bg-gray-50 font-bold">
                                <a :href="'/' + customSubDepartments[activeCustomSubIndex].url">
                                    <Button label="View all" :icon="faExternalLink" size="xs" />
                                </a>
                            </div> -->
                        </div>

                        <!-- Custom Top Menus Families -->
                        <div v-if="activeCustomTopSubIndex !== null && customTopFamilies.length">
                            <div v-for="(child, cIndex) in customTopFamilies" :key="cIndex"
                                class="p-2 px-4  cursor-pointer hover:bg-gray-50">
                                <a v-if="child.url !== null" :href="getHref(child)" :target="getTarget(child)">{{
                                    child.name }}</a>
                                <span v-else>{{ child.name }}</span>
                            </div>
                        </div>

                        <!-- No families message -->
                        <div v-if="(activeSubIndex !== null && !sortedFamilies.length) || (activeCustomSubIndex !== null && !customFamilies.length) || (activeCustomTopSubIndex !== null && !customTopFamilies.length)"
                            class="p-2 text-gray-400 italic">
                            No further items
                        </div>
                    </div>
                </div>
            </div>

        </Drawer>
    </div>
</template>

<style scoped lang="scss">
.menu-container-mobile {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.menu-container {
    height: 100%;
    background: #fff;
}

.menu-content {
    flex: 1;
    overflow-y: auto;
    padding-bottom: 1rem;
}

.login-section {
    flex-shrink: 0;
    padding: 1rem 1.25rem;
    border-top: 1px solid #e5e5e5;
    display: flex;
    align-items: center;
    justify-content: flex-start;

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

.disclosure-panel {
    padding: 0.75rem 1rem 1rem;
}

.disclosure-panel a {
    display: block;
    transition: color 0.2s ease;
}

.disclosure-panel a:hover {
    text-decoration: underline;
    color: #2563eb;
}

/* ✅ Smooth width transition */
.p-drawer {
    transition: width 0.35s ease-in-out;
    background: #fff;
}

.p-drawer-content {
    padding: 0 !important;
    transition: width 0.35s ease-in-out;
}

/* Hover & active states */
.menu-link {
    @apply flex items-center justify-between px-4 py-2 cursor-pointer transition-colors duration-200 rounded-lg;
}

.menu-link:hover {
    background: #f9fafb;
}

.menu-link.active {
    background: #f3f4f6;
    font-weight: 600;
    color: #2563eb;
}
</style>