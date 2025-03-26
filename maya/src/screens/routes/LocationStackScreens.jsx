import React, { useContext, useEffect, useState, useCallback, useMemo } from 'react';
import {
  SafeAreaView,
  View,
  ActivityIndicator,
} from 'react-native';
import BottomTabs from '@/src/components/BottomTabs';
import { AuthContext } from '@/src/components/Context/context';
import ShowLocation from '@/src/screens/Fulfilment/Location/ShowLocation';
import PalletsInLocation from '@/src/screens/Fulfilment/Location/PallestInLocations'
import request from '@/src/utils/Request';
import { ALERT_TYPE, Toast } from 'react-native-alert-notification';
import { faPallet, faTachometerAlt } from '@/private/fa/pro-regular-svg-icons';
import { faInventory, faPalletAlt } from '@/private/fa/pro-light-svg-icons';

const LocationStackScreens = ({ navigation, route }) => {
  const { organisation, warehouse } = useContext(AuthContext);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const { id } = route.params;

  // Gunakan useCallback agar fungsi ini tidak berubah setiap render
  const fetchData = useCallback(async () => {
    setLoading(true);
    request({
      urlKey: 'get-location',
      args: [organisation.id, warehouse.id, id],
      onSuccess: response => {
        setData(response);
        setLoading(false);
      },
      onFailed: error => {
        setLoading(false);
        Toast.show({
          type: ALERT_TYPE.DANGER,
          title: 'Error',
          textBody: error.detail?.message || 'Failed to fetch data',
        });
      },
    });
  }, [organisation.id, warehouse.id, id]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  useEffect(() => {
    navigation.setOptions({
      title: data ? `Location ${data.code}` : 'Location',
    });
  }, [navigation, data]);

  const TabArr = useMemo(() => [
    {
      route: "showcase-location",
      label: 'Showcase',
      icon: faInventory,
      component: props => (
        <ShowLocation
          {...props}
          navigation={navigation}
          route={route}
          data={data}
          handleRefresh={fetchData} 
        />
      ),
    },
    {
      route: "pallet-in-location",
      label: 'Showcase',
      icon: faPalletAlt,
      component: props => (
        <PalletsInLocation
          {...props}
          navigation={navigation}
          route={route}
          data={data}
          handleRefresh={fetchData} 
        />
      ),
    },
  ], [navigation, route, data, fetchData]);

  return (
    <SafeAreaView style={{ flex: 1 }}>
      {loading ? (
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
          <ActivityIndicator size="large" color="#4F46E5" />
        </View>
      ) : data ? (
        <BottomTabs tabArr={TabArr} />
      ) : (
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
          <ActivityIndicator size="large" color="#4F46E5" />
        </View>
      )}
    </SafeAreaView>
  );
};

const RenderStackScreen = ({ navigation, route }) => {
  return <LocationStackScreens navigation={navigation} route={route} />;
};

export default RenderStackScreen;
