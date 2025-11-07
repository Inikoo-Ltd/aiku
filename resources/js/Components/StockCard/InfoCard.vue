<script setup lang="ts">
import { ref } from 'vue';
import { routeType } from "@/types/route"
import { stockLocation, Datum } from "@/types/StockLocation"
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import { useFormatTime } from "@/Composables/useFormatTime"
import { router, useForm } from '@inertiajs/vue3'
import { notify } from "@kyvg/vue3-notification"
import Popover from '@/Components/Popover.vue'
import Button from "@/Components/Elements/Buttons/Button.vue"
import SetSetting from '@/Components/StockCard/StockLocationComponents/SetSettings.vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faShoppingBasket, faClock, faPencil, faSave, faTimes } from '@far'
import { faStickyNote, faClipboard, faInventory, faForklift } from '@fal'
import { faStickyNote as fasStickyNote } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'

library.add(faShoppingBasket, faStickyNote, faClock, faClipboard, faInventory, faForklift, fasStickyNote, faPencil , faSave, faTimes)

const props = defineProps<{
    data: stockLocation
    locationRoute: routeType;
    associateLocationRoute: routeType;
    disassociateLocationRoute: routeType;
    auditRoute: routeType;
    moveLocationRoute: routeType;
}>();

const disclosure = ref([])
const editNotes = ref(false)
const loading = ref(false)



const daysAudit = (day : Date) =>{
    if(day){
    const audited_at = new Date(day);
    const today = new Date();
    const difference = today - audited_at;
    const differenceDay = Math.floor(difference / (1000 * 60 * 60 * 24));
    return(differenceDay)
    } return 0
   
}


const SendEditLocation = (item : Datum) =>{
    router.patch(route(
        'grp.models.location_org_stock.update',
        { locationOrgStock: item.id }),
        { notes : item.notes,
          type : item.type
        },
        {
            onBefore: () => { loading.value = true },
            onSuccess: () => {
                    loading.value = false
                    editNotes.value = false
            },
            onError: () => {
                notify({
                    title: "Failed",
                    text: "failed to add location",
                    type: "error"
                })
                loading.value = false
            }

        })
}

const onSetPickingLocation = (item : Datum) =>{
    item.type = 'picking'
    SendEditLocation(item)
}

const hideOther = (id : Number) => {
  disclosure.value.filter((d, i) => i !== id).forEach(c => c())
}

const sendSetting = ({settingForm = {} , loading = false, close = ()=>null }) => {
    settingForm.patch(route(
       'grp.models.location_org_stock.update',
       { locationOrgStock: settingForm.id }
    ),
        {
            onBefore: () => { loading = true },
            onSuccess: () => { loading = false , close()},
            onError: () => {
                notify({
                    title: "Failed",
                    text: "failed to add location",
                    type: "error"
                })
                loading = false
            }
        })
}

console.log(props.data)

</script>

