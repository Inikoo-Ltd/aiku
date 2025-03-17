<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 23 Jul 2023 23:21:23 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import { computed } from 'vue'
// import SlideControls from "@/Components/Slider/Corners/SlideControls.vue";
import LinkButton from "@/Components/Banners/Slider/Corners/LinkButton.vue";
import CornerText from "@/Components/Banners/Slider/Corners/CornerText.vue";
import CornerFooter from "@/Components/Banners/Slider/Corners/CornerFooter.vue";
import Ribbon from "@/Components/Banners/Slider/Corners/Ribbon.vue";
import { get } from 'lodash-es'
import { CornerData } from '@/types/BannerWorkshop'

const props = defineProps<{
    position: string  // topLeft, bottomRight, etc
    corner: CornerData
    swiperRef?: Element
}>()

const positionClasses = computed(() => {
    let classes;
    if (props.corner.type != 'ribbon') {
        switch (props.position) {
            case 'topMiddle':
                classes = 'top-6 flex w-full justify-center text-center';
                break;
            case 'bottomMiddle':
                classes = 'bottom-6 flex w-full justify-center text-center';
                break;
            case 'topRight':
                classes = 'top-6 right-7 text-right';
                break;
            case 'topLeft':
                classes = 'top-6 left-7 text-left';
                break;
            case 'bottomRight':
                classes = 'bottom-6 right-8 text-right';
                break;
            case 'bottomLeft':
                classes = 'bottom-6 left-8 text-left';
                break;
        }
    } else {
        // Ribbon
        switch (props.position) {
            case 'topRight':
                classes = 'top-0 right-0 text-right';
                break;
            case 'topLeft':
                classes = 'top-0 left-0 text-left';
                break;
            case 'bottomRight':
                classes = 'bottom-0 right-0 text-right';
                break;
            case 'bottomLeft':
                classes = 'bottom-0 left-0 text-left';
                break;
        }
    }

    return classes;
});

const components: any = {
    // 'slideControls': SlideControls,
    'linkButton': LinkButton,
    'cornerText': CornerText,
    'cornerFooter': CornerFooter,
    'ribbon': Ribbon
}

const getComponent = (componentName: any) => {
    return components[componentName] ?? null;
};

</script>

<template>
    <div :class="positionClasses" class="absolute" :style="`width : ${get(corner,['data','width'])}%`">
        <!-- {{ positionClasses }} -->
        <component :is="getComponent(corner.type)" :data="{...corner.data, position}" :swiperRef="swiperRef" />
    </div>
</template>

