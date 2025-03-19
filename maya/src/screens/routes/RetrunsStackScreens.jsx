import React, {useEffect, useContext, useRef, useMemo} from 'react';
import {
  SafeAreaView,
  ActivityIndicator,
  View,
  TouchableOpacity,
} from 'react-native';
import BottomTabs from '@/src/components/BottomTabs';
import {ReturnProvider, useReturn} from '@/src/components/Context/return';
import {AuthContext} from '@/src/components/Context/context';
import Menu from '@/src/components/Menu';
import PalletsInReturn from '@/src/screens/Fulfilment/Return/PalletsInReturn';
import ShowFulfilmentReturn from '@/src/screens/Fulfilment/Return/ShowFulfilmentReturn';
import ItemsInReturn from '@/src/screens/Fulfilment/Return/ItemsInReturn';
import request from '@/src/utils/Request';
import {ALERT_TYPE, Toast} from 'react-native-alert-notification';

import {
  faPallet,
  faTachometerAlt,
  faBars,
  faNarwhal
} from '@/private/fa/pro-regular-svg-icons';
import {FontAwesomeIcon} from '@fortawesome/react-native-fontawesome';

const ReturnStackScreen = ({navigation, route}) => {
  const dataReturn = useReturn();
  const {organisation, warehouse} = useContext(AuthContext);
  const id = route.params?.id;
  const menuRef = useRef(null);

/*   const getFilteredActions = () => {
    const state = dataReturn?.data?.state;
    if (state === 'confirmed') return [{id: 'picking', title: 'picking'}];
    if (state === 'picking') return [{id: 'picked', title: 'picked'}];
    if (state === 'picked') return [{id: 'dispatch', title: 'dispatched'}];
    return [];
  };
 */
  const getDataFromServer = async () => {
    request({
      urlKey: 'get-return',
      args: [organisation.id, warehouse.id, id],
      onSuccess: response => {
        dataReturn?.setData(response?.data); // Ensure data is set
      },
      onFailed: error => {
        Toast.show({
          type: ALERT_TYPE.DANGER,
          title: 'Error',
          textBody: error?.detail?.message || 'Failed to fetch data',
        });
      },
    });
  };

  const onPressMenu = event => {
    switch (event) {
      case 'picking':
        setStatusReturn(event);
        break;
      case 'picked':
        setStatusReturn(event);
        break;
      case 'dispatch':
        setStatusReturn(event);
        break;
      default:
        console.log('Unknown option selected');
    }
  };

  const setStatusReturn = state => {
    if (!dataReturn?.data?.id) return;
    request({
      urlKey: `set-return-${state}`,
      method: 'patch',
      args: [dataReturn.data.id],
      data: {state},
      onSuccess: response => {
        getDataFromServer();
        Toast.show({
          type: ALERT_TYPE.SUCCESS,
          title: 'Success',
          textBody: `Successfully updated return to ${state}`,
        });
      },
      onFailed: error => {
        console.log(error);
        Toast.show({
          type: ALERT_TYPE.DANGER,
          title: 'Error',
          textBody: error.detail?.message || 'Failed to update data',
        });
      },
    });
  };


  const TabOptions = [
    {
      route: 'pallets-in-return',
      label: 'Pallet',
      icon: faPallet,
      component: props => (
        <PalletsInReturn
          {...props}
          navigation={navigation}
          route={route}
          handleRefresh={getDataFromServer}
          onChangeState={onPressMenu}
        />
      ),
    },
    {
      route: 'items-in-return',
      label: 'SKUs',
      icon: faNarwhal,
      component: props => (
        <ItemsInReturn
          {...props}
          navigation={navigation}
          route={route}
          handleRefresh={getDataFromServer}
          onChangeState={onPressMenu}
        />
      ),
    },
    {
      route: 'return-showcase',
      label: 'Showcase',
      icon: faTachometerAlt,
      component: props => (
        <ShowFulfilmentReturn
          {...props}
          navigation={navigation}
          route={route}
          handleRefresh={getDataFromServer}
          onChangeState={onPressMenu}
        />
      ),
    },
  ];

  const filteredTabOptions = useMemo(() => {
    if (dataReturn?.data?.type === 'pallet') {
      return TabOptions.filter(tab => tab.route !== 'items-in-return');
    } 
    if (dataReturn?.data?.type === 'stored_item') {
      return TabOptions.filter(tab => tab.route !== 'pallets-in-return');
    }
    return TabOptions;
  }, [dataReturn?.data?.type]);
  
  

  useEffect(() => {
    getDataFromServer();
  }, []);

  useEffect(() => {
    navigation.setOptions({
      title: dataReturn?.data
        ? `Return ${dataReturn.data.reference}`
        : 'Return Details',
     /*  headerRight: () => (
        <View className="px-4">
          <Menu
            ref={menuRef}
            onPressAction={({nativeEvent}) => onPressMenu(nativeEvent)}
            button={
              <TouchableOpacity onPress={() => menuRef?.current?.menu.show()}>
                <FontAwesomeIcon icon={faBars} />
              </TouchableOpacity>
            }
            actions={getFilteredActions()}
          />
        </View>
      ), */
    });
  }, [dataReturn.data]);

  return (
    <SafeAreaView style={{flex: 1}}>
      {dataReturn?.data ? (
        <BottomTabs tabArr={filteredTabOptions} />
      ) : (
        <View style={{flex: 1, justifyContent: 'center', alignItems: 'center'}}>
          <ActivityIndicator size="large" color='#4F46E5' />
        </View>
      )}
    </SafeAreaView>
  );
};

const RenderStackScreen = ({navigation, route}) => {
  return (
    <ReturnProvider>
      <ReturnStackScreen navigation={navigation} route={route} />
    </ReturnProvider>
  );
};

export default RenderStackScreen;
