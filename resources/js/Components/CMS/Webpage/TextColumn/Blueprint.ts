export default {
    blueprint: [
      {
        name: "Layout",
        key: ["value","layout_type"],
        type: "layout_type",
      },
      {
        name: "Properties",
        key: ["container", "properties"],
        replaceForm: [
          {
            key: ["background"],
            label:"Background",
            type: "background",
          },
          {
            key: ["gap"],
            label:"Gap",
            type: "numberCss",
          },
          {
            key: ["padding"],
            label:"Padding",
            type: "padding",
          },
          {
            key: ["margin"],
            label:"Margin",
            type: "margin",
          },
          {
            key: ["border"],
            label:"Border",
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
    ]
  }