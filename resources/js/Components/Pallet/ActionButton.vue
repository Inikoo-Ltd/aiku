<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 18:32:23 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrashAlt } from "@far"
import { faSignOutAlt, faSpellCheck, faCheck, faTimes, faCheckDouble, faCross, faFragile, faGhost, faBoxUp, faStickyNote, faSquare } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { inject, ref } from "vue"
import Popover from "@/Components/Popover.vue"
import { trans } from "laravel-vue-i18n"
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import { routeType } from "@/types/route"
import { notify } from "@kyvg/vue3-notification"
import { layoutStructure } from "@/Composables/useLayoutStructure"

library.add(faTrashAlt, faSignOutAlt, faSpellCheck, faCheck, faTimes, faCheckDouble, faCross, faFragile, faGhost, faBoxUp, faStickyNote, faSquare);

const props = defineProps<{
    item: {
        index: number
        status: string  // storing, etc
        setAsDamaged: routeType
        setAsLost: routeType
    }
}>()

const layout = inject('layout', layoutStructure)

const loading = ref(false)
const palletStatus = ref('damaged')
const form = useForm({ message: '' })
const errorMessage = ref('')

/* const onUpdateStatus = (routes, data, type) => {
    router.patch(route(routes.name, routes.parameters), data, {
        onStart: () => {
            if (type == 'damaged') loadingDamaged.value = true
            if (type == 'lost') loadingLost.value = true
        },
        onFinish: () => {
            if (type == 'damaged') loadingDamaged.value = false
            if (type == 'lost') loadingLost.value = false
        }
    })
}
 */

 
// Method: set pallet status as 'damaged' or 'lost'
const setPalletStatus = (routes: routeType, close: Function) => {
    const routeToVisit = palletStatus.value === 'damaged' ? route('grp.org.warehouses.show.inventory.pallets.damaged.index', layout.currentParams) : route('grp.org.warehouses.show.inventory.pallets.lost.index', layout.currentParams)
    form.patch(route(
        routes.name,
        routes.parameters
    ), {
        preserveScroll: true,
        onStart: () => { loading.value = true },
        onSuccess: () => {
            close()
            notify({
                title: `Succesfully set the pallet as ${palletStatus.value}.`,
                text: '',
                type: 'success',
                data: {
                    html: `<div class="hover:underline cursor-pointer">See ${palletStatus.value} pallets list</div>`,
                    function: () => router.visit(routeToVisit)
                }
            })
        },
        onError: (errors) => {
            console.log('errors', errors)
            errorMessage.value = errors
            // loading.value = false
        },
        onFinish: () => { loading.value = false }
    })
}


const typePallet = [
    { label: 'Damaged', value: 'damaged' },
    { label: 'Lost', value: 'lost' },
]
</script>

<template>
    <Popover v-if="item.status === 'storing'" class="relative" position="bottom-[125%] right-1/2">
        <template #button>
            <Button :key="item.index" iconRight="fal fa-fragile" v-tooltip="trans('Set pallet as damaged')" :size="'xs'" type="negative" />
        </template>

        <template #content="{ close }">
            <div class="w-[250px]">
                <span class="text-xs mt-2">{{ trans('Status') }}: </span>
                <div class="flex items-center mb-3 gap-x-4">
                    <div v-for="(typeData, typeIdx) in typePallet" :key="typeIdx" class="relative py-1">
                        <input type="radio" :id="typeData.value" :value="typeData.value"
                            :checked="palletStatus == typeData.value" @input="() => palletStatus = typeData.value"
                            class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4 cursor-pointer">
                        <label :for="typeData.value" class="pl-2 select-none cursor-pointer">{{ typeData.label }}</label>
                    </div>
                </div>


                <label for="message" class="text-xs">{{ trans('Message') }}:</label>
                <div class="mt-1">
                    <!-- <textarea v-model.trim="form.message" id="message" name="message" rows="3"
                        class="block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" /> -->
                    <PureTextarea v-model.trim="form.message" name="message" placeholder="Add detail about the pallet's status" />
                </div>

                <div v-if="errorMessage" class="text-red-500 text-xs italic">
                    {{ errorMessage }}
                </div>
            </div>

            <div class="flex justify-end mt-3">
                <Button :style="'save'" full :loading="loading" @click="setPalletStatus( palletStatus == 'damaged' ? item.setAsDamaged : item.setAsLost, close)" />
            </div>
        </template>
    </Popover>
</template>




<style lang="scss"></style>