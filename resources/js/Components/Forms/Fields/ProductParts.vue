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
import { InputNumber } from 'primevue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { routeType } from '@/types/route'

library.add(faInfoCircle, faFacebook, faLinkedin, faGoogle, faTwitter)

const props = defineProps<{
    form?: any
    fieldName: string
    options: string[] | {}
    fieldData: {
        fetch_route?: routeType
        init_options: [
            {},
            {}
        ]
    }
}>()


const onDeleteParcel = (index: number) => {
	props.form[props.fieldName].splice(index, 1)
}
</script>

<template>
    <div class="max-w-2xl rounded-md">
        <!-- <pre>{{ props.form[props.fieldName] }}</pre> -->

        <Fieldset :legend="`${trans('Parcels')} (0)`">
            <!-- Header Row -->
            <div class="grid grid-cols-12 items-center gap-x-6 mb-2">
                <div class="flex justify-center">
                    <!-- <FontAwesomeIcon icon="fas fa-plus" class="" fixed-width aria-hidden="true" /> -->
                </div>

                <div class="col-span-2 flex items-center space-x-1">
                    <!-- <FontAwesomeIcon icon="fal fa-weight" class="" fixed-width aria-hidden="true" /> -->
                    <span>Qty</span>
                </div>
                <div class="col-span-9 flex items-center space-x-1">
                    <!-- <FontAwesomeIcon icon="fal fa-ruler-triangle" class="" fixed-width aria-hidden="true" /> -->
                    <span>Stock name</span>
                </div>
            </div>

            <!--  -->
            <div class="grid gap-y-2 xmax-h-64 xoverflow-y-auto pr-2">
                <TransitionGroup xv-if="parcelsCopy?.length" name="list">
                    <div v-for="(part, pIndex) in form[fieldName]" :key="pIndex" class="flex xgrid grid-cols-12 xitems-center gap-x-6">
                        <div @click="() => onDeleteParcel(pIndex)" class="mt-2 xflex justify-center text-red-400 hover:text-red-600 cursor-pointer">
                            <FontAwesomeIcon icon="fal fa-trash-alt" class="" fixed-width aria-hidden="true" />
                        </div>
                        
                        <div class="col-span-2 xflex items-center space-x-2 w-12 xqwezxc">
                            <InputNumber :min="0.001" v-model="part.qty" xclass="w-12" size="small" placeholder="0" fluid />
                        </div>
                        
                        <div class="col-span-9 xflex items-center gap-x-1 font-light w-64">
                            <PureMultiselectInfiniteScroll
                                :modelValue="form[fieldName].code"
                                aupdate:modelValue="(e) => (set(data, ['brand', 'id'], e), onAttachBrand(e))"
                                :fetchRoute="fieldData.fetch_route"
                                :placeholder="trans('Select brand')"
                                valueProp="id"
                                required
                                aoptionsList="(options) => dataServiceList = options"
                                :initOptions="props.fieldData.init_options ?? undefined"
                            >
                                <template #singlelabel="{ value }">
                                    <div class="w-full text-left pl-4">{{ value.name }} </div>
                                </template>
                                <template #option="{ option, isSelected, isPointed }">
                                    <div class="flex justify-between w-full">
                                        {{ option.name }}
                                    </div>
                                </template>

                                <!-- <template #afterlist>
                                    <div class="w-full p-2 border-t border-gray-300">
                                        <Button
                                            @click="() => (isModalBrand = true)"
                                            label="Add new brand"
                                            icon="fas fa-plus"
                                            full
                                            type="secondary"
                                        />
                                    </div>
                                </template> -->
                            </PureMultiselectInfiniteScroll>
                        </div>

                        <div class="w-72">
                            <PureTextarea
                                v-model="part.description"
                                xclass="w-32"
                                size="small"
                                :placeholder="trans('Part description')"
                                rows="3"
                            />
                        </div>
                    </div>
                </TransitionGroup>

                <!-- <div v-else class="text-center text-gray-400">
                    {{ trans('No parcels') }}
                </div> -->
                
            </div>

            <!-- Repeat for more rows -->
            <div class="grid grid-cols-12 mt-2">
                <div></div>
                <div @click="() => props.form[props.fieldName].push({ id: null, qty: 1, code: null, name: ''})" class="hover:bg-gray-200 cursor-pointer border border-dashed border-gray-400 col-span-11 text-center py-1.5 text-xs rounded">
                    <FontAwesomeIcon icon="fas fa-plus" class="text-gray-500" fixed-width aria-hidden="true" />
                    {{ trans("Add another Part") }}
                </div>
            </div>
        </Fieldset>
    </div>
</template>