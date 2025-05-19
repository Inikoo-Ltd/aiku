export default {
	blueprint: [
		{
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label: "Background",
					type: "background",
				},
				{
					key: ["padding"],
					label: "Padding",
					type: "padding",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["margin"],
					label: "Margin",
					type: "margin",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["border"],
					label: "Border",
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
		{
			name: "Button",
			key: ["button"],
			editGlobalStyle : "button",
			replaceForm: [
				{
					key: ["link"],
					label : "Link",
					type: "link",
				},
				{
					key: ["text"],
					label : "Text",
					type: "text",
				},
				{
					key: ["container",'properties',"text"],
					type: "textProperty",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container",'properties',"background"],
					label : "Background",
					type: "background",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container",'properties',"margin"],
					label : "Margin",
					type: "margin",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container",'properties',"padding"],
					label : "Padding",
					type: "padding",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container",'properties',"border"],
					label : "Border",
					type: "border",
					useIn : ["desktop", "tablet", "mobile"],
				},
			],
		
		},
	],
}
