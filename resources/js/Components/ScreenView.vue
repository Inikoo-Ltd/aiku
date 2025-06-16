<script setup lang="ts">
import { inject, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faDesktop, faMobileAndroidAlt, faTabletAndroidAlt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { layoutStructure } from '@/Composables/useLayoutStructure';
import { trans } from 'laravel-vue-i18n';
library.add(faDesktop, faMobileAndroidAlt, faTabletAndroidAlt)

const screenView = defineModel()

const props = withDefaults(defineProps<{
    // currentView?: string
    showList?:Array<string>
}>(), {
    // currentView: 'desktop',
    showList: ['mobile', 'tablet', 'desktop']
})

defineEmits<{
    (e: 'screenView', deviceType: string): void
}>()

const layout = inject('layout', layoutStructure)

// const screenView = ref(props.currentView)

</script>

<template>
    <div class="flex">
        <div v-if="showList.includes('mobile')"
            class="py-1 px-2 cursor-pointer"
            :class="[screenView == 'mobile' ? 'selected-bg' : 'unselected-bg']"
            @click="screenView = 'mobile', $emit('screenView', 'mobile')"
            v-tooltip="trans('Mobile view')"
        >
            <FontAwesomeIcon icon='fal fa-mobile-android-alt' fixed-width aria-hidden='true' />
        </div>

        <div v-if="showList.includes('tablet')"
            class="py-1 px-2 cursor-pointer  md:block hidden"
            :class="[screenView == 'tablet' ? 'selected-bg' : 'unselected-bg']"
            @click="screenView = 'tablet', $emit('screenView', 'tablet')"
            v-tooltip="trans('Tablet view')"
        >
            <FontAwesomeIcon icon='fal fa-tablet-android-alt' fixed-width aria-hidden='true' />
        </div>

        <div v-if="showList.includes('desktop')"
            class="py-1 px-2 cursor-pointer lg:block hidden"
            :class="[screenView == 'desktop' ? 'selected-bg' : 'unselected-bg']"
            @click="screenView = 'desktop', $emit('screenView', 'desktop')"
            v-tooltip="trans('Desktop view')"
        >
            <FontAwesomeIcon icon='fal fa-desktop' class='' fixed-width aria-hidden='true' />
        </div>
    </div>
</template>

<style lang="scss" scoped>
.selected-bg {
    background-color: v-bind('layout?.app?.theme[0]') !important;
    color: v-bind('layout?.app?.theme[1]') !important;
}
.unselected-bg {
    @apply text-gray-500;
}
.unselected-bg:hover {
    color: v-bind('layout?.app?.theme[0]') !important;
}
</style>


