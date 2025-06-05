<script setup lang='ts'>
import { computed, inject } from 'vue'
import { trans } from 'laravel-vue-i18n'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import { faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLink, faUnlink } from "@fal"
import { faExclamation } from "@fas"
import ColorPicker from '@/Components/Utils/ColorPicker.vue'
import { useFontFamilyList } from '@/Composables/useFont'
import { set, get } from 'lodash'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'

library.add(faExclamation, faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter, faLink, faUnlink)

interface TextProperty {
    color: string,
    fontFamily: String
    fontSize: Number | string
}

const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()


const model = defineModel<TextProperty | any>({
    required: true
})



const fontFamilies = [...useFontFamilyList];

</script>

<template>
    <div class="flex flex-col pt-1 pb-3">
        <div class="pb-2">
            <div class="px-3 items-center mb-2">
                <div class="col-span-4">
                    <PureMultiselect v-model="model"
                        @update:modelValue="(e) => {model = e, emits('update:modelValue', e)}"
                        required :options="fontFamilies">
                        <template #option="{ option, isSelected, isPointed, search }">
                            <span :style="{
                                fontFamily: option.value
                            }">
                                {{ option.label }}
                            </span>
                        </template>
                        <template #label="{ value }">
                            <div class="multiselect-single-label" :style="{
                                fontFamily: value.value
                            }">
                                {{ value.label }}
                            </div>
                        </template>
                    </PureMultiselect>
                </div>
            </div>
        </div>
    </div>
</template>