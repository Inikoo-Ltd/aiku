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
			name: "Properties",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["dimension"],
					label: "Dimension",
					type: "dimension",
					props_data: {},
				},
				{
					key: ["padding"],
					label: "Padding",
					type: "padding",
					props_data: {},
				},
				{
					key: ["margin"],
					label: "Margin",
					type: "margin",
					props_data: {},
				},
				{
					key: ["border"],
					label: "Border",
					type: "border",
					props_data: {},
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
