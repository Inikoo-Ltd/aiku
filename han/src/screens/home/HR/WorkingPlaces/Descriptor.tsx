export default {
    columns:  [
        {
          title: 'Name',
          dataIndex: 'name',
          type:'text',
          formItemProps: {
            rules: { required: true },
          },
          fieldProps: {
            placeholder : ''
          },
          colProps: 24
        },
        {
          title: 'Type',
          dataIndex: 'type',
          type:'text',
          formItemProps: {
            rules: { required: true },
          },
          colProps:24
        },
        {
            title: 'Country',
            dataIndex: 'country',
            type:'text',
            formItemProps: {
              rules: { required: true },
            },
            colProps: 24
          },
          {
            title: 'City',
            dataIndex: 'city',
            type:'text',
            formItemProps: {
              rules: { required: true },
            },
            colProps: 12
          },
          {
            title: 'Postal',
            dataIndex: 'postal',
            type:'text',
            formItemProps: {
              rules: { required: true },
            },
            colProps:12
          },
          {
            title: 'Address',
            dataIndex: 'address',
            type:'text',
            formItemProps: {
              rules: { required: true },
            },
            colProps: 12
          },
          {
            title: 'Address Line 2',
            dataIndex: 'address_line_2',
            type:'text',
            formItemProps: {
              rules: { required: true },
            },
            colProps: 12
          },
          {
            title: 'Address Line 3',
            dataIndex: 'address_line_3',
            type:'text',
            formItemProps: {
              rules: { required: true },
            },
            colProps:12
          },
          {
            title: 'Province',
            dataIndex: 'province',
            type:'text',
            formItemProps: {
              rules: { required: true },
            },
            colProps: 12
          },
      ]
  }