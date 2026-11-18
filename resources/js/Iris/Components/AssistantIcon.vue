<script setup lang="ts">
import { computed } from "vue"
import MerlinIcon from "@/Iris/Components/MerlinIcon.vue"
import { useShoppingAssistant } from "@/Iris/Composables/useShoppingAssistant"

const props = withDefaults(defineProps<{
    littleBird?: boolean
    mono?: boolean
    themed?: boolean
}>(), {
    littleBird: undefined,
    mono: false,
    themed: true,
})

const { isLittleBird } = useShoppingAssistant()

const showsLittleBird = computed(() => props.littleBird ?? isLittleBird.value)
</script>

<template>
    <img v-if="showsLittleBird" src="/art/little-bird.gif" alt="" aria-hidden="true" class="little-bird-icon" />
    <MerlinIcon v-else :mono :themed="themed && !mono" />
</template>

<style scoped>
.little-bird-icon {
    display: inline-block;
    width: 1.75em;
    height: 1.75em;
    flex-shrink: 0;
    object-fit: contain;
    vertical-align: -0.5em;
    user-select: none;
}
</style>
