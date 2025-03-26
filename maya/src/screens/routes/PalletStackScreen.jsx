import React, {useContext, useEffect, useState, useRef} from 'react';
import {View, ActivityIndicator, TouchableOpacity, Text} from 'react-native';
import BottomTabs from '@/src/components/BottomTabs';
import request from '@/src/utils/Request';
import {ALERT_TYPE, Toast} from 'react-native-alert-notification';
import {AuthContext} from '@/src/components/Context/context';
import Modal from '@/src/components/Modal';
import {Textarea, TextareaInput} from '@/src/components/ui/textarea';
import {
    Radio,
    RadioGroup,
    RadioIndicator,
    RadioLabel,
    RadioIcon,
} from '@/src/components/ui/radio';
import {Input, InputField} from '@/src/components/ui/input';
import {CircleIcon} from '@/src/components/ui/icon';
import Menu from '@/src/components/Menu';
import {useForm, Controller} from 'react-hook-form';
import {Button, ButtonText, ButtonSpinner} from '@/src/components/ui/button';
import Empty from '@/src/components/Empty';
import ModalMoveLocation from '@/src/components/MoveLocationModal';
import PalletShowcase from '@/src/screens/Fulfilment/Pallet/ShowPallet';
import ItemsInPallet from '@/src/screens/Fulfilment/Pallet/ItemsInPallet';

import {FontAwesomeIcon} from '@fortawesome/react-native-fontawesome';
import {
    faBars,
    faSave,
    faPallet,
    faNarwhal,
} from '@/private/fa/pro-regular-svg-icons';

