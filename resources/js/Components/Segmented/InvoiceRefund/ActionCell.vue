<script setup lang="ts">
import { ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import InputNumber from 'primevue/inputnumber'


import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowAltCircleLeft } from "@fas"
import { faPlus, faMinus, faEdit, faCross, faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faArrowAltCircleLeft)
const props = withDefaults(
    defineProps<{
        modelValue: any
        max: Number
        min: Number
        currency: String
        data: Object
    }>(),
    {}
)

const emit = defineEmits(["update:modelValue","refund"])

const editMode = ref(false)
const loadingRefundAll = ref(false)
const loadingPartial = ref(false)

const refundAll = () => {
    emit('update:modelValue', props.max)
    emit('refund', loadingRefundAll)
}

const toggleEditMode = () => {
    editMode.value = !editMode.value
}

</script>

<template>
    <!-- <Button size="s" :label="trans('Refund All')" type="secondary" icon="fas fa-arrow-alt-circle-left"
            :loading="loadingRefundAll" @click="refundAll" />
        <Button :key="editMode + 'button' + modelValue" :label="trans('Refund Item Partially')"
            icon="fal fa-arrow-circle-left" :type="editMode ? 'gray' : 'tertiary'" @click="toggleEditMode"
            :loading="loadingPartial" /> -->


    <div class="w-fit max-w-[100px] flex items-center gap-x-2">
        <div>
            <InputNumber :modelValue="props.modelValue" @update:modelValue="(value) => emit('update:modelValue', value)"
                buttonLayout="horizontal" showButtons :min="props.min" :max="props.max" :currency="props.currency"
                :maxFractionDigits="2" mode="currency" inputClass="width-12">
                <template #decrementicon>
                    <FontAwesomeIcon :icon="['fal', 'minus']" aria-hidden="true" />
                </template>
                <template #incrementicon>
                    <FontAwesomeIcon :icon="['fal', 'plus']" aria-hidden="true" />
                </template>
            </InputNumber>
        </div>
        <LoadingIcon v-if="loadingPartial" class="h-8" />
        <FontAwesomeIcon v-else-if="modelValue > 0" @click="() => emit('refund', loadingPartial)" icon="fad fa-save"
            class="h-8 cursor-pointer" :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
        <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
    </div>
    <!-- </div> -->
</template>

<style scoped></style>