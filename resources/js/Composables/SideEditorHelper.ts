
import type { Component } from 'vue'
import ButtonVisibleLoggedIn from '@/Components/CMS/Fields/ButtonVisibleLoggedIn.vue'
import PanelProperties from '@/Components/CMS/Fields/PanelProperties.vue'
import SideEditorInputHTML from '@/Components/CMS/Fields/SideEditorInputHTML.vue'
import Border from '@/Components/CMS/Fields/Border.vue'
import Padding from '@/Components/CMS/Fields/Padding.vue'
import Margin from '@/Components/CMS/Fields/Margin.vue'
import Dimension from '@/Components/CMS/Fields/Dimension.vue'
import Link from '@/Components/CMS/Fields/Link.vue'
import Background from '@/Components/CMS/Fields/Background.vue'
import ButtonProperties from '@/Components/CMS/Fields/ButtonProperties.vue'
import UploadImage from '@/Components/Pure/UploadImage.vue'
import Payments from '@/Components/CMS/Fields/Payment.vue'
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorForm.vue"
import socialMedia from '@/Components/CMS/Fields/SocialMedia.vue'
import Script from '@/Components/CMS/Fields/Script.vue'
import SelectLayout from '@/Components/CMS/Fields/SelectLayout.vue'
import InputText from 'primevue/inputtext'
import OverviewForm from '@/Components/CMS/Fields/OverviewForm.vue'
import ArrayPhone from '@/Components/CMS/Fields/ArrayPhone.vue'
import NotFoundComponents from '@/Components/CMS/Webpage/NotFoundComponent.vue'
import Overview from '@/Components/CMS/Fields/Overview.vue'
import TextProperty from '@/Components/Workshop/Properties/TextProperty.vue'
import ImagesArray from '@/Components/CMS/Fields/ImagesArray.vue'
import cardsProperty from '@/Components/CMS/Fields/CardArray.vue'

import { set } from 'lodash-es'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import TextHeader from '@/Components/CMS/Fields/TextHeader.vue'

// Field list of SideEditor
export const getComponent = (componentName: string) => {
    const components: Component = {
        'text': InputText,
        'editorhtml': SideEditorInputHTML,
        'upload_image': UploadImage,
        'payment_templates': Payments,
        'editor': Editor,
        'socialMedia': socialMedia,
        "VisibleLoggedIn": ButtonVisibleLoggedIn,
       /*  "properties": PanelProperties, */
        "overview-property" : Overview,
        "textHeader": TextHeader,
        "background": Background,
        "border": Border,
        "padding": Padding,
        "margin": Margin,
        "dimension": Dimension,
        "select" : PureMultiselect,
        "cards-property": cardsProperty,
        "button": ButtonProperties,
        "link": Link,
        "overview_form": OverviewForm,
        "layout_type": SelectLayout,
        "script": Script,
        "arrayPhone":ArrayPhone,
        "textProperty": TextProperty,
        "images-property" : ImagesArray
    }
    
    return components[componentName] ?? NotFoundComponents
}

export const getFormValue = (data: {}, fieldKeys: string | string[]) => {
    const keys = Array.isArray(fieldKeys) ? fieldKeys : [fieldKeys];
    return keys.reduce((acc, key) => acc && acc[key], data) ?? null;
};

export const setFormValue = (mValue: any, fieldKeys: string | string[], newVal: any) => {
    const keys = Array.isArray(fieldKeys) ? fieldKeys : [fieldKeys];
    return set(mValue, keys, newVal);
};