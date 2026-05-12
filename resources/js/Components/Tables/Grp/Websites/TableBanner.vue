<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 13 Jul 2023 22:20:34 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { library } from "@fortawesome/fontawesome-svg-core"
import Table from '@/Components/Table/Table.vue'
import { Banner } from "@/types/banner"
import Icon from '@/Components/Icon.vue'
import { faSeedling, faBroadcastTower, faImage, faSparkles, faRocket, faDoNotEnter } from '@fal'
import Image from "@common/Components/Image.vue"
import { useFormatTime } from '@/Composables/useFormatTime'
import { useLocaleStore } from '@/Stores/locale'


const locale = useLocaleStore()

library.add(faSeedling, faBroadcastTower, faImage, faSparkles, faRocket, faDoNotEnter)

const props = defineProps<{
    data: object,
    tab?: string
}>()


function bannerRoute(banner: Banner) {
  console.log(route().current())
    switch (route().current()) {
        case "grp.org.shops.show.web.banners.index":
            return route(
                "grp.org.shops.show.web.banners.show",
                [
                    route().params.organisation,
                    route().params.shop,
                    route().params.website,
                    banner.slug,
                ]);
      case "grp.org.fulfilments.show.web.banners.index":
            return route(
                "grp.org.fulfilments.show.web.banners.show",
                [
                    route().params.organisation,
                    route().params.fulfilment,
                    route().params.website,
                    banner.slug,
                ]);
        default:
            return ''
    }
}

</script>

<template>

    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item: banner }">
            <Link :href="bannerRoute(banner)" :id="banner['slug']" class="primaryLink">
            {{ banner['name'] }}
            </Link>
        </template>

        <template #cell(state)="{ item: banner }">
            <Icon :data="banner['state_icon']" class="px-1" />
        </template>

        <template #cell(image_thumbnail)="{ item: banner }">
            <div class="h-11 overflow-hidden aspect-[4/1]">
                <Image v-if="banner['image_thumbnail']" :src="banner['image_thumbnail']" />
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                    <defs>
                        <pattern id="pattern_mQij" patternUnits="userSpaceOnUse" width="13" height="13"
                            patternTransform="rotate(45)">
                            <line x1="0" y="0" x2="0" y2="13" stroke="#CCCCCC" stroke-width="12" />
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#pattern_mQij)" :opacity="0.4" />
                </svg>

            </div>
        </template>

        <template #cell(websites)="{ item: banner }">
            <Link v-for="website in banner['websites']" :href="websiteRoute(banner, website.slug)"
                class="specialUnderlineCustomer py-4 px-2 mr-2">{{ website.name }}</Link>
        </template>

        <template #cell(date)="{ item: banner }">
            <div class="text-gray-500">
                {{ useFormatTime(banner['date'], { localeCode: locale.language.code, formatTime: 'hm' }) }}
                <Icon class="ml-1" :data="banner['date_icon']" />
            </div>
        </template>
    </Table>


</template>
