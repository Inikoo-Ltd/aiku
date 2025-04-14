<script setup lang="ts">
import { useColorTheme } from '@/Composables/useStockList'
import ColorSchemeWorkshopWebsite from '@/Components/CMS/Website/Layout/ColorSchemeWorkshopWebsite.vue'
import { routeType } from '@/types/route'
import { router } from '@inertiajs/vue3'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { useFontFamilyList } from '@/Composables/useFont'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'

import { onMounted, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPaintBrushAlt, faRocketLaunch } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { get, isEqual, set } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
library.add(faPaintBrushAlt, faRocketLaunch)

const props = defineProps<{
    modelValue: any
    data: {
        routeList: {
            headerRoute: routeType
            footerRoute: routeType
            webpageRoute: routeType
            notificationRoute: routeType
            menuLeftRoute: routeType
            menuRightRoute: routeType
            menuRoute: routeType
        }
        updateColorRoute: routeType
        theme: {
            color: string[]
            layout: string
            fontFamily: string 
        }
    }
}>()

const listColorTheme = [...useColorTheme]
const onClickColor = (colorTheme: string[], index: number) => {
    set(props.data, 'theme.color', colorTheme)
}
const isLoadingPublish = ref(false)

const fieldGroupAnimateSection = ref()
onMounted(() => {
    if (!get(props.data, 'theme.color', false)) {
        set(props.data, 'theme.color', [...listColorTheme[0]])
    }

    route().v().query?.section ? (
        setTimeout(() => {
            fieldGroupAnimateSection.value = ['bg-yellow-500/80']
            setTimeout(() => {
                fieldGroupAnimateSection.value = []
            }, 800)
        }, 130)
    ) : ''
})

const onPublishTheme = () => {
    router.patch(route(props.data.updateColorRoute.name, props.data.updateColorRoute.parameters),
        {
            layout: props.data.theme
        },
        {
            onStart: () => isLoadingPublish.value = true,
            onSuccess: () => {
                notify({
                    title: trans('Success!'),
                    text: trans('Theme config changed'),
                    type: 'success',
                })
            },
            onError: () => {
                notify({
                    title: trans('Something went wrong'),
                    text: trans('Unsuccessfully change theme config'),
                    type: 'error',
                })
            },
            onFinish: () => isLoadingPublish.value = false
        }
    )
}
</script>

