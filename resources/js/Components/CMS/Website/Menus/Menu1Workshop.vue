<script setup lang="ts">
import { Collapse } from "vue-collapsed";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faChevronRight, faSignOutAlt, faShoppingCart, faSearch, faChevronDown, faTimes, faPlusCircle, faUserCircle } from "@fas";
import { faHeart } from "@far";
import { faBars, faChevronLeft, faChevronRight as falChevronRight } from "@fal";
import { ref, inject, nextTick, onMounted } from "vue";
import { getStyles } from "@/Composables/styles";
import { layoutStructure } from "@/Composables/useLayoutStructure";
import { debounce } from "lodash-es";
import { trans } from "laravel-vue-i18n";

library.add(faChevronLeft, falChevronRight, faChevronRight, faSignOutAlt, faShoppingCart, faHeart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faUserCircle);

const props = withDefaults(defineProps<{
    fieldValue: {}
    screenType: "mobile" | "tablet" | "desktop"
}>(), {});

const layout = inject("layout", layoutStructure);
;

const isCollapsedOpen = ref(false);
const debSetCollapsedTrue = debounce(() => {
    isCollapsedOpen.value = true;
}, 800);

const debSetCollapsedFalse = debounce(() => {
    isCollapsedOpen.value = false;
}, 400);

const onMouseEnterMenu = (navigation: {}, idxNavigation: number) => {
    debSetCollapsedTrue();
    hoveredNavigation.value = navigation;

};

const hoveredNavigation = ref(null);

const _scrollContainer = ref(null);
const isAbleScrollToRight = ref(false);
const isAbleScrollToLeft = ref(false);

const checkScroll = () => {
    const el = _scrollContainer.value;
    if (!el) return;

    // console.log('el.scrollLeft', el.scrollLeft, 'el.clientWidth', el.clientWidth, 'el.scrollWidth', el.scrollWidth)

    isAbleScrollToLeft.value = el.scrollLeft > 0;
    isAbleScrollToRight.value = parseInt(el.scrollLeft) + 1 + el.clientWidth < el.scrollWidth;
};
const scrollRight = () => {
    _scrollContainer.value?.scrollBy({ left: 200, behavior: "smooth" });
};

const scrollLeft = () => {
    _scrollContainer.value?.scrollBy({ left: -200, behavior: "smooth" });
};
onMounted(() => {
    nextTick(() => {
        checkScroll();
        // Optional: Recheck on window resize
        window.addEventListener("resize", checkScroll);
    });
});

const isOpenMenuMobile = inject("isOpenMenuMobile", ref(false));

</script>

