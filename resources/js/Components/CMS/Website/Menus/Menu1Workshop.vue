<script setup lang="ts">
import { Collapse } from "vue-collapsed";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import {
    faChevronRight,
    faSignOutAlt,
    faShoppingCart,
    faSearch,
    faChevronDown,
    faTimes,
    faPlusCircle,
    faUserCircle,
    faSpinner
} from "@fas";
import { faHeart } from "@far";
import { faBars, faChevronLeft, faChevronRight as falChevronRight, faAlbumCollection } from "@fal";
import { ref, inject, nextTick, onMounted, computed, watch } from "vue";
import { getStyles } from "@/Composables/styles";
import { layoutStructure } from "@/Composables/useLayoutStructure";
import { debounce, get } from "lodash-es";
import { trans } from "laravel-vue-i18n";
import LinkIris from "@/Components/Iris/LinkIris.vue";
import { menuCategoriesToMenuStructure } from "@/Composables/Iris/useMenu"

library.add(
    faChevronLeft,
    falChevronRight, faAlbumCollection,
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
    faSpinner
);

const props = withDefaults(
    defineProps<{
        fieldValue: {};
        screenType: "mobile" | "tablet" | "desktop";
    }>(),
    {}
);

const layout = inject("layout", layoutStructure);
const isCollapsedOpen = ref(false);
const hoveredNavigation = ref<any>(null);
const loadingItem = ref<string | null>(null);

const debSetCollapsedTrue = debounce(() => (isCollapsedOpen.value = true), 150);
const debSetCollapsedFalse = debounce(() => (isCollapsedOpen.value = false), 400);

const onMouseEnterMenu = (navigation: any) => {
    debSetCollapsedTrue();
    hoveredNavigation.value = navigation;
};


// Spinner logic for subnav
const onClickSubnav = (link: any) => {
    if (!link?.link?.href) return;
    loadingItem.value = link.id || link.label;
    /* setTimeout(() => (window.location.href = link.link.href), 600); */
};

// Scroll logic
const _scrollContainer = ref<HTMLElement | null>(null);
const isAbleScrollToRight = ref(false);
const isAbleScrollToLeft = ref(false);

const checkScroll = () => {
    const el = _scrollContainer.value;
    if (!el) return;
    isAbleScrollToLeft.value = el.scrollLeft > 0;
    isAbleScrollToRight.value =
        parseInt(el.scrollLeft.toString()) + 1 + el.clientWidth < el.scrollWidth;
};

const scrollRight = () => _scrollContainer.value?.scrollBy({ left: 200, behavior: "smooth" });
const scrollLeft = () => _scrollContainer.value?.scrollBy({ left: -200, behavior: "smooth" });

onMounted(() => {
    nextTick(() => {
        checkScroll();
        window.addEventListener("resize", checkScroll);
    });
});

const isOpenMenuMobile = inject("isOpenMenuMobile", ref(false));

// Unified icon resolver
const getNavigationIcon = (navigation: any) => {
    if (loadingItem.value === (navigation.id || navigation.label)) return "fas fa-spinner";
    if (navigation.type === "multiple") return "fas fa-chevron-down";
    return navigation.icon || null;
};


// Section: Sidebar menu
const sidebarMenu = inject('sidebarMenu', null) // come from layout PreviewLayout
const compSelectedSidebar = computed(() => {
    return sidebarMenu?.value || layout.iris?.sidebar
})
const compCustomTopNavigation = computed(() => {
    if (get(props, 'fieldValue.setting_on_sidebar.is_follow', false)) {
        return compSelectedSidebar.value?.data?.fieldValue?.navigation
    } else {
        return null
    }
})
const compCustomBottomNavigation = computed(() => {
    if (get(props, 'fieldValue.setting_on_sidebar.is_follow', false)) {
        return compSelectedSidebar.value?.data?.fieldValue?.navigation_bottom
    } else {
        return null
    }
})
const computedSelectedSidebarData = computed(() => {
    if (!get(props, 'fieldValue.setting_on_sidebar.is_follow', false)) {
        return []
    }

    const selectedProductCategories = compSelectedSidebar.value?.data?.fieldValue?.product_categories || compSelectedSidebar.value?.product_categories

    const productCategoriesAuto = menuCategoriesToMenuStructure(selectedProductCategories) || []

    return [
        ...productCategoriesAuto,

    ] 
})

