import React, {useContext, useEffect, useState, useRef} from 'react';
import {
    View,
    TouchableOpacity,
    Text,
    Animated,
    PanResponder,
    ActivityIndicator,
    FlatList,
    TextInput,
    StyleSheet,
    Modal as ModalNative,
} from 'react-native';
import {Button, ButtonText, ButtonSpinner} from '@/src/components/ui/button';
import Modal from '@/src/components/Modal';
import globalStyles from '@/globalStyles';
import request from '@/src/utils/Request';
import {ALERT_TYPE, Toast} from 'react-native-alert-notification';
import {AuthContext} from '@/src/components/Context/context';
import Empty from '@/src/components/Empty';
import {useForm, Controller} from 'react-hook-form';
import {RNHoleView} from 'react-native-hole-view';
import Menu from '@/src/components/Menu';

import {FontAwesomeIcon} from '@fortawesome/react-native-fontawesome';
import {library} from '@fortawesome/fontawesome-svg-core';
import {
    Camera,
    useCameraDevice,
    useCodeScanner,
} from 'react-native-vision-camera';
import {
    holesConfig,
    handleCameraPermission,
    ScannerConfig,
} from '@/src/utils/Scanner';
import {
    faEdit,
    faFragile,
    faHistory,
    faSave,
    faBars
} from '@/private/fa/pro-light-svg-icons';
import {faInventory} from '@/private/fa/pro-regular-svg-icons';

library.add(faFragile, faInventory);

const AuditPallet = ({navigation, route}) => {
    const {organisation, warehouse} = useContext(AuthContext);
    const [data, setData] = useState(null);
    const [showScanner, setShowScanner] = useState(false);
    const [loading, setLoading] = useState(true);
    const {id, pallet_id} = route.params;
    const menuRef = useRef(null);
    const Menus = [
        {id: 'add-sku', title: 'Add SKU'},
        {id: 'complete-audit', title: 'Complete Audit'},
    ];


    const fetchData = async () => {
        request({
            urlKey: 'get-pallet-audit',
            args: [organisation.id, warehouse.id, pallet_id, id],
            onSuccess: response => {
                setData(response.data);
                setLoading(false);
            },
            onFailed: error => {
                Toast.show({
                    type: ALERT_TYPE.DANGER,
                    title: 'Error',
                    textBody: error.detail?.message || 'Failed to fetch data',
                });
                setLoading(false);
            },
        });
    };

    const device = useCameraDevice('back');
    const codeScanner = useCodeScanner({
        ...ScannerConfig,
        onCodeScanned: codes => {
            setShowScanner(false)
            createSKU(codes[0].value)
        },
    });

    const onPressMenu = event => {
        switch (event.event) {
            case 'add-sku':
                setShowScanner(true);
                break;
            case 'complete-audit':
                CompleteAudit();
                break;
            default:
                console.log('Unknown option selected');
        }
    };


    const CompleteAudit = () =>{
        request({
            urlKey: 'complete-stored-item-audit',
            method: 'post',
            args: [id],
            data:{
                pallet_id        : pallet_id,
                stored_item_id   : slug,
                audited_quantity : 1
            },
            onSuccess: response => {
               navigation.navigate("show-pallet", { id : pallet_id})
            },
            onFailed: error => {
                console.log(error);
                Toast.show({
                    type: ALERT_TYPE.DANGER,
                    title: 'Error',
                    textBody:
                        error.detail?.message || 'Failed to complete the audit',
                });
            },
        })
    }

    const createSKU = (slug) => {
        request({
            urlKey: 'add-stored-item-audit',
            method: 'post',
            args: [id],
            data:{
                pallet_id        : pallet_id,
                stored_item_slug  : slug,
                audited_quantity : 1
            },
            onSuccess: response => {
                fetchData();
            },
            onFailed: error => {
                console.log(error);
                Toast.show({
                    type: ALERT_TYPE.DANGER,
                    title: 'Error',
                    textBody:
                        error.detail?.message || 'Failed to create new SKU',
                });
            },
        })
    };

    useEffect(() => {
        fetchData();
        navigation.setOptions({
          /*   title: data ? `Pallet ${data.reference}` : 'Pallet Details', */
            headerRight: () => (
                <View className="px-4">
                    <Menu
                        ref={menuRef}
                        onPressAction={({nativeEvent}) =>
                            onPressMenu(nativeEvent)
                        }
                        button={
                            <TouchableOpacity
                                onPress={() => menuRef?.current?.menu.show()}>
                                <FontAwesomeIcon icon={faBars} />
                            </TouchableOpacity>
                        }
                        actions={Menus}
                    />
                </View>
            ),
        });
    }, [id,navigation]);


    if (loading) {
        return (
            <View className="flex-1 items-center justify-center bg-gray-100">
                <ActivityIndicator size="large" color="#4F46E5" />
            </View>
        );
    }

    return (
        <View style={{padding: 16, backgroundColor: '#f4f4f4'}}>
            <FlatList
                data={data?.editDeltas || []} // Ens
                keyExtractor={item => item.stored_item_id.toString()}
                showsVerticalScrollIndicator={false}
                ListEmptyComponent={<Empty />}
                renderItem={({item}) => <GroupItem item={item} fetchData={fetchData()}/>}
            />

            <View style={styles.container}>
                <ModalNative
                    visible={showScanner}
                    animationType="slide"
                    transparent={false}
                    onRequestClose={() => setShowScanner(false)}
                    presentationStyle="fullScreen">
                    <View style={styles.modalContent}>
                        <Camera
                            codeScanner={codeScanner}
                            style={styles.camera}
                            device={device}
                            isActive={true}
                        />
                        <RNHoleView
                            holes={holesConfig()}
                            style={[
                                globalStyles.scanner.rnholeView,
                                globalStyles.scanner.fullScreenCamera,
                            ]}
                        />
                    </View>
                </ModalNative>
            </View>
        </View>
    );
};