<template>
    <div class="p-8 grid grid-cols-4 gap-6 bg-gray-50 rounded-lg h-[79vh]">
        <!-- Theme Selector -->
        <div class="space-y-6 col-span-1 p-4 bg-white rounded-lg shadow-md relative h-full">
            <!-- Header -->
            <div id="theme_colors" class="rounded pb-4 transition-all duration-1000" :class="fieldGroupAnimateSection">
                <div class="flex items-center gap-2 mb-4">
                    <hr class="h-0.5 rounded-full w-full bg-gray-300" />
                    <span class="whitespace-nowrap text-sm text-gray-600 font-semibold">{{ trans("Select Theme")
                        }}</span>
                    <hr class="h-0.5 rounded-full w-full bg-gray-300" />
                </div>

                <!-- Color Options -->
                <div class="flex flex-wrap justify-center gap-8">
                    <div v-for="(colorTheme, index) in listColorTheme" :key="index"
                        class="relative flex items-center gap-x-1">
                        <div @click="onClickColor(colorTheme, index)"
                            class="flex ring-1 ring-gray-300 transition duration-300 rounded-md overflow-hidden cursor-pointer"
                            :class="{ 'ring-2 ring-indigo-500': isEqual(data.theme?.color, colorTheme) }">
                            <div class="h-6 w-6" :style="{ backgroundColor: colorTheme[0] }"></div>
                            <div class="h-6 w-6" :style="{ backgroundColor: colorTheme[1] }"></div>
                            <div class="h-6 w-6" :style="{ backgroundColor: colorTheme[2] }"></div>
                            <div class="h-6 w-6" :style="{ backgroundColor: colorTheme[3] }"></div>
                            <div class="h-6 w-6" :style="{ backgroundColor: colorTheme[4] }"></div>
                            <div class="h-6 w-6" :style="{ backgroundColor: colorTheme[5] }"></div>
                        </div>
                        <Transition name="spin-to-down">
                            <FontAwesomeIcon v-if="isEqual(data.theme?.color, colorTheme)" icon='fal fa-check'
                                class='absolute -right-6 text-green-600' fixed-width aria-hidden='true' />
                        </Transition>
                    </div>
                </div>
            </div>

            <!-- Layout Selector -->
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <hr class="h-0.5 rounded-full w-full bg-gray-300" />
                    <span class="whitespace-nowrap text-sm text-gray-600 font-semibold">Select Layout</span>
                    <hr class="h-0.5 rounded-full w-full bg-gray-300" />
                </div>

                <!-- Radio Options for Layout -->
                <div class="flex gap-4 justify-center flex-wrap">
                    <!-- Fullscreen Layout Option -->
                    <label for="radio-layout-fullscreen"
                        class="flex flex-col items-center gap-2 p-4 border border-gray-200 rounded-md cursor-pointer hover:bg-gray-50 transition"
                        :class="{ 'border-indigo-500 bg-indigo-50': data.theme?.layout === 'fullscreen' }">
                        <input id="radio-layout-fullscreen" name="radio-layout-fullscreen" type="radio"
                            value="fullscreen" @change="(e) => (set(data, 'theme.layout', e.target.value))"
                            class="hidden">
                        <div class="w-20 h-12 bg-gray-200 rounded-md flex items-center justify-center">
                            <div class="w-full h-full"
                                style="background: repeating-linear-gradient(45deg, #ebf8ff, #ebf8ff 10px, #bee3f8 10px, #bee3f8 20px);">
                            </div>
                        </div>
                        <span class="text-sm font-semibold">{{ trans("Fullscreen") }}</span>
                    </label>

                    <!-- Blog in the Middle Layout Option -->
                    <label for="radio-layout-blog"
                        class="flex flex-col items-center gap-2 p-4 border border-gray-200 rounded-md cursor-pointer hover:bg-gray-50 transition"
                        :class="{ 'border-indigo-500 bg-indigo-50': data.theme?.layout === 'blog' }">
                        <input id="radio-layout-blog" name="radio-layout-fullscreen" type="radio" value="blog"
                            @change="(e) => (set(data, 'theme.layout', e.target.value))" class="hidden">
                        <div class="w-20 h-12 bg-gray-200 rounded-md flex items-center justify-center">
                            <div class="w-[60%] h-full rounded"
                                style="background: repeating-linear-gradient(45deg, #ebf8ff, #ebf8ff 10px, #bee3f8 10px, #bee3f8 20px);">
                            </div>
                        </div>
                        <span class="text-sm font-semibold">{{ trans("Middle") }}</span>
                    </label>
                </div>
            </div>

            <!-- Font Family Selector -->
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <hr class="h-0.5 rounded-full w-full bg-gray-300" />
                    <span class="whitespace-nowrap text-sm text-gray-600 font-semibold">{{ trans("Font Family")
                        }}</span>
                    <hr class="h-0.5 rounded-full w-full bg-gray-300" />
                </div>

                <div class="flex flex-wrap justify-center gap-3">
                    <PureMultiselect :modelValue="get(data, 'theme.fontFamily', null)"
                        @update:modelValue="(e) => set(data, 'theme.fontFamily', e.value)" required
                        :options="useFontFamilyList" caret>
                        <template #option="{ option, isSelected, isPointed, search }">
                            <span :style="{ fontFamily: option.value }">{{ option.label }}</span>
                        </template>

                        <template #label="{ value }">
                            <div class="multiselect-single-label" :style="{ fontFamily: value.value }">{{ value.label }}
                            </div>
                        </template>
                    </PureMultiselect>
                </div>
            </div>

            <!-- Publish Button at the Bottom with Absolute Positioning -->
            <div class="absolute bottom-0 left-0 right-0 p-4 bg-white border-t">
                <Button @click="() => onPublishTheme()" type="submit" :loading="isLoadingPublish" full label="Publish"
                    icon="fal fa-rocket-launch" />
            </div>
        </div>


        <!-- Workshop Preview -->
        <div class="space-y-6 col-span-3 ">
            <div class="rounded-lg shadow-md bg-white p-8 h-full flex justify-center ">
                <ColorSchemeWorkshopWebsite :routeList="data.routeList" :color="data.theme?.color"
                    :layout="data.theme?.layout" :fontFamily="data.theme?.fontFamily" />
            </div>
        </div>
    </div>
</template>
