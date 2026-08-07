<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Table from '@/Components/Table/Table.vue';
import { Webpage } from "@/types/webpage";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import {
    faSignIn, faHome, faNewspaper, faBrowser, faUfoBeam, faExternalLink, faSquare
} from '@fal'
import { faCheckSquare } from '@fas'
import { library } from "@fortawesome/fontawesome-svg-core";
import Icon from '@/Components/Icon.vue';
import { computed } from 'vue';
import { toLower, upperFirst } from 'lodash-es';

library.add(
    faSignIn, faHome, faNewspaper, faBrowser, faUfoBeam
)

const props = defineProps<{
    data: object
    tab?: string
}>()

const openWebsite = (href: string) => {
    window.open(href, '_blank')
}
function resolveWebpageRoute(webpage: Webpage) {

    switch (route().current()) {

        case 'grp.org.fulfilments.show.web.webpages.index.type.info':
        case 'grp.org.fulfilments.show.web.webpages.index.type.content':
        case 'grp.org.fulfilments.show.web.webpages.index.type.operations':
            return route(
                'grp.org.fulfilments.show.web.webpages.show',
                [
                    route().params['organisation'],
                    route().params['fulfilment'],
                    route().params['website'],
                    webpage.slug
                ]);
                
        case 'grp.org.shops.show.web.webpages.index.type.info':
        case 'grp.org.shops.show.web.webpages.index.type.content':
        case 'grp.org.shops.show.web.webpages.index.type.operations':
            return route(
                'grp.org.shops.show.web.webpages.show',
                [
                    route().params['organisation'],
                    route().params['shop'],
                    route().params['website'],
                    webpage.slug
                ]);

        case "grp.org.shops.show.web.webpages.index.sub_type.sub_department.families":
        case 'grp.org.shops.show.web.webpages.index.sub_type.department.sub_departments':
        case 'grp.org.shops.show.web.webpages.index.sub_type.department.families':
        case 'grp.org.shops.show.web.webpages.index.sub_type.department.products':
        case 'grp.org.shops.show.web.webpages.index.sub_type.family.products':
        case 'grp.org.shops.show.web.webpages.index.sub_type.family':
        case 'grp.org.shops.show.web.webpages.index.sub_type.sub_department':
        case 'grp.org.shops.show.web.webpages.index.sub_type.product':
        case 'grp.org.shops.show.web.webpages.index.sub_type.department':
        case 'grp.org.shops.show.web.webpages.index.sub_type.department.families_overview':
        case 'grp.org.shops.show.web.webpages.index':
            return route(
                'grp.org.shops.show.web.webpages.show',
                [
                    route().params['organisation'],
                    route().params['shop'],
                    route().params['website'],
                    webpage.slug
                ]);
        case 'grp.org.shops.show.web.webpages.index.type.checkout':
            return route(
                'grp.org.shops.show.web.webpages.show',
                [
                    route().params['organisation'],
                    route().params['shop'],
                    route().params['website'],
                    webpage.slug
                ]);
        case 'grp.org.shops.show.web.webpages.index.type.catalogue':
            return route(
                'grp.org.shops.show.web.webpages.show',
                [
                    route().params['organisation'],
                    route().params['shop'],
                    route().params['website'],
                    webpage.slug
                ]);
        case 'grp.org.shops.show.web.webpages.index.type.small-print':
            return route(
                'grp.org.shops.show.web.webpages.show',
                [
                    route().params['organisation'],
                    route().params['shop'],
                    route().params['website'],
                    webpage.slug
                ]);
        case 'grp.org.shops.show.web.blogs.index':
            return route(
                'grp.org.shops.show.web.blogs.show',
                [
                    route().params['organisation'],
                    route().params['shop'],
                    route().params['website'],
                    webpage.slug
                ]);

        case 'grp.org.fulfilments.show.web.webpages.index':
            return route(
                'grp.org.fulfilments.show.web.webpages.show',
                [
                    route().params['organisation'],
                    route().params['fulfilment'],
                    route().params['website'],
                    webpage.slug
                ]);

        default: 
            return ''
    }
}

function resolveSubDepartmentsRoute(webpage: Webpage) {
    switch (route().current()) {

        case 'grp.org.shops.show.web.webpages.index.sub_type.department':
        case 'grp.org.shops.show.web.webpages.index.sub_type.department.families_overview':
            return route(
                'grp.org.shops.show.web.webpages.index.sub_type.department.sub_departments',
                [
                    route().params['organisation'],
                    route().params['shop'],
                    route().params['website'],
                    webpage.slug
                ]);

        default: 
            return ''
    }
}

