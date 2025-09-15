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
}>();

const layout = inject("layout", {});
const isLoggedIn = inject('isPreviewLoggedIn', false)
const onLogout = inject('onLogout')
const isOpenMenuMobile = inject('isOpenMenuMobile', ref(false));

const isMobile = ref(false);
const activeIndex = ref(null); // active category
const activeSubIndex = ref(null); // active subdepartment

// Computed properties for sorted data
const sortedProductCategories = computed(() => {
    if (!props.productCategories) return [];
    return [...props.productCategories].sort((a, b) =>
        (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base' })
    );
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

const sortedFamilies = computed(() => {
    if (activeSubIndex.value === null || !sortedSubDepartments.value[activeSubIndex.value]?.families) return [];
    return [...sortedSubDepartments.value[activeSubIndex.value].families].sort((a, b) =>
        (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base' })
    );
});

// reset subdepartment when category changes
const setActiveCategory = (index: number) => {
    activeIndex.value = index;
    activeSubIndex.value = null;
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

console.log('layout', layout)
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
            width: isMobile ? null : !isNull(activeIndex) ? !isNull(activeSubIndex) ? '60%' : '40%' : '20%'
        }">
            <template #header>
                <img :src="header?.logo?.image?.source?.original" :alt="header?.logo?.alt" class="h-16" />
            </template>

            <div v-if="isMobile" class="menu-container-mobile">
                <div class="menu-content">
                    <div v-for="(item, index) in sortedNavigation" :key="index">
                        <!-- MULTIPLE TYPE WITH DROPDOWN -->
                        <Disclosure v-if="item.type === 'multiple'" v-slot="{ open }">
                            <DisclosureButton class="w-full text-left p-4 font-semibold text-gray-600 border-b">
                                <div class="flex justify-between items-center text-lg"
                                    :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }">
                                    <div>
                                        <a v-if="item.link && item.link.href" :href="item.link.href"
                                            :target="item.link.target">
                                            <span>{{ item.label }}</span>
                                        </a>
                                        <span v-else>
                                            {{ item.label }}
                                        </span>
                                    </div>
                                    <FontAwesomeIcon :icon="faChevronCircleDown"
                                        :class="{ 'rotate-180': open, 'transition-transform duration-300': true }" />
                                </div>
                            </DisclosureButton>

                            <DisclosurePanel class="disclosure-panel">
                                <div v-for="(submenu, subIndex) in item.subnavs" :key="subIndex" class="mb-6">
                                    <a v-if="submenu.title" :href="submenu.link?.href" :target="submenu.link?.target"
                                        class="block text-base font-bold text-gray-700 mb-2"
                                        :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation?.properties) }">
                                        {{ submenu.title }}
                                    </a>

                                    <div v-if="submenu.links" class="space-y-2 mt-2 ml-4 pl-4  border-gray-200">
                                        <a v-for="(menu, menuIndex) in [...submenu.links].sort((a, b) => (a.label || '').localeCompare(b.label || '', undefined, { sensitivity: 'base' }))" :key="menuIndex"
                                            :href="menu.link?.href" :target="menu.link?.target"
                                            :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation_link?.properties) }"
                                            class="block text-sm text-gray-700 relative hover:text-primary transition-all">
                                            <span class="absolute left-0 -ml-4">–</span>
                                            {{ menu.label }}
                                        </a>
                                    </div>

                                </div>
                            </DisclosurePanel>
                        </Disclosure>

                        <!-- SINGLE LINK -->
                        <div v-else class="py-4 px-5 border-b">
                            <a :href="item.link?.href" :target="item.link?.target"
                                :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }"
                                class="font-bold text-gray-600 text-lg">
                                {{ item.label }}
                            </a>
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

            <!-- Two-column menu -->

            <div v-else
                :class="['menu-container grid h-full', activeIndex !== null && 'grid-cols-2', activeSubIndex !== null && 'grid-cols-3']">
                <!-- Column 1: Categories -->
                <div :class="[activeIndex !== null && 'border-r', 'overflow-y-auto']">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-2 py-4 border-b">
                        <h3 class="font-semibold text-sm">Departments</h3>
                    </div>

                    <!-- List -->
                    <div v-for="(item, index) in sortedProductCategories" :key="index" class="p-2 px-4 flex items-center justify-between cursor-pointer transition-colors duration-200"
                        :class="[
                            activeIndex === index
                                ? `bg-gray-100 font-semibold text-[${layout.iris.theme?.color[0]}]`
                                : ' hover:bg-gray-50'
                        ]" @click="setActiveCategory(index)">
                        <div>{{ item.name }}</div>
                        <FontAwesomeIcon :icon="faChevronRight" class="text-xs transition-transform duration-200" />
                    </div>
                </div>

                <!-- Column 2: Subdepartments -->
                <div v-if="activeIndex !== null" :class="[activeSubIndex !== null && 'border-r']">
                    <!-- Header -->
                    <div class="flex items-center justify-between py-4 px-4">
                        <h3 class="font-semibold text-sm">Sub-Departments</h3>
                    </div>

                    <div class="overflow-y-auto">
                    <div v-if="sortedSubDepartments.length">
                        <div v-for="(sub, sIndex) in sortedSubDepartments" :key="sIndex"
                            class="p-2 px-4 flex items-center justify-between cursor-pointer transition-colors duration-200"
                            :class="[
                                activeSubIndex === sIndex
                                    ? `bg-gray-100 font-semibold text-[${layout.iris.theme?.color[0]}]`
                                    : 'hover:bg-gray-50 text-gray-700'
                            ]" @click="activeSubIndex = sIndex">
                            <div>{{ sub.name }}</div>
                            <FontAwesomeIcon :icon="faChevronRight" class="transition-transform duration-200 text-xs" />
                        </div>
                        <div class="p-2 px-4  cursor-pointer font-bold">
                            <a :href="'/' + sortedProductCategories[activeIndex].url">
                                <Button label="View all" :icon="faExternalLink" size="xs" />
                            </a>
                        </div>
                    </div>
                    <div v-else class="p-2 text-gray-400 italic">
                        No subdepartments available
                    </div>
                    </div>
                </div>

                <!-- Column 3: Families -->
                <div v-if="activeSubIndex !== null" >
                    <!-- Header -->
                    <div class="flex items-center justify-between p-4">
                        <h3 class="font-semibold text-sm">Families</h3>
                    </div>

                    <div v-if="activeSubIndex !== null" class="overflow-y-auto">
                    <div v-if="sortedFamilies.length">
                        <div v-for="(child, cIndex) in sortedFamilies"
                            :key="cIndex" class="p-2 px-4  cursor-pointer hover:bg-gray-50">
                            <a :href="'/' + child.url">{{ child.name }}</a>
                        </div>
                        <div class="p-2 px-4  cursor-pointer hover:bg-gray-50 font-bold">
                            <a :href="'/' + sortedSubDepartments[activeSubIndex].url">
                                <Button label="View all" :icon="faExternalLink" size="xs" />
                            </a>
                        </div>
                    </div>
                    </div>
                    <div v-else class="p-2 text-gray-400 italic">
                        No further items
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

  a, div {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    font-size: 0.95rem;
    transition: color 0.25s ease;
  }

  a:hover {
    color: #2563eb; /* primary hover */
  }

  div:hover {
    color: #dc2626; /* logout hover */
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
