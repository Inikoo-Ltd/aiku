import { trans } from "laravel-vue-i18n"

export default {
	blueprint: [
		{
			name: "Bestseller 1",
			key: ["bestseller", "bestseller1"],
			replaceForm: [
				{
					name: "Icon",
					key: ["icon"],
					replaceForm: [
						{
							key: [ "text","color"],
							label: "Color",
							type: "color",
						},
						{
							key: ["use_icon"],
							label: "Use Icon",
							type: "switch",
							props_data: {
								defaultValue: true,
							}
						},
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
					key: ["icon"],
					replaceForm: [
						{
							key: [ "text","color"],
							label: "Color",
							type: "color",
						},
						{
							key: ["use_icon"],
							label: "Use Icon",
							type: "switch",
							props_data: {
								defaultValue: true,
							}
						},
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
			name: "Bestseller 3",
			key: ["bestseller", "bestseller3"],
			replaceForm: [
				{
					name: "Icon",
					key: ["icon"],
					replaceForm: [
						{
							key: [ "text","color"],
							label: "Color",
							type: "color",
						},
						{
							key: ["use_icon"],
							label: "Use Icon",
							type: "switch",
							props_data: {
								defaultValue: true,
							}
						},
					],
				},
				{
					key: ["use_icon"],
					label: "Use Icon",
					type: "switch",
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
