import {
	AROMA_ONLY_SECTIONS,
	aboutSection,
	customisationSection,
	faqSection,
	labelingGuideSection,
	marketingSection,
	showsAromaSections,
	storageSection,
} from "@/Components/CMS/Webpage/Family2ExtraDescription/tabSections"

export default (data?: any) => ({
	blueprint: [
		aboutSection(),
		marketingSection(),
		faqSection(),
		customisationSection(),
		labelingGuideSection(),
		storageSection(),
		{
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					useIn: ["desktop", "tablet", "mobile"],
					label: "Background",
					type: "background",
				},
				{
					key: ["padding"],
					useIn: ["desktop", "tablet", "mobile"],
					label: "Padding",
					type: "padding",
				},
				{
					key: ["margin"],
					useIn: ["desktop", "tablet", "mobile"],
					label: "Margin",
					type: "margin",
				},
			],
		},
	].filter(
		(section: any) =>
			!AROMA_ONLY_SECTIONS.includes(section?.key?.[0]) || showsAromaSections(data)
	),
})
