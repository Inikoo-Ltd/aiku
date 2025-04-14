import React, {
    forwardRef,
    useEffect,
    useState,
    useImperativeHandle,
    useCallback,
    useMemo,
} from 'react';
import {
    View,
    Text,
    TouchableOpacity,
    RefreshControl,
    Platform,
} from 'react-native';
import {Spinner} from '@/src/components/ui/spinner';
import request from '@/src/utils/Request';
import globalStyles from '@/globalStyles';
import {SearchIcon} from '@/src/components/ui/icon';
import {ALERT_TYPE, Toast} from 'react-native-alert-notification';
import {useSafeAreaInsets} from 'react-native-safe-area-context';
import Empty from '@/src/components/Empty';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import {
    Input,
    InputField,
    InputSlot,
    InputIcon,
} from '@/src/components/ui/input';

const BaseList = forwardRef((props, ref) => {
    const [data, setData] = useState([]);
    const [page, setPage] = useState(1);
    const [searchQuery, setSearchQuery] = useState('');
    const [isFetching, setIsFetching] = useState(false);
    const [meta, setMeta] = useState();
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const insets = useSafeAreaInsets();

    // Fetch Data
    const getDataFromServer = useCallback(
        async (isLoadMore = false, newPage = 1) => {
            if (isLoadMore) setIsLoadingMore(true);
            else setIsFetching(true);

            request({
                urlKey: props.urlKey,
                args: props.args,
                params: {
                    ...props.params,
                    [props.prefix ? `${props.prefix}_perPage` : 'perPage']: 10,
                    [props.prefix ? `${props.prefix}Page` : 'page']: newPage,
                    ['filter[global]']: searchQuery,
                },
                onBoth: (success, response) => {
                    if (success) {
                        setData(
                            isLoadMore
                                ? [...data, ...response.data]
                                : response.data,
                        );
                        setMeta(response.meta);
                    } else {
                        Toast.show({
                            type: ALERT_TYPE.DANGER,
                            title: 'Error',
                            textBody:
                                response?.data?.message ||
                                'Failed to fetch data',
                        });
                    }
                    setIsFetching(false);
                    setIsLoadingMore(false);
                },
            });
        },
        [props.urlKey, props.args, props.params, searchQuery, data],
    );

    const fetchMoreData = (isLoadMore = false) => {
        if (isLoadMore) {
            if (meta?.last_page !== page) setPage(prevPage => prevPage + 1);
        } else {
            setPage(1);
            getDataFromServer(false);
        }
    };

    const handleRefresh = () => {
        setIsRefreshing(true);
        fetchMoreData(false);
        setIsRefreshing(false);
    };

    useImperativeHandle(ref, () => ({
        handleRefresh,
        showTotalResults,
        data,
        meta,
    }));

    useEffect(() => {
        getDataFromServer(page > 1, page);
    }, [page]);

    useEffect(() => {
        fetchMoreData(false);
    }, []);

    useEffect(() => {
        fetchMoreData(false);
    }, [searchQuery]);

    // Detect When Scrolling Near Bottom
    const handleScroll = ({nativeEvent}) => {
        const {layoutMeasurement, contentOffset, contentSize} = nativeEvent;
        const isCloseToBottom =
            layoutMeasurement.height + contentOffset.y >=
            contentSize.height - 100;

        if (isCloseToBottom) {
            fetchMoreData(true);
        }
    };

    const renderItem = useMemo(
        () =>
            ({item}) =>
                props.listItem ? (
                    props.listItem({item, navigation: props.navigation})
                ) : (
                    <GroupItem item={item} navigation={props.navigation} />
                ),
        [props.listItem, props.navigation],
    );

    return (
        <View style={{flex: 1}}>
            {/* Search Bar dengan Sticky Header */}
           
                <Input
                    variant="outline"
                    size="md"
                    className="flex-row items-center">
                    <InputField
                        placeholder="Search..."
                        value={searchQuery}
                        onChangeText={setSearchQuery}
                        keyboardType="default"
                        autoCapitalize="none"
                    />
                    <InputSlot className="pr-3">
                        <InputIcon as={SearchIcon} />
                    </InputSlot>
                </Input>
                {props.showTotalResults(meta)}
       

            {/* List dengan Margin Top Agar Tidak Tertutup */}
            <KeyboardAwareScrollView
                style={[
                    props.listContainerStyle,
                    {marginTop: 10},
                ]}
                enableOnAndroid={true}
                onEndReached={() => fetchMoreData(true)}
                extraHeight={Platform.OS === 'android' ? 100 : 0}
                keyboardShouldPersistTaps="handled"
                contentContainerStyle={{flexGrow: 1}}
                onScroll={handleScroll}
                scrollEventThrottle={16} // Lebih responsif
                refreshControl={
                    <RefreshControl
                        refreshing={isRefreshing}
                        onRefresh={handleRefresh}
                    />
                }>
                {isFetching ? (
                    <View
                        style={{
                            flex: 1,
                            justifyContent: 'center',
                            alignItems: 'center',
                        }}>
                        <Spinner size="large" color={'#837FE1'} />
                    </View>
                ) : (
                    <>
                        {data.length > 0 ? (
                            data.map((item, index) => (
                                <View key={`${item[props.itemKey]}-${index}`}>
                                    {renderItem({item})}
                                </View>
                            ))
                        ) : (
                            <Empty />
                        )}
                        {isLoadingMore && (
                            <View style={{paddingVertical: 10}}>
                                <Spinner size="small" color={'#837FE1'} />
                            </View>
                        )}
                    </>
                )}
            </KeyboardAwareScrollView>
        </View>
    );
});

const GroupItem = ({item, navigation}) => {
    return (
        <TouchableOpacity
            style={globalStyles.list.card}
            activeOpacity={0.7}
            onPress={() => null}>
            <View style={globalStyles.list.container}>
                <View style={globalStyles.list.textContainer}>
                    <Text style={globalStyles.list.title}>
                        {item.reference}
                    </Text>
                    <Text style={globalStyles.list.description}>
                        {item.slug || 'No description available'}
                    </Text>
                </View>
            </View>
        </TouchableOpacity>
    );
};

const showTotalResults = meta => {
    return (
        <View className="bg-indigo-500 px-4 py-1 mt-2 self-start">
            <Text className="text-white font-semibold">
                {meta?.total || 0} Results
            </Text>
        </View>
    );
};

BaseList.defaultProps = {
    urlKey: '',
    args: [],
    params: {},
    height: 100,
    listContainerStyle: {flex: 1, marginBottom: 60},
    showTotalResults,
    itemKey: 'id',
};

export default BaseList;
