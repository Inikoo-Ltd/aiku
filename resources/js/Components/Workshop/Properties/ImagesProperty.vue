<script setup lang="ts">
import { faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core";
import { faLink, faUnlink } from "@fal";
import { faExclamation, faCaretDown, faCaretLeft } from "@fortawesome/free-solid-svg-icons";
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue";
import { provide } from "vue";
import { trans } from "laravel-vue-i18n";
import { routeType } from "@/types/route";

library.add(faExclamation, faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter, faLink, faUnlink, faCaretDown, faCaretLeft);

defineProps<{ uploadRoutes: routeType }>();

const emit = defineEmits(["update:modelValue"]);
const model = defineModel();

const blueprint = [
  { key: ["source"], label: "Image", type: "upload_image" },
    {
    key: ["caption"],
    label: trans("Caption"),
    type: "text",
  },
  { key: ["link_data"], label: "Link", type: "link" },
  { key: ["properties", "alt"], label: "Alt", type: "text" },
  {
    key: ["properties", "object_fit"],
    label: "Object Image",
    type: "select",
    props_data: {
      placeholder: "Object",
      options: [
        { label: "contain", value: "contain" },
        { label: "cover", value: "cover" },
        { label: "none", value: "none" },
        { label: "scale-down", value: "scale-down" },
        { label: "fill", value: "fill" }
      ],
    },
  },
  {
    key: ["properties", "object_position"],
    label: "Object Position",
    type: "select",
    props_data: {
      placeholder: "Object",
      options: [
        { label: "Bottom", value: "bottom" },
        { label: "Center", value: "center" },
        { label: "Left", value: "left" },
        { label: "Right", value: "right" },
        { label: "Top", value: "top" },
        { label: "Left Bottom", value: "left bottom" },
        { label: "Left Top", value: "left top" },
        { label: "Right Bottom", value: "right bottom" },
        { label: "Right Top", value: "right top" },
      ],
    },
  },
  {
    key: ["attributes", "fetchpriority"],
    label: trans("Fetch Priority"),
    information: trans("Priority of the image to loaded. Higher priority images are loaded first (good for LCP)."),
    type: "select",
    props_data: {
      placeholder: trans("Priority"),
      options: [
        { label: trans("High"), value: "high" },
        { label: trans("Low"), value: "low" },
      ],
    },
  },
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
      @update:modelValue="(e)=>{model = e, onSaveWorkshopFromId()}"
    />
  </div>
</template>

<style scoped></style>
