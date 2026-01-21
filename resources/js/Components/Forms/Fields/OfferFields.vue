<script setup lang="ts">
import { ref, nextTick } from "vue"
import { get } from "lodash-es"
import {
    Disclosure,
    DisclosureButton,
    DisclosurePanel
} from "@headlessui/vue"
import { trans } from "laravel-vue-i18n";

const props = defineProps<{
    form: any
    fieldName: string
}>()

const editingOffer = ref<string | null>(null)
const editingAllowance = ref<string | null>(null)

const offerInputRef = ref<HTMLInputElement | null>(null)
const allowanceInputRef = ref<HTMLInputElement | null>(null)

function editOffer(key: string) {
    editingAllowance.value = null
    editingOffer.value = key

    nextTick(() => {
        console.log(offerInputRef.value)
        if(offerInputRef.value) offerInputRef.value?.focus()
         if(offerInputRef.value) offerInputRef.value?.select()
    })
}

function editAllowance(id: string) {
    editingOffer.value = null
    editingAllowance.value = id

    nextTick(() => {
        if(allowanceInputRef.value) allowanceInputRef.value?.focus()
        if(allowanceInputRef.value) allowanceInputRef.value?.select()
    })
}

function closeEdit() {
    editingOffer.value = null
    editingAllowance.value = null
}
</script>


<template>
    <div class="space-y-2">
        <div class="rounded-md overflow-hidden border border-indigo-200 bg-indigo-50/40">
            <Disclosure v-for="(offer, key) in form[fieldName]" :key="key" as="div"
                class="border-b border-indigo-200 last:border-b-0">
                <DisclosureButton class="w-full text-left focus:outline-none">
                    <div class="flex items-center justify-between w-full px-3 py-2">
                        <div class="min-w-0 space-y-0.5">
                            <!-- OFFER LABEL -->
                            <div>
                                <input 
                                    v-if="editingOffer === key" 
                                    :ref="(e)=>offerInputRef = e" 
                                    v-model="offer.label" 
                                    type="text"
                                    class="w-full text-sm font-medium text-indigo-900 rounded-md border border-indigo-300 bg-white px-2 py-1 focus:ring-2 focus:ring-indigo-400" 
                                    @blur="closeEdit" 
                                    @keyup.enter="closeEdit" 
                                    @keydown.space.stop
                                    @keydown.enter.stop 
                                />
                                <div v-else @click.stop="editOffer(key)"
                                    class="text-sm font-medium text-indigo-900 cursor-text hover:underline">
                                    {{ offer.label || 'Untitled offer' }}
                                </div>

                            </div>

                            <div class="text-xs text-indigo-500 truncate">
                                {{ key }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span v-if="offer.allowances?.length" class="text-xs px-2 py-0.5 rounded-full
                                       bg-indigo-100 text-indigo-700 font-medium">
                                {{ offer.allowances.length }} items
                            </span>

                            <span class="text-xs px-2 py-0.5 rounded-full capitalize  bg-emerald-100 text-emerald-700 font-semibold">
                                {{ offer.state }}
                            </span>
                        </div>
                    </div>
                </DisclosureButton>

                <DisclosurePanel class="px-4 py-3 space-y-2 bg-white">
                    <div class="text-xs font-semibold text-gray-500 uppercase">
                        {{  trans('Allowances') }}
                    </div>

                    <ul v-if="offer.allowances?.length"
                        class="divide-y divide-gray-100 rounded-md border border-gray-200">
                        <li v-for="(allowance, index) in offer.allowances" :key="index"
                            class="flex items-center justify-between px-3 py-2 gap-3">
                            <div class="min-w-0 flex-1">
                                <!-- ALLOWANCE LABEL -->
                                <input 
                                    v-if="editingAllowance === `${key}-${index}`" 
                                    :ref="(e)=>allowanceInputRef = e"
                                    v-model="allowance.label" 
                                    type="text" 
                                    class="w-full text-sm font-medium text-gray-800 rounded-md border border-gray-300 bg-white px-2 py-1 focus:ring-2 focus:ring-indigo-400" 
                                    @blur="closeEdit" 
                                    @keyup.enter="closeEdit" 
                                    @keydown.space.stop
                                    @keydown.enter.stop 
                                />
                                <div v-else @click.stop="editAllowance(`${key}-${index}`)"
                                    class="text-sm font-medium text-gray-800 cursor-text hover:underline">
                                    {{ allowance.label || 'Untitled allowance' }}
                                </div>


                                <div class="text-xs text-gray-500">
                                    {{ allowance.type }}
                                </div>
                            </div>

                            <span class="text-xs font-mono text-gray-400 shrink-0">
                                #{{ index + 1 }}
                            </span>
                        </li>
                    </ul>

                    <div v-else class="text-sm text-gray-400 italic">
                         {{  trans('No allowances available') }}
                    </div>
                </DisclosurePanel>
            </Disclosure>
        </div>

        <p v-if="get(form, ['errors', fieldName])" class="text-sm text-red-600">
            {{ get(form, ['errors', fieldName]) }}
        </p>
    </div>
</template>