const selectedMenu = get(props, 'fieldValue.setting_on_sidebar.is_follow', false) ? computedSelectedSidebarData : props.fieldValue.navigation

const navHoverClass = ref(getStyles(props.fieldValue?.hover?.container?.properties, props.screenType,false))

watch(
  () => props.fieldValue?.hover,
  () => {
    navHoverClass.value = getStyles(props.fieldValue?.hover?.container?.properties, props.screenType,false)
  },
  { deep: true }
)


</script>

<template>
    <div class="bg-white py-1 border-b border-0.5 border-gray-300" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        margin: 0,
        padding: 0,
        ...getStyles(fieldValue?.container?.properties, screenType),
    }">
        <div @mouseleave="() => (debSetCollapsedFalse(), debSetCollapsedTrue.cancel())"
            @mouseenter="() => (debSetCollapsedTrue(), debSetCollapsedFalse.cancel())"
            :style="getStyles(fieldValue?.navigation_container?.properties, screenType)"
            class="relative flex justify-between items-center gap-x-2 px-4">

            <!-- Button: All categories -->
            <div v-if="layout.retina?.type !== 'fulfilment'" class="relative"
                @mouseenter="() => (debSetCollapsedFalse(), debSetCollapsedTrue.cancel())">
                <div @click="() => { isOpenMenuMobile = true }"
                    class="flex items-center gap-x-2 h-fit px-5 py-1 text-sm rounded-full hover:bg-gray-400/20 w-fit cursor-pointer whitespace-nowrap"
                    :style="{
                        border: `solid 1px ${fieldValue?.navigation_container?.properties?.text?.color}`,
                        color: fieldValue?.navigation_container?.properties?.text?.color
                    }"
                >
                    <FontAwesomeIcon icon="fal fa-bars" class="opacity-80 text-[10px]" fixed-width aria-hidden="true" />
                    <span class="font-medium">{{ trans("All Categories") }}</span>
                </div>
                <Transition>
                    <div v-if="isAbleScrollToLeft"
                        class="absolute -right-24 z-10 top-0 h-full w-24 pointer-events-none"
                        :style="{
                            background: `linear-gradient(to right, ${layout?.app?.webpage_layout?.container?.properties?.background?.color} 0%, ${layout?.app?.webpage_layout?.container?.properties?.background?.color} 45%, transparent 100%)`
                        }"
                    />
                </Transition>

                <Transition>
                    <div v-if="isAbleScrollToLeft" @click="() => scrollLeft()"
                        class="w-6 h-6 z-10 bg-gray-500 hover:bg-gray-700 text-white rounded-full flex items-center justify-center absolute -right-10 top-1/2 -translate-y-1/2 cursor-pointer text-inherit">
                        <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" class="text-[8px]" />
                    </div>
                </Transition>
            </div>

            <!-- Scroll Gradient + Arrows -->
            <Transition>
                <div v-if="isAbleScrollToRight"
                    class="absolute right-4 z-10 top-0 h-full w-24 pointer-events-none"
                    :style="{
                        background: `linear-gradient(to left, ${layout?.app?.webpage_layout?.container?.properties?.background?.color} 0%, ${layout?.app?.webpage_layout?.container?.properties?.background?.color} 35%, transparent 100%)`
                    }"
                />
            </Transition>
            <Transition>
                <div v-if="isAbleScrollToRight" @click="scrollRight"
                    class="w-6 h-6 z-10 bg-gray-500 hover:bg-gray-700 text-white rounded-full flex items-center justify-center absolute right-4 top-1/2 -translate-y-1/2 cursor-pointer">
                    <FontAwesomeIcon icon="fal fa-chevron-left" rotation="180" fixed-width class="text-[8px]" />
                </div>
            </Transition>

            <!-- Section: list menu Navigation -->
            <nav ref="_scrollContainer" @scroll="checkScroll"
                class="relative flex text-sm text-gray-600 w-full overflow-x-auto scrollbar-hide ml-5">
                <!-- Navigation: Custom Top (if follow sidebar) -->
                <template v-for="(navigation, idxNavigation) in compCustomTopNavigation" :key="idxNavigation">
                    <component
                        :is="navigation?.link?.href ? LinkIris : 'div'"
                        @mouseenter="() => onMouseEnterMenu(navigation)"
                        :type="navigation?.link?.type"
                        :style="getStyles(fieldValue?.custom_navigation_styling?.custom_top?.properties, screenType)"
                        :href="navigation?.link?.href"
                        :canonical_url="navigation?.link?.canonical_url"
                        class="group w-full  py-2 px-6 flex items-center justify-center transition duration-200" :class="hoveredNavigation?.id === navigation.id && isCollapsedOpen
                            ? 'navigation'
                            : navigation?.link?.href
                                ? 'cursor-pointer  navigation'
                                : ''">
                        <span class="text-center whitespace-nowrap">{{ navigation.label }}</span>
                        <div>
                            <FontAwesomeIcon v-if="getNavigationIcon(navigation)" :icon="getNavigationIcon(navigation)"
                                :spin="loadingItem === (navigation.id || navigation.label)" class="ml-2 text-[8px]" />
                        </div>
                    </component>
                </template>

                <!-- Navigation: main -->
                <template v-for="(navigation, idxNavigation) in selectedMenu" :key="idxNavigation">
                    <component
                        :is="navigation?.link?.href ? LinkIris : 'div'"
                        @mouseenter="() => onMouseEnterMenu(navigation)"
                        :type="navigation?.link?.type"
                        :style="getStyles(fieldValue?.navigation_container?.properties, screenType)"
                        :href="navigation?.link?.href"
                        :canonical_url="navigation?.link?.canonical_url"
                        class="group w-full py-2 px-6 flex items-center justify-center transition duration-200"
                        :class="hoveredNavigation?.id === navigation.id && isCollapsedOpen
                            ? 'navigation underline'
                            : navigation?.link?.href
                                ? 'cursor-pointer  navigation'
                                : ''">
                        <span class="text-center whitespace-nowrap">{{ navigation.label }}</span>
                        <div class="ml-2">
                            <FontAwesomeIcon v-if="getNavigationIcon(navigation)" :icon="getNavigationIcon(navigation)"
                                :spin="loadingItem === (navigation.id || navigation.label)" class="text-[8px] align-middle" />
                        </div>
                    </component>
                </template>

                <!-- Navigation: Custom Bottom (if follow sidebar) -->
                <template v-for="(navigation, idxNavigation) in compCustomBottomNavigation" :key="idxNavigation">
                    <component
                        :is="navigation?.link?.href ? LinkIris : 'div'"
                        @mouseenter="() => onMouseEnterMenu(navigation)"
                        :type="navigation?.link?.type"
                        :style="getStyles(fieldValue?.custom_navigation_styling?.custom_bottom?.properties, screenType)"
                        :href="navigation?.link?.href"
                        :canonical_url="navigation?.link?.canonical_url"
                        class="group w-full  py-2 px-6 flex items-center justify-center transition duration-200" :class="hoveredNavigation?.id === navigation.id && isCollapsedOpen
                            ? 'navigation'
                            : navigation?.link?.href
                                ? 'cursor-pointer  navigation'
                                : ''">
                        <span class="text-center whitespace-nowrap">{{ navigation.label }}</span>
                        <div>
                            <FontAwesomeIcon v-if="getNavigationIcon(navigation)" :icon="getNavigationIcon(navigation)"
                                :spin="loadingItem === (navigation.id || navigation.label)" class="ml-2 text-[8px]" />
                        </div>
                    </component>
                </template>
            </nav>

            <!-- Drawer: Sub Navigation -->
            <Collapse v-if="hoveredNavigation?.subnavs" :when="isCollapsedOpen" as="div"
                class="z-[49] absolute left-0 top-full bg-white border-t w-full shadow-lg"
                :style="getStyles(fieldValue?.container?.properties, screenType)"
            >
                <div class="grid grid-cols-4 gap-8 p-6">
                    <div v-for="subnav in hoveredNavigation?.subnavs" :key="subnav.title" class="">
                        <component
                            :is="subnav?.link?.href ? LinkIris : 'div'"
                            :href="subnav?.link?.href"
                            :type="subnav?.link?.type" :target="subnav?.link?.target"
                            :canonical_url="subnav?.link?.canonical_url"
                            :style="{
                                ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
                                margin: 0,
                                background: 'transparent',
                                padding: 0,
                                fontWeight: 600,
                                ...getStyles(fieldValue?.sub_navigation?.properties, screenType)
                            }"
                            class="font-semibold text-gray-700 transition flex items-center gap-x-3"
                            @start="() => onClickSubnav(subnav)"
                            @finish="() => loadingItem = null"
                        >
                            <span>{{ subnav.title }}</span>
                            <!-- Spinner / Icon -->
                            <FontAwesomeIcon v-if="loadingItem === (subnav.id || subnav.label)" icon="fas fa-spinner" spin fixed-width class="text-[10px] text-orange-500" />
                            <FontAwesomeIcon v-else-if="subnav.icon" :icon="subnav.icon" fixed-width class="text-[10px] text-gray-400" />
                        </component>

                        <div v-for="linkData in subnav?.links"
                            :key="subnav.title"
                            class="navigation"
                            :style="{
                                ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
                                margin: 0,
                                background: 'transparent',
                                padding: 0,
                                fontWeight: 600,
                                ...getStyles(fieldValue?.sub_navigation_link?.properties, screenType)
                            }"
                        >
                            <LinkIris v-if="linkData.link?.href" class="" :href="linkData.link.href"
                                :canonical_url="linkData.link.canonical_url" :type="linkData.link.type">
                                <template #default>
                                    <div class="py-1">{{ linkData.label }}</div>
                                </template>
                            </LinkIris>
                            <div v-else class="py-1">{{ linkData.label }}</div>
                        </div>

                        <!-- Section: Sub Department - Collections -->
                        <div v-for="linkData in subnav?.collections"
                            :key="subnav.title"
                            class="navigation"
                            :style="{
                                ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
                                margin: 0,
                                background: 'transparent',
                                padding: 0,
                                fontWeight: 600,
                                ...getStyles(fieldValue?.sub_navigation_link?.properties, screenType)
                            }"
                        >
                            <LinkIris
                                class=""
                                :href="linkData.url"
                                type="internal"
                            >
                                <template #default>
                                    <div class="py-1">
                                        {{ linkData.name }}
                                        <FontAwesomeIcon v-tooltip="trans('Collection')" icon="fal fa-album-collection" class="opacity-60" fixed-width aria-hidden="true" />
                                    </div>
                                </template>
                            </LinkIris>
                        </div>
                    </div>

                    <!-- Section: Department - Collection -->
                    <div class="">
                        <div
                            ahref="/collection"
                            xtype="internal"
                            xtarget="subnav?.link?.target"
                            :style="{
                                ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
                                margin: 0,
                                background: 'transparent',
                                padding: 0,
                                fontWeight: 600,
                                ...getStyles(fieldValue?.sub_navigation?.properties, screenType)
                            }"
                            class="font-semibold text-gray-700 transition flex items-center gap-x-3"
                            @start="() => onClickSubnav(subnav)"
                            @finish="() => loadingItem = null"
                        >
                            <span>
                                {{ trans('Collection') }}
                                <FontAwesomeIcon v-tooltip="trans('Collection on :department', { department: hoveredNavigation.label })" icon="fal fa-album-collection" class="opacity-60" fixed-width aria-hidden="true" />
                            </span>
                        </div>

                        <div v-for="linkData in hoveredNavigation.collections"
                            :key="linkData.id"
                            zclass="navigation"
                            :style="{
                                ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
                                margin: 0,
                                background: 'transparent',
                                padding: 0,
                                fontWeight: 600,
                                ...getStyles(fieldValue?.sub_navigation_link?.properties, screenType)
                            }"
                        >
                            <LinkIris
                                class=""
                                :href="linkData.url"
                                type="internal">
                                <template #default>
                                    <div class="py-1">
                                        {{ linkData.name }}
                                    </div>
                                </template>
                            </LinkIris>
                        </div>
                    </div>
                </div>
            </Collapse>
        </div>
    </div>
</template>

<style scoped lang="scss">
.container {
    max-width: 1980px;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.editor-class a {
    font-weight: 400;
}

.sub-nav-title {
    .editor-class a {
        font-weight: 600;
    }
}


.navigation {
    &:hover {
        background: v-bind('navHoverClass?.background || null') !important;
        color: v-bind('navHoverClass?.color || null') !important;
        font-family: v-bind('navHoverClass?.fontFamily || null') !important;
        font-size: v-bind('navHoverClass?.fontSize || null') !important;
        font-style: v-bind('navHoverClass?.fontStyle || null') !important;;
    }
}
</style>
