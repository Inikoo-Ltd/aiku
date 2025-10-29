<script setup lang="ts">
import Drawer from 'primevue/drawer';
import { ref, inject, onMounted, onUnmounted, computed } from 'vue';
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
// import { library } from '@fortawesome/fontawesome-svg-core'
import { faBars, faChevronCircleDown } from '@fal';
import { getStyles } from "@/Composables/styles";
import { isNull } from 'lodash-es';
import IrisSidebarDesktop from './Iris/Layout/IrisSidebarDesktop.vue'
import IrisSidebarMobile from './Iris/Layout/IrisSidebarMobile.vue'
import { Image as ImageTS } from '@/types/Image'
import Image from './Image.vue'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    header: { logo?: { image: { source: string } } },
    screenType: string
    productCategories: Array<any>
    menu?: { data: Array<any> }
    customMenusBottom?: Array<any>
    customMenusTop?: Array<any>
    sidebarLogo: ImageTS
    sidebar: {
        data: {
            fieldValue: {
                sidebar_logo: ImageTS
                logo_dimension: {
                    width: {
                        unit: string
                        value: number
                    }
                    height: {
                        unit: string
                        value: number
                    }
                }
                container: {}
                navigation: {}
                navigation_bottom: {}
                product_categories: {}
            }
        }
    }
}>();


const layout = inject("layout", {});
const isOpenMenuMobile = inject('isOpenMenuMobile', ref(false));

const isMobile = ref(false);
const activeIndex = ref<number | null>(null); // active category
const activeSubIndex = ref<number | null>(null); // active subdepartment
const activeCustomIndex = ref<number | null>(null); // active custom menu
const activeCustomSubIndex = ref<number | null>(null); // active custom menu subdepartment
const activeCustomTopIndex = ref<number | null>(null); // active custom menu top
const activeCustomTopSubIndex = ref<number | null>(null); // active custom menu top subdepartment

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

// const sortedNavigation = computed(() => {
//     if (!props.menu?.navigation) return [];
//     return [...props.menu.navigation].sort((a, b) =>
//         (a.label || '').localeCompare(b.label || '', undefined, { sensitivity: 'base' })
//     );
// });

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

    return `${item.url}`; // Internal
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

const internalHref = (item) => {
    // "https://www.aw-dropship.com/new",   -> /new
    // "http://aw-dropship.com/new",   -> /new
    // "www.aw-dropship.com/new",   -> /new
    // "aw-dropship.com/new"   -> /new
    if (!item.url) return '';

    const path = item.url.replace(/^(https?:\/\/)?(www\.)?[^/]+/, "");
    
    return path
}


const onClickLuigi = () => {
    const input = document.getElementById('luigi_mobile') as HTMLInputElement | null;
    if (input) input.focus();
}
</script>

