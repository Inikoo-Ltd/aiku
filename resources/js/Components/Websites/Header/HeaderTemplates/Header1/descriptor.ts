export const data = {
    key : 'header1',
    logo : null,
    headerText : 'FAIRLY TRADING WHOLESALE GIFTS SINCE 1995',
    chip_text : 'Become a Gold Reward Member',
    chip_background_color : '#57534E'
}


export const bluprintForm = [
    {
        name : 'Title',
        key : 'headerText',
        type : 'editor',
        props_data : {
            type : 'basic',
            toogle : ['bold', 'fontSize', 'italic','underline','link','highlight','color','undo','redo','clear']
        }
    },
    {
        name : 'Chip',
        key : 'chip_text',
        type : 'editor',
        props_data : {
            type : 'basic',
            toogle : ['bold', 'fontSize', 'italic','underline','link','highlight','color','undo','redo','clear']
        }
    },
    {
        name : 'Logo',
        key : 'logo',
        type : 'upload_image'
    },
]