const GroupItem = ({item: initialItem, fetchData}) => {
    const [item, setItem] = useState(initialItem);
    const [openModalAudit, setOpenModalAudit] = useState(false);
    const translateX = useRef(new Animated.Value(0)).current;
    const SWIPE_THRESHOLD = 60;
    const MAX_SWIPE = 100;
    const [loadingSave, setLoadingSave] = useState(false);
    const {control, handleSubmit, reset, setValue} = useForm({
        defaultValues: {
            audited_quantity: item.audited_quantity,
        },
    });

    const panResponder = useRef(
        PanResponder.create({
            onStartShouldSetPanResponder: () => true,
            onMoveShouldSetPanResponder: (_, gestureState) =>
                Math.abs(gestureState.dx) > 3,
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

    const submitData = value => {
        request({
            urlKey: 'edit-stored-item-audit',
            method: 'patch',
            args: [item.stored_item_audit_delta_id],
            data: {audited_quantity : parseInt(value.audited_quantity)},
            onSuccess: responese => {
                setItem({...item, audited_quantity: value.audited_quantity});
                setOpenModalAudit(false);
                Toast.show({
                    type: ALERT_TYPE.SUCCESS,
                    title: 'SUCCESS',
                    textBody:
                        "Update Audit Delta"
                });
            },
            onFailed: error => {
                console.log(error);
                setOpenModalAudit(false);
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


    const undoAudit = () => {
        request({
            urlKey: 'delete-stored-item-audit',
            method: 'delete',
            args: [item.stored_item_audit_delta_id],
            onSuccess: responese => {
                fetchData
            },
            onFailed: error => {
                console.log(error)
                Toast.show({
                    type: ALERT_TYPE.DANGER,
                    title: 'Error',
                    textBody:
                        error.detail?.message ||
                        'Failed to update pallet ' + item.reference,
                });
            },
        });
    }

    return (
        <View style={{marginVertical: 0}}>
            <View
                style={[{width: MAX_SWIPE}, globalStyles.button_swipe_primary]}>
                <TouchableOpacity
                    size="md"
                    variant="solid"
                    onPress={() => setOpenModalAudit(true)}>
                    <FontAwesomeIcon icon={faEdit} size={25} color="#615FFF" />
                </TouchableOpacity>
            </View>

            <View
                style={[{width: MAX_SWIPE}, globalStyles.button_swipe_danger]}>
                <TouchableOpacity
                    size="md"
                    variant="solid"
                    onPress={() => undoAudit()}>
                    <FontAwesomeIcon icon={faHistory} color="red" size={25} />
                </TouchableOpacity>
            </View>

            <Animated.View
                {...panResponder.panHandlers}
                pointerEvents="box-none"
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
                        <View style={globalStyles.list.textContainer}>
                            <View className="flex-row gap-3">
                                <Text style={globalStyles.list.title}>
                                    {item.reference}
                                </Text>
                                {item.type === 'new_item' && (
                                    <View className="bg-indigo-500 px-2 py-1 rounded-full self-start">
                                        <Text className="text-white text-xs font-bold">
                                            New
                                        </Text>
                                    </View>
                                )}
                            </View>

                            <View className="flex-row">
                                <Text
                                    className="w-1/2"
                                    style={globalStyles.list.description}>
                                    Quantity : {item.quantity}
                                </Text>
                                <Text
                                    className="w-1/2"
                                    style={globalStyles.list.description}>
                                    Audit quantity : {item.audited_quantity}
                                </Text>
                            </View>
                        </View>
                    </View>
                </TouchableOpacity>
            </Animated.View>
  
            <Modal
                key={`${item.reference}-audit`}
                isVisible={openModalAudit}
                title={`${item.reference} audit`}
                onClose={() => setOpenModalAudit(false)}>
                <View className="w-full">
                    <Text className="text-sm font-semibold mt-4 mb-1">
                        Quantity
                    </Text>
                    <Controller
                        name="audited_quantity"
                        control={control}
                        render={({field}) => (
                            <TextInput
                                className="w-full py-2 px-3 my-2 border border-gray-300 rounded-md text-base"
                                placeholder="Enter Quantity"
                                value={field.value}
                                onChangeText={field.onChange}
                                keyboardType="numeric"
                                onSubmitEditing={handleSubmit(submitData)}
                                blurOnSubmit={false}
                                returnKeyType="done"
                            />
                        )}
                    />

                    <Button size="lg" onPress={handleSubmit(submitData)}>
                        {loadingSave ? (
                            <ButtonSpinner />
                        ) : (
                            <View className="flex-row items-center gap-2">
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

const styles = StyleSheet.create({
    container: {flex: 1, justifyContent: 'center', alignItems: 'center'},
    modalContent: {flex: 1, backgroundColor: 'black'},
    camera: {flex: 1},
});

export default AuditPallet;
