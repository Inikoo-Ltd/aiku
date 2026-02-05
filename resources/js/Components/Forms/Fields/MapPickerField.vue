<script setup lang="ts">
import { ref } from "vue"
import MapPickerModal from "@/Components/HumanResources/MapPickerModal.vue"
import { InputText } from "primevue";
import Button from "@/Components/Elements/Buttons/Button.vue";

const props = defineProps<{
    form: Record<string, any>
    fieldName: string
    fieldData: Record<string, any>
}>()

const show = ref(false)

const parseInitial = () => {
    if (!props.form || !props.fieldName || !props.form[props.fieldName]) {
        return null
    }
    const [lat, lng] = props.form[props.fieldName].split(',').map(Number)
    return { lat, lng }
}

const selected = ref(parseInitial())

const onSelected = (pos: any) => {
    const value = `${pos.lat}, ${pos.lng}`

    props.form[props.fieldName] = value
    selected.value = pos
    show.value = false
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex items-center gap-3">
            <InputText class="w-full" :value="form[fieldName]" />
            <Button label="Find On Map" class="!p-1" @click="show = true" />
        </div>

        <MapPickerModal v-model="show" @selected="onSelected" :lat="selected?.lat" :lng="selected?.lng" />
    </div>
</template>
