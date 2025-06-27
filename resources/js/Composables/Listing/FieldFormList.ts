import type { Component } from 'vue'
import { defineAsyncComponent } from 'vue'


import Input from '@/Components/Forms/Fields/Input.vue'
import Select from '@/Components/Forms/Fields/Select.vue'
import SelectBillingCycle from '@/Components/Forms/Fields/SelectBillingCycle.vue'
import Phone from '@/Components/Forms/Fields/Phone.vue'
import Date from '@/Components/Forms/Fields/Date.vue'
import DateRadio from '@/Components/Forms/Fields/DateRadio.vue'
import Address from "@/Components/Forms/Fields/Address.vue"
import Radio from '@/Components/Forms/Fields/Radio.vue'
import Country from "@/Components/Forms/Fields/Country.vue"
import Currency from "@/Components/Forms/Fields/Currency.vue"
import InputWithAddOn from '@/Components/Forms/Fields/InputWithAddOn.vue'
import Password from "@/Components/Forms/Fields/Password.vue"
import Toggle from '@/Components/Forms/Fields/Toggle.vue'
// import Rental from '@/Components/Rental/Rental.vue'
import Textarea from "@/Components/Forms/Fields/Textarea.vue"
import TextEditor from "@/Components/Forms/Fields/TextEditor.vue"

import ImageCropSquare from '@/Components/Forms/Fields/ImageCropSquare.vue'
import Avatar from '@/Components/Forms/Fields/Avatar.vue'
import Theme from '@/Components/Forms/Fields/Theme.vue'
import ColorMode from '@/Components/Forms/Fields/ColorMode.vue'
import Checkbox from '@/Components/Forms/Fields/Checkbox.vue'
import AppTheme from '@/Components/Forms/Fields/AppTheme.vue'
import Action from '@/Components/Forms/Fields/Action.vue'
import AppLogin from '@/Components/Forms/Fields/AppLogin.vue'
import Pin from '@/Components/Forms/Fields/Pin.vue'
import GeneratePassword from '@/Components/Forms/Fields/GeneratePassword.vue'
import TaxNumber from '@/Components/Forms/Fields/TaxNumber.vue'
import EditorHtml from '@/Components/Forms/Fields/EditorHtml.vue'
import StructureDataWebsite from '@/Components/Forms/Fields/StructureDataWebsite.vue'
import SelectInfiniteScroll from '@/Components/Forms/Fields/SelectInfiniteScroll.vue'


const GoogleSearch = defineAsyncComponent(() => import('@/Components/Forms/Fields/GoogleSearch.vue'))
const ProductParts = defineAsyncComponent(() => import('@/Components/Forms/Fields/ProductParts.vue'))
const PollTypeSelect = defineAsyncComponent(() => import('@/Components/Forms/Fields/PollTypeSelect.vue'))
const EmployeeState = defineAsyncComponent(() => import('@/Components/Forms/Fields/Employee/EmployeeState.vue'))
const Language = defineAsyncComponent(() => import("@/Components/Forms/Fields/Language.vue"))
const WebRegistrations = defineAsyncComponent(() => import('@/Components/Forms/Fields/WebRegistrations.vue'))
const Permissions = defineAsyncComponent(() => import("@/Components/Forms/Fields/Permissions.vue"))
const Agreement = defineAsyncComponent(() => import('@/Components/Rental/Agreement.vue'))
const SenderEmail = defineAsyncComponent(() => import('@/Components/Forms/Fields/SenderEmail.vue'))
const CustomerRoles = defineAsyncComponent(() => import('@/Components/Forms/Fields/CustomerRoles.vue'))
const JobPosition = defineAsyncComponent(() => import('@/Components/Forms/Fields/JobPosition.vue'))
const Interest = defineAsyncComponent(() => import('@/Components/Forms/Fields/Interest.vue'))
const EmployeePosition = defineAsyncComponent(() => import('@/Components/Forms/Fields/EmployeePosition.vue'))
const MailshotRecipient = defineAsyncComponent(() => import('@/Components/Forms/Fields/MailshotRecipients.vue'))


export const componentsList: {[key: string]: Component} = {
    'image_crop_square': ImageCropSquare,
    'input': Input,
    'inputWithAddOn': InputWithAddOn,
    'phone': Phone,
    'date': Date,
    'date_radio': DateRadio,
    'select': Select,
    'address': Address,
    'radio': Radio,
    'country': Country,
    'currency': Currency,
    'password': GeneratePassword,
    'purePassword': Password,
    'customerRoles': CustomerRoles,
    'textarea': Textarea,
    'textEditor': TextEditor,
    'toggle': Toggle,
    'jobPosition': JobPosition,
    'senderEmail': SenderEmail,
    'employeePosition': EmployeePosition,
    'interest': Interest,
    'rental': Agreement,
    'webRegistrations': WebRegistrations,
    'mailshotRecipient' : MailshotRecipient,
    'select_billing_cycle': SelectBillingCycle,

    'action': Action,
    'theme': Theme,
    'colorMode': ColorMode,
    'avatar': Avatar,
    'language': Language,
    'permissions': Permissions,
    'checkbox': Checkbox,
    'app_login': AppLogin,
    'app_theme': AppTheme,
    'googleSearch': GoogleSearch,
    'product_parts': ProductParts,
    'employeeState': EmployeeState,
    'pin' : Pin,
    'tax_number' : TaxNumber,
    'editor' : EditorHtml,
    'structure_data_website' : StructureDataWebsite,
    'poll_type_select': PollTypeSelect,

    'select_infinite': SelectInfiniteScroll
}

export const getComponent = (componentName: string) => {
    return componentsList[componentName]
}