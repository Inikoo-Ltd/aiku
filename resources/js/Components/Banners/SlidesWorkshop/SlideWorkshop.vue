<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 23 Jul 2023 16:36:45 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage, faExpandArrows, faAlignCenter, faTrash, faStopwatch } from '@fal'
import TextAlign from './Fields/TextAlign.vue'
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"

import Toogle from './Fields/PrimitiveToggle.vue'
import PrimitiveInput from './Fields/PrimitiveInput.vue'
import Select from './Fields/PrimitiveSelect.vue'
import Radio from './Fields/PrimitiveRadio.vue'
import SlideBackground from "./Fields/SlideBackground.vue"
import Corners from "@/Components/Banners/SlidesWorkshop/Fields/Corners/Corners.vue"
import Colorpicker from '@/Components/Banners/SlidesWorkshop/Fields/ColorPicker.vue'
import SelectFont from '@/Components/Banners/SlidesWorkshop/Fields/SelectFont.vue'
import { routeType } from '@/types/route'


library.add(faImage, faExpandArrows, faAlignCenter, faTrash, faStopwatch)
const props = defineProps<{
    currentComponentBeenEdited: Object
    blueprint: Array
    remove : Function
    common: any
    bannerType?: string
    uploadRoutes: routeType
}>()

const getComponent = (componentName: string) => {
    const components: any = {
        'text': PrimitiveInput,
        'radio': Radio,
        'slideBackground': SlideBackground,
        'corners': Corners,
        'colorpicker' : Colorpicker,
        'select': Select,
        'selectFont': SelectFont,
        'textAlign': TextAlign,
        'toogle':Toogle
    };
    return components[componentName]
};


const current = ref(0);

defineExpose({
    current,
});

</script>

<template>
    <div class="divide-y divide-gray-200 lg:grid grid-flow-col lg:grid-cols-12 lg:divide-y-0 lg:divide-x w-full">

        <!-- Left Tab: Navigation -->
        <aside class="py-0 lg:col-span-3 lg:h-full">
            <nav role="navigation" class="space-y-1">
                <ul class="flex sm:block">
                    <li v-for="(item, key) in blueprint" :key="key" @click="current = key"
                        :class="[
                            'group cursor-pointer px-6 sm:px-3 py-2 flex items-center justify-center sm:justify-start text-sm font-medium',
                            key == current
                                ? 'bg-gray-200 sm:border-l-4 sm:border-amber-300 text-gray-600 transition-all duration-100 ease-in-out'
                                : 'hover:bg-gray-100 text-gray-400 hover:text-gray-500 transition-all duration-100 ease-in-out',
                        ]"
                        :aria-current="key === current ? 'page' : undefined"
                    >
                        <FontAwesomeIcon v-if="item.icon" aria-hidden="true" class="flex-shrink-0 sm:-ml-1 sm:mr-3 h-6 w-6 text-gray-500 sm:text-gray-400 sm:group-hover:text-gray-500" :icon="item.icon" />
                        <span class="hidden sm:inline truncate">{{trans(item.title)}}</span>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Content of forms -->
        <div class="px-4 sm:px-6 md:px-4 pt-6 xl:pt-4 col-span-9 flex flex-grow justify-center">
            <div class="flex flex-col w-full gap-y-1">
                <dl v-for="(fieldData, index ) in blueprint[current].fields" :key="index" class="pb-4 sm:pb-5 sm:gap-4 w-full">
                    <!-- Title -->
                    <dt v-if="fieldData.name != 'image_source' && fieldData.label" class="text-sm font-medium text-gray-500">
                        <div class="inline-flex items-start leading-none">
                            <span>{{ fieldData.label }}</span>
                        </div>
                    </dt>

                    <!-- Fields -->
                    <dd class="flex text-sm text-gray-700 sm:mt-0 w-full">
                        <div class="relative flex-grow">
                            <component 
                                :is="getComponent(fieldData['type'])" 
                                :data="currentComponentBeenEdited"
                                :fieldName="fieldData.name" 
                                :fieldData="fieldData" 
                                :key="fieldData.type+index+fieldData.label" 
                                :counter="false" 
                                :common="common"
                                :uploadRoutes="uploadRoutes"
                                :bannerType="bannerType"
                            >
                            </component>
                        </div>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</template>



