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
			name: "Properties",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label :"Background",
					type: "background",
					
				},
				{
					key: ["padding"],
					label : "Padding",
					type: "padding",
					
				},
				{
					key: ["margin"],
					label : "Margin",
					type: "margin",
					
				},
				{
					key: ["border"],
					label : "Border",
					type: "border",
					
				},
				{
                    key: ["shadow"],
                    label : "Shadow",
                    type: "shadow",
                },
                {
                    key: ["shadowColor"],
                    label : "Shadow Color",
                    type: "color",
                },
			],
		},
	],
}
