// components/return/PalletInReturnItem.tsx
import { useReturns } from '@/components/context/return';
import Modal from '@/components/Modal';
import { createGlobalStyles } from '@/globalStyles';
import { faSignOutAlt } from '@/private/fa/pro-light-svg-icons';
import {
  faCheck as faCheckRegular,
  faHistory,
  faSave,
  faTimes as faTimesRegular,
} from '@/private/fa/pro-regular-svg-icons';
import request from '@/utils/Request';
import { library } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { useRef, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import {
  ActivityIndicator,
  Animated,
  PanResponder,
  Text,
  TextInput,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';
import { ALERT_TYPE, Toast } from 'react-native-alert-notification';

library.add(faCheckRegular, faHistory, faSave, faTimesRegular, faSignOutAlt);

const MAX_SWIPE = 100;
const SWIPE_THRESHOLD = 60;

const PalletInReturnItem = ({ item: initialItem }) => {
  const [item, setItem] = useState(initialItem);
  const { data, setData } = useReturns();
  const [loadingSave, setLoadingSave] = useState(false);
  const [modalSetNotPicked, setModalSetNotPicked] = useState(false);
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const styles = createGlobalStyles(isDark);

  const translateX = useRef(new Animated.Value(0)).current;
  const { control, handleSubmit } = useForm({
    defaultValues: { state: '', notes: '' },
  });

  const panResponder = useRef(
    PanResponder.create({
      onStartShouldSetPanResponder: () => true,
      onMoveShouldSetPanResponder: (_, gestureState) => Math.abs(gestureState.dx) > 5,
      onPanResponderMove: (_, gestureState) => {
        translateX.setValue(Math.min(Math.max(gestureState.dx, -MAX_SWIPE), MAX_SWIPE));
      },
      onPanResponderRelease: (_, gestureState) => {
        const toValue =
          gestureState.dx > SWIPE_THRESHOLD
            ? MAX_SWIPE
            : gestureState.dx < -SWIPE_THRESHOLD
              ? -MAX_SWIPE
              : 0;
        Animated.spring(translateX, {
          toValue,
          useNativeDriver: true,
        }).start();
      },
    })
  ).current;

  const animateBack = () =>
    Animated.spring(translateX, { toValue: 0, useNativeDriver: true }).start();

  const onPicked = () => {
    request({
      urlKey: 'set-pallet-picked',
      method: 'patch',
      args: [item.id],
      data: {},
      onSuccess: (response) => {
        setItem((prev) => ({
          ...prev,
          state: response.data.state,
          state_icon: response.data.state_icon,
        }));
        setData((prev) => ({
          ...prev,
          number_pallet_picked: prev.number_pallet_picked + 1,
        }));
        animateBack();
        Toast.show({
          type: ALERT_TYPE.SUCCESS,
          title: 'Success',
          textBody: 'Updated pallet ' + item.reference,
        });
      },
      onFailed: (error) => {
        Toast.show({
          type: ALERT_TYPE.DANGER,
          title: 'Error',
          textBody: error.detail?.message || 'Failed to update pallet',
        });
      },
    });
  };

  const undoPicked = () => {
    request({
      urlKey: 'undo-pallet-picked',
      method: 'patch',
      args: [item.id],
      data: {},
      onSuccess: (response) => {
        setItem((prev) => ({
          ...prev,
          state: response.data.state,
          state_icon: response.data.state_icon,
        }));
        setData((prev) => ({
          ...prev,
          number_pallet_picked: prev.number_pallet_picked - 1,
        }));
        animateBack();
        Toast.show({
          type: ALERT_TYPE.SUCCESS,
          title: 'Success',
          textBody: 'Reverted pallet ' + item.reference,
        });
      },
    });
  };

  const notPicked = (formData) => {
    setLoadingSave(true);
    request({
      urlKey: 'set-pallet-not-picked',
      method: 'patch',
      data: formData,
      args: [item.id],
      onSuccess: (response) => {
        setItem((prev) => ({
          ...prev,
          state: response.data.state,
          state_icon: response.data.state_icon,
        }));
        const key = `number_pallets_state_${formData.state}`;
        setData((prev) => ({
          ...prev,
          [key]: prev[key] + 1,
        }));
        animateBack();
        setModalSetNotPicked(false);
        setLoadingSave(false);
        Toast.show({
          type: ALERT_TYPE.SUCCESS,
          title: 'Success',
          textBody: 'Updated pallet ' + item.reference,
        });
      },
      onFailed: (error) => {
        setLoadingSave(false);
        setModalSetNotPicked(false);
        Toast.show({
          type: ALERT_TYPE.DANGER,
          title: 'Error',
          textBody: error.detail?.message || 'Failed to update pallet',
        });
      },
    });
  };

  return (
    <View>
    <View style={{ position: 'relative' }}>
      {/* Action Buttons (Swipe Background) */}
      <View
        style={[
          styles.list.card,
          {
            position: 'absolute',
            width: '100%',
            flexDirection: 'row',
            top: 0,
            bottom: 0,
          },
        ]}
      >
        {/* Left Action (Undo or Not Picked) */}
        <View
          style={[
            styles.button_swipe_danger,
            {
              width: MAX_SWIPE,
              justifyContent: 'center',
              alignItems: 'center',
              borderTopLeftRadius: styles.list.card.borderRadius,
              borderBottomLeftRadius: styles.list.card.borderRadius,
            },
          ]}
        >
          <TouchableOpacity onPress={() => {
            item.state === 'picked' ? undoPicked() : setModalSetNotPicked(true);
          }}>
            <FontAwesomeIcon
              icon={item.state === 'picked' ? faHistory : faTimesRegular}
              size={20}
              color={item.state === 'picked' ? 'black' : 'red'}
            />
          </TouchableOpacity>
        </View>

        {/* Spacer */}
        <View style={{ flex: 1 }} />

        {/* Right Action (Mark as Picked) */}
        {item.state !== 'picked' && (
          <View
            style={[
              styles.button_swipe_primary,
              {
                width: MAX_SWIPE,
                justifyContent: 'center',
                alignItems: 'center',
                borderTopRightRadius: styles.list.card.borderRadius,
                borderBottomRightRadius: styles.list.card.borderRadius,
              },
            ]}
          >
            <TouchableOpacity onPress={onPicked}>
              <FontAwesomeIcon icon={faCheckRegular} size={20} color="#7CCE00" />
            </TouchableOpacity>
          </View>
        )}
      </View>

      {/* Swipeable Foreground */}
      <Animated.View
        {...panResponder.panHandlers}
        style={[
          { transform: [{ translateX }] },
          styles.list.card,
        ]}
      >
        <TouchableOpacity activeOpacity={0.7}>
          <View style={styles.list.container}>
            <View style={styles.list.avatarContainer}>
              {item?.state_icon && (
                <FontAwesomeIcon icon={item.state_icon.icon} size={24} color={item.state_icon.color} />
              )}
              {item?.type_icon && (
                <FontAwesomeIcon icon={item.type_icon.icon} size={24} />
              )}
            </View>
            <View style={styles.list.textContainer}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                <Text style={styles.list.title}>{item?.customer_reference || 'N/A'}</Text>
                <Text style={styles.list.title}>{item?.location_code || '-'}</Text>
              </View>
              <Text style={styles.list.description}>{item?.reference}</Text>
            </View>
          </View>
        </TouchableOpacity>
      </Animated.View>
    </View>

      {/* Modal */}
      <Modal isVisible={modalSetNotPicked} title="Set Damaged" onClose={() => setModalSetNotPicked(false)}>
        <View style={{ width: '100%' }}>
          <Text style={{ fontSize: 14, fontWeight: 'bold', marginBottom: 4 }}>State</Text>
          <Controller
            name="state"
            control={control}
            render={({ field }) => (
              <View style={{ flexDirection: 'row', marginVertical: 8, gap: 12 }}>
                {['damaged', 'lost', 'other_incident'].map((value) => (
                  <TouchableOpacity
                    key={value}
                    style={{
                      flexDirection: 'row',
                      alignItems: 'center',
                      paddingHorizontal: 12,
                      paddingVertical: 8,
                      borderRadius: 6,
                      backgroundColor: field.value === value ? '#4F46E5' : '#E5E7EB',
                    }}
                    onPress={() => field.onChange(value)}
                  >
                    <Text style={{ color: field.value === value ? '#fff' : '#111827', fontWeight: 'bold' }}>
                      {value.replace('_', ' ')}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>
            )}
          />

          <Text style={{ fontSize: 14, fontWeight: 'bold', marginBottom: 4, marginTop: 16 }}>Notes</Text>
          <Controller
            name="notes"
            control={control}
            render={({ field }) => (
              <TextInput
                multiline
                placeholder="Additional notes..."
                value={field.value}
                onChangeText={field.onChange}
                style={{
                  backgroundColor: '#fff',
                  borderColor: '#D1D5DB',
                  borderWidth: 1,
                  padding: 10,
                  borderRadius: 8,
                  height: 80,
                  textAlignVertical: 'top',
                }}
              />
            )}
          />

          <TouchableOpacity
            onPress={handleSubmit(notPicked)}
            style={{
              marginTop: 20,
              paddingVertical: 12,
              borderRadius: 8,
              backgroundColor: '#4F46E5',
              alignItems: 'center',
              flexDirection: 'row',
              justifyContent: 'center',
              gap: 10,
            }}
          >
            {loadingSave ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <>
                <FontAwesomeIcon icon={faSave} size={18} color="#fff" />
                <Text style={{ color: '#fff', fontWeight: 'bold' }}>Save</Text>
              </>
            )}
          </TouchableOpacity>
        </View>
      </Modal>
    </View>
  );
};

export default PalletInReturnItem;
