<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { ref, reactive } from 'vue'
import { Popover, PopoverButton, PopoverPanel, Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronDown, faCheckSquare, faSquare } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { onMounted } from 'vue'
import { useLocaleStore } from '@/Stores/locale'
import Button from '../Elements/Buttons/Button.vue'
import Icon from '@/Components/Icon.vue'
import { Icon as IconTS } from '@/types/Utils/Icon'
library.add(faChevronDown, faCheckSquare, faSquare)


const props = defineProps<{
    elements: {
        [key: string]: {  // 'status'
            elements: {
                [key: string]: number[] | string[] | IconTS // active, lose, inactive, no_rental_agreement: ['No Rental Agreement', 126, 'NRA']
            }
            key: string
            label: string
        }
    }
    tableName: string
}>()

// console.log('element', props.elements)
const emits = defineEmits<{
    (e: 'checkboxChanged', value: SelectedElement): void
}>()

interface SelectedElement {
    [key: string]: string[]
}

const selectedGroup = ref(Object.keys(props.elements)[0]) || ref('')
const selectedElement: SelectedElement = reactive({
    [selectedGroup.value]: props.elements[selectedGroup.value]?.elements ? Object.keys(props.elements[selectedGroup.value].elements) : []
})

let timeout: any = null

// Method: Click the box
const onClickCheckbox = (elementName: string, scope: string) => {
    // Set timeout to prevent single on running twice on doubleclick
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        // If scope is not exist
        if(!selectedElement[scope]) {
            selectedElement[scope] = []  // Then initialise to empty array 
        }

        if (selectedElement[scope].includes(elementName)) {
            // If current active is more than 1, then can deselect
            if(selectedElement[scope].length > 1) {
                // console.log('qq', selectedElement[scope])
                selectedElement[scope] = selectedElement[scope].filter((item: string) => item !== elementName)
                // console.log('qq', selectedElement[scope])
            }
        } else {
            selectedElement[scope].push(elementName)
        }

        // console.log('end of onClick', selectedElement[scope])
        emits('checkboxChanged', selectedElement)
    }, 200)
}

// Method: Double click the box
const onDoubleClickCheckbox = (elementName: string, scope: string) => {
    clearTimeout(timeout)

    // If scope is not exist, then set to empty array
    if(!selectedElement[scope]) {
        selectedElement[scope] = []
    }

    // // If already checked, then check all
    // if (selectedElement[scope].includes(elementName)) {
    //     selectedElement[scope] = [...Object.keys(props.elements[selectedGroup.value].elements)]
    // }
    // // If not checked yet, then only select it
    // else {
    //     selectedElement[scope] = [elementName]
    // }

    selectedElement[scope] = [elementName]

    emits('checkboxChanged', selectedElement)
}

onMounted(() => {
    // console.log('fff', props.elements)
    // To handle selected checkbox on hard-refresh
    const prefix = props.tableName === 'default' ? 'elements' : props.tableName + '_' + 'elements'  // To handle banners_elements, users_elements, etc
    const searchParams = new URLSearchParams(window.location.search)
    const stateParam = searchParams.get(`${prefix}[${selectedGroup.value}]`)
    stateParam ? selectedElement[selectedGroup.value] = stateParam.split(",") : false

    // const asdfzxc = Object.keys(props.elements)
    // console.log('rr', asdfzxc.some(elementName => window.location.search.includes(`elements[${elementName}]`)))
})

</script>

