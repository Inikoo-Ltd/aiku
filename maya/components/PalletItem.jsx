import Modal from '@/components/Modal'; // ✅ Your reusable modal
import globalStyles from '@/globalStyles';
import {
  faCheck,
  faCheckDouble,
  faCross,
  faPallet,
  faSeedling,
  faShare,
  faSpellCheck,
  faTimes,
  faWarehouseAlt
} from '@/private/fa/pro-light-svg-icons';
import { faFileInvoiceDollar, faFragile, faInventory, faSave } from '@/private/fa/pro-regular-svg-icons';
import request from '@/utils/Request';
import { library } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { useRef, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import {
  Animated,
  PanResponder,
  Text,
  TextInput,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';
import { ALERT_TYPE, Toast } from 'react-native-alert-notification';

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

const MAX_SWIPE = 100;
const SWIPE_THRESHOLD = 60;

const PalletItem = ({ item: initialItem, navigation }) => {
  const [item, setItem] = useState(initialItem);
  const [showModalMove, setShowModalMove] = useState(false);
  const [showModalDamage, setShowModalDamage] = useState(false);
  const [selectedStatus, setSelectedStatus] = useState('');
  const [locationValue, setLocationValue] = useState(item.location_code || '');
  const translateX = useRef(new Animated.Value(0)).current;
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';

  const { control, handleSubmit } = useForm({
    defaultValues: {
      status: '',
      notes: '',
    },
  });

  const panResponder = useRef(
    PanResponder.create({
      onStartShouldSetPanResponder: () => true,
      onMoveShouldSetPanResponder: (_, gesture) => Math.abs(gesture.dx) > 3,
      onPanResponderMove: (_, gesture) => {
        translateX.setValue(
          Math.min(Math.max(gesture.dx, -MAX_SWIPE), MAX_SWIPE)
        );
      },
      onPanResponderRelease: (_, gesture) => {
        const move =
          gesture.dx > SWIPE_THRESHOLD
            ? MAX_SWIPE
            : gesture.dx < -SWIPE_THRESHOLD
            ? -MAX_SWIPE
            : 0;
        Animated.spring(translateX, {
          toValue: move,
          useNativeDriver: true,
        }).start();
      },
    })
  ).current;

  const onSubmitSetLocation = () => {
    request({
      method: 'patch',
      urlKey: 'set-pallet-location',
      args: [locationValue, item.id],
      data: { location: locationValue },
      onSuccess: (response) => {
        setItem((prev) => ({
          ...prev,
          location_code: response.data.location_code,
        }));
        setShowModalMove(false);
        Toast.show({
          type: ALERT_TYPE.SUCCESS,
          title: 'Success',
          textBody: `Pallet moved to ${response.data.location_code}`,
        });
        Animated.spring(translateX, { toValue: 0, useNativeDriver: true }).start();
      },
      onFailed: (error) => {
        setShowModalMove(false);
        Toast.show({
          type: ALERT_TYPE.DANGER,
          title: 'Error',
          textBody: error.detail?.message || 'Failed to move pallet',
        });
      },
    });
  };

  const onSubmitSetDamaged = (formData) => {
    request({
      method: 'patch',
      urlKey: 'set-pallet-not-picked',
      args: [item.id],
      data: formData,
      onSuccess: () => {
        setShowModalDamage(false);
        Toast.show({
          type: ALERT_TYPE.SUCCESS,
          title: 'Success',
          textBody: `Pallet marked as ${formData.status}`,
        });
      },
      onFailed: (error) => {
        setShowModalDamage(false);
        Toast.show({
          type: ALERT_TYPE.DANGER,
          title: 'Error',
          textBody: error.detail?.message || 'Failed to mark pallet',
        });
      },
    });
  };

  return (
    <View className="relative">
      {/* Swipe Left */}
      <View className="absolute right-0 top-0 h-full justify-center items-end pr-4 w-[100px] bg-red-100">
        <TouchableOpacity onPress={() => setShowModalDamage(true)}>
          <FontAwesomeIcon icon={faFragile} color="red" size={25} />
        </TouchableOpacity>
      </View>

      {/* Swipe Right */}
      <View className="absolute left-0 top-0 h-full justify-center items-start pl-4 w-[100px] bg-indigo-100">
        <TouchableOpacity onPress={() => setShowModalMove(true)}>
          <FontAwesomeIcon icon={faInventory} size={25} color="#615FFF" />
        </TouchableOpacity>
      </View>

      {/* Swipeable Card */}
      <Animated.View
        {...panResponder.panHandlers}
        className={`
          bg-white dark:bg-gray-800
          border border-gray-200 dark:border-gray-700
          p-4 
        `}
        style={[
          { transform: [{ translateX }] },
          {
            shadowColor: isDark ? '#000' : '#000',
            shadowOffset: { width: 0, height: 1 },
            shadowOpacity: 0.05,
            shadowRadius: 2,
            elevation: 2,
          },
        ]}
      >
        <TouchableOpacity
          activeOpacity={0.7}
          onPress={() => navigation.navigate('show-pallet', { id: item.id })}
        >
          <View style={globalStyles.list.container}>
            <View style={globalStyles.list.avatarContainer}>
              {item.state_icon && (
                <FontAwesomeIcon
                  icon={item.state_icon.icon}
                  size={24}
                  color={item.state_icon.color}
                  style={{ marginVertical: 3 }}
                />
              )}
              {item.type_icon && (
                <FontAwesomeIcon
                  icon={item.type_icon.icon}
                  size={24}
                  style={{ marginVertical: 3 }}
                />
              )}
            </View>

            <View style={globalStyles.list.textContainer}>
              <View className="flex-row justify-between">
                <Text className="text-base font-semibold text-gray-900 dark:text-white">
                  {item.customer_reference || 'N/A'}
                </Text>
                <Text className="text-base font-semibold text-gray-500 dark:text-gray-300">
                  {item.location_code || '-'}
                </Text>
              </View>
              <Text className="text-sm text-gray-600 dark:text-gray-400">{item.reference}</Text>
            </View>
          </View>
        </TouchableOpacity>
      </Animated.View>

      {/* Modal: Move Location */}
      <Modal
        isVisible={showModalMove}
        onClose={() => setShowModalMove(false)}
        title="Move Pallet"
      >
        <Text className="text-sm mb-1 text-gray-700 dark:text-gray-200">New Location</Text>
        <TextInput
          className="border border-gray-300 dark:border-gray-600 dark:text-white bg-white dark:bg-gray-800 rounded px-3 py-2 mb-4"
          value={locationValue}
          onChangeText={setLocationValue}
          placeholder="Enter new location code"
        />
        <View className="flex-row justify-end space-x-2">
          <TouchableOpacity onPress={() => setShowModalMove(false)}>
            <Text className="text-sm text-gray-600 dark:text-gray-400">Cancel</Text>
          </TouchableOpacity>
          <TouchableOpacity onPress={onSubmitSetLocation}>
            <Text className="text-sm font-semibold text-blue-600 dark:text-blue-400">Save</Text>
          </TouchableOpacity>
        </View>
      </Modal>

      {/* Modal: Set Damaged */}
      <Modal
        isVisible={showModalDamage}
        onClose={() => setShowModalDamage(false)}
        title="Set Damaged"
      >
        <Text className="text-sm mb-1 text-gray-700 dark:text-gray-200">Status</Text>
        <View className="flex-row space-x-4 mb-4">
          {['damaged', 'lost', 'other_incident'].map((status) => (
            <TouchableOpacity
              key={status}
              className={`px-3 py-2 rounded border ${
                selectedStatus === status
                  ? 'bg-blue-500 border-blue-500'
                  : 'border-gray-300 dark:border-gray-600'
              }`}
              onPress={() => setSelectedStatus(status)}
            >
              <Text className="text-white text-sm capitalize">
                {status.replace('_', ' ')}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        <Text className="text-sm mb-1 text-gray-700 dark:text-gray-200">Notes</Text>
        <Controller
          name="notes"
          control={control}
          render={({ field }) => (
            <TextInput
              multiline
              numberOfLines={3}
              className="border border-gray-300 dark:border-gray-600 dark:text-white bg-white dark:bg-gray-800 rounded px-3 py-2 mb-4"
              placeholder="Additional notes..."
              value={field.value}
              onChangeText={field.onChange}
            />
          )}
        />

        <View className="flex-row justify-end space-x-2">
          <TouchableOpacity onPress={() => setShowModalDamage(false)}>
            <Text className="text-sm text-gray-600 dark:text-gray-400">Cancel</Text>
          </TouchableOpacity>
          <TouchableOpacity
            onPress={handleSubmit((formData) =>
              onSubmitSetDamaged({ ...formData, status: selectedStatus })
            )}
          >
            <View className="flex-row items-center gap-1">
              <FontAwesomeIcon icon={faSave} size={16} color="#2563EB" />
              <Text className="text-sm font-semibold text-blue-600 dark:text-blue-400">Save</Text>
            </View>
          </TouchableOpacity>
        </View>
      </Modal>
    </View>
  );
};

export default PalletItem;
