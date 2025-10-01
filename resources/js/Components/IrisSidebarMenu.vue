<script setup lang="ts">
import Drawer from 'primevue/drawer';
import { ref, inject, onMounted, onUnmounted, computed } from 'vue';
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
// import { library } from '@fortawesome/fontawesome-svg-core'
import { faBars, faChevronCircleDown } from '@fal';
import { getStyles } from "@/Composables/styles";
import { isNull } from 'lodash-es';
import SidebarDesktop from './Iris/Layout/SidebarDesktop.vue'
import IrisSidebarMobile from './Iris/Layout/IrisSidebarMobile.vue'

const props = defineProps<{
    header: { logo?: { image: { source: string } } },
    screenType: String
    productCategories: Array<any>
    menu?: { data: Array<any> }
    customMenusBottom?: Array<any>
    customMenusTop?: Array<any>
}>();

const layout = inject("layout", {});
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
            <IrisSidebarMobile
                v-if="isMobile"
                :productCategories
                :customMenusTop
                :customMenusBottom
                :activeIndex
                :activeCustomIndex
                :activeCustomTopIndex
                :getHref
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
            />

            <!-- Sidebar Menu: Desktop -->
            <SidebarDesktop
                v-else
                :productCategories
                :customMenusTop
                :customMenusBottom
                :activeIndex
                :activeCustomIndex
                :activeCustomTopIndex
                :getHref
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