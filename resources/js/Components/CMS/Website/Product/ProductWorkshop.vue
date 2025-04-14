<script setup lang="ts">
import { ref, watch } from "vue"
import { trans } from 'laravel-vue-i18n'
import { useColorTheme } from '@/Composables/useStockList'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Popover from 'primevue/popover'
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import SelectButton from 'primevue/selectbutton'
import ToggleSwitch from 'primevue/toggleswitch'
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import BlockList from '@/Components/CMS/Webpage/BlockList.vue'
import { getIrisComponent } from "@/Composables/getIrisComponents"

import { faRocketLaunch } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { routeType } from "@/types/route"
library.add(faRocketLaunch)

const props = defineProps<{
    data: {
        updateRoute: routeType
        web_block_types : {
            data : Array<any>
        }
    }
}>()

const emits = defineEmits(['update:modelValue', 'autoSave'])
const op = ref()
const comment = ref('')
const isLoading = ref(false)
const usedTemplates = ref(props.data.web_block_types.data[0])
const mode = ref({ name: 'Logged In', value: 'login' })
const optionsToogle = ref([
    { name: 'Logged Out', value: 'logout' },
    { name: 'Logged In', value: 'login' },
    { name: 'Membership', value: 'member' }
])

console.log('ddd',props.data)


const toggle = (event) => {
    op.value.toggle(event)
}

const selectPreviousTemplate = () => {
    let index = props.data.web_block_types.data.findIndex((item) => item.key == usedTemplates.value.code)
    if (index > 0) {
        usedTemplates.value = props.data.web_block_types.data[index - 1]
    }
}

const selectNextTemplate = () => {
    let index = props.data.web_block_types.data.findIndex((item) => item.key == usedTemplates.value.code)
    if (index < props.data.web_block_types.data.length - 1) {
        usedTemplates.value = props.data.web_block_types.data[index + 1]
    }
}

const onPublish = async (action: {}, popover: {}) => {
    try {
        isLoading.value = true
        const response = await axios.patch(route(props.data.updateRoute.name, props.data.updateRoute.parameters), { data: usedTemplates.value, comment: comment.value })
    } catch (error) {
        const errorMessage = error.response?.data?.message || error.message || "Unknown error occurred"
        notify({
            title: "Something went wrong.",
            text: errorMessage,
            type: "error",
        })
    } finally {
        isLoading.value = false
    }
}

</script>

<template>
    <div class="h-[79vh] grid overflow-hidden grid-cols-4">
        <div class="col-span-1 flex flex-col border-r border-gray-300 shadow-lg relative overflow-auto">
            <div class="px-4 py-3 rounded-t-lg shadow">
                <div class="flex items-center">
                    <font-awesome-icon :icon="['fas', 'chevron-left']"
                        class="px-4 cursor-pointer text-gray-600 hover:text-gray-800 transition duration-200"
                        @click="selectPreviousTemplate" />
                    <PureMultiselect 
                        :options="data.web_block_types.data" 
                        label="code"  
                        :object="true"
                        :valueProp="'code'"
                        :model-value="usedTemplates" 
                        @update:model-value="(value) => { console.log(value)}"
                        :required="true" 
                        class="mx-2 focus:ring-2 focus:ring-blue-500" 
                    />
                    <font-awesome-icon :icon="['fas', 'chevron-right']"
                        class="px-4 cursor-pointer text-gray-600 hover:text-gray-800 transition duration-200"
                        @click="selectNextTemplate" />
                </div>
            </div>
            <div class="px-4 py-5 flex-grow">
                <div class="flex justify-center mb-6">
                    <SelectButton v-model="mode" :options="optionsToogle" optionLabel="name"
                        aria-labelledby="multiple" />
                </div>
                <div class="px-8">
                    <div class="py-5 border-t border-gray-300">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-lg font-semibold">Show FAQs</span>
                            <ToggleSwitch v-model="usedTemplates.data.fieldValue.setting.faqs" />
                        </div>
                        <div class="text-xs text-gray-500">
                            Toggle to show or hide frequently asked questions for your product.
                        </div>
                    </div>
                    <div class="py-5 border-t border-gray-300">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-lg font-semibold">Product Specification</span>
                            <ToggleSwitch v-model="usedTemplates.data.fieldValue.setting.product_specs" />
                        </div>
                        <div class="text-xs text-gray-500">
                            Toggle to show or hide product specifications for your product.
                        </div>
                    </div>
                    <div class="py-5 border-t border-gray-300">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-lg font-semibold">Customer Reviews</span>
                            <ToggleSwitch v-model="usedTemplates.data.fieldValue.setting.customer_review" />
                        </div>
                        <div class="text-xs text-gray-500">
                            Toggle to show or hide customer reviews for your product.
                        </div>
                    </div>
                    <div class="py-5 border-t border-gray-300">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-lg font-semibold">Payments & Policy</span>
                            <ToggleSwitch v-model="usedTemplates.data.fieldValue.setting.payments_and_policy" />
                        </div>
                        <div class="text-xs text-gray-500">
                            Toggle to show or hide payment and policy information for your product.
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-4 py-4 bg-gray-200">
                <Button type="submit" full label="Publish" :icon="faRocketLaunch" @click="toggle" />
            </div>
        </div>

        <div class="bg-gray-100 h-full col-span-3 rounded-lg shadow-lg">
            <div class="bg-gray-100 px-6 py-6 h-[79vh] rounded-lg  overflow-auto">
                <div :class="usedTemplates?.code ? 'bg-white shadow-md rounded-lg' : ''">
                    <section v-if="usedTemplates?.code">
                        <component :is="getIrisComponent(usedTemplates.code)" :fieldValue="usedTemplates.data.fieldValue" />
                    </section>
                </div>
            </div>
        </div>
    </div>

    <Popover ref="op">
        <div class="flex flex-col gap-4 w-[25rem]">
            <div>
                <div class="inline-flex items-start leading-none">
                    <FontAwesomeIcon :icon="'fas fa-asterisk'" class="font-light text-[12px] text-red-400 mr-1" />
                    <span class="capitalize">{{ trans('comment') }}</span>
                </div>
                <div class="py-2.5">
                    <textarea rows="3" v-model="comment"
                        class="block w-64 lg:w-96 rounded-md shadow-sm border-gray-300 focus:border-gray-500 focus:ring-gray-500 sm:text-sm" />
                </div>
                <div class="flex justify-end">
                    <Button :key="comment.length" size="xs" icon="far fa-rocket-launch" label="Publish"
                        :type="comment.length > 0 ? 'primary' : 'disabled'" @click="onPublish">
                        <template #icon>
                            <LoadingIcon v-if="isLoading" />
                            <FontAwesomeIcon v-else icon='far fa-rocket-launch' class='' aria-hidden='true' />
                        </template>
                    </Button>
                </div>
            </div>
        </div>
    </Popover>
</template>


<style scoped>
.splitpanes__pane {
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
    box-shadow: 0 0 3px rgba(0, 0, 0, .2) inset;
}
</style>