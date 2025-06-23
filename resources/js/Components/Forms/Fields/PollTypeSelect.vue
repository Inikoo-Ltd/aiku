<script setup lang='ts'>
import PureInput from '@/Components/Pure/PureInput.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import FileUpload from 'primevue/fileupload'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faInfoCircle } from '@fal'
import { faFacebook, faLinkedin, faGoogle, faTwitter } from '@fortawesome/free-brands-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import { ref } from 'vue'
import Image from '@/Components/Image.vue'
import { InputText, Select, Textarea } from 'primevue'
import Button from '@/Components/Elements/Buttons/Button.vue'

library.add(faInfoCircle, faFacebook, faLinkedin, faGoogle, faTwitter)

const props = defineProps<{
    form?: any
    fieldName: string
    options: {
        id: number
        label: string
        value: string
        name: string
    }[]
    fieldData: {
        options: string
        type_options: []
    }
}>()

// const onFileSelect = (event: any) => {
//     const file = event.files?.[0]
//     if (!file) return

//     const reader = new FileReader()

//     reader.onload = (e) => {
//         props.form[props.fieldName].image = {
//             original: e.target?.result,
//         }
//     }

//     reader.readAsDataURL(file)
// }

// const selectedPreview = ref('')
// const xxxOptions = ref([
//     { label: 'Option 1'},
// ])
</script>

<template>
    <div class="max-w-2xl rounded-md">
        <div class="space-y-4 xpt-4">
            <div>
                <!-- <label class="text-gray-600 font-semibold cursor-pointer">
                    Select the type
                </label> -->
                <!-- <pre>{{ form }}</pre> -->
                <Select
                    v-model="form[fieldName].type"
                    :options="fieldData.options"
                    option-label="label"
                    option-value="value"
                    class="w-full"
                    placeholder="Select a title"
                />
            </div>

            <div class="xborder-t xborder-gray-300 xpt-3 ">
                <!-- <Textarea
                    v-if="selectedPreview === 'open_question'"
                    placeholder="ssss"
                /> -->
                <!-- <pre>{{ form[fieldName].poll_options }}</pre> -->
                
                <div v-if="form[fieldName].type === 'option'" class="border-t border-gray-300 pt-3 grid gap-y-2">
                    <div v-for="(opt, optIdx) in form[fieldName].poll_options" class="flex gap-x-2 items-center">
                        <InputText
                            fluid
                            v-model="opt.label"
                            :placeholder="trans('Input label for this option')"
                        />

                        <div @click="form[fieldName].poll_options.splice(optIdx, 1)" class="group cursor-pointer text-red-400 hover:text-red-600">
                            <FontAwesomeIcon icon="fal fa-trash-alt" class="" fixed-width aria-hidden="true" />
                        </div>
                    </div>

                    <Button
                        label="Add Option"
                        icon="fas fa-plus"
                        full
                        type="dashed"
                        @click="form[fieldName].poll_options.push({ label: `Option ${form[fieldName].poll_options.length + 1}` })"
                    />
                </div>
            </div>
        </div>
    </div>
</template>