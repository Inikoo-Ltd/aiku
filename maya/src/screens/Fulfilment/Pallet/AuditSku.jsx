import React, {useContext, useEffect, useState, useRef} from 'react';
import {View, ActivityIndicator, FlatList, TouchableOpacity, Text } from 'react-native';
import globalStyles from '@/globalStyles';
import request from '@/src/utils/Request';
import {ALERT_TYPE, Toast} from 'react-native-alert-notification';
import {AuthContext} from '@/src/components/Context/context';
import Empty from '@/src/components/Empty'

const AuditPallet = ({navigation, route}) => {
    const {organisation, warehouse} = useContext(AuthContext);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const {id, pallet_id} = route.params;
  console.log('sdsdsss',id, pallet_id)
    const fetchData = async () => {
        request({
            urlKey: 'get-pallet-audit',
            args: [organisation.id, warehouse.id, pallet_id, id],
            onSuccess: response => {
                console.log(response)
                setData(response.data);
                setLoading(false);
            },
            onFailed: error => {
                console.log(error);
                Toast.show({
                    type: ALERT_TYPE.DANGER,
                    title: 'Error',
                    textBody: error.detail?.message || 'Failed to fetch data',
                });
                setLoading(false);
            },
        });
    };

    useEffect(() => {
        fetchData();
    }, [id]);

    if (loading) {
        return (
            <View className="flex-1 items-center justify-center bg-gray-100">
                <ActivityIndicator size="large" color="#4F46E5" />
            </View>
        );
    }

    return (
      <View>
        <FlatList
          data={data?.editDeltas?.new_stored_items || []} // Ens
          keyExtractor={(item) => item.stored_item_id.toString()}
          showsVerticalScrollIndicator={false}
          ListEmptyComponent={(
            <Empty />
          )}
          renderItem={({ item }) => (
            <GroupItem item={item} navigation={navigation}  />
          )}
        />
      </View>
    );
};


const GroupItem = ({ item, navigation }) => {
  return (
    <TouchableOpacity
      style={[
        globalStyles.list.card,
        globalStyles.list.activeCard,
      ]}
      activeOpacity={0.7}
    >
      <View style={globalStyles.list.container}>
        <View style={globalStyles.list.textContainer}>
          <Text style={globalStyles.list.title}>{item.name}</Text>
          <Text style={globalStyles.list.description}>{item.code || 'No code available'}</Text>
        </View>
      </View>
    </TouchableOpacity>
  );
};

export default AuditPallet;
