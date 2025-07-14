import { debounce } from 'lodash';
import {
  forwardRef,
  useCallback,
  useEffect,
  useImperativeHandle,
  useMemo,
  useState,
} from 'react';
import {
  ActivityIndicator,
  Platform,
  RefreshControl,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { ALERT_TYPE, Toast } from 'react-native-alert-notification';
import { KeyboardAwareScrollView } from 'react-native-keyboard-aware-scroll-view';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import Empty from '@/components/Empty';
import FormTextInput from '@/components/FormTextInput';
import globalStyles from '@/globalStyles';
import request from '@/utils/Request';
import { faMagnifyingGlass } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';

// Spinner component
const Spinner = ({ size = 'large', color = '#6366F1', centered = false }) => {
  if (centered) {
    return (
      <View style={styles.spinnerCentered}>
        <ActivityIndicator size={size} color={color} />
      </View>
    );
  }
  return <ActivityIndicator size={size} color={color} />;
};

// Search input with icon
const SearchInput = ({ value, onChangeText }) => (
  <View style={{ position: 'relative', marginBottom: 12, paddingHorizontal: 16 }}>
    <FormTextInput
      placeholder="Search..."
      value={value}
      onChangeText={onChangeText}
      label={null}
    />
    <View style={{ position: 'absolute', right: 28, top: 36 }}>
      <FontAwesomeIcon icon={faMagnifyingGlass} size={16} color="#9CA3AF" />
    </View>
  </View>
);

const BaseList = forwardRef((props, ref) => {
  const [data, setData] = useState([]);
  const [page, setPage] = useState(1);
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [isFetching, setIsFetching] = useState(false);
  const [meta, setMeta] = useState();
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const insets = useSafeAreaInsets();

  useEffect(() => {
    const handler = debounce(() => setDebouncedSearch(searchQuery), 500);
    handler();
    return () => handler.cancel();
  }, [searchQuery]);

  const getDataFromServer = useCallback(
    async (isLoadMore = false, newPage = 1) => {
      if (isLoadMore) setIsLoadingMore(true);
      else setIsFetching(true);

      try {
        const response = await request({
          urlKey: props.urlKey,
          args: props.args,
          params: {
            ...props.params,
            [props.prefix ? `${props.prefix}_perPage` : 'perPage']: 10,
            [props.prefix ? `${props.prefix}Page` : 'page']: newPage,
            ['filter[global]']: debouncedSearch,
          },
        });

        if (response?.data) {
          setData(prev =>
            isLoadMore ? [...prev, ...response.data] : response.data
          );
          setMeta(response.meta);
        } else {
          throw new Error('Invalid response');
        }
      } catch (err) {
        Toast.show({
          type: ALERT_TYPE.DANGER,
          title: 'Error',
          textBody: err?.message || 'Failed to fetch data',
        });
      } finally {
        setIsFetching(false);
        setIsLoadingMore(false);
      }
    },
    [props.urlKey, props.args, props.params, debouncedSearch]
  );

  const fetchMoreData = (isLoadMore = false) => {
    if (isLoadMore && meta?.last_page !== page) {
      setPage(prevPage => prevPage + 1);
    } else if (!isLoadMore) {
      setPage(1);
      getDataFromServer(false, 1);
    }
  };

  const handleRefresh = () => {
    setIsRefreshing(true);
    setPage(1);
    getDataFromServer(false, 1).finally(() => {
      setIsRefreshing(false);
    });
  };

  useImperativeHandle(ref, () => ({
    handleRefresh,
    data,
    meta,
  }));

  useEffect(() => {
    getDataFromServer(page > 1, page);
  }, [page, debouncedSearch]);

  const handleScroll = ({ nativeEvent }) => {
    const { layoutMeasurement, contentOffset, contentSize } = nativeEvent;
    const isCloseToBottom =
      layoutMeasurement.height + contentOffset.y >= contentSize.height - 100;

    if (isCloseToBottom) fetchMoreData(true);
  };

  const renderItem = useMemo(
    () =>
      ({ item }) =>
        props.listItem ? (
          props.listItem({ item })
        ) : (
          <GroupItem item={item} />
        ),
    [props.listItem]
  );

  return (
    <View style={{ flex: 1 }}>
      <SearchInput value={searchQuery} onChangeText={setSearchQuery} />

      {/* Result count */}
      {props.showTotalResults !== false &&
        (typeof props.showTotalResults === 'function' ? (
          props.showTotalResults(meta)
        ) : (
          <Text style={styles.resultText}>
            {meta?.total ?? 0} result{meta?.total === 1 ? '' : 's'} found
          </Text>
        ))}

      <KeyboardAwareScrollView
        style={[props.listContainerStyle, { marginTop: 10 }]}
        enableOnAndroid={true}
        extraHeight={Platform.OS === 'android' ? 100 : 0}
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={{ flexGrow: 1 }}
        onScroll={handleScroll}
        scrollEventThrottle={16}
        refreshControl={
          <RefreshControl
            refreshing={isRefreshing}
            onRefresh={handleRefresh}
          />
        }
      >
        {isFetching && data.length === 0 ? (
          <Spinner centered />
        ) : data.length > 0 ? (
          <>
            {data.map((item, index) => (
              <View key={`${item[props.itemKey]}-${index}`} style={styles.listItemContainer}>
                {renderItem({ item })}
              </View>
            ))}
            {isLoadingMore && (
              <View style={{ paddingVertical: 10 }}>
                <Spinner size="small" />
              </View>
            )}
          </>
        ) : (
          <Empty />
        )}
      </KeyboardAwareScrollView>
    </View>
  );
});

// Default fallback for item render
const GroupItem = ({ item }) => (
  <TouchableOpacity
    style={[
      globalStyles.list.card,
      {
        backgroundColor: '#F9FAFB',
        borderRadius: 8,
        padding: 12,
        shadowColor: '#000',
        shadowOpacity: 0.1,
        shadowRadius: 6,
        shadowOffset: { width: 0, height: 2 },
        elevation: 3,
      },
    ]}
    activeOpacity={0.8}
    onPress={() => null}
  >
    <View style={globalStyles.list.container}>
      <View style={globalStyles.list.textContainer}>
        <Text style={[globalStyles.list.title, { fontWeight: '600', fontSize: 16 }]}>
          {item.reference}
        </Text>
        <Text style={[globalStyles.list.description, { fontSize: 14, color: '#6B7280' }]}>
          {item.slug || 'No description available'}
        </Text>
      </View>
    </View>
  </TouchableOpacity>
);

// Style section
const styles = StyleSheet.create({
  spinnerCentered: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 40,
  },
  resultText: {
    marginBottom: 12,
    marginTop: 4,
    paddingHorizontal: 16,
    fontSize: 14,
    fontWeight: '500',
    color: '#6B7280', // Tailwind gray-500
  },
  listItemContainer: {
    marginBottom: 10,
    marginHorizontal: 12,
  },
});

BaseList.defaultProps = {
  urlKey: '',
  args: [],
  params: {},
  height: 100,
  listContainerStyle: { flex: 1, marginBottom: 60 },
  itemKey: 'id',
  showTotalResults: true,
};

export default BaseList;
