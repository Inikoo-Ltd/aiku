export const blueprintCard = {
	blueprint: [

				{
					key: ["visible"],
					type: "switch",
					label: "Visible",
				},
				{
					key: ["text"],
					type: "editorhtml",
					name: "Text",
				},
				{
					name: "Image",
					key: ["image"],
					replaceForm: [
						{
							key: ["source"],
							label: "Image",
							type: "image-cropped",
							props_data: {
								stencilProps: {
									aspectRatio: [null],
									movable: true,
									scalable: true,
									resizable: true,
								},
							},
						},
						{
							key: ["alt"],
							label: "Alternate Text",
							type: "text",
						},
						{
							key: ["link"],
							label: "Link",
							type: "link",
						},
						{
							key: ["container", "properties", "dimension"],
							label: "Dimension",
							type: "dimension",
							useIn: ["desktop", "tablet", "mobile"],
						},
					],
				},
				{
					key: ["button"],
					name: "Button",
					replaceForm: [
						{
							key: ["link"],
							label: "Link",
							type: "link",
						},
						{
							key: ["text"],
							label: "Text",
							type: "text",
						},
						{
							key: ["container", "properties", "text"],
							type: "textProperty",
							useIn: ["desktop", "tablet", "mobile"],
						},
						{
							key: ["container", "properties", "background"],
							label: "Background",
							type: "background",
							useIn: ["desktop", "tablet", "mobile"],
						},
						{
							key: ["container", "properties", "margin"],
							label: "Margin",
							type: "margin",
							useIn: ["desktop", "tablet", "mobile"],
						},
						{
							key: ["container", "properties", "padding"],
							label: "Padding",
							type: "padding",
							useIn: ["desktop", "tablet", "mobile"],
						},
						{
							key: ["container", "properties", "border"],
							label: "Border",
							type: "border",
							useIn: ["desktop", "tablet", "mobile"],
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
						{
							key: ["border"],
							useIn: ["desktop", "tablet", "mobile"],
							label: "Border",
							type: "border",
						},
						{
							key: ["shadow"],
							label: "Shadow",
							type: "shadow",
							useIn: ["desktop", "tablet", "mobile"],
						},
						{
							key: ["shadowColor"],
							label: "Shadow Color",
							type: "color",
							useIn: ["desktop", "tablet", "mobile"],
						},
					],
				},
			],
		}


export const Mainbluprint = {
    blueprint: [
        {
            key: ["cards"],
            name: "Cards",
            type: "array-data",
			defaultValue : [],
            props_data: {
                blueprint: blueprintCard.blueprint,
                order_name: "card",
                can_drag: true,
                can_delete: true,
                can_add: true,
                new_value_data: {
                    card: {
                        text: "<h2>STARTER<br>GIFT SET</h2>",
                        image: null
                    }
                }
            }
        }
    ]
}