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
    /* {
        key: ['image',"dimension"],
        label: "Dimension",
        type: "dimension",
        props_data: {
            defaultValue: {
                height: {
                    value: "100",
                    unit: "%"
                },
                width: {
                    value: "100",
                    unit: "%"
                }
            }
        }
    },
    {
        key: ['cover',"padding"],
        label: "Padding",
        type: "padding",
        props_data: {
            defaultValue: {
                unit: "px",
                top: {
                    "value": 0
                },
                left: {
                    "value": 0
                },
                right: {
                    "value": 0
                },
                bottom: {
                    "value": 0
                }
            },
        }
    },
    {
        key: ["margin"],
        label: "Margin",
        type: "margin",
        props_data: {
            defaultValue: {
                unit: "px",
                top: {
                    "value": 0
                },
                left: {
                    "value": 0
                },
                right: {
                    "value": 0
                },
                bottom: {
                    "value": 0
                }
            },
        }
    },
    {
        key: ["border"],
        label: "Border",
        type: "border",
        props_data: {
            defaultValue: {
                color: "#000000",
                unit: "px",
                rounded: {
                    unit: "px",
                    topright: {
                        value: 0
                    },
                    topleft: {
                        value: 0
                    },
                    bottomright: {
                        value: 0
                    },
                    bottomleft: {
                        value: 0
                    }
                },
                top: {
                    value: 0
                },
                left: {
                    value: 0
                },
                right: {
                    value: 0
                },
                bottom: {
                    value: 0
                }
            }
        }
    }, */
    {
        key: ["link_data"],
        label: "Link",
        type: "link",
    },
    {
        key: ["properties","alt"],
        label: "Alt",
        type: "text",
    },
    {
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
    },
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
