<script setup lang="ts">
import { faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core";
import { faLink, faUnlink } from "@fal";
import { faExclamation, faCaretDown, faCaretLeft } from "@fortawesome/free-solid-svg-icons";
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue";
import { provide } from "vue";
import { routeType } from "@/types/route";

library.add(faExclamation, faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter, faLink, faUnlink, faCaretDown, faCaretLeft);

defineProps<{ uploadRoutes: routeType }>();

const emit = defineEmits(["update:modelValue"]);
const model = defineModel();

const blueprint = [
   /*  {
        key: ["properties", "padding"],
        label: "Padding",
        type: "padding",
    },
    {
        key: ["properties", "margin"],
        label: "Margin",
        type: "margin",
    }, */
];


const onSaveWorkshopFromId = () => {
    emit("update:modelValue", model.value);
};

/* provide("onSaveWorkshopFromId", onSaveWorkshopFromId); */
</script>

<template>
    <div>
        <SideEditor 
            :blueprint="blueprint" 
            v-model="model" 
            :uploadImageRoute="uploadRoutes" 
            @update:modelValue="e => {model = e,onSaveWorkshopFromId()} "
        />
    </div>
</template>

<style scoped></style>
