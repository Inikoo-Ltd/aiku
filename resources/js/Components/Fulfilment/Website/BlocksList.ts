import type { Component } from 'vue'

import WowsbarBanner from '@/Components/Fulfilment/Website/Block/WowsbarBanner.vue'
import ProductPage from '@/Components/Fulfilment/Website/Block/ProductPage.vue'
import Text from '@/Components/Fulfilment/Website/Block/TextContent.vue'
import FamilyPageOffer from '@/Components/Fulfilment/Website/Block/FamilyPage-offer.vue'
import ProductList from '@/Components/Fulfilment/Website/Block/ProductList.vue'
import CTA from '@/Components/Fulfilment/Website/Block/CTA.vue'
import Rewiews from '@/Components/Fulfilment/Website/Block/Reviews.vue'
import Image from '@/Components/Fulfilment/Website/Block/Image.vue'
import CTA2 from '@/Components/Fulfilment/Website/Block/CTA2.vue'
import CTA3 from '@/Components/Fulfilment/Website/Block/CTA3.vue'
import Gallery from '@/Components/Fulfilment/Website/Block/Gallery.vue'
import Iframe from '@/Components/Fulfilment/Website/Block/Iframe.vue'
import BentoGrid from '@/Components/Fulfilment/Website/Block/BentoGrid.vue'
import Product from '@/Components/Fulfilment/Website/Block/Product.vue'
import NotFoundComponents from '@/Components/Fulfilment/Website/Block/NotFoundComponent.vue'
import Grid1 from '@/Components/Fulfilment/Website/Block/Grid1.vue'
import Department from '@/Components/Fulfilment/Website/Block/Department.vue'
import Overview from '@/Components/Fulfilment/Website/Block/Overview.vue'
import Script from '@/Components/Fulfilment/Website/Block/Script.vue'
import SeeAlso from '@/Components/Fulfilment/Website/Block/SeeAlso.vue'
import Action from "@/Components/Forms/Fields/Action.vue"


export const getComponent = (componentName: string) => {
    const components: Component = {
        'bannerWowsbar': WowsbarBanner,
        'productPage': ProductPage,
        'text': Text,
        'family': FamilyPageOffer,
        'products': ProductList,
        'cta': CTA,
        'cta2': CTA2,
        'CTA3': SeeAlso,
        'reviews': Rewiews,
        'image': Image,
        "gallery": Gallery,
        "iframe": Iframe,
        "bentoGrid": BentoGrid,
        "product": Product,
        "grid1": Grid1,
        "overview": Overview,
        "department": Department,
        "script": Script
    }

    return components[componentName] ?? NotFoundComponents
}