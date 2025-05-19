import { trans } from "laravel-vue-i18n"

export default {
	blueprint: [
		{
			name: "Image",
			key: ["image"],
			replaceForm: [
				{
					key : ['source'],
					label: "Image",
					type: "upload_image",
				},
				{
					key : ['alt'],
					label: "Alternate Text",
					type: "text",
				},
				{
					key: ["properties", "object_fit"],
					label: "Object Image",
					useIn : ["desktop", "tablet", "mobile"],
					type: "select",
					props_data: {
						placeholder: "Object",
						options: [
							{
								label: "contain",
								value: "contain",
							},
							{
								label: "cover",
								value: "cover",
							},
							{
								label: "none",
								value: "none",
							},
							{
								label: "scale-down",
								value: "scale-down",
							},
						],
					},
				},
				{
					key: ["properties", "object_position"],
					label: "Object Position",
					useIn : ["desktop", "tablet", "mobile"],
					type: "select",
					props_data: {
						placeholder: "Object",
						options: [
							{
								label: "Bottom",
								value: "bottom",
							},
							{
								label: "Center",
								value: "center",
							},
							{
								label: "Left",
								value: "left",
							},
							{
								label: "Right",
								value: "right",
							},
							{
								label: "Top",
								value: "top",
							},
							{
								label: "Left Bottom",
								value: "left bottom",
							},
							{
								label: "Left Top",
								value: "left top",
							},
							{
								label: "Right Bottom",
								value: "right bottom",
							},
							{
								label: "Right Top",
								value: "right top",
							},
						],
					},
				},
				{
					key: ["attributes", "fetchpriority"],
					label: trans("Fetch Priority"),
					information: trans(
						"Priority of the image to loaded. Higher priority images are loaded first (good for LCP)."
					),
					type: "select",
					props_data: {
						placeholder: trans("Priority"),
						options: [
							{
								label: trans("High"),
								value: "high",
							},
							{
								label: trans("Low"),
								value: "low",
							},
						],
					},
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
		{
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					useIn : ["desktop", "tablet", "mobile"],
					label :"Background",
					type: "background",
	
				},
				{
					key: ["padding"],
					useIn : ["desktop", "tablet", "mobile"],
					label : "Padding",
					type: "padding",
					
				},
				{
					key: ["margin"],
					useIn : ["desktop", "tablet", "mobile"],
					label : "Margin",
					type: "margin",
					
				},
				{
					key: ["border"],
					useIn : ["desktop", "tablet", "mobile"],
					label : "Border",
					type: "border",
					
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
