import React, { useContext, useEffect, useState, useRef, useMemo } from 'react';
import {
  SafeAreaView,
  View,
  TouchableOpacity,
  ActivityIndicator,
} from 'react-native';
import BottomTabs from '@/src/components/BottomTabs';
import { DeliveryProvider, useDelivery } from '@/src/components/Context/delivery';
import Menu from '@/src/components/Menu';
import { AuthContext } from '@/src/components/Context/context';
import PalletInDeliveries from '@/src/screens/Fulfilment/Delivery/PalletInDeliveries';
import ShowFulfilmentDelivery from '@/src/screens/Fulfilment/Delivery/ShowFulfilmentDelivery';
import request from '@/src/utils/Request';
import { ALERT_TYPE, Toast } from 'react-native-alert-notification';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { faBars } from '@/private/fa/pro-regular-svg-icons';
import { faPallet, faTachometerAlt } from '@/private/fa/pro-regular-svg-icons';

const DeliveryStackScreen = ({ navigation, route }) => {
  const { organisation, warehouse } = useContext(AuthContext);
  const { data, setData } = useDelivery();
  const menuRef = useRef(null);
  const { id } = route.params;

  // State untuk loading
  const [loading, setLoading] = useState(true);

  const fetchData = async () => {
    setLoading(true); // Set loading sebelum fetch
    request({
        urlKey: 'get-delivery',
        args: [organisation.id, warehouse.id, id],
        onSuccess: response => {
          setData(response.data)
          setLoading(false);
        },
        onFailed : error => {
          console.error('Fetch data error:', error);
          Toast.show({
            type: ALERT_TYPE.DANGER,
            title: 'Error',
            textBody: error.detail?.message || 'Failed to fetch data',
          });
        }
      })
  };

  useEffect(() => {
    fetchData();
  }, []);


  // Perbarui title setelah data diperbarui
  useEffect(() => {
    navigation.setOptions({
      title: data ? `Delivery ${data.reference}` : 'Delivery Details',
    });
  }, [navigation, data]);

  const setStatusDelivery = state => {
    request({
      urlKey: 'set-delivery-' + state,
      method: 'patch',
      args: [data?.id],
      data: { state: state },
      onSuccess: (response) => {
        fetchData();
        Toast.show({
          type: ALERT_TYPE.SUCCESS,
          title: 'Success',
          textBody: 'Successfully updated pallet to ' + state,
        });
      },
      onFailed: error => {
        console.log(error)
        Toast.show({
          type: ALERT_TYPE.DANGER,
          title: 'Error',
          textBody: error.detail?.message || 'Failed to update data',
        });
      },
    });
  };

  const onPressMenu = event => {
    switch (event) {
      case 'received':
      case 'booking-in':
      case 'booked-in':
        setStatusDelivery(event);
        break;
      case 'cancel':
        console.log('Option Cancel selected');
        break;
      default:
        console.log('Unknown option selected');
    }
  };

  const TabArr = useMemo(() => [
    {
      route: 'delivery-showcase',
      label: 'Showcase',
      icon: faTachometerAlt,
      component: props => (
        <ShowFulfilmentDelivery
          {...props}
          navigation={navigation}
          route={route}
          handleRefresh={fetchData}
          onChangeState={onPressMenu}
        />
      ),
    },
    {
      route: 'pallets-in-delivery',
      label: 'Pallets',
      icon: faPallet,
      component: props => (
        <PalletInDeliveries
          {...props}
          navigation={navigation}
          route={route}
          handleRefresh={fetchData}
          onChangeState={onPressMenu}
        />
      ),
    },
  ], [navigation, route]);
  

  

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
  return (
    <DeliveryProvider>
      <DeliveryStackScreen navigation={navigation} route={route} />
    </DeliveryProvider>
  );
};

export default RenderStackScreen;
