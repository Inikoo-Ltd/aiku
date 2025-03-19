import React, {useContext, useEffect, useRef, useState} from 'react';
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
import Modal from '@/src/components/Modal';
import request from '@/src/utils/Request';
import {useReturn} from '@/src/components/Context/return';
import {Textarea, TextareaInput} from '@/src/components/ui/textarea';
import SetStateButton from '@/src/components/SetStateButton';
import {Alert, AlertText } from '@/src/components/ui/alert';
import {
  Radio,
  RadioGroup,
  RadioIndicator,
  RadioLabel,
  RadioIcon,
} from '@/src/components/ui/radio';
import {getFilteredActionsReturn} from '@/src/utils';
import {CircleIcon} from '@/src/components/ui/icon';
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
  faSignOutAlt,
  faGhost,
  faCheckCircle
} from '@/private/fa/pro-light-svg-icons';
import {
  faFileInvoiceDollar,
  faInventory,
  faTimes as faTimesRegular,
  faCheck as faCheckRegular,
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
  faSignOutAlt,
  faGhost,
);

const PalletsInReturn = ({navigation, route, onChangeState}) => {
  const {organisation, warehouse} = useContext(AuthContext);
  const {data, setData} = useReturn();
  const {id} = route.params;
  const [totalPallets, setTotalPallets] = useState(0);
  const _BaseList = useRef(null)
  
  useEffect(() => {
    setTotalPallets(
      data.number_pallet_picked +
      data.number_pallets_state_other_incident +
      data.number_pallets_state_lost +
      data.number_pallets_state_damaged
    );
  }, [data]);

  return (
    <View style={globalStyles.container}>
      {data.state != 'dispatched' ? (
        <SetStateButton
          progress={{
            value: totalPallets.toString(),
            size: 'lg',
            total: data.number_pallets,
            orientation: 'horizontal',
          }}
          button2={{
            size: 'md',
            action: 'primary',
            variant: 'link',
            style: { borderTopLeftRadius: 0, borderBottomLeftRadius: 0 },
            onPress: () => onChangeState(getFilteredActionsReturn(data.state).id),
            text: "Set to "  + getFilteredActionsReturn(data.state).title,
          }}
        />
      ) : (
        <Alert action="success" variant="solid">
          <FontAwesomeIcon icon={faCheckCircle} color='green' />
          <AlertText>Already Dispatched</AlertText>
        </Alert>
      )}
      <BaseList
        navigation={navigation}
        urlKey="get-return-pallets"
        args={[organisation.id, warehouse.id, id]}
        ref={_BaseList}
        showResult={false}
        height={80}
        showTotalResults={()=>null}
        listItem={({item, navigation}) => (
          <GroupItem item={item} navigation={navigation} />
        )}
      />
    </View>
  );
};

