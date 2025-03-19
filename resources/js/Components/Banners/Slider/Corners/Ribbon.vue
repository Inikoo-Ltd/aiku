<script setup lang="ts">
import { computed } from 'vue'
import { get } from 'lodash-es'
const props = defineProps<{
    data?: {
        text?: string
        fontSize?: {
            fontSubtitle?: string
            fontTitle?: string
        }
        ribbon_color?: string
        text_color?: string
        position: string
    }
    // swiperRef?: Element
}>()

const positionRibbon = computed(() => {
    let classes
    switch (props.data?.position) {
        case 'topRight':
            classes = 'corner-ribbon-top';
            break;
        case 'topLeft':
            classes = 'corner-ribbon-top ribbon-topleft';
            break;
        case 'bottomRight':
            classes = 'corner-ribbon-bottom';
            break;
        case 'bottomLeft':
            classes = 'corner-ribbon-bottom ribbon-bottomleft';
            break;
    }

    return classes
})
</script>

<template>
    <div v-if="data && data.text" class="text-white whitespace-nowrap" :class="positionRibbon" :ribbon-text="data.text"
        :style="{
            '--ribbon-color': data && data.ribbon_color ? data.ribbon_color : 'rgba(244, 63, 94, 255)',
            'color': get(data, 'text_color', 'black')
        }">
        <!-- Your content goes here -->
    </div>
</template>

<style>
.corner-ribbon-top {
    --folded: 6px;
    /* folded part */
    --ribbon-color: rgb(246, 255, 0);
    /* color */
    --fontSize: 16px;
    /* ribbon font-size */
    position: relative;
}

.corner-ribbon-top::before {
    content: attr(ribbon-text);
    font-size: var(--fontSize);
    /* I : position & coloration */
    position: absolute;
    top: 0;
    right: 0;
    transform: translate(29.29%, -100%) rotate(45deg);
    transform-origin: bottom left;
    padding: 5px 35px calc(var(--folded) + 5px);
    background: linear-gradient(rgba(0, 0, 0, 0.5) 0 0) bottom/100% var(--folded) no-repeat var(--ribbon-color);
    /* II : clipping */
    clip-path: polygon(0 0, 100% 0, 100% 100%, calc(100% - var(--folded)) calc(100% - var(--folded)), var(--folded) calc(100% - var(--folded)), 0 100%);
    /* III : masking */
    -webkit-mask:
        linear-gradient(135deg, transparent calc(50% - var(--folded)*0.707), #fff 0) bottom left,
        linear-gradient(-135deg, transparent calc(50% - var(--folded)*0.707), #fff 0) bottom right;
    -webkit-mask-size: 300vmax 300vmax;
    -webkit-mask-composite: destination-in;
    mask-composite: intersect;
}

.ribbon-topleft::before {
    left: 0;
    right: auto;
    transform: translate(-29.29%, -100%) rotate(-45deg);
    transform-origin: bottom right;
}


/* Ribbon bottom */
.corner-ribbon-bottom {
    --folded: 6px;
    /* folded part */
    --ribbon-color: rgb(246, 255, 0);
    /* color */
    --fontSize: 16px;
    /* ribbon font-size */
    position: relative;
}

.corner-ribbon-bottom::before {
    content: attr(ribbon-text);
    font-size: var(--fontSize);
    /* I : position & coloration */
    position: absolute;
    top: 0;
    right: 0;
    transform: translate(29.29%, 0%) rotate(-45deg);
    transform-origin: top left;
    padding: 5px 35px calc(var(--folded) + 5px);
    background: linear-gradient(rgba(0, 0, 0, 0.5) 0 0) bottom/100% var(--folded) no-repeat var(--ribbon-color);
    /* II : clipping */
    clip-path: polygon(0 0,
            100% 0,
            100% 100%,
            calc(100% - var(--folded)) calc(100% - var(--folded)),
            var(--folded) calc(100% - var(--folded)),
            0 100%);
    /* III : masking */
    -webkit-mask:
        linear-gradient(45deg, transparent calc(50% - var(--folded)*0.707), #fff 0) top left,
        linear-gradient(-45deg, transparent calc(50% - var(--folded)*0.707), #fff 0) top right;
    -webkit-mask-size: 300vmax 300vmax;
    -webkit-mask-composite: destination-in;
    mask-composite: intersect;
}

.ribbon-bottomleft::before {
    left: 0;
    right: auto;
    transform: translate(-29.29%, -0%) rotate(45deg);
    transform-origin: top right;
}
</style>