function resolveFamiliesRoute(webpage: Webpage) {
    switch (route().current()) {

        case 'grp.org.shops.show.web.webpages.index.sub_type.department.families_overview':
        case 'grp.org.shops.show.web.webpages.index.sub_type.department':
            return route(
                'grp.org.shops.show.web.webpages.index.sub_type.department.families',
                [
                    route().params['organisation'],
                    route().params['shop'],
                    route().params['website'],
                    webpage.slug
                ]);
        case 'grp.org.shops.show.web.webpages.index.sub_type.sub_department':
        case 'grp.org.shops.show.web.webpages.index.sub_type.department.sub_departments':
            return route(
                'grp.org.shops.show.web.webpages.index.sub_type.sub_department.families',
                [
                    route().params['organisation'],
                    route().params['shop'],
                    route().params['website'],
                    webpage.slug
                ]);

        default: 
            return ''
    }
}

function resolveProductsRoute(webpage: Webpage) {
    switch (route().current()) {

        case 'grp.org.shops.show.web.webpages.index.sub_type.department.families_overview':
        case 'grp.org.shops.show.web.webpages.index.sub_type.department':
            return route(
                'grp.org.shops.show.web.webpages.index.sub_type.department.products',
                [
                    route().params['organisation'],
                    route().params['shop'],
                    route().params['website'],
                    webpage.slug
                ]);
        case 'grp.org.shops.show.web.webpages.index.sub_type.sub_department':
        case 'grp.org.shops.show.web.webpages.index.sub_type.department.sub_departments':
            return route(
                'grp.org.shops.show.web.webpages.index.sub_type.sub_department.products',
                [
                    route().params['organisation'],
                    route().params['shop'],
                    route().params['website'],
                    webpage.slug
                ]);
        case 'grp.org.shops.show.web.webpages.index.sub_type.family':
        case 'grp.org.shops.show.web.webpages.index.sub_type.department.families':
        case 'grp.org.shops.show.web.webpages.index.sub_type.sub_department.families':
            return route(
                'grp.org.shops.show.web.webpages.index.sub_type.family.products',
                [
                    route().params['organisation'],
                    route().params['shop'],
                    route().params['website'],
                    webpage.slug
                ]);
        default: 
            return ''
    }
}

type WebpageRow = Record<string, any>

type SelectedWebpage = {
    id: string
    code: string
    title: string
}

/**
 * Ziggy's route() is rebuilt on every render and each row asks for up to four urls,
 * twice each (v-if + :href), so the resolved urls are cached per webpage id.
 */
function memoizeRouteResolver(resolver: (webpage: Webpage) => string) {
    const resolvedRoutes = new Map<string, string>()

    return (webpage: Webpage) => {
        const cacheKey = String(webpage.id ?? webpage.slug)

        if (!resolvedRoutes.has(cacheKey)) {
            resolvedRoutes.set(cacheKey, resolver(webpage))
        }

        return resolvedRoutes.get(cacheKey) as string
    }
}

const webpageRoute = memoizeRouteResolver(resolveWebpageRoute)
const subDepartmentsRoute = memoizeRouteResolver(resolveSubDepartmentsRoute)
const familiesRoute = memoizeRouteResolver(resolveFamiliesRoute)
const productsRoute = memoizeRouteResolver(resolveProductsRoute)

const selectedWebpages = defineModel<SelectedWebpage[]>('selectedWebpages');

const webpageRows = computed<WebpageRow[]>(() => (props.data as { data?: WebpageRow[] })?.data ?? [])

const selectedWebpageIds = computed(() => new Set((selectedWebpages.value ?? []).map(item => item.id)))

const toSelectedWebpage = (webpage: WebpageRow): SelectedWebpage => ({
    id: webpage.id,
    code: webpage.code,
    title: webpage.title,
})

const onChangeChecked = (checked: boolean, selectedItem: WebpageRow) => {
    if (!selectedWebpages.value) return

    if (checked) {
        if (!selectedWebpageIds.value.has(selectedItem.id)) {
            selectedWebpages.value = [...selectedWebpages.value, toSelectedWebpage(selectedItem)]
        }
    } else {
        selectedWebpages.value = selectedWebpages.value.filter(item => item.id != selectedItem.id)
    }
}

const isWebpageChecked = (webpage: WebpageRow) => {
    return selectedWebpageIds.value.has(webpage.id)
}

const isAllWebpagesChecked = computed(() => {
    const ids = selectedWebpageIds.value

    return webpageRows.value.length > 0 && webpageRows.value.every(row => ids.has(row.id))
})

const onCheckedAll = ({ data, allChecked }: { data: WebpageRow[], allChecked: boolean }) => {
    if (!selectedWebpages.value) return

    if (allChecked) {
        const ids = selectedWebpageIds.value
        const newItems = data.filter(row => !ids.has(row.id)).map(row => toSelectedWebpage(row))

        selectedWebpages.value = [...selectedWebpages.value, ...newItems]
    } else {
        const rowIds = new Set(data.map(row => row.id))

        selectedWebpages.value = selectedWebpages.value.filter(item => !rowIds.has(item.id))
    }
}

</script>


