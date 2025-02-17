import React, {useContext} from 'react';
import {View, TouchableOpacity, Text} from 'react-native';
import dayjs from 'dayjs';
import {AuthContext} from '@/src/components/Context/context';
import BaseList from '@/src/components/BaseList';
import globalStyles from '@/globalStyles';

const DeliveryNotes = ({navigation}) => {
  const {organisation, warehouse} = useContext(AuthContext);

  return (
    <View style={globalStyles.container}>
      <BaseList
        navigation={navigation}
        urlKey="get-delivery-notes"
        args={[organisation.id, warehouse.id]}
        listItem={({item, navigation}) => (
          <GroupItem item={item} navigation={navigation} />
        )}
      />
    </View>
  );
};

const GroupItem = ({item, navigation}) => {
  const formattedDate = item.date
    ? dayjs(item.date).format('MMMM D[,] YYYY') // Example: "August 27th, 2019"
    : 'No Date Available';

  return (
    <TouchableOpacity
      style={[
        globalStyles.list.card,
        {
          flexDirection: 'row',
          justifyContent: 'space-between',
          alignItems: 'center',
          padding: 10,
        },
      ]}
      activeOpacity={0.7}
      onPress={() => navigation.navigate('show-delivery-note', {id: item.id})}>
      {/* Left section (Text Content) */}
      <View style={{flex: 1}}>
        <Text style={globalStyles.list.title}>{formattedDate}</Text>
        <Text style={globalStyles.list.description}>
          {item.reference || '[No reference available]'} - {item.customer_name}
        </Text>
      </View>

      {/* Right section (Weight) */}
      <View style={{marginLeft: 10}}>
        <Text style={{fontWeight: 'bold'}}>{item.weight} kg</Text>
      </View>
    </TouchableOpacity>
  );
};

export default DeliveryNotes;
