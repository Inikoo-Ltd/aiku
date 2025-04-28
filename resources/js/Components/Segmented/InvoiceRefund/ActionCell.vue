<script setup lang="ts">
import { ref } from "vue";
import { useForm } from "@inertiajs/vue3"; // Import useForm
import Button from "@/Components/Elements/Buttons/Button.vue";
import { trans } from "laravel-vue-i18n";
import InputNumber from "primevue/inputnumber";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faArrowAltCircleLeft } from "@fas";
import { faPlus, faMinus, faEdit, faCross, faTimes } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
// import { emits } from "v-calendar/dist/types/src/use/datePicker.js";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";

library.add(faArrowAltCircleLeft);

const props = withDefaults(
  defineProps<{
    modelValue: any;
    max: number;
    min: number;
    currency: string;
    step: Number|String
  }>(),
  {
    step  : 1
  }
);

const emit = defineEmits(["update:modelValue", "refund"]);

const editMode = ref(false);
const loadingRefundAll = ref(false);
const loadingPartial = ref(false);

// Initialize form with useForm
const form = useForm({
  refund_amount: props.modelValue, // Bind the form field to modelValue
});


defineExpose({
  form
})
</script>

<template>
  <div class="w-full flex items-center gap-x-2">
    <div>
      <InputNumber
        v-model="form.refund_amount"
        buttonLayout="horizontal"
        showButtons
        :min="props.min"
        :max="props.max"
        :currency="props.currency"
        :maxFractionDigits="2"
        mode="currency"
        :input-style="{ width : '100px'}"
        :step="step"
      >
        <template #decrementicon>
          <FontAwesomeIcon :icon="['fal', 'minus']" aria-hidden="true" />
        </template>
        <template #incrementicon>
          <FontAwesomeIcon :icon="['fal', 'plus']" aria-hidden="true" />
        </template>
      </InputNumber>
    </div>

    <slot name="save-area" :form="form">
      <LoadingIcon v-if="form.processing" class="h-8" />
      <FontAwesomeIcon
        v-else-if="form.isDirty"
        @click="() => emit('refund', form)"
        icon="fad fa-save"
        class="h-8 cursor-pointer"
        :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }"
        aria-hidden="true"
      />
      <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
    </slot>
  </div>
  <slot name="bottom-button" :form="form"></slot>
</template>

<style scoped></style>
