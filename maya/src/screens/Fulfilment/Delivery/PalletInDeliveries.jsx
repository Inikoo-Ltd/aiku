import React, {useContext, useRef, useState, memo, useCallback, useEffect} from 'react';
import {
  View,
  TouchableOpacity,
  Text,
  Animated,
  PanResponder,
} from 'react-native';
import {useForm, Controller} from 'react-hook-form';
import {AuthContext} from '@/src/components/Context/context';
import BaseList from '@/src/components/BaseList';
import globalStyles from '@/globalStyles';
import {ALERT_TYPE, Toast} from 'react-native-alert-notification';
import {Button, ButtonText, ButtonSpinner} from '@/src/components/ui/button';
import {Input, InputField} from '@/src/components/ui/input';
import Modal from '@/src/components/Modal';
import request from '@/src/utils/Request';
import {useDelivery} from '@/src/components/Context/delivery';
import {getFilteredActionsDelivery} from '@/src/utils';
import SetStateButton from '@/src/components/SetStateButton';
import {Alert, AlertText} from '@/src/components/ui/alert';
import ModalMoveLocation from '@/src/components/MoveLocationModal';

import {FontAwesomeIcon} from '@fortawesome/react-native-fontawesome';
import {library} from '@fortawesome/fontawesome-svg-core';
import {
  faSeedling,
  faShare,
  faSpellCheck,
  faCheck,
  faCross,
  faCheckDouble,
  faWarehouseAlt,
  faPallet,
  faTimes,
  faCheckCircle
} from '@/private/fa/pro-light-svg-icons';
import {
  faFileInvoiceDollar,
  faInventory,
  faTimes as faTimesRegular,
  faHistory,
  faSave,
} from '@/private/fa/pro-regular-svg-icons';

library.add(
  faSeedling,
  faShare,
  faSpellCheck,
  faCheck,
  faCross,
  faCheckDouble,
  faWarehouseAlt,
  faPallet,
  faTimes,
  faFileInvoiceDollar,
);

const PalletInDeliveries = ({ navigation, route, onChangeState }) => {
  const { organisation, warehouse } = useContext(AuthContext);
  const { data, setData } = useDelivery();
  const { id } = route.params;
  const [totalPallets, setTotalPallets] = useState(0);

   useEffect(() => {
      setTotalPallets(
        data.number_pallets_state_not_received + data.number_pallets_state_booked_in + data.number_pallet_storing
      );
    }, [data]);

  return (
    <View style={globalStyles.container}>
      {data.state !== 'booked_in' ? (
        <SetStateButton
          progress={{
            value: totalPallets,
            size: 'lg',
            total: data.number_pallets,
            orientation: 'horizontal',
          }}
          button2={{
            size: 'md',
            action: 'primary',
            variant: 'link',
            style: { borderTopLeftRadius: 0, borderBottomLeftRadius: 0 },
            onPress: () => onChangeState(getFilteredActionsDelivery(data.state).id),
            text: 'Set to ' + getFilteredActionsDelivery(data.state).title,
          }}
        />
      ) : (
        <SetStateButton
          renderButton2={() => (
            <Alert action="success" variant="solid">
              <FontAwesomeIcon icon={faCheckCircle} color="green" />
              <AlertText>Already Booked In</AlertText>
            </Alert>
          )}
          progress={{
            value: totalPallets,
            size: 'lg',
            total: data.number_pallets,
            orientation: 'horizontal',
          }}
          button2={{
            size: 'md',
            action: 'primary',
            variant: 'link',
            style: { borderTopLeftRadius: 0, borderBottomLeftRadius: 0 },
            onPress: () => onChangeState(getFilteredActionsDelivery(data.state).id),
            text: 'Set to ' + getFilteredActionsDelivery(data.state).title,
          }}
        />
      )}

      <BaseList
        navigation={navigation}
        urlKey="get-pallets-delivery"
        args={[organisation.id, warehouse.id, id]}
        height={100}
        showTotalResults={()=>null}
        listItem={({ item, navigation }) => (
          <GroupItem item={item} navigation={navigation}/>
        )}
      />
    </View>
  );
};


