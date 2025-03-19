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
import request from '@/src/utils/Request';
import {useReturn} from '@/src/components/Context/return';
import SetStateButton from '@/src/components/SetStateButton';
import {Alert, AlertText} from '@/src/components/ui/alert';
import {getFilteredActionsReturn} from '@/src/utils';
import {FontAwesomeIcon} from '@fortawesome/react-native-fontawesome';
import {library} from '@fortawesome/fontawesome-svg-core';
import {Center} from '@/src/components/ui/center';
import {Button, ButtonText, ButtonSpinner} from '@/src/components/ui/button';
import Modal from '@/src/components/Modal';
import {Input, InputField} from '@/src/components/ui/input';

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
    faCheckCircle,
    faInventory,
} from '@/private/fa/pro-light-svg-icons';
import {
    faFileInvoiceDollar,
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
    faInventory,
);

const ItemsInReturn = ({navigation, route, onChangeState}) => {
    const {organisation, warehouse} = useContext(AuthContext);
    const {data, setData} = useReturn();
    const {id} = route.params;
    const _BaseList = useRef(null);
    const [totalItems, setTotalItems] = useState(0);

     useEffect(() => {
        setTotalItems(
          data.number_picked_items
        );
      }, [data]);

    return (
        <View style={globalStyles.container}>
            {data.state != 'dispatched' ? (
                <SetStateButton
                    progress={{
                        value: totalItems,
                        size: 'lg',
                        total: data.number_ordered_items,
                        orientation: 'horizontal',
                      }}
                    button2={{
                        size: 'md',
                        action: 'primary',
                        variant: 'link',
                        style: { borderTopLeftRadius: 0, borderBottomLeftRadius: 0 },
                        onPress: () =>
                            onChangeState(
                                getFilteredActionsReturn(data.state).id,
                            ),
                        text:
                            'Set to ' +
                            getFilteredActionsReturn(data.state).title,
                    }}
                />
            ) : (
                <Alert action="success" variant="solid">
                    <FontAwesomeIcon icon={faCheckCircle} color="green" />
                    <AlertText>Already Dispatched</AlertText>
                </Alert>
            )}
            <BaseList
                navigation={navigation}
                urlKey="get-return-stored-items"
                args={[organisation.id, warehouse.id, id]}
                ref={_BaseList}
                showResult={false}
                height={80}
                showTotalResults={() => null}
                listItem={({item, navigation}) => (
                    <GroupItem item={item} navigation={navigation} />
                )}
            />
        </View>
    );
};

