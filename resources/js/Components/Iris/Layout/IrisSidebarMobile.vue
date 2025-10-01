<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronRight, faExternalLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { faSignIn, faSignOut, faTimesCircle } from '@fas'
import { faChevronCircleDown } from '@fal'
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

library.add(faChevronRight, faExternalLink)

const props = defineProps<{
    productCategories: {}
    customMenusTop: {}[]
    customMenusBottom: {}
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
}>()

const layout = inject('layout', retinaLayoutStructure)

const isLoggedIn = inject('isPreviewLoggedIn', false)
const onLogout = inject('onLogout', () => console.log('Logout function not injected'))
</script>

<template>
    <div class="menu-container-mobile">
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
                            <FontAwesomeIcon :icon="faChevronCircleDown" fixed-width
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
                                <FontAwesomeIcon :icon="faChevronCircleDown" fixed-width
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

        <div class="login-section pl-3 pr-5 py-4 border-t border-[#e5e5e5] flex items-center">
            <ButtonWithLink
                v-if="!isLoggedIn"
                url="/app"
                :label="trans('Login')"
                full
                :icon="faSignIn"
            />
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
</template>

<style scoped lang="scss">
.menu-container-mobile {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.menu-content {
    flex: 1;
    overflow-y: auto;
    padding-bottom: 1rem;
}

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
</style>