import React, {useContext, useEffect, useState, useRef} from 'react';
import {View, ActivityIndicator, TouchableOpacity, Text} from 'react-native';

import request from '@/src/utils/Request';
import {ALERT_TYPE, Toast} from 'react-native-alert-notification';
import {AuthContext} from '@/src/components/Context/context';

const AuditPallet = ({navigation, route}) => {
    const {organisation, warehouse} = useContext(AuthContext);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const {id, pallet_id} = route.params;

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
        <>
            <View>
                <Text>halooo</Text>
            </View>
        </>
    );
};

export default AuditPallet;
