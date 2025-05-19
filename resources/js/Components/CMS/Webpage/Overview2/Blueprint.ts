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
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["dimension"],
					useIn : ["desktop", "tablet", "mobile"],
					label: "Dimension",
					type: "dimension",
					props_data: {},
				},
				{
					key: ["padding"],
					useIn : ["desktop", "tablet", "mobile"],
					label: "Padding",
					type: "padding",
					props_data: {},
				},
				{
					key: ["margin"],
					useIn : ["desktop", "tablet", "mobile"],
					label: "Margin",
					type: "margin",
					props_data: {},
				},
				{
					key: ["border"],
					useIn : ["desktop", "tablet", "mobile"],
					label: "Border",
					type: "border",
					props_data: {},
				},
				{
                    key: ["shadow"],
                    label : "Shadow",
					useIn : ["desktop", "tablet", "mobile"],
                    type: "shadow",
                },
                {
                    key: ["shadowColor"],
                    label : "Shadow Color",
					useIn : ["desktop", "tablet", "mobile"],
                    type: "color",
                },
			],
		},
	],
}
