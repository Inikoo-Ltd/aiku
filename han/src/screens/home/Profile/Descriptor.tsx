export default {
    columns:  [
        {
            title: 'Name',
            dataIndex: 'contact_name',
            type:'text',
            formItemProps: {
              rules: { required: false },
            },
            fieldProps: {
              placeholder : ''
            },
            colProps: 24
          },
          {
            title: 'Email',
            dataIndex: 'email',
            type:'text',
            formItemProps: {
              rules: { required: false },
            },
            fieldProps: {
              placeholder : ''
            },
            colProps: 24
          },
        {
          title: 'About',
          dataIndex: 'about',
          type:'textArea',
          formItemProps: {
            rules: { required: false },
          },
          fieldProps: {
            placeholder : '',
            multiline : true,
          },
          colProps: 24
        },
      ]
  }