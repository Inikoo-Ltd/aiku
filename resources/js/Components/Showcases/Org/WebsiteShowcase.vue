<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Feb 2024 12:36:21 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faFragile, faGlobe, faLink, faSearch, faPencil } from "@fal"
import { computed, ref } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { trans } from "laravel-vue-i18n"
import { StatsBoxTS } from "@/types/Components/StatsBox"
import StatsBox from "@/Components/Stats/StatsBox.vue"
import { routeType } from "@/types/route"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { useFormatTime, useRangeFromNow } from "@/Composables/useFormatTime"

library.add(faGlobe, faLink, faSearch)

const props = defineProps<{
    data: {
        slug: string
        url: string
        domain: string
        state: string
        status: string
        created_at: string
        updated_at: string
        layout: string
        stats: StatsBoxTS[]
        content_blog_stats: StatsBoxTS[]
    }
    route_storefront: routeType
    luigi_data: {
        last_reindexed: string
        luigisbox_tracker_id: string
        luigisbox_private_key: string
        luigisbox_lbx_code: string
    }
}>()

const links = ref([
    { label: trans("Edit Header"), route_target: props.data.layout.headerRoute, icon: faPencil },
    { label: trans("Edit Menu"), route_target: props.data.layout.menuRoute, icon: faPencil },
    { label: trans("Edit Footer"), route_target: props.data.layout.footerRoute, icon: faPencil }
]);

window.reindexwebsite = async () => {
    try {
        const response = await axios.post(
            route(
                'grp.models.website_luigi.reindex',
                {
                    website: props.data?.id
                }
            ),
            { }
        )

        console.log('success reindex website', response.data)
        if (response.status !== 200) {
            
        }
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error.message || trans("Please try again or contact administrator"),
            type: 'error'
        })
    }
}

// Section: Button reindex website search
const isAbleReindex = computed(() => {
    const lastReindexed30Minutes = new Date(props.luigi_data.last_reindexed)
    lastReindexed30Minutes.setMinutes(lastReindexed30Minutes.getMinutes() + 30)

    return lastReindexed30Minutes < new Date()
})
const dateLastReindex = new Date(props.luigi_data.last_reindexed)
const dateAdd30MinutesLastReindex = dateLastReindex.setMinutes(dateLastReindex.getMinutes() + 30) 

</script>

<template>
    <!-- Box: Url and Buttons in a single row -->
    <div class="px-6 py-12 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- URL Box -->
            <div class="">
                <div class="bg-white w-fit h-fit flex items-center gap-x-3 md:w-96">
                    <a :href="props.data.url" target="_blank" v-tooltip="trans('Go To Website')"
                        class="hover:bg-gray-50 ring-1 ring-gray-300 cursor-pointer rounded overflow-hidden flex text-xxs md:text-base text-gray-500">
                        <div class="bg-gray-200 py-2 px-2">
                            <FontAwesomeIcon :icon="faGlobe" class="px-1" aria-hidden="true" />
                        </div>
                        <div class="flex items-center px-4">
                            {{ props.data.url }}
                        </div>
                    </a>
                </div>

                <div class="border-t border-gray-300 mt-6 pt-4">
                    <div class="font-semibold w-fit text-lg mb-2">
                        {{trans('Product Catalogue')}}
                    </div>

                    <div class="grid grid-cols-2 gap-2 md:max-w-lg">
                        <StatsBox
                            v-for="stat in props.data.stats"
                            :stat
                        />
                    </div>
                    <div class="mt-6 font-semibold w-fit text-lg mb-2">
                        {{trans('Content & Blog')}}
                    </div>
                    <div class="grid grid-cols-2 gap-2 md:max-w-lg">
                        <StatsBox
                            v-for="stat in props.data.content_blog_stats"
                            :stat
                        />
                    </div>
                </div>
            </div>

            <!-- Buttons Card (in the right part of the grid) -->
            <div class="flex justify-end">
                <div class="w-64 border border-gray-300 rounded-md p-2 h-fit">
                    <div class="p-2">
                        <ButtonWithLink :routeTarget="route_storefront" icon="fal fa-home" type="tertiary" :label="trans('Storefront')" full />
                    </div>

                    <div v-for="(item, index) in links" :key="index" class="px-2 py-1">
                        <ButtonWithLink :routeTarget="item.route_target" full :icon="item.icon" :label="item.label"
                            type="secondary" />
                    </div>

                    <div class="p-2 space-y-2">
                        <ButtonWithLink :routeTarget="{
                            name: 'grp.models.website.break_cache',
                            parameters: {
                                website: data?.id
                            }
                        }" method="post" :icon="faFragile" type="tertiary" :label="trans('Break cache')" full>
                            <template #iconRight>
                                <div v-tooltip="trans('If you made some changes but didn\'t updated yet in the website, use this feature')" class="text-gray-400 hover:text-gray-700">
                                    <FontAwesomeIcon icon="fal fa-info-circle" class="" fixed-width aria-hidden="true" />
                                </div>
                            </template>
                        </ButtonWithLink>

                        <ButtonWithLink
                            v-if="luigi_data?.luigisbox_tracker_id"s
                            v-tooltip="isAbleReindex ? '' : trans('You can reindex again at :date', { date: useFormatTime(new Date(dateAdd30MinutesLastReindex), { formatTime: 'hm' }) })"
                            :disabled="!isAbleReindex"
                            :routeTarget="{
                                name: 'grp.models.website_luigi.reindex',
                                parameters: {
                                    website: data?.id
                                }
                            }"
                            icon="fal fa-search"
                            method="post"
                            :type="!isAbleReindex || luigi_data?.luigisbox_private_key ? 'tertiary' : 'warning'"
                            full
                        >
                            <template #label>
                                <span class="text-xs">
                                    {{ trans('Reindex Website Search') }}
                                </span>
                            </template>
                            <template v-if="isAbleReindex" #iconRight>
                                <div v-if="luigi_data?.luigisbox_private_key" v-tooltip="trans('This will reindexing the product that will appear in the search feature')" class="text-gray-400 hover:text-gray-700">
                                    <FontAwesomeIcon icon="fal fa-info-circle" class="" fixed-width aria-hidden="true" />
                                </div>
                                <div v-else v-tooltip="trans('Please input Luigi Private Key do start reindexing')" class="text-amber-500">
                                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="" fixed-width aria-hidden="true" />
                                </div>
                            </template>
                        </ButtonWithLink>

                        <!-- {{ useFormatTime(lastReindexed30Minutes, {
                            formatTime: 'hm'
                        }) }}
                        <br>
                        {{ props.luigi_data.last_reindexed }} -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