const GroupItem = ({item: initialItem, navigation}) => {
    const [item, setItem] = useState(initialItem);
    const inputRef = useRef(null);
    const {data, setData} = useReturn();
    const [loadingSave, setLoadingSave] = useState(false);
    const [modalSetPicked, setModalSetPicked] = useState(false);
    const translateX = useRef(new Animated.Value(0)).current;
    const SWIPE_THRESHOLD = 60;
    const MAX_SWIPE = 100;
    const {control, handleSubmit, reset, setValue} = useForm({
        defaultValues: {
            quantity_picked: parseInt(item.quantity_ordered).toString(),
        },
    });

    const panResponder = useRef(
        PanResponder.create({
            onStartShouldSetPanResponder: () => true,
            onMoveShouldSetPanResponder: (_, gestureState) =>
                Math.abs(gestureState.dx) > 5,
            onPanResponderMove: (_, gestureState) => {
                if (Math.abs(gestureState.dx) > 100) {
                    translateX.setValue(
                        Math.min(
                            Math.max(gestureState.dx, -MAX_SWIPE),
                            MAX_SWIPE,
                        ),
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

    const onPicked = (allPicked = true, data) => {
        setLoadingSave(true);
        request({
            urlKey: 'set-stored-item-pick',
            method: 'patch',
            args: [item.id],
            data: allPicked
                ? {
                      quantity_picked: 
                          parseInt(item.quantity_ordered)
                          .toString(),
                  }
                : data,
            onSuccess: response => {
                setLoadingSave(false);
                setItem(prevItem => ({
                    ...prevItem,
                    state: response.data.state,
                    state_icon: response.data.state_icon,
                    quantity_picked: response.data.quantity_picked,
                }));
                setModalSetPicked(false);
                Animated.spring(translateX, {
                    toValue: 0,
                    useNativeDriver: true,
                }).start();

                setData(prevItem => ({
                    ...prevItem,
                    number_picked_items: prevItem.number_picked_items + parseInt(item.quantity_ordered)
                }));

                Toast.show({
                    type: ALERT_TYPE.SUCCESS,
                    title: 'Success',
                    textBody: 'Updated sku ' + item.stored_items_name,
                });
            },
            onFailed: error => {
                console.log(error);
                setLoadingSave(false);
                setModalSetPicked(false);
                Toast.show({
                    type: ALERT_TYPE.DANGER,
                    title: 'Error',
                    textBody:
                        error.detail?.message ||
                        'Failed to update sku ' + item.stored_items_name,
                });
            },
        });
    };

    const undoPicked = () => {
        request({
            urlKey: 'set-stored-item-undo-pick',
            method: 'patch',
            args: [item.id],
            onSuccess: response => {
                setData(prevItem => ({
                    ...prevItem,
                    number_picked_items: prevItem.number_picked_items - item.quantity_picked
                }));
                setItem(prevItem => ({
                    ...prevItem,
                    state: response.data.state,
                    state_icon: response.data.state_icon,
                    quantity_picked: response.data.quantity_picked,
                }));
                setModalSetPicked(false);

                Animated.spring(translateX, {
                    toValue: 0,
                    useNativeDriver: true,
                }).start();

                Toast.show({
                    type: ALERT_TYPE.SUCCESS,
                    title: 'Success',
                    textBody: 'Updated sku ' + item.stored_items_name,
                });
            },
            onFailed: error => {
                console.log(error);
                setModalSetPicked(false);
                Toast.show({
                    type: ALERT_TYPE.DANGER,
                    title: 'Error',
                    textBody:
                        error.detail?.message ||
                        'Failed to update sku ' + item.stored_items_name,
                });
            },
        });
    };

    useEffect(() => {
        if (modalSetPicked && inputRef.current) {
            setTimeout(() => inputRef.current.focus(), 100); // Small delay ensures focus
        }
    }, [modalSetPicked]);

    return (
        <View style={{marginVertical: 0}}>
            {data?.state == 'picking' &&
                item?.state != 'damaged' &&
                item?.state != 'lost' &&
                item?.state != 'other_incident' && (
                    <>
                        {item.state != 'picked' && (
                            <>
                                <View
                                    style={[
                                        globalStyles.button_swipe_danger,
                                        {
                                            width: MAX_SWIPE,
                                            backgroundColor: '#ECFCCA',
                                        },
                                    ]}>
                                    <TouchableOpacity
                                        size="md"
                                        variant="solid"
                                        onPress={() => setModalSetPicked(true)}>
                                        <Center>
                                            <FontAwesomeIcon
                                                icon={faCheckRegular}
                                                color="#7CCE00"
                                                size={25}
                                            />
                                            <Text style={{textAlign: 'center'}}>
                                                Custom Pick
                                            </Text>
                                        </Center>
                                    </TouchableOpacity>
                                </View>
                                <View
                                    style={[
                                        globalStyles.button_swipe_primary,
                                        {
                                            width: MAX_SWIPE,
                                            backgroundColor: '#ECFCCA',
                                        },
                                    ]}>
                                    <TouchableOpacity
                                        size="md"
                                        variant="solid"
                                        onPress={() => onPicked(true)}>
                                        <Center>
                                            <FontAwesomeIcon
                                                icon={faCheckRegular}
                                                color="#7CCE00"
                                                size={25}
                                            />
                                            <Text style={{textAlign: 'center'}}>
                                                Pick All
                                            </Text>
                                        </Center>
                                    </TouchableOpacity>
                                </View>
                            </>
                        )}
                        {item.state == 'picked' && (
                            <View
                                style={[
                                    globalStyles.button_swipe_danger,
                                    {
                                        width: MAX_SWIPE,
                                        backgroundColor: '#E5E7EB',
                                    },
                                ]}>
                                <TouchableOpacity
                                    size="md"
                                    variant="solid"
                                    onPress={() => undoPicked()}>
                                    <FontAwesomeIcon
                                        icon={faHistory}
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
                                    // color={item.state_icon.color}
                                />
                            )}
                        </View>

                        <View style={globalStyles.list.textContainer}>
                            <View className="flex-row justify-between">
                                <Text style={globalStyles.list.title}>
                                    {item?.stored_items_name || 'N/A'}
                                </Text>
                                <Text style={globalStyles.list.title}>
                                    {parseInt(item.quantity_picked) +
                                        '/' +
                                        parseInt(item.quantity_ordered)}
                                </Text>
                            </View>

                            <Text style={globalStyles.list.description}>
                                Pallet : {item?.pallets_reference}
                            </Text>
                            <Text style={globalStyles.list.description}>
                                Location : {item?.location_code || 'N/A'}
                            </Text>
                        </View>
                    </View>
                </TouchableOpacity>
            </Animated.View>

            <Modal
                isVisible={modalSetPicked}
                title="Set SKU to picked"
                onClose={() => setModalSetPicked(false)}>
                <View className="w-full">
                    <Text className="text-sm font-semibold mb-1">
                        Number sku to picked
                    </Text>
                    <Controller
                        name="quantity_picked"
                        control={control}
                        render={({field}) => (
                            <Input className="my-2">
                                <InputField
                                    placeholder="sku..."
                                    ref={inputRef}
                                    value={field.value}
                                    keyboardType="numeric"
                                    onChangeText={text => {
                                        let numericValue = text.replace(
                                            /[^0-9]/g,
                                            '',
                                        ); // Allow only numbers

                                        if (
                                            numericValue !== '' &&
                                            parseInt(numericValue) >
                                                item.quantity_ordered
                                        ) {
                                            numericValue =
                                                item.quantity_ordered;
                                        }

                                        field.onChange(numericValue);
                                    }}
                                />
                            </Input>
                        )}
                    />
                    <Button
                        size="lg"
                        onPress={handleSubmit(e => onPicked(false, e))}>
                        {loadingSave ? (
                            <ButtonSpinner />
                        ) : (
                            <View
                                style={{
                                    flexDirection: 'row',
                                    alignItems: 'center',
                                    gap: 8,
                                }}>
                                <FontAwesomeIcon
                                    icon={faSave}
                                    size={20}
                                    color="#fff"
                                />
                                <ButtonText>Save</ButtonText>
                            </View>
                        )}
                    </Button>
                </View>
            </Modal>
        </View>
    );
};

export default ItemsInReturn;