<template>
    <!-- <pre>{{ elements }}</pre> -->

    <Popover class="relative md:hidden">
        <!-- Button: Filter table -->
        <PopoverButton :as="Button" type="tertiary" label="Filter table" icon="fal fa-filter" />

        <Transition>
            <PopoverPanel class="z-20 absolute right-0 mt-2 min-w-80 origin-top-right rounded-lg overflow-hidden bg-white shadow-lg ring-1 ring-black/5 focus:outline-none">
                <div v-for="(element, elementScope, idxElement) in elements"
                    :key="`${elementScope}${idxElement}}`"
                    class="w-full"
                    :class="idxElement === 0 ? '' : 'mt-4'"
                >
                    <div class="text-center py-1 bg-slate-300 text-gray-600 capitalize">
                        {{ element.label }}
                    </div>
                    <!-- List of element (checkbox) -->
                    <div class="max-w-96 grid grid-cols-2 rounded overflow-hidden w-full flex-wrap justify-end gap-0.5 ">
                        <div v-for="(value, elementKey) of element.elements"
                            :key="`${elementKey}${idxElement}`"
                            class="hover:bg-gray-100 flex items-center gap-x-1 px-3 py-2.5 cursor-pointer select-none"
                            :class="[selectedElement[elementScope]?.includes(elementKey) ? 'bg-gray-50' : 'bg-white']"
                            @click="onClickCheckbox(elementKey, elementScope)"
                            @dblclick="onDoubleClickCheckbox(elementKey, elementScope)" role="filter"
                        >
                            <FontAwesomeIcon v-if="selectedElement[elementScope]?.includes(elementKey)" icon="fal fa-check-square" aria-hidden="true" />
                            <FontAwesomeIcon v-else icon="fal fa-square" aria-hidden="true" />
                            <div :class="[selectedElement[elementScope]?.includes(elementKey) ? '' : 'text-gray-400']"
                                class="capitalize space-x-1">
                                <span class="font-normal">{{ value[0] }}</span>
                                <span :class="[value[1] ? 'font-semibold' : 'text-gray-400']" class="">
                                    ({{ useLocaleStore().number(value[1]) }})
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </PopoverPanel>
        </Transition>
    </Popover>

    <div v-if="!!selectedGroup" class="hidden md:flex items-center text-xs justify-between w-fit">
        <div class="w-fit flex gap-x-1 lg:gap-x-0 justify-end border border-gray-200 rounded">
            <!-- List of element (checkbox) -->
            <div class="w-full max-w-lg rounded overflow-hidden flex flex-wrap justify-end gap-0.5 ">
                <div v-for="(value, element, index) of elements[selectedGroup]?.elements" :key="element"
                    class="hover:bg-gray-100 flex flex-auto items-center gap-x-1 px-3 py-2.5 cursor-pointer select-none"
                    :class="[selectedElement[selectedGroup]?.includes(element) ? 'bg-gray-50' : 'bg-white']"
                    @click="onClickCheckbox(element, selectedGroup)"
                    @dblclick="onDoubleClickCheckbox(element, selectedGroup)"
                    role="filter"
                    :id="value[0].replace(' ','-')"
                >
                    <FontAwesomeIcon v-if="selectedElement[selectedGroup]?.includes(element)" icon="fal fa-check-square" aria-hidden="true" />
                    <FontAwesomeIcon v-else icon="fal fa-square" aria-hidden="true" />
                    <div class="capitalize space-x-1"
                        :class="[
                            selectedElement[selectedGroup]?.includes(element)
                            ? 'text-gray-700'
                            : 'text-gray-400'
                    ]">
                        <span class="hidden lg:inline font-normal">{{ value[0] }}</span>
                        <span class="lg:hidden font-normal">{{ value[2] || value[0] }}</span>
                        <span :class="[value[1] ? 'font-semibold' : 'text-gray-400']" class="">({{ useLocaleStore().number(value[1] || 0) }})</span>
                        <Icon v-if="value?.[3]?.icon" :data="value[3]" />
                    </div>
                </div>
            </div>
            
            <!-- Button: Select state -->
            <Menu as="div" class="relative inline-block text-left" v-slot="{ open }">
                <!-- Initial button -->
                <div v-if="props.elements" class="w-min bg-gray-200 rounded-r ring-1 ring-gray-300">
                    <MenuButton class="inline-flex relative w-full justify-start items-center py-2 px-1 xl:py-2.5 font-medium text-gray-600 capitalize focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-500 focus-visible:ring-opacity-75"
                        :class="[Object.keys(props.elements).length > 1 ? '' : 'cursor-default']"
                    >
                        <span v-if="Object.keys(props.elements).length > 1" class="pl-2 flex items-center justify-center">
                            <FontAwesomeIcon icon="fal fa-chevron-down" class="transition-all duration-200 ease-in-out" :class="[open ? 'rotate-180' : '']" aria-hidden="true" />
                        </span>
                        <span class="px-4">{{ elements[selectedGroup].label }}</span>
                    </MenuButton>
                </div>
                <!-- List of button -->
                <transition v-if="Object.keys(props.elements).length > 1" enter-active-class="transition duration-100 ease-out"
                    enter-from-class="transform scale-95 opacity-0" enter-to-class="transform scale-100 opacity-100"
                    leave-active-class="transition duration-75 ease-in" leave-from-class="transform scale-100 opacity-100"
                    leave-to-class="transform scale-95 opacity-0">
                    <MenuItems
                        class="absolute right-0 mt-2 w-40 min-w-min origin-top-right divide-y overflow-hidden divide-gray-100 rounded bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                        <div class="">
                            <MenuItem v-for="element in props.elements" v-slot="{ active }" @click="selectedGroup = element.key"
                                :class="[selectedGroup == element.key ? 'bg-gray-200' : '']"
                                :disabled="selectedGroup == element.key"
                            >
                                <button :class="[
                                    active ? 'bg-gray-100' : 'text-gray-600',
                                    'group flex w-full items-center pl-4 py-2 capitalize',
                                ]">
                                    <!-- <EditIcon :active="active" class="mr-2 h-5 w-5 text-orange-400" aria-hidden="true" /> -->
                                    {{ element.key }}
                                </button>
                            </MenuItem>
                        </div>
                    </MenuItems>
                </transition>
            </Menu>
        </div>
    </div>
</template>
