import type { Component } from 'vue'

import WowsbarBanner from '@/Components/CMS/Webpage/WowsbarBanner/WowsbarBannerWorkshop.vue'
import Text from '@/Components/CMS/Webpage/Text/TextContentWorkshop.vue'

import CTA from '@/Components/CMS/Webpage/Cta1/Cta1Workshop.vue'
import ImageWorkshop from '@/Components/CMS/Webpage/Image/ImageWorkshop.vue'
import CTA2 from '@/Components/CMS/Webpage/Cta2/Cta2Workshop.vue'
import CTA3 from '@/Components/CMS/Webpage/Cta3/Cta3Workshop.vue'
import Gallery from '@/Components/CMS/Webpage/Gallery/GalleryWorkshop.vue'
import Pricing from '@/Components/CMS/Webpage/Pricing/PricingWorkshop.vue'
import Timeline from '@/Components/CMS/Webpage/Timeline/TimelineWorkshop.vue'
import Iframe from '@/Components/CMS/Webpage/Iframe/IframeWorkshop.vue'
import BentoGrid from '@/Components/CMS/Webpage/BentoGrid/BentoGridWorksop.vue'
import Overview from '@/Components/CMS/Webpage/Overview/OverviewWorkshop.vue'
import Script from '@/Components/CMS/Webpage/Script/ScriptWorkShop.vue'
import  CtaAurora1 from "@/Components/CMS/Webpage/CtaAurora1/CtaAurora1Workshop.vue"
import Overview2 from "@/Components/CMS/Webpage/Overview2/Overview2Workshop.vue"
import Footer1 from '@/Components/CMS/Website/Footers/footerTheme1/Footer1Workshop.vue'
import Topbar1 from '@/Components/CMS/Website/TopBars/Template/Topbar1/Topbar1Workshop.vue'
import Topbar2 from '@/Components/CMS/Website/TopBars/Template/Topbar2/Topbar2Workshop.vue'
import Topbar3 from '@/Components/CMS/Website/TopBars/Template/Topbar3/Topbar3Workshop.vue'
import Header1 from '@/Components/CMS/Website/Headers/Header1/Header1Workshop.vue'
import Header2 from '@/Components/CMS/Website/Headers/Header2/Header2Workshop.vue'
import Menu1 from '@/Components/CMS/Website/Menus/Menu1Workshop.vue'
import TextColumn from '@/Components/CMS/Webpage/TextColumn/TextColumnWorkshop.vue'
import Topbar1Fulfilment from '@/Components/CMS/Website/TopBars/Template/Topbar1Fulfilment/Topbar1FulfilmentWorkshop.vue'
import Topbar2Fulfilment from '@/Components/CMS/Website/TopBars/Template/Topbar2Fulfilment/Topbar2FulfilmentWorkshop.vue' 
import Topbar3Fulfilment from '@/Components/CMS/Website/TopBars/Template/Topbar3Fulfilment/Topbar3FulfilemntWorkshop.vue'
import Button from "@/Components/CMS/Webpage/Button/ButtonWorkshop.vue"
import NotFoundComponents from '@/Components/CMS/Webpage/NotFoundComponent.vue'
import ColumnWorkshop from '@/Components/CMS/Webpage/Column/ColumnWorkshop.vue'
import DisclosureWorkshop from '@/Components/CMS/Webpage/Disclosure/DisclosureWorkshop.vue'
import FamilyIris1 from '@/Components/CMS/Webpage/Family-1/family1Workshop.vue'
import Department1Iris from '@/Components/CMS/Webpage/Department1/Department1Iris.vue'
import Step2Workshop from '@/Components/CMS/Webpage/Step1/Step1Workshop.vue'
import Carousel1Workshop from '@/Components/CMS/Webpage/Carousel-1/Carousel1Workshop.vue'
import ProductWorkshop1 from '@/Components/CMS/Webpage/Product1/Dropshipping/Product1Workshop.vue'
import SubDepartmentWorkshop1 from '@/Components/CMS/Webpage/SubDepartment1/SubDepartmentWorkshop.vue'
import Families1Workshop from '@/Components/CMS/Webpage/Families1/Families1Workshop.vue'
import Families2Workshop from '@/Components/CMS/Webpage/Families2/Families2Workshop.vue'


import Collections1Workshop from '@/Components/CMS/Webpage/Collections1/Collections1Workshop.vue'
import CTAVideo1Workshop from '@/Components/CMS/Webpage/CtaVideo1/CtaVideo1Workshop.vue'
import Video1Workshop from '@/Components/CMS/Webpage/Video/Video1Workshop.vue'
import UserSubscribeWorkshop from '@/Components/CMS/Webpage/UserSubscribe/UserSubscribeWorkshop.vue'
import Cta4 from '@/Components/CMS/Webpage/Cta4/Cta4Workshop.vue'
import SeeAlso1Workshop from '@/Components/CMS/Webpage/SeeAlso1/SeeAlso1Workshop.vue'
import BlogWorkshop from '@/Components/CMS/Webpage/Blog/BlogWorkshop.vue'
import EditFooter1Translation from '@/Components/CMS/Website/Footers/footerTheme1/EditFooter1Translation.vue'
import CarouselCtaWorkshop from '@/Components/CMS/Webpage/CarouselCta/CarouselCtaWorkshop.vue'
import CarouselImageBackground from '@/Components/CMS/Webpage/CarouselImageBackground/CarouselImageBackgroundWorkshop.vue'
import SubDepartmentWorkshop2 from '@/Components/CMS/Webpage/SubDepartment2/SubDepartmentWorkshop.vue'