<template>
    <!-- Main Navigation -->
    <div class="bg-white py-1 border-b border-0.5 border-gray-300" :style="{...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin : 0, padding : 0, ...getStyles(fieldValue?.container?.properties,screenType)}">
        <div
            @mouseleave="() => (debSetCollapsedFalse(), debSetCollapsedTrue.cancel())"
            :style="getStyles(fieldValue?.navigation_container?.properties,screenType)"
            class="relative xcontainerx flex  justify-between items-center gap-x-2 px-4">

            <!-- All categories -->
            <div v-if="layout.retina?.type !== 'fulfilment'" class="relative">
                <div @click="() => {isOpenMenuMobile = true}" class="flex items-center gap-x-2 h-fit px-5 py-1  text-sm rounded-full hover:bg-gray-100 border border-gray-300 w-fit cursor-pointer whitespace-nowrap ">
                    <FontAwesomeIcon icon="fal fa-bars" class="text-gray-400" fixed-width aria-hidden="true" :class="'text-[10px]'" />
                    <span class="font-medium text-gray-600">{{ trans("All Categories") }}</span>
                </div>

                <Transition>
                    <div v-if="isAbleScrollToLeft"
                        class="bg-gradient-to-r from-white via-white to-transparent absolute -right-20 z-10 top-0 h-full w-16 pointer-events-none" />
                </Transition>

                <Transition>
                    <div v-if="isAbleScrollToLeft" @click="() => scrollLeft()"
                         class="w-6 h-6 z-10 bg-gray-500 hover:bg-gray-700 text-white rounded-full flex items-center justify-center absolute -right-10 top-1/2 -translate-y-1/2 cursor-pointer text-inherit"
                    >
                        <FontAwesomeIcon icon="fal fa-chevron-left"  fixed-width aria-hidden="true" class="text-[8px]"/>
                    </div>
                </Transition>
            </div>

            <Transition>
                <div v-if="isAbleScrollToRight"
                    class="bg-gradient-to-l from-white via-white to-transparent absolute right-8 z-10 top-0 h-full w-16 pointer-events-none" />
            </Transition>

            <Transition>
                <div v-if="isAbleScrollToRight" @click="() => scrollRight()"
                     class="w-6 h-6 z-10 bg-gray-500 hover:bg-gray-700 text-white rounded-full flex items-center justify-center absolute right-4 top-1/2 -translate-y-1/2 cursor-pointer text-inherit"
                >
                    <FontAwesomeIcon icon="fal fa-chevron-left" rotation="180"  fixed-width aria-hidden="true" class="text-[8px]" />
                </div>
            </Transition>


            <nav ref="_scrollContainer" @scroll="() => checkScroll()"
                class="relative flex text-sm text-gray-600 w-full overflow-x-auto scrollbar-hide ml-5">

                <template v-for="(navigation, idxNavigation) in fieldValue?.navigation" :key="idxNavigation">
                    <a :href="navigation?.link?.href" :target="navigation?.link?.target"
                        @mouseenter="() => (onMouseEnterMenu(navigation, idxNavigation))"
                        amouseleave="() => onMouseLeaveMenu()"
                        :style="getStyles(fieldValue?.navigation_container?.properties, screenType)"
                        class="group w-full  py-2 px-6 flex items-center justify-center transition duration-200" :class="hoveredNavigation?.id === navigation.id && isCollapsedOpen
                                ? 'bg-gray-100 text-orange-500'
                                : navigation?.link?.href
                                    ? 'cursor-pointer hover:bg-gray-100 hover:text-orange-500'
                                    : ''
                            ">
                        <FontAwesomeIcon v-if="navigation.icon" :icon="navigation.icon" class="mr-2" />

                        <span xv-if="!navigation?.link?.href" class="text-center whitespace-nowrap">{{ navigation.label
                            }}</span>

                        <FontAwesomeIcon v-if="navigation.type == 'multiple'" :icon="faChevronDown"
                            class="ml-2 text-[8px]" fixed-width />

                    </a>
                </template>
            </nav>

            <Collapse v-if="hoveredNavigation?.subnavs" :when="isCollapsedOpen" as="div"
                class="z-[49] absolute left-0 top-full bg-white border border-gray-300 w-full shadow-lg"
                :class="isCollapsedOpen ? '' : 'border-t-0'"
                :style="getStyles(fieldValue?.container?.properties, screenType)" >
                <div class="grid grid-cols-4 gap-3 p-6">
                    <div v-for="subnav in hoveredNavigation?.subnavs" :key="subnav.title" class="space-y-4">
                        <div v-if="!subnav?.link?.href && subnav.title"
                            :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin : 0, padding : 0, fontWeight : 600, ...getStyles(fieldValue?.sub_navigation?.properties, screenType) }"
                            class="text-gray-700">{{ subnav.title }}</div>
                        <a v-if="subnav?.link?.href && subnav.title" :href="subnav?.link?.href"
                            :target="subnav?.link?.target"
                            :style="{...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin : 0, padding : 0, fontWeight : 600, ...getStyles(fieldValue?.sub_navigation?.properties, screenType)}"
                            class="font-semibold text-gray-700">{{ subnav.title }}</a>
                        <!-- Sub-navigation Links -->
                        <div class="flex flex-col gap-y-3">
                            <div v-for="link in subnav.links" :key="link.url" class="flex items-center gap-x-3">
                                <FontAwesomeIcon :icon="link.icon || faChevronRight"
                                    class="text-[10px] text-gray-400" />
                                <a :href="link?.link?.href" :target="link?.link?.target"
                                    :style="getStyles(fieldValue?.sub_navigation_link?.properties, screenType)"
                                    class="text-gray-500 hover:text-orange-500 hover:underline transition duration-200">
                                    {{ link.label }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </Collapse>

        </div>
    </div>
</template>

<style scoped>
.container {
    max-width: 1980px;
}

/* Optional: Hide scrollbars on some browsers */
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.editor-class a  {
   font-weight: 400;
}

.sub-nav-title  {
    .editor-class a {
        font-weight: 600;
    }}
</style>
