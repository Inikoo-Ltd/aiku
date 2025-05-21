
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
import InputNumberCss from '@/Components/CMS/Fields/InputNumberCss.vue'
import JustifyContent from '@/Components/CMS/Fields/JustifyContent.vue'
import Shadow from '@/Components/CMS/Fields/Shadow.vue'
import ColumnComponentPicker from '@/Components/CMS/Fields/ColumnComponentPicker.vue'
import Disclosure from '@/Components/CMS/Fields/Disclosure.vue'
import ArrayEdit from '@/Components/CMS/Fields/ArrayEdit/LabelAndOrderArray.vue'
import InputNumber from 'primevue/inputnumber';

import { set } from 'lodash-es'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import TextHeader from '@/Components/CMS/Fields/TextHeader.vue'
import ColorPicker from '@/Components/CMS/Fields/ColorPicker.vue'
import ColorProperty from '@/Components/Workshop/Properties/ColorProperty.vue'
import TextInputSwitch from '@/Components/CMS/Fields/TextInputSwitch.vue'
import TimelineArray from '@/Components/CMS/Fields/TimelineArray.vue'
import IconPickerBox from '@/Components/CMS/Fields/IconPickerBox.vue'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import ToggleSwitch from 'primevue/toggleswitch';
// Field list of SideEditor
export const getComponent = (componentName: string) => {
    const components: Component = {
        'text': InputText, //done
        'editorhtml': SideEditorInputHTML,
        'upload_image': UploadImage, //done
        'payment_templates': Payments, //done
        'editor': Editor, //done
        'socialMedia': socialMedia, //done
        "VisibleLoggedIn": ButtonVisibleLoggedIn, //done
       /*  "properties": PanelProperties, */
        "overview-property" : Overview,
        "textHeader": TextHeader, //done
        "background": Background, // done
        "border": Border, //done
        "padding": Padding, //done
        "margin": Margin, //done
        "dimension": Dimension, //done
        "select" : PureMultiselect, // done
        "cards-property": cardsProperty,
        "button": ButtonProperties, //done
        "link": Link, //done
        "overview_form": OverviewForm,
        "layout_type": SelectLayout,
        "script": Script, //done
        "arrayPhone":ArrayPhone, //done
        "textProperty": TextProperty, //done
        "images-property" : ImagesArray,
        "numberCss" : InputNumberCss, //done
        "justify-content" : JustifyContent, //done
        "shadow" : Shadow, // done
        "color" : ColorProperty, //done
        "column-layout" : ColumnComponentPicker,
        "disclosure" : Disclosure,
        "inputSwitch" : TextInputSwitch,
        "timeline" : TimelineArray,
        "array-data" : ArrayEdit,
        "icon-picker" : IconPickerBox,
        'number' : PureInputNumber,
        'switch' : ToggleSwitch ,
    }
    
    return components[componentName] ?? NotFoundComponents
}

export const getFormValue = (data: {}, fieldKeys: string | string[]) => {
    const keys = Array.isArray(fieldKeys) ? fieldKeys : [fieldKeys];
    return keys.reduce((acc, key) => acc && acc[key], data) ?? null;
};

export const setFormValue = (mValue = {} , fieldKeys: string | string[], newVal: any) => {
    const keys = Array.isArray(fieldKeys) ? fieldKeys : [fieldKeys];
    return set(mValue, keys, newVal);
};