import LuigiTrends1Workshop from '@/Components/CMS/Webpage/LuigiTrends1/LuigiTrends1Workshop.vue'
import LuigiLastSeen1Workshop from '@/Components/CMS/Webpage/LuigiLastSeen1/LuigiLastSeen1Workshop.vue'
import LuigiItemAlternatives1Workshop from '@/Components/CMS/Webpage/LuigiItemAlternatives1/LuigiItemAlternatives1Workshop.vue'

import RecommendationCustomerRecentlyBought1Workshop from '@/Components/CMS/Webpage/RecomendationRecentlyBought1/RecommendationCustomerRecentlyBought1Workshop.vue'
import CtaImageBackroundWorkshop from '@/Components/CMS/Webpage/CtaImageBackround/CtaImageBackroundWorkshop.vue'
import TimelineWorkshop2 from '@/Components/CMS/Webpage/Step2/Step2Workshop.vue'
import ListProductWorkshop from '@/Components/CMS/Webpage/Products/Dropshipping/ListProductsWorkshop.vue'
import ListProductsEcomWorkshop from '@/Components/CMS/Webpage/Products/Ecommerce/ListProductsEcomWorkshop.vue'
import RenderDropshippingProductWorkshop from '@/Components/CMS/Webpage/Product/Dropshipping/RenderDropshippingProductWorkshop.vue'
import Product1WorkshopEcom from '@/Components/CMS/Webpage/Product1/Ecommerce/Product1WorkshopEcom.vue'
import Product2WorkshopEcom from '@/Components/CMS/Webpage/Product2/Product2WorkshopEcom.vue'
import ProductWorkshop2 from '@/Components/CMS/Webpage/Product2/Product2WorkshopEcom.vue'

const components = (shop_type?: string): Record<string, Component> => {
    return {
        //topbar
        'top-bar-1': Topbar1,
        'top-bar-2': Topbar2,
        'top-bar-3': Topbar3,
        'top-bar-1-fulfilment': Topbar1Fulfilment,
        'top-bar-2-fulfilment': Topbar2Fulfilment,
        'top-bar-3-fulfilment': Topbar3Fulfilment,

        //header
        'header-1': Header1,
        'header-2': Header2,

        //menu 
        'menu-1': Menu1,

        //footer
        'footer-1': Footer1,

        //department
        'department' : Department1Iris,

        //sub-department
        'sub-departments-1' : SubDepartmentWorkshop1,
        'sub-departments-2' : SubDepartmentWorkshop2,

        //family
        'families-1' : Families1Workshop,
        'families-2' : Families2Workshop,
        'family-1': FamilyIris1,

        //product
        'product-1': shop_type == 'b2b' ? RenderDropshippingProductWorkshop : RenderDropshippingProductWorkshop,
        'product-2': RenderDropshippingProductWorkshop,

        //product list
        'products-1' : shop_type == 'b2b' ? ListProductsEcomWorkshop : ListProductWorkshop,
        'products-2' : ListProductsEcomWorkshop,

        //see-also
        'see-also-1' : SeeAlso1Workshop,

        // Luigi
        'luigi-trends-1' : LuigiTrends1Workshop,
        'luigi-last-seen-1' : LuigiLastSeen1Workshop,
        'luigi-item-alternatives-1' : LuigiItemAlternatives1Workshop,
        'recommendation-customer-recently-bought-1': RecommendationCustomerRecentlyBought1Workshop,

        "cta-image-background" : CtaImageBackroundWorkshop,
        'banner': WowsbarBanner,
        "bento-grid-1": BentoGrid,
        "bricks": Gallery,
        'cta1': CTA,
        'cta2': CTA2,
        'cta3': CTA3,
        "iframe": Iframe,
        'images': ImageWorkshop,
        "overview_aurora": Overview,
        "script": Script,
        'text': Text,
        'cta_aurora_1' : CtaAurora1,
        'overview_2' : Overview2, 
        'text-column' : TextColumn,
        'pricing': Pricing,
        'button' : Button,
        'column-layout-2': ColumnWorkshop,
        'disclosure': DisclosureWorkshop,
        'timeline' : Timeline,
        'step-1' : Step2Workshop,
        'carousel-1' : Carousel1Workshop,
        'collections-1': Collections1Workshop,
        'cta-video-1' : CTAVideo1Workshop,
        'video-1': Video1Workshop,
        "user-subscribe": UserSubscribeWorkshop,
        'cta4' :Cta4,
        'blog' : BlogWorkshop,
        'carousel-cta' : CarouselCtaWorkshop,
        'carousel-image-background' : CarouselImageBackground,
        'step-2' : TimelineWorkshop2
    }
}

export const getComponent = (componentName: string, options?: {
    shop_type?: string // 'b2b' | 'dropshipping'
}) => {
    return components(options?.shop_type)[componentName] ?? NotFoundComponents
}

export const getTranslationComponent = (componentName: string) => {
    const components: Component = {
        'footer-1': EditFooter1Translation,
    }

    return components[componentName] ?? NotFoundComponents
}
export const getProductRenderB2bComponent = (
    componentName: string,
    options: Record<string, any> = {}
) => {
    const components: Record<string, any> = {
        "product-1": Product1WorkshopEcom,
        "product-2": Product2WorkshopEcom,
    }

    return components[componentName] ?? null
}


export const getProductRenderDropshippingComponentWorkshop  = (
    componentName: string,
    options: Record<string, any> = {}
) => {
    const components: Record<string, any> = {
        "product-1": ProductWorkshop1,
    }

    return components[componentName] ?? null
}
