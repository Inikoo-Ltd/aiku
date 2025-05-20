import { trans } from "laravel-vue-i18n"

export default {
	blueprint: [
		{
			name: "Column-1",
			key: ["column_1"],
			type: "column-layout"
		},
		{
			name: "Column-2",
			key: ["column_2"],
			type: "column-layout"
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