<template>
    <Table 
        :resource="data"
        :isCheckBox="true"
        :isChecked="isWebpageChecked"
        checkboxKey='id'
        :name="tab"
        class="mt-5"
    >
        <template #header-checkbox>
            <div @click="onCheckedAll({ data: webpageRows, allChecked: !isAllWebpagesChecked })" class="py-1.5 cursor-pointer">
                <FontAwesomeIcon
                    :icon="isAllWebpagesChecked ? faCheckSquare : faSquare"
                    :class="isAllWebpagesChecked ? 'text-green-500' : 'text-gray-500 hover:text-gray-700'"
                    class="mx-auto block h-5 my-auto"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
        </template>

        <template #checkbox="{ data: webpage }">
            <FontAwesomeIcon
                v-if="isWebpageChecked(webpage)"
                @click="onChangeChecked(false, webpage)"
                :icon="faCheckSquare"
                class="text-green-500 p-2 cursor-pointer text-lg mx-auto block"
                fixed-width
                aria-hidden="true"
            />
            <FontAwesomeIcon
                v-else
                @click="onChangeChecked(true, webpage)"
                :icon="faSquare"
                class="text-gray-500 hover:text-gray-700 p-2 cursor-pointer text-lg mx-auto block"
                fixed-width
                aria-hidden="true"
            />
        </template>

        <!-- Column: Code -->
        <template #cell(code)="{ item: webpage }">
            <Link v-if="!!webpageRoute(webpage)" :href="webpageRoute(webpage)" class="primaryLink">
                {{ webpage['code'] }}
            </Link>
            <div v-else>
                {{ webpage['code'] }}
            </div>
        </template>

        <!-- Column: State -->
        <template #cell(state)="{ item: webpage }">
            <Icon :data="webpage.state_icon" class="px-1 mx-auto block" />
        </template>

        <template #cell(number_current_sub_departments)="{ item: webpage }">
            <Link v-if="!!subDepartmentsRoute(webpage)" :href="subDepartmentsRoute(webpage)" class="secondaryLink">
                {{ webpage['number_current_sub_departments'] }}
            </Link>
            <div v-else>
                {{ webpage['number_current_sub_departments'] }}
            </div>
        </template>

        <template #cell(number_current_families)="{ item: webpage }">
            <Link v-if="!!familiesRoute(webpage)" :href="familiesRoute(webpage)" class="secondaryLink">
                {{ webpage['number_current_families'] }}
            </Link>
            <div v-else>
                {{ webpage['number_current_families'] }}
            </div>
        </template>

        <template #cell(number_current_products)="{ item: webpage }">
            <Link v-if="!!productsRoute(webpage)" :href="productsRoute(webpage)" class="secondaryLink">
                {{ webpage['number_current_products'] }}
            </Link>
            <div v-else>
                {{ webpage['number_current_products'] }}
            </div>
        </template>

        <template #cell(type)="{ item: webpage }">
            <!-- <FontAwesomeIcon :icon="webpage.typeIcon.icon" class="" /> -->
            <Icon :data="webpage.typeIcon" class="px-1" />
        </template>

         <template #cell(sub_type)="{ item: webpage }">
           <span>{{ upperFirst(toLower(String(webpage.sub_type ?? '').replace(/_/g, ' '))) }}</span>
        </template>

        <template #cell(action)="{ item: webpage }">
            <a
                v-if="webpage.href"
                class="px-2 cursor-pointer text-gray-400 hover:text-gray-700"
                v-tooltip="'Open the website in new tab'"
                :href="webpage.href ?? '#'"
                target="_blank"
                rel="noopener noreferrer"

            >
                <FontAwesomeIcon :icon="faExternalLink" size="xl" fixed-width aria-hidden="true" />
            </a>

            <div v-else class="text-gray-400">
                {{ ctrans("No canonical url") }}
            </div>
        </template>

        <template #heading(level)="{ item: column }">
            <div class="flex flex-row items-center justify-start">
                <div v-if="typeof column.label === 'object'">
                    <FontAwesomeIcon v-if="column.label.type === 'icon'" :title="capitalize(column.label.tooltip)"
                        aria-hidden="true" :icon="column.label.data" size="lg" />
                    <FontAwesomeIcon v-else :title="'icon'" aria-hidden="true" :icon="column.label" size="lg" />
                </div>

                <svg v-if="column.sortable" aria-hidden="true" class="w-3 h-3 ml-2" :class="{
                    'text-gray-400': !column.sorted,
                    'text-green-500': column.sorted,
                }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" :sorted="column.sorted">
                    <path v-if="!column.sorted" fill="currentColor"
                        d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41zm255-105L177 64c-9.4-9.4-24.6-9.4-33.9 0L24 183c-15.1 15.1-4.4 41 17 41h238c21.4 0 32.1-25.9 17-41z" />

                    <path v-if="column.sorted === 'asc'" fill="currentColor"
                        d="M279 224H41c-21.4 0-32.1-25.9-17-41L143 64c9.4-9.4 24.6-9.4 33.9 0l119 119c15.2 15.1 4.5 41-16.9 41z" />

                    <path v-if="column.sorted === 'desc'" fill="currentColor"
                        d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41z" />
                </svg>
            </div>
        </template>
    </Table>
</template>
