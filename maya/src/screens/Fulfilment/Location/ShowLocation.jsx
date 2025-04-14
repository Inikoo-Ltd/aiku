import React, {useContext, useEffect, useState} from 'react';
import {View, ScrollView, ActivityIndicator} from 'react-native';
import {AuthContext} from '@/src/components/Context/context';
import request from '@/src/utils/Request';
import {ALERT_TYPE, Toast} from 'react-native-alert-notification';
import {Card} from '@/src/components/ui/card';
import {Heading} from '@/src/components/ui/heading';
import {Text} from '@/src/components/ui/text';
import Description from '@/src/components/Description';
import Barcode from 'react-native-barcode-svg';
import {Center} from '@/src/components/ui/center';

const ShowLocation = ({navigation, route, data, handleRefresh}) => {
  const schema = [
    {
      label: 'code',
      value: data?.code,
    },
    {label: 'Status', value: data?.status},
    {label: 'Stock Value', value: data?.stock_value},
    {
      label: 'Empty',
      value: data?.is_empty,
    },
    {
      label: 'Max weight',
      value: data?.max_weight ? data.max_weight.toString() : '0',
    },
    {
      label: 'Max volume',
      value: data?.max_volume ? data.max_volume.toString() : '0',
    },
  ];


  if (!data) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-100">
        <Text className="text-lg text-gray-600">No Data Available</Text>
      </View>
    );
  }

  return (
    <ScrollView className="flex-1 bg-gray-50 p-4">

      <Card>
        <Center>
          <Barcode
            value={data?.slug}
            format="CODE128"
            maxWidth={250}
            height={60}
          />
        </Center>
        <Center>
          <Heading>{data?.slug}</Heading>
        </Center>
      </Card>

      <Card className="bg-white p-6 rounded-xl shadow-md mt-4">
        <Heading>Details</Heading>
        <Description schema={schema} />
      </Card>
    </ScrollView>
  );
};

export default ShowLocation;
