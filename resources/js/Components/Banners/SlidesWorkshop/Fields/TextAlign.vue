<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faAlignLeft, faAlignCenter, faAlignRight } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref, watch, toRefs, computed } from "vue"
import { set, get } from 'lodash-es'
import { BannerWorkshop } from '@/types/BannerWorkshop'


library.add(faAlignLeft, faAlignCenter, faAlignRight)

const props = defineProps<{
    fieldName: string | []
    fieldData?: {
        options: {
            label: string
            value: string
            icon: string | string[]
        }[]
    }
    data: BannerWorkshop
    counter: boolean
}>()


// const options = [
//     {
//         "label": "Align left",
//         "value": "left",
//         "icon": "fal fa-align-left"
//     },
//     {
//         "label": "Align center",
//         "value": "center",
//         "icon": "fal fa-align-center"
//     },
//     {
//         "label": "Align right",
//         "value": "right",
//         "icon": "fal fa-align-right"
//     }
// ]


const { data, fieldName } = toRefs(props)
const emits = defineEmits()

const setFormValue = (data: Object, fieldName: string | []) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName)
    } else {
        return get(data,fieldName,get(props,['fieldData','defaultValue']))
    }
}

const getNestedValue = (obj: Object, keys: string[]) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === "object" && key in acc) return get(acc,key,get(props,['fieldData','defaultValue']))
        return get(props,['fieldData','defaultValue'],null)
    }, obj)
}

const value = ref(setFormValue(props.data, props.fieldName))

watch(value, (newValue) => {
    updateLocalFormValue(newValue)
})

watch(data, (newValue) => {
    value.value = setFormValue(newValue, props.fieldName)
})

const updateLocalFormValue = (newValue) => {
    let localData = { ...props.data }
    if (Array.isArray(props.fieldName)) {
        set(localData, props.fieldName, newValue)
    } else {
        localData[props.fieldName] = newValue
    }
    emits("update:data", localData) // Emit event to update parent component's data
}


</script>

<template>
    <div class="py-1">
    <!-- <pre>{{ data.common }}</pre> -->
        <div class="flex gap-x-2">
            <div v-for="option in props.fieldData?.options " @click="value = option.value" class="flex items-center justify-center bg-gray-100 rounded p-2 ring-1 ring-gray-300 cursor-pointer"
                :class="[ value == option.value ? 'bg-gray-300' : 'hover:bg-gray-200']"
            >
                <FontAwesomeIcon :icon='option.icon' class='' aria-hidden='true' />
            </div>
        </div>
    </div>
</template>