const GroupItem = ({item: initialItem, navigation}) => {
  const [item, setItem] = useState(initialItem);
  const {data, setData} = useReturn();
  const [loadingSave, setLoadingSave] = useState(false);
  const [modalSetNotPicked, setModalSetNotPicked] = useState(false);
  const translateX = useRef(new Animated.Value(0)).current;
  const SWIPE_THRESHOLD = 60;
  const MAX_SWIPE = 100;
  const {control, handleSubmit, reset, setValue} = useForm({
    defaultValues: {
      state: '',
      notes: '',
    },
  });

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
  
  const onPicked = () => {
    request({
      urlKey: 'set-pallet-picked',
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
            number_pallet_picked: prevItem.number_pallet_picked + 1
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

  const undoPicked = () => {
    request({
      urlKey: 'undo-pallet-picked',
      method: 'patch',
      data: {},
      args: [item.id],
      onSuccess: response => {
        setItem(prevItem => ({
          ...prevItem,
          state: response.data.state,
          state_icon: response.data.state_icon,
        }));
        setData(prevItem => ({
          ...prevItem,
          number_pallet_picked: prevItem.number_pallet_picked - 1
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

  const notPicked = formData => {
    request({
      urlKey: 'set-pallet-not-picked',
      method: 'patch',
      data: formData,
      args: [item.id],
      onSuccess: response => {
        setItem(prevItem => ({
          ...prevItem,
          state: response.data.state,
          state_icon: response.data.state_icon,
        }));
       
        if(formData.state == 'damaged'){
          setData(prevItem => ({
            ...prevItem,
            number_pallets_state_damaged: prevItem.number_pallets_state_damaged + 1
        }));
        }else if(formData.state == 'lost'){
          setData(prevItem => ({
            ...prevItem,
            number_pallets_state_lost: prevItem.number_pallets_state_lost + 1
        }));
        }else if(formData.state == 'other_incident'){
          setData(prevItem => ({
            ...prevItem,
            number_pallets_state_other_incident: prevItem.number_pallets_state_other_incident + 1
        }));
        }
       

        Animated.spring(translateX, {
          toValue: 0,
          useNativeDriver: true,
        }).start();

        setModalSetNotPicked(false);

        Toast.show({
          type: ALERT_TYPE.SUCCESS,
          title: 'Success',
          textBody: 'Updated pallet ' + item.reference,
        });
      },
      onFailed: error => {
        setModalSetNotPicked(false);
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
      {data?.state == 'picking' &&
        item?.state != 'damaged' &&
        item?.state != 'lost' &&
        item?.state != 'other_incident' && (
          <>
            {item.state == 'picked' ? (
              <View
                style={[
                  globalStyles.button_swipe_primary,
                  {width: MAX_SWIPE, backgroundColor: '#E5E7EB'},
                ]}>
                <TouchableOpacity
                  size="md"
                  variant="solid"
                  onPress={() => undoPicked()}>
                  <FontAwesomeIcon icon={faHistory} size={25} />
                </TouchableOpacity>
              </View>
            ) : (
              <View
                style={[
                  globalStyles.button_swipe_primary,
                  {width: MAX_SWIPE, backgroundColor: '#ECFCCA'},
                ]}>
                <TouchableOpacity
                  size="md"
                  variant="solid"
                  onPress={() => onPicked()}>
                  <FontAwesomeIcon
                    icon={faCheckRegular}
                    size={25}
                    color="#7CCE00"
                  />
                </TouchableOpacity>
              </View>
            )}

            {item.state != 'picked' && (
              <View
                style={[{width: MAX_SWIPE}, globalStyles.button_swipe_danger]}>
                <TouchableOpacity
                  size="md"
                  variant="solid"
                  onPress={() => setModalSetNotPicked(true)}>
                  <FontAwesomeIcon
                    icon={faTimesRegular}
                    color="red"
                    size={25}
                  />
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

            {/*  <View
              style={{
                marginLeft: 10,
                flexDirection: 'row',
                alignItems: 'center',
                gap: 5,
              }}>
              <Text style={globalStyles.list.title}>
                {item?.location_code || '-'}
              </Text>
            </View> */}
          </View>
        </TouchableOpacity>
      </Animated.View>

      <Modal
        isVisible={modalSetNotPicked}
        title="Set Damaged"
        onClose={() => setModalSetNotPicked(false)}>
        <View className="w-full">
          <Text className="text-sm font-semibold mb-1">state</Text>
          <Controller
            name="state"
            control={control}
            render={({field}) => (
              <RadioGroup
                value={field.value}
                onChange={field.onChange}
                className="my-2">
                <View className="flex-row gap-4">
                  <Radio value="damaged">
                    <RadioIndicator>
                      <RadioIcon as={CircleIcon} />
                    </RadioIndicator>
                    <RadioLabel>Damaged</RadioLabel>
                  </Radio>

                  <Radio value="lost">
                    <RadioIndicator>
                      <RadioIcon as={CircleIcon} />
                    </RadioIndicator>
                    <RadioLabel>Lost</RadioLabel>
                  </Radio>

                  <Radio value="other_incident">
                    <RadioIndicator>
                      <RadioIcon as={CircleIcon} />
                    </RadioIndicator>
                    <RadioLabel>Other</RadioLabel>
                  </Radio>
                </View>
              </RadioGroup>
            )}
          />

          <Text className="text-sm font-semibold mt-4 mb-1">Notes</Text>
          <Controller
            name="notes"
            control={control}
            render={({field}) => (
              <Textarea className="my-2">
                <TextareaInput
                  placeholder="Additional notes..."
                  value={field.value}
                  onChangeText={field.onChange}
                />
              </Textarea>
            )}
          />

          <Button size="lg" onPress={handleSubmit(notPicked)}>
            {loadingSave ? (
              <ButtonSpinner />
            ) : (
              <View
                style={{flexDirection: 'row', alignItems: 'center', gap: 8}}>
                <FontAwesomeIcon icon={faSave} size={20} color="#fff" />
                <ButtonText>Save</ButtonText>
              </View>
            )}
          </Button>
        </View>
      </Modal>
    </View>
  );
};

export default PalletsInReturn;
