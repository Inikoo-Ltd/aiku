<script setup lang='ts'>
import { getComponent } from '@/Composables/getWorkshopComponents'
import { getIrisComponent } from '@/Composables/getIrisComponents'
import { sendMessageToParent } from '@/Composables/Workshop';

const props = defineProps<{
    data: {
        topBar: {
            id: number
            code: string
            data: {
                component: string
                fieldValue: {
                    key: string
                    greeting: {
                        text: string
                        visible: string
                    }
                    container: {
                        properties: {
                            text: {
                                color: string
                                fontFamily: string
                            }
                            background: {
                                type: string
                                color: string
                                image: {
                                    original: string | null
                                }
                            }
                        }
                    }
                    main_title: {
                        text: string
                        visible: string
                    }
                }
            }
            icon: string | null
            name: string
            show: boolean
            scope: string
            blueprint: {
                key: string[]
                name: string
                type: string[]
                props_data: any[]
                replaceForm?: {
                    key: string[]
                    type: string[]
                }[]
            }[]
            component: string
            created_at: string
            screenshot: string | null
            updated_at: string
            visibility: {
                in: boolean
                out: boolean
            }
            description: string | null
        }
    }
    menu: {
        key: string,
        data: object,
    }
    screenType: 'mobile' | 'tablet' | 'desktop'
    loginMode:Boolean
    previewMode?:Boolean
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()
const { mode } = route().params;
</script>

<template>
        <!-- Section: TopBars -->
         <div class="hidden lg:block">
            <component
                v-if="data?.topBar?.data?.fieldValue"
                :is="getComponent(data?.topBar.code)"
                v-model="data.topBar.data.fieldValue"
                :loginMode="loginMode"
                :fieldValue="data.topBar.data.fieldValue"
                @update:model-value="(e)=>emits('update:modelValue', e)"
                @setPanelActive="(data : string)=>sendMessageToParent('TopbarPanelOpen',data)"
            />
         </div>

        <!-- Section: Header -->
        <component
            v-if="data?.header?.code"
            :is="mode == 'iris' ? getIrisComponent(data?.header?.code) : getComponent(data?.header?.code)"
            v-model="data.header.data.fieldValue"
            :loginMode="loginMode"
            :fieldValue="data.header.data.fieldValue"
             @update:model-value="(e)=>emits('update:modelValue', e)"
             @setPanelActive="(data : string)=>sendMessageToParent('HeaderPanelOpen',data)"
             :screenType="screenType"
        />

        <!-- Section: Menu -->
        <component
            v-if="menu?.menu?.data"
            :is="getComponent(menu?.menu.code)"
            :navigations="menu?.menu?.data.fieldValue.navigation"
            class="hidden md:block"
        />
</template>