<template>
    <div class="mobile-menu editor-class">
        <button @click="isOpenMenuMobile = true" class="">
            <FontAwesomeIcon :icon="props.header?.mobile?.menu?.icon || faBars"
                :style="{ ...getStyles(header?.mobile?.menu?.container?.properties, screenType) }"
                fixed-width
                aria-hidden="true"
            />
        </button>

        <Drawer
            v-model:visible="isOpenMenuMobile"
            :header="''"
            :showCloseIcon="false"
            :style="{
                ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
                margin: 0,
                padding: 0,
                border: 'none !important',
                ...getStyles(props.menu?.container?.properties),
                ...getStyles(props.sidebar?.data?.fieldValue?.container?.properties),
                width: isMobile ? null : !isNull(activeIndex) || !isNull(activeCustomIndex) || !isNull(activeCustomTopIndex) ?
                    (!isNull(activeSubIndex) || !isNull(activeCustomSubIndex) || !isNull(activeCustomTopSubIndex)) ? '798px' : '545px' : '290px'
            }"
            class="h-screen"
        >
            <template #header>
                <div>
                    <div class="md:max-w-[270px] overflow-hidden">
                        <!-- <Image
                            v-if="sidebarLogo"
                            :src="sidebarLogo"
                            class="h-fit w-full object-contain aspect-auto"
                            :alt="trans('Sidebar logo')"
                        /> -->
                        <img
                            xv-else :src="sidebarLogo?.original || header?.logo?.image?.source?.original"
                            :alt="header?.logo?.alt"
                            zclass="w-full h-auto max-h-20 object-contain"
                            :style="getStyles(props.sidebar?.data?.fieldValue?.logo_dimension)"
                        />
                    </div>
                    
                    <!-- Section: input search -->
                    <div class="mt-6 flex gap-x-4 items-center">
                        <div @click="() => onClickLuigi()" class="flex-grow border border-gray-300/40 rounded-md px-2 py-1">
                            <FontAwesomeIcon icon="fal fa-search" class="" fixed-width aria-hidden="true" />
                            <span v-if="layout?.currentQuery?.q" class="ml-2 text-sm">{{layout?.currentQuery?.q}}</span>
                            <span v-else class="ml-2 text-sm italic opacity-60">{{ trans("I am looking for..") }}</span>
                        </div>

                        <FontAwesomeIcon icon="fal fa-times" class="opacity-50 text-xl" fixed-width aria-hidden="true" />
                    </div>
                </div>
            </template>

            <!-- Sidebar Menu: Mobile -->
            <IrisSidebarMobile
                v-if="isMobile"
                :containerStyle="props.sidebar?.data?.fieldValue?.container?.properties || props.menu?.container?.properties"
                :productCategories
                :customMenusTop
                :customTopSubDepartments
                :customMenusBottom
                :customSubDepartments
                :activeIndex
                :activeCustomIndex
                :activeCustomTopIndex
                :internalHref
                :getTarget
                :setActiveCategory
                :setActiveCustomCategory
                :setActiveCustomTopCategory
                :sortedFamilies
                :customFamilies
                :customTopFamilies
                :sortedProductCategories
                :sortedSubDepartments
                :activeSubIndex
                :activeCustomSubIndex
                :activeCustomTopSubIndex
                :changeActiveSubIndex="(index) => activeSubIndex = index"
                :changeActiveCustomSubIndex="(index) => activeCustomSubIndex = index"
                :changeActiveCustomTopSubIndex="(index) => activeCustomTopSubIndex = index"
                @closeMobileMenu="isOpenMenuMobile = false"
                :fieldValue="props.sidebar?.data?.fieldValue"
            />

            <!-- Sidebar Menu: Desktop -->
            <IrisSidebarDesktop
                v-else
                :containerStyle="props.sidebar?.data?.fieldValue?.container?.properties || props.menu?.container?.properties"
                :productCategories
                :customMenusTop
                :customTopSubDepartments
                :customMenusBottom
                :customSubDepartments
                :activeIndex
                :activeCustomIndex
                :activeCustomTopIndex
                :internalHref
                :getTarget
                :setActiveCategory
                :setActiveCustomCategory
                :setActiveCustomTopCategory
                :sortedFamilies
                :customFamilies
                :customTopFamilies
                :sortedProductCategories
                :sortedSubDepartments
                :activeSubIndex
                :activeCustomSubIndex
                :activeCustomTopSubIndex
                :changeActiveSubIndex="(index) => activeSubIndex = index"
                :changeActiveCustomSubIndex="(index) => activeCustomSubIndex = index"
                :changeActiveCustomTopSubIndex="(index) => activeCustomTopSubIndex = index"
                @closeMobileMenu="isOpenMenuMobile = false"
                :fieldValue="props.sidebar?.data?.fieldValue"
            />
        </Drawer>
    </div>
</template>

<style scoped lang="scss">





/* ✅ Smooth width transition */
// .p-drawer {
//     transition: width 0.35s ease-in-out;
//     background: #fff;
// }

// .p-drawer-content {
//     padding: 0 !important;
//     transition: width 0.35s ease-in-out;
// }

/* Hover & active states */
// .menu-link {
//     @apply flex items-center justify-between px-4 py-2 cursor-pointer rounded-lg;
// }

// .menu-link:hover {
//     background: #f9fafb;
// }

// .menu-link.active {
//     background: #f3f4f6;
//     font-weight: 600;
//     color: #2563eb;
// }
</style>