<template>
    <ul class="divide-y divide-gray-100 bg-white shadow-sm ring-1 ring-gray-900/5">
        <Disclosure v-for="(location, index) in data?.locations?.data" :key="index" as="li" v-slot="{ open, close }">
            <div class="relative flex justify-between gap-x-6 px-4 py-4 hover:bg-gray-50 sm:px-6 w-full"
                :class="open && 'bg-gray-100'">
                <div class="flex items-center w-1/2 gap-x-2">
                    <!-- Location Icon -->
                    <DisclosureButton class="flex-none rounded-full focus:outline-hidden"
                        :class="location.notes && 'text-yellow-400'" @click="hideOther(index)"
                        :ref="el => (disclosure[index] = close)">
                        <FontAwesomeIcon v-tooltip="'Notes'" :icon="location.notes ? fasStickyNote : faStickyNote" />
                    </DisclosureButton>

                    <div v-if="location.type != 'picking'" class="relative">
                        <Popover position="left-0 top-[-120px]">
                            <template #button>
                                <FontAwesomeIcon v-tooltip="location.type"
                                    class="h-5 w-5 flex-none rounded-full bg-gray-50 cursor-pointer"
                                    :icon="faShoppingBasket" />
                            </template>
                            <template #content="{ close: closed }">
                                <div class="w-[250px]">
                                    <p class="text-xs pb-4">
                                        Do you want set <strong>{{ location.code }}</strong> to be the Picking location
                                        ?
                                    </p>
                                    <div class="flex justify-end gap-2">
                                        <Button type="gray" size="xs" label="NO" @click="closed()"></Button>
                                        <Button type="save" size="xs" label="Yes"
                                            @click="()=>onSetPickingLocation(location)"></Button>
                                    </div>
                                </div>
                            </template>
                        </Popover>
                    </div>
                    <FontAwesomeIcon v-else :class="'text-indigo-500'" v-tooltip="location.type"
                        class="h-5 w-5 flex-none rounded-full bg-gray-50 cursor-pointer" :icon="faShoppingBasket" />

                    <div class="px-2">
                        <div class="text-sm font-semibold leading-6 text-gray-900">
                        <div class="flex gap-1">
                            <div v-tooltip="'location'">{{ location.location.code }} {{ "   " }}</div>
                                <div v-if="location.type == 'picking'">
                                    <Popover position="left-0 top-[-140px]">
                                            <template #button>
                                            <div v-if="location.settings.min_stock || location.settings.max_stock" class="text-gray-400 relative">
                                                (<span v-tooltip="'minimum stock'">{{ location?.settings?.min_stock }}</span>,
                                                <span v-tooltip="'maximum stock'">{{ location?.settings?.max_stock }}</span>)
                                            </div>
                                            <div v-else class="text-gray-400">( ? )</div>
                                            </template>
                                            <template #content="{ close: closed }">
                                                <div class="w-[300px]">
                                                    <SetSetting :data="location" :close="closed" @submitSetting="sendSetting"/>
                                                </div>
                                            </template>
                                        </Popover>
                                </div>
                                <div v-else class="text-gray-400">( ? )</div>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Stock Information -->
                <div class="flex items-center w-1/4 gap-x-4">
                    <div class="flex sm:flex-col sm:items-end">
                        <div class="flex gap-x-1" v-tooltip="`Audited At : ${useFormatTime(location.audited_at)} `">
                            <div class="flex-auto">
                                <div class="text-sm font-semibold leading-6 text-gray-900">
                                    {{daysAudit(location.audited_at)}}</div>
                            </div>
                            <FontAwesomeIcon class="h-4 w-4 mt-1 flex-none rounded-full bg-gray-50" :icon="faClock" />
                        </div>
                    </div>
                </div>

                <!-- Right Side: Stock Information (Duplicated) -->
                <slot name="Quantity" :itemData="location" :index="index">
                    <div class="flex justify-end w-1/4">
                        <div class="flex justify-end">
                            <div v-tooltip="'Quantity'" class="text-sm font-semibold leading-6 text-gray-900">{{
                                location.quantity }}</div>
                        </div>
                    </div>
                </slot>
            </div>

            <DisclosurePanel class="px-4 pb-2 pt-4 text-sm text-gray-500">
                <PureTextarea :modelValue="location.notes" @update:modelValue="(value)=>location.notes  = value"
                    :disabled="!editNotes" :rows="4" placeholder="Write a notes ....">
                    <template v-if="!loading" #stateIcon>
                        <div v-if="!editNotes" @click="()=>editNotes=true"
                            class="w-8 h-8 flex items-center justify-center text-sm text-white rounded-full bg-indigo-500 cursor-pointer">
                            <FontAwesomeIcon :icon="faPencil" />
                        </div>
                        <div v-else class="flex gap-3">
                            <div @click="()=>editNotes=false"
                                class="w-8 h-8 flex items-center justify-center text-sm text-red-500 rounded-full bg-white border border-red-500 cursor-pointer">
                                <FontAwesomeIcon :icon="faTimes" />
                            </div>
                            <div @click="()=>SendEditLocation(location)"
                                class="w-8 h-8 flex items-center justify-center text-sm text-white rounded-full bg-indigo-500 cursor-pointer">
                                <FontAwesomeIcon :icon="faSave" />
                            </div>
                        </div>
                    </template>
                </PureTextarea>
            </DisclosurePanel>
        </Disclosure>
    </ul>
</template>
