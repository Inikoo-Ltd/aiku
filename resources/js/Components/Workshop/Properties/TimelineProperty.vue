<script setup lang="ts">
import { faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter } from "@fad";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faLink, faUnlink } from "@fal";
import { faExclamation, faCaretDown, faCaretLeft } from "@fas";
import SideEditor from "../SideEditor/SideEditor.vue";
import { watch,ref } from "vue";

library.add(faExclamation, faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter, faLink, faUnlink, faCaretDown, faCaretLeft);

const emit = defineEmits(['update:modelValue']);

/* const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
});

const data = ref(props.modelValue) */

const model = defineModel()

const blueprint = [
    {
        key: ["title"],
        label: "Title",
        type: "editorhtml",
    },
    {
        key: ["description"],
        label: "Description",
        type: "editorhtml",
    }
 
]

const value = ref({...model.value})

watch((value),(newValue)=>{
    model.value = newValue
},{deep:true})

</script>

<template>
    <div>
        <SideEditor 
            :blueprint="blueprint" 
            v-model="value"
         />
    </div>
</template>

<style scoped></style>
