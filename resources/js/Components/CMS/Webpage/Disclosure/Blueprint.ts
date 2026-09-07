import { ctrans } from "@/Composables/useTrans"
import { trans } from "laravel-vue-i18n"

export default {
	blueprint: [
		{
			label: "# Id ",
			key: ["id"],
			type: "text",
			information : 'id selector is used to select one unique element!'
		},
		{
			name: "Settings",
			key: ["value"],
			type: "disclosure",
		},
		{
			name: "SEO",
			key: ["seo"],
			replaceForm: [
				{
					key: ["is_faq"],
					label: "Frequently asked questions?",
					type: "switch",
					information: ctrans('Add FAQPage structured data (schema.org) for this block. Only enable it when every item is a real question and answer.'),
				},
			],
		},
		{
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label :"Background",
					type: "background",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["padding"],
					label : "Padding",
					type: "padding",
					useIn : ["desktop", "tablet", "mobile"],
					
				},
				{
					key: ["margin"],
					label : "Margin",
					type: "margin",
					useIn : ["desktop", "tablet", "mobile"],
					
				},
				{
					key: ["border"],
					label : "Border",
					type: "border",
					useIn : ["desktop", "tablet", "mobile"],
					
				},
				{
                    key: ["shadow"],
                    label : "Shadow",
                    type: "shadow",
					useIn : ["desktop", "tablet", "mobile"],
                },
                {
                    key: ["shadowColor"],
                    label : "Shadow Color",
                    type: "color",
					useIn : ["desktop", "tablet", "mobile"],
                },
			],
		},
	],
}
