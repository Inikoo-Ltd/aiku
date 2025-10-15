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
import SwitchLanguage from "../SwitchLanguage.vue"
import LinkIris from "../LinkIris.vue"

library.add(faChevronRight, faExternalLink)

const props = defineProps<{
    productCategories: {}
    customMenusTop: {}[]
    customMenusBottom: {}
    activeIndex: {}
    activeCustomIndex: {}
    activeCustomTopIndex: {}
    internalHref: Function
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
                        <DisclosureButton class="w-full text-left px-2 py-2 font-semibold border-b">
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
                                <LinkIris v-if="subDept?.url !== null" :href="internalHref(subDept)"
                                    :target="getTarget(subDept)"
                                    class="block text-base font-bold mb-2"
                                    :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation?.properties) }">
                                    {{ subDept.name }}
                                </LinkIris>
                                <span v-else class="block text-base font-bold mb-2"
                                    :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation?.properties) }">
                                    {{ subDept.name }}
                                </span>
                                <div v-if="subDept.families" class="space-y-2 mt-2 ml-4 pl-4 border-gray-200">
                                    <div v-for="(family, familyIndex) in subDept.families" :key="familyIndex">
                                        <LinkIris 
                                            v-if="family?.url !== null" :href="internalHref(family)" :target="getTarget(family)"
                                            :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation_link?.properties) }"
                                            class="block text-sm relative hover:text-primary transition-all">
                                            <span class="absolute left-0 -ml-4">–</span>
                                            {{ family.name }}
                                        </LinkIris>
                                        <span v-else
                                            :key="'span-' + familyIndex" v-if="family?.url === null"
                                            :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation_link?.properties) }"
                                            class="block text-sm relative">
                                            <span class="absolute left-0 -ml-4">–</span>
                                            {{ family.name }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </DisclosurePanel>
                    </Disclosure>

                    <!-- Custom Menu Top SINGLE LINK -->
                    <div v-else class="px-2 py-2 border-b">
                        <LinkIris v-if="customTopItem?.url !== null" :href="internalHref(customTopItem)"
                            :target="getTarget(customTopItem)"
                            :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }"
                            class="font-bold">
                            {{ customTopItem.name }}
                        </LinkIris>

                        <span v-else
                            :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }"
                            class="font-bold">{{ customTopItem.name }}</span>
                    </div>
                </div>
            </div>

            <!-- Middle: Product Categories (auto) -->
            <div v-for="(category, index) in sortedProductCategories" :key="index">
                <!-- Product Category WITH Sub-departments -->
                <Disclosure v-if="category.sub_departments && category.sub_departments.length > 0" as="div" class="border-b"
                    v-slot="{ open }">
                    <DisclosureButton class="w-full text-left px-2 py-2 font-semibold border-b">
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
                            <LinkIris
                                v-if="subDept?.url !== null"
                                :href="internalHref(subDept)"
                            >
                                <div :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation?.properties) }"
                                    class="block text-base font-bold mb-2">
                                    <span class="absolute left-0 -ml-4">–</span>
                                    {{ subDept.name }}
                                </div>
                            </LinkIris>
                            <span v-else class="block text-base font-bold mb-2"
                                :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation?.properties) }">
                                {{ subDept.name }}
                            </span>

                            <div v-if="subDept.families" class="space-y-2 mt-2 pl-4 border-gray-200">
                                <div v-for="(family, familyIndex) in subDept.families" :key="familyIndex">
                                    <LinkIris
                                        v-if="family?.url !== null" 
                                        :href="internalHref(family)"
                                    >
                                        <div :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation_link?.properties) }"
                                            class="block text-sm relative hover:text-primary transition-all">
                                            <span class="absolute left-0 -ml-4">–</span>
                                            {{ family.name }}
                                        </div>
                                    </LinkIris>
                                    <span v-else
                                        :key="'span-' + familyIndex" v-if="family?.url === null"
                                        :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation_link?.properties) }"
                                        class="block text-sm relative">
                                        <span class="absolute left-0 -ml-4">–</span>
                                        {{ family.name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </DisclosurePanel>
                </Disclosure>

                <!-- Product Category SINGLE LINK -->
                <div v-else class="px-2 py-2 border-b">
                    <LinkIris
                        v-if="category?.url !== null"
                        :href="internalHref(category)"
                        :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }"
                        class="font-bold">
                        {{ category.name }}
                    </LinkIris>

                    <span v-else
                        :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }"
                        class="font-bold">{{ category.name }}</span>
                </div>
            </div>

            <!-- Section: bottom menu -->
            <div v-if="customMenusBottom && customMenusBottom.length > 0">
                <!-- <hr class="my-4 border-gray-300"> -->
                <div v-for="(customItem, customIndex) in customMenusBottom" :key="'custom-' + customIndex">
                    <!-- Custom Menu WITH Sub-departments -->
                    <Disclosure v-if="customItem.sub_departments && customItem.sub_departments.length > 0"
                        v-slot="{ open }">
                        <DisclosureButton class="w-full text-left px-2 py-2 font-semibold border-b">
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
                                <LinkIris v-if="subDept?.url !== null" :href="internalHref(subDept)"
                                    class="block text-base font-bold mb-2"
                                    :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation?.properties) }">
                                    {{ subDept.name }}
                                </LinkIris>

                                <span v-else class="block text-base font-bold mb-2"
                                    :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation?.properties) }">
                                    {{ subDept.name }}
                                </span>
                                <div v-if="subDept.families" class="space-y-2 mt-2 ml-4 pl-4 border-gray-200">
                                    <div v-for="(family, familyIndex) in subDept.families" :key="familyIndex">
                                        <LinkIris 
                                            v-if="family?.url !== null" :href="internalHref(family)" :target="getTarget(family)"
                                            :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation_link?.properties) }"
                                            class="block text-sm relative hover:text-primary transition-all">
                                            <span class="absolute left-0 -ml-4">–</span>
                                            {{ family.name }}
                                        </LinkIris>
                                        <span v-else
                                            :key="'span-' + familyIndex" v-if="family?.url === null"
                                            :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.sub_navigation_link?.properties) }"
                                            class="block text-sm relative">
                                            <span class="absolute left-0 -ml-4">–</span>
                                            {{ family.name }}
                                        </span>
                                    </div>

                                </div>
                            </div>
                        </DisclosurePanel>
                    </Disclosure>

                    <!-- Custom Menu SINGLE LINK -->
                    <div v-else class="px-2 py-2 border-b">
                        <LinkIris v-if="customItem?.url !== null" :href="internalHref(customItem)"
                            :target="getTarget(customItem)"
                            :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }"
                            class="font-bold">
                            {{ customItem.name }}
                        </LinkIris>

                        <span v-else
                            :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), margin: 0, padding: 0, ...getStyles(props.menu?.navigation_container?.properties) }"
                            class="font-bold">{{ customItem.name }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Switch Language -->
        <div v-if="layout.app.environment !== 'production' && Object.values(layout.iris.website_i18n?.language_options || {})?.length" class="border-t border-[#e5e5e5] px-4 mb-1 flex justify-between items-center text-xs">
            <div>{{ trans("Language") }}:</div>
            <SwitchLanguage>
                <template #default="{ isLoadingChangeLanguage }">
                    <div class="underline text-xs py-2">
                        {{ Object.values(layout.iris.website_i18n?.language_options || {})?.find(language => language.code === layout.iris.website_i18n.current_language?.code)?.name }}
                        <img class="inline pr-1 pl-1 h-[1em]" :src="`/flags/${layout.iris.website_i18n.current_language?.flag}`" :alt="layout.iris.website_i18n.current_language?.code" title='capitalize(countryName)'  />
                    </div>
                </template>
            </SwitchLanguage>
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