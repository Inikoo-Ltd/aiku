import type { Component } from 'vue'

import WowsbarBanner from '@/Components/CMS/Webpage/WowsbarBannerWorkshop.vue'
import ProductPage from '@/Components/CMS/Webpage/ProductPage.vue'
import Text from '@/Components/CMS/Webpage/TextContentWorkshop.vue'
import FamilyPageOffer from '@/Components/CMS/Webpage/FamilyPage-offer.vue'
import ProductList from '@/Components/CMS/Webpage/ProductList.vue'
import CTA from '@/Components/CMS/Webpage/CTAWorkshop.vue'
import Rewiews from '@/Components/CMS/Webpage/ReviewsWorkshop.vue'
import Image from '@/Components/CMS/Webpage/ImageWorkshop.vue'
import CTA2 from '@/Components/CMS/Webpage/CTA2Workshop.vue'
import CTA3 from '@/Components/CMS/Webpage/CTA3Workshop.vue'
import Gallery from '@/Components/CMS/Webpage/GalleryWorkshop.vue'
import Iframe from '@/Components/CMS/Webpage/IframeWorkshop.vue'
import BentoGrid from '@/Components/CMS/Webpage/BentoGridWorksop.vue'
import Product from '@/Components/CMS/Webpage/Product.vue'
import NotFoundComponents from '@/Components/CMS/Webpage/NotFoundComponent.vue'
import Grid1 from '@/Components/CMS/Webpage/Grid1.vue'
import Department from '@/Components/CMS/Webpage/DepartmentWorkshop.vue'
import Overview from '@/Components/CMS/Webpage/OverviewWorkshop.vue'
import Script from '@/Components/CMS/Webpage/ScriptWorkShop.vue'
import SeeAlso from '@/Components/CMS/Webpage/SeeAlsoWorkshop.vue'
import  CtaAurora1 from "@/Components/CMS/Webpage/CtaAurora1Workshop.vue"
import Overview2 from "@/Components/CMS/Webpage/Overview2Workshop.vue"
import Action from "@/Components/Forms/Fields/Action.vue"


export const getComponent = (componentName: string) => {
    const components: Component = {
        'banner': WowsbarBanner,
        "bento-grid-1": BentoGrid,
        "bricks": Gallery,
        //categories
        'cta1': CTA,
        'cta2': CTA2,
        'cta3': CTA3,
        "department": Department,
        'family': FamilyPageOffer,
        "iframe": Iframe,
        'images': Image,
        "overview_aurora": Overview,
        'product': ProductPage,
        'products': ProductList,
        "script": Script,
        'text': Text,
        'cta_aurora_1' : CtaAurora1,
        'overview_2' : Overview2
        /* "product": Product, */
    }

    return components[componentName] ?? NotFoundComponents
}