const PalletStackScreen = ({navigation, route}) => {
    const {organisation, warehouse} = useContext(AuthContext);
    const [data, setData] = useState(null);
    const [loadingSave, setLoadingSave] = useState(false);
    const [loading, setLoading] = useState(true);
    const [showModalDamaged, setShowModalDamaged] = useState(false);
    const [showModalMovePallet, setShowModalMovePallet] = useState(false);
    const menuRef = useRef(null);
    const {id} = route.params;

    const {control, handleSubmit, reset, setValue} = useForm({
        defaultValues: {
            status: '',
            notes: '',
            /*    location: '', */
        },
    });

    const Menus = [
        {id: 'audit-sku', title: 'Audit SKU'},
        {id: 'move-pallet', title: 'Move Pallet'},
        {
            id: 'set-damaged',
            title: 'Set Damaged',
            attributes: {destructive: true},
        },
    ];

    const createAudit = () =>{
        request({
            urlKey: 'create-pallet-audit',
            args: [organisation.id, warehouse.id, id],
            data: {
                pallet_id: data.id,
            },
            onSuccess: response => {
                console.log(response)
                navigation.navigate('audit-sku', {id: response.id, pallet_id: data.id  });
            },
            onFailed: error => {
                Toast.show({
                    type: ALERT_TYPE.DANGER,
                    title: 'Error',
                    textBody: error.detail?.message || 'Failed to create audit',
                });
            },
        });
    }

    const onPressMenu = event => {
        switch (event.event) {
            case 'move-pallet':
                setShowModalMovePallet(true);
                break;
            case 'set-damaged':
                setShowModalDamaged(true);
                break;
            case 'audit-sku':
                if(data.audit){
                    navigation.navigate('audit-sku', {id: data.audit ,pallet_id: data.id });
                }else{
                    createAudit()
                }
                break;
            default:
                console.log('Unknown option selected');
        }
    };

    const fetchData = async () => {
        try {
            const response = await request({
                urlKey: 'get-pallet',
                args: [organisation.id, warehouse.id, id],
            });
            setData(response.data);
        } catch (error) {
            Toast.show({
                type: ALERT_TYPE.DANGER,
                title: 'Error',
                textBody: error.detail?.message || 'Failed to fetch data',
            });
        } finally {
            setLoading(false);
        }
    };

    const onSubmitSetDamaged = async formData => {
        request({
            urlKey: 'set-pallet-not-picked',
            method: 'patch',
            data: formData,
            args: [data.id],
            onSuccess: response => {
                fetchData();
                setShowModalDamaged(false);
                Toast.show({
                    type: ALERT_TYPE.SUCCESS,
                    title: 'Success',
                    textBody: 'Updated pallet ' + data.reference,
                });
            },
            onFailed: error => {
                setShowModalDamaged(false);
                Toast.show({
                    type: ALERT_TYPE.DANGER,
                    title: 'Error',
                    textBody:
                        error.detail?.message ||
                        'Failed to update pallet ' + data.reference,
                });
            },
        });
    };

    const onSubmitSetLocation = formData => {
        request({
            method: 'patch',
            urlKey: 'set-pallet-location',
            args: [data.id, formData.location],
            data: {},
            onSuccess: response => {
                fetchData();
                setShowModalMovePallet(false);
                Toast.show({
                    type: ALERT_TYPE.SUCCESS,
                    title: 'Success',
                    textBody: 'success update pallet to ' + formData.location,
                });
            },
            onFailed: error => {
                console.log(error);
                setShowModalMovePallet(false);
                Toast.show({
                    type: ALERT_TYPE.DANGER,
                    title: 'Error',
                    textBody: error.detail?.message || 'Failed to update data',
                });
            },
        });
    };

    useEffect(() => {
        fetchData();
    }, [id]);

    useEffect(() => {
        navigation.setOptions({
            title: data ? `Pallet ${data.reference}` : 'Pallet Details',
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
    }, [navigation, data]);

    if (loading) {
        return (
            <View className="flex-1 items-center justify-center bg-gray-100">
                <ActivityIndicator size="large" color="#4F46E5" />
            </View>
        );
    }

    return (
        <>
            {data ? (
                <BottomTabs
                    tabArr={[
                        {
                            route: 'show-pallet',
                            label: 'Pallet',
                            icon: faPallet,
                            component: () => <PalletShowcase data={data} />,
                        },
                        {
                            route: 'items-in-pallet',
                            label: 'SKU In Pallet',
                            icon: faNarwhal,
                            component: () => <ItemsInPallet data={data} />,
                        },
                    ]}
                />
            ) : (
                <Empty />
            )}

            <Modal
                isVisible={showModalDamaged}
                title="Set Damaged"
                onClose={() => setShowModalDamaged(false)}>
                <View className="w-full">
                    <Text className="text-sm font-semibold mb-1">Status</Text>
                    <Controller
                        name="status"
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

                    <Text className="text-sm font-semibold mt-4 mb-1">
                        Notes
                    </Text>
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

                    <Button
                        size="lg"
                        onPress={handleSubmit(onSubmitSetDamaged)}>
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

            <ModalMoveLocation
                isVisible={showModalMovePallet}
                title="Move Pallet"
                onClose={() => setShowModalMovePallet(false)}
                onSave={onSubmitSetLocation}
                location={data.location_code}
            />

            {/*     <Modal
                isVisible={showModalMovePallet}
                title="Move Pallet"
                onClose={() => setShowModalMovePallet(false)}>
                <View className="w-full">
                    <Text className="text-sm font-semibold mb-1">Location</Text>
                    <Controller
                        name="location"
                        control={control}
                        render={({field}) => (
                            <Input variant="outline" size="md">
                                <InputField
                                    placeholder="Enter new location..."
                                    value={field.value}
                                    onChangeText={field.onChange}
                                />
                            </Input>
                        )}
                    />

                    <Button
                        size="lg"
                        className="my-3"
                        onPress={handleSubmit(onSubmitSetLocation)}>
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
                                <ButtonText>Move Pallet</ButtonText>
                            </View>
                        )}
                    </Button>
                </View>
            </Modal> */}

            
        </>
    );
};

export default PalletStackScreen;
