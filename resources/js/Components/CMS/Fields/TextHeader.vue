<script setup lang="ts">
import { ref, watch } from "vue";
import OverviewProperty from "@/Components/Workshop/Properties/OverviewProperty.vue";
import { cloneDeep } from "lodash-es";

// Define reactive modelValue prop
const props = defineProps({
    modelValue: {
        type: Object, // Changed to Object instead of Array
        required: true,
    },
});

// Emit changes to modelValue
const emit = defineEmits(["update:modelValue"]);

// Function to update properties
const onChangeProperty = (data) => {
    const updatedData = cloneDeep(props.modelValue);
    updatedData.properties = data;
    emit("update:modelValue", updatedData);
};
</script>

<template>
    <div>
        <OverviewProperty v-model="modelValue.container.properties"
            @update:model-value="onChangeProperty"></OverviewProperty>
    </div>
</template>

<style scoped></style>
