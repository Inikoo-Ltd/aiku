import { trans } from "laravel-vue-i18n"

export default {
	blueprint: [
		{
			name: "Bestseller 1",
			key: ["bestseller", "bestseller1"],
			replaceForm: [
				{
					name: "Icon",
					key: ["icon", "text"],
					replaceForm: [
						{
							key: ["color"],
							label: "Color",
							type: "color",
						},
						/* {
							key: ["fontSize"],
							label: "fontSize",
							type: "number",
							props_data: {
								suffix: "px",
							},
						}, */
					],
				},
				{
					name: "Text",
					key: ["text"],
					replaceForm: [
						{
							key: ["caption"],
							label: "Text",
							type: "text",
						},
						{
							key: ["properties", "text"],
							label: "Text style",
							type: "textProperty",
						},
					],
				},
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
							key: ["border"],
							useIn: ["desktop", "tablet", "mobile"],
							label: "Border",
							type: "border",
						},
					],
				},
			],
		},
		{
			name: "Bestseller 2",
			key: ["bestseller", "bestseller2"],
			replaceForm: [
				{
					name: "Icon",
					key: ["icon", "text"],
					type: "textProperty",
				},
				{
					name: "Text",
					key: ["text"],
					replaceForm: [
						{
							key: ["caption"],
							label: "Text",
							type: "text",
						},
						{
							key: ["properties", "text"],
							label: "Text style",
							type: "textProperty",
						},
					],
				},
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
							key: ["border"],
							useIn: ["desktop", "tablet", "mobile"],
							label: "Border",
							type: "border",
						},
					],
				},
			],
		},
		{
			name: "Bestseller 3",
			key: ["bestseller", "bestseller3"],
			replaceForm: [
				{
					name: "Icon",
					key: ["icon", "text"],
					type: "textProperty",
				},
				{
					name: "Text",
					key: ["text"],
					replaceForm: [
						{
							key: ["caption"],
							label: "Text",
							type: "text",
						},
						{
							key: ["properties", "text"],
							label: "Text style",
							type: "textProperty",
						},
					],
				},
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
							key: ["border"],
							useIn: ["desktop", "tablet", "mobile"],
							label: "Border",
							type: "border",
						},
					],
				},
			],
		},
	],
}