const GroupItem = ({item: initialItem, navigation}) => {
  const [item, setItem] = useState(initialItem);
  const {data, setData} = useDelivery();
  const [showModalMovePallet, setShowModalMovePallet] = useState(false);
  const [loadingSave, setLoadingSave] = useState(false);
  const translateX = useRef(new Animated.Value(0)).current;
  const SWIPE_THRESHOLD = 60;
  const MAX_SWIPE = 100;
  /* const {control, handleSubmit, reset, setValue} = useForm({
    defaultValues: {
      location: '',
    },
  }); */

  const panResponder = useRef(
    PanResponder.create({
      onStartShouldSetPanResponder: () => true,
      onMoveShouldSetPanResponder: (_, gestureState) => Math.abs(gestureState.dx) > 5,
      onPanResponderMove: (_, gestureState) => {
        if (Math.abs(gestureState.dx) > 100) {
          translateX.setValue(
            Math.min(Math.max(gestureState.dx, -MAX_SWIPE), MAX_SWIPE),
          );
        }
      },
      onPanResponderRelease: (_, gestureState) => {
        if (gestureState.dx > SWIPE_THRESHOLD) {
          Animated.spring(translateX, {
            toValue: MAX_SWIPE,
            useNativeDriver: true,
          }).start();
        } else if (gestureState.dx < -SWIPE_THRESHOLD) {
          Animated.spring(translateX, {
            toValue: -MAX_SWIPE,
            useNativeDriver: true,
          }).start();
        } else {
          Animated.spring(translateX, {
            toValue: 0,
            useNativeDriver: true,
          }).start();
        }
      },
    }),
  ).current;

  const onNotReceived = () => {
    request({
      urlKey: 'set-pallet-not-received',
      method: 'patch',
      args: [item.id],
      data: {},
      onSuccess: response => {
        setItem(prevItem => ({
          ...prevItem,
          state: response.data.state,
          state_icon: response.data.state_icon,
        }));

        setData(prevItem => ({
          ...prevItem,
          number_pallets_state_not_received: prevItem.number_pallets_state_not_received + 1,
        }));

        Animated.spring(translateX, {
          toValue: 0,
          useNativeDriver: true,
        }).start();

        Toast.show({
          type: ALERT_TYPE.SUCCESS,
          title: 'Success',
          textBody: 'Updated pallet ' + item.reference,
        });
      },
      onFailed: error => {
        console.log(error);
        Toast.show({
          type: ALERT_TYPE.DANGER,
          title: 'Error',
          textBody:
            error.detail?.message ||
            'Failed to update pallet ' + item.reference,
        });
      },
    });
  };

  const onUndoNotReceived = () => {
    request({
      urlKey: 'undo-pallet-not-received',
      method: 'patch',
      args: [item.id],
      data: {},
      onSuccess: response => {
        setItem(prevItem => ({
          ...prevItem,
          state: response.data.state,
          state_icon: response.data.state_icon,
        }));

        setData(prevState => ({
            ...prevState,
            number_pallets_state_not_received:
                prevState.number_pallets_state_not_received - 1,
        }));
      

        Animated.spring(translateX, {
          toValue: 0,
          useNativeDriver: true,
        }).start();

        Toast.show({
          type: ALERT_TYPE.SUCCESS,
          title: 'Success',
          textBody: 'Updated pallet ' + item.reference,
        });
      },
      onFailed: error => {
        Toast.show({
          type: ALERT_TYPE.DANGER,
          title: 'Error',
          textBody:
            error.detail?.message ||
            'Failed to update pallet ' + item.reference,
        });
      },
    });
  };

  const onSubmitSetLocation = formData => {
    request({
      method: 'patch',
      urlKey: 'set-delivery-pallet-location',
      args: [item.id, formData.location],
      data: formData,
      onSuccess: response => {
        setShowModalMovePallet(false);
        if (!item.location_code) {
            setData(prevItem => ({
                ...prevItem,
                number_pallets_state_booked_in:
                    prevItem.number_pallets_state_booked_in + 1,
            }));
        }

        setItem(prevItem => ({
          ...prevItem,
          location_code: response.data.location.resource.code,
          state_icon: response.data.state_icon,
        }));

        Animated.spring(translateX, {
          toValue: 0,
          useNativeDriver: true,
        }).start();

       

        Toast.show({
          type: ALERT_TYPE.SUCCESS,
          title: 'Success',
          textBody: 'Updated pallet ' + item.reference,
        });
      },
      onFailed: error => {
        setShowModalMovePallet(false);
        Toast.show({
          type: ALERT_TYPE.DANGER,
          title: 'Error',
          textBody:
            error.detail?.message ||
            'Failed to update pallet ' + item.reference,
        });
      },
    });
  };

  return (
    <View style={{marginVertical: 0}}>
      {data.state === 'booking_in' && (
        <>
          {item.state !== 'not_received' ? (
            <View
              style={[{width: MAX_SWIPE}, globalStyles.button_swipe_primary]}>
              <TouchableOpacity
                size="md"
                variant="solid"
                onPress={() => setShowModalMovePallet(true)}>
                <FontAwesomeIcon icon={faInventory} size={25} color="#615FFF" />
              </TouchableOpacity>
            </View>
          ) : (
            <View
              style={[
                globalStyles.button_swipe_danger,
                {width: MAX_SWIPE, backgroundColor: '#E5E7EB'},
              ]}>
              <TouchableOpacity
                size="md"
                variant="solid"
                onPress={onUndoNotReceived}>
                <FontAwesomeIcon icon={faHistory} size={25} />
              </TouchableOpacity>
            </View>
          )}

          {item.state !== 'not_received' && (
            <View
              style={[{width: MAX_SWIPE}, globalStyles.button_swipe_danger]}>
              <TouchableOpacity
                size="md"
                variant="solid"
                onPress={onNotReceived}>
                <FontAwesomeIcon icon={faTimesRegular} color="red" size={25} />
              </TouchableOpacity>
            </View>
          )}
        </>
      )}

      <Animated.View
        {...panResponder.panHandlers}
        style={[
          {
            transform: [{translateX}],
            shadowColor: '#000',
            shadowOffset: {width: 0, height: 2},
            shadowOpacity: 0.2,
            shadowRadius: 4,
          },
          globalStyles.list.card,
        ]}>
        <TouchableOpacity activeOpacity={0.7}>
          <View style={globalStyles.list.container}>
            <View style={globalStyles.list.avatarContainer}>
              {item?.state_icon && (
                <FontAwesomeIcon
                  icon={item.state_icon.icon}
                  size={24}
                  style={{marginVertical: 3}}
                  color={item.state_icon.color}
                />
              )}
              {item?.type_icon && (
                <FontAwesomeIcon
                  icon={item.type_icon.icon}
                  size={24}
                  style={{marginVertical: 3}}
                />
              )}
            </View>

            <View style={globalStyles.list.textContainer}>
              <View className="flex-row justify-between">
                <Text style={globalStyles.list.title}>
                  {item?.customer_reference || 'N/A'}
                </Text>
                <Text style={globalStyles.list.title}>
                  {item?.location_code || '-'}
                </Text>
              </View>

              <Text style={globalStyles.list.description}>
                {item?.reference}
              </Text>
            </View>
          </View>
        </TouchableOpacity>
      </Animated.View>

      <ModalMoveLocation 
       isVisible={showModalMovePallet}
       title='Move Pallet'
       onClose={() => setShowModalMovePallet(false)}
       onSave={onSubmitSetLocation}
       location={item.location_slug}
      />
    </View>
  );
};

export default PalletInDeliveries;
