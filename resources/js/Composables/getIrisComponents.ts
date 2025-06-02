import type { Component } from 'vue'
import { defineAsyncComponent } from 'vue'

import Footer1Iris from "@/Components/CMS/Website/Footers/footerTheme1/Footer1Iris.vue"
import Header1Iris from "@/Components/CMS/Website/Headers/Header1/Header1Iris.vue"
import Header2Iris from "@/Components/CMS/Website/Headers/Header2/Header2Iris.vue"
import Topbar1Iris from "@/Components/CMS/Website/TopBars/Template/Topbar1/Topbar1Iris.vue"
import Topbar2Iris from "@/Components/CMS/Website/TopBars/Template/Topbar2/Topbar2Iris.vue"
import Topbar3Iris from "@/Components/CMS/Website/TopBars/Template/Topbar3/Topbar3Iris.vue"
import Menu1Workshop from "@/Components/CMS/Website/Menus/Menu1Workshop.vue"
import WowsbarBannerIris from "@/Components/CMS/Webpage/WowsbarBanner/WowsbarBannerIris.vue"
import BentoGridIris from "@/Components/CMS/Webpage/BentoGrid/BentoGridIris.vue"
import GalleryIris from "@/Components/CMS/Webpage/Gallery/GalleryIris.vue"
import CTAIris from "@/Components/CMS/Webpage/CTA/CTAIris.vue"
import CTA2Iris from "@/Components/CMS/Webpage/CTA2/CTA2Iris.vue"
import CTA3Iris from "@/Components/CMS/Webpage/CTA3/CTA3Iris.vue"
import Department1Iris from "@/Components/CMS/Webpage/Department1/Department1Iris.vue"
import IframeIris from "@/Components/CMS/Webpage/Iframe/IframeIris.vue"
import ImageIris from "@/Components/CMS/Webpage/Image/ImageIris.vue"
import OverviewIris from "@/Components/CMS/Webpage/Overview/OverviewIris.vue"
import ScriptIris from "@/Components/CMS/Webpage/Script/ScriptIris.vue"
import TextContentIris from "@/Components/CMS/Webpage/Text/TextContentIris.vue"
import CtaAurora1Iris from "@/Components/CMS/Webpage/CTAAurora1/CtaAurora1Iris.vue"
import Overview2Iris from "@/Components/CMS/Webpage/Overview2/Overview2Iris.vue"
import Pricing from "@/Components/CMS/Webpage/Pricing/PricingIris.vue"
import Timeline from "@/Components/CMS/Webpage/Timeline/TimelineIris.vue"
import TextColumnIris from "@/Components/CMS/Webpage/TextColumn/TextColumnIris.vue"
import Topbar1FulfilmentIris from "@/Components/CMS/Website/TopBars/Template/Topbar1Fulfilment/Topbar1FulfilmentIris.vue"
import Topbar2FulfilmentIris from "@/Components/CMS/Website/TopBars/Template/Topbar2Fulfilment/Topbar2FulfilmentIris.vue"
import Topbar3FulfilmentIris from "@/Components/CMS/Website/TopBars/Template/Topbar3Fulfilment/Topbar3FulfilmentIris.vue"
import ButtonIris from '@/Components/CMS/Webpage/Button/ButtonIris.vue'
import ColumnIris from '@/Components/CMS/Webpage/Column/ColumnIris.vue'
import DisclosureIris from '@/Components/CMS/Webpage/Disclosure/DisclosureIris.vue'
import Step2Iris from '@/Components/CMS/Webpage/Step1/Step1Iris.vue'
import SubDepartments1Iris from '@/Components/CMS/Webpage/SubDepartement1/SubDepartementIris.vue'

import NotFoundComponent from "@/Components/CMS/Webpage/NotFoundComponent.vue"
import FamilyIris1 from '@/Components/CMS/Webpage/Family1/FamilyIris1.vue'
import ProductIris1 from '@/Components/CMS/Webpage/Product1/ProductIris1.vue'
import Carousel1Iris from '@/Components/CMS/Webpage/Carousel-1/Carousel1Iris.vue'
import Products1Iris from '@/Components/CMS/Webpage/Products1/Products1Iris.vue'


const components: Record<string, Component> = {
    'footer-1': Footer1Iris,
    'header-1': Header1Iris,
    'header-2': Header2Iris,
    'top-bar-1': Topbar1Iris,
    'top-bar-2': Topbar2Iris,
    'top-bar-3': Topbar3Iris,
    'menu-1': Menu1Workshop,
    'banner': WowsbarBannerIris,
    'bento-grid-1': BentoGridIris,
    'bricks': GalleryIris,
    'cta1': CTAIris,
    'cta2': CTA2Iris,
    'cta3': CTA3Iris,
    'iframe': IframeIris,
    'images': ImageIris,
    'overview_aurora': OverviewIris,
    'script': ScriptIris,
    'text': TextContentIris,
    'cta_aurora_1': CtaAurora1Iris,
    'overview_2': Overview2Iris,
    'text-column': TextColumnIris,
    'pricing': Pricing,
    'top-bar-1-fulfilment': Topbar1FulfilmentIris,
    'top-bar-2-fulfilment': Topbar2FulfilmentIris,
    'top-bar-3-fulfilment': Topbar3FulfilmentIris,
    'button' : ButtonIris,
    'column-layout-2': ColumnIris,
    'disclosure': DisclosureIris,
    'family-1' : FamilyIris1,
    'families-1' : FamilyIris1,
    'product-1' : ProductIris1,
    'products-1' : Products1Iris,
    'timeline': Timeline,
    'step-1' : Step2Iris,
    'departments' : Department1Iris,
    'department-1' : Department1Iris,
    'carousel-1' : Carousel1Iris,
    'sub-department-1' : SubDepartments1Iris,
    'sub-departments-1' : SubDepartments1Iris,
}


export const getIrisComponent = (componentName: string) => {
    return components[componentName] ?? NotFoundComponent
}
