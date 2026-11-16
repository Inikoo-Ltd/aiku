<script setup lang="ts">
import { computed } from "vue"
import merlinLogo from "@/../art/merlin.png"
import merlinLogoLarge from "@/../art/merlin-large.png"

const props = withDefaults(defineProps<{
    mono?: boolean
    large?: boolean
}>(), {
    mono: false,
    large: false,
})

const logoSource = computed(() => props.large ? merlinLogoLarge : merlinLogo)

const monoStyle = {
    WebkitMaskImage: `url("${merlinLogo}")`,
    maskImage: `url("${merlinLogo}")`,
}
</script>

<template>
    <span v-if="mono" class="merlin-icon merlin-icon-mono" :style="monoStyle" aria-hidden="true" />
    <img v-else :src="logoSource" alt="" aria-hidden="true" draggable="false" class="merlin-icon" />
</template>

<style scoped>
.merlin-icon {
    display: inline-block;
    width: 1.75em;
    height: 1.75em;
    flex-shrink: 0;
    object-fit: contain;
    vertical-align: -0.5em;
    user-select: none;
}

.merlin-icon-mono {
    background-color: currentColor;
    -webkit-mask-position: center;
    mask-position: center;
    -webkit-mask-size: contain;
    mask-size: contain;
    -webkit-mask-repeat: no-repeat;
    mask-repeat: no-repeat;
}
</style>
