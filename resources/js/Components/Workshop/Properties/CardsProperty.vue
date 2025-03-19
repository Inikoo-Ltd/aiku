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
        key: ["link"],
        label: "Link",
        type: "link",
    },
    {
        key: ["name"],
        label: "Title",
        type: "text",
    },
    {
        key: ["description"],
        label: "Description",
        type: "textProperty",
    },
    {
        key: ["priceMonthly"],
        label: "Price Monthly",
        type: "text",
    },
    {
        key: ["features"],
        label: "Features",
        type: "arrayPhone",
    },
  /*   {
        key: ["properties","object_fit"],
        label: "Object Image",
        type: "select",
        props_data: {
            placeholder : 'Object',
            options : [
                {
                    label : 'contain',
                    value :  'contain' 
                },
                {
                    label : 'cover',
                    value :  'cover' 
                },
                {
                    label : 'none',
                    value :  'none' 
                },
                {
                    label : 'scale-down',
                    value :  'scale-down' 
                },
            ]
        }
    },
    {
        key: ["properties","object_position"],
        label: "Object Position",
        type: "select",
        props_data: {
            placeholder : 'Object',
            options : [
                {
                    label : 'Bottom',
                    value :  'bottom' 
                },
                {
                    label : 'Center',
                    value :  'center' 
                },
                {
                    label : 'Left',
                    value :  'left' 
                },
                {
                    label : 'Right',
                    value :  'right' 
                },
                {
                    label : 'Top',
                    value :  'top' 
                },
                {
                    label : 'Left Bottom',
                    value :  'left bottom' 
                },
                {
                    label : 'Left Top',
                    value :  'left top' 
                },
                {
                    label : 'Right Bottom',
                    value :  'right bottom' 
                },
                {
                    label : 'Right Top',
                    value :  'right top' 
                },
            ]
        }
    }, */
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
