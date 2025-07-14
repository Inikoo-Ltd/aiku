import { useDeliveries } from '@/components/context/delivery';
import SimpleModal from '@/components/Modal';
import { createGlobalStyles } from '@/globalStyles';
import { faHistory, faInventory, faTimes } from '@/private/fa/pro-regular-svg-icons';
import request from '@/utils/Request';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { useRef, useState } from 'react';
import {
  Animated,
  PanResponder,
  Text,
  View,
  useColorScheme,
} from 'react-native';
import { ALERT_TYPE, Toast } from 'react-native-alert-notification';

const MAX_SWIPE = 120;

const GroupItem = ({ item: initialItem }) => {
  const { data, setData } = useDeliveries();
  const [item, setItem] = useState(initialItem);
  const [showModal, setShowModal] = useState(false);
  const translateX = useRef(new Animated.Value(0)).current;

  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const styles = createGlobalStyles(isDark);

  const handleSwipeAction = (direction) => {
    if (direction === 'left') {
      item.state !== 'not_received' ? onNotReceived() : onUndo();
    } else if (direction === 'right' && item.state !== 'not_received') {
      setShowModal(true);
    }
  };

  const panResponder = useRef(
    PanResponder.create({
      onMoveShouldSetPanResponder: (_, gesture) => Math.abs(gesture.dx) > 5,
      onPanResponderMove: (_, gesture) => {
        translateX.setValue(Math.min(Math.max(gesture.dx, -MAX_SWIPE), MAX_SWIPE));
      },
      onPanResponderRelease: (_, gesture) => {
        if (gesture.dx > MAX_SWIPE * 0.8) {
          handleSwipeAction('right');
        } else if (gesture.dx < -MAX_SWIPE * 0.8) {
          handleSwipeAction('left');
        }

        Animated.spring(translateX, {
          toValue: 0,
          useNativeDriver: true,
        }).start();
      },
    })
  ).current;

  const updateState = (urlKey, successMessage, updateCount) => {
    request({
      method: 'patch',
      urlKey,
      args: [item.id],
      onSuccess: (res) => {
        setItem((prev) => ({ ...prev, ...res.data }));
        setData((prev) => updateCount(prev));

        Toast.show({
          type: ALERT_TYPE.SUCCESS,
          title: 'Success',
          textBody: `${successMessage} ${item.reference}`,
        });
      },
      onFailed: (err) => {
        Toast.show({
          type: ALERT_TYPE.DANGER,
          title: 'Error',
          textBody: err.detail?.message || 'Failed to update',
        });
      },
    });
  };

  const onNotReceived = () =>
    updateState('set-pallet-not-received', 'Marked Not Received', (prev) => ({
      ...prev,
      number_pallets_state_not_received: prev.number_pallets_state_not_received + 1,
    }));

  const onUndo = () =>
    updateState('undo-pallet-not-received', 'Undo Not Received', (prev) => ({
      ...prev,
      number_pallets_state_not_received: prev.number_pallets_state_not_received - 1,
    }));

  return (
    <View style={{ marginBottom: 12 }}>
      <View style={{ position: 'relative' }}>
        {/* Action Backgrounds */}
        <View style={[styles.list.card, { position: 'absolute', width: '100%', flexDirection: 'row' }]}>
          {/* Left Action */}
          <View
            style={[
              styles.button_swipe_danger,
              {
                borderTopLeftRadius: styles.list.card.borderRadius,
                borderBottomLeftRadius: styles.list.card.borderRadius,
                width: MAX_SWIPE,
                justifyContent: 'center',
                alignItems: 'center',
              },
            ]}
          >
            <FontAwesomeIcon
              icon={item.state !== 'not_received' ? faTimes : faHistory}
              size={20}
              color={item.state !== 'not_received' ? 'red' : 'black'}
            />
          </View>

          {/* Spacer to fill middle */}
          <View style={{ flex: 1 }} />

          {/* Right Action */}
          <View
            style={[
              styles.button_swipe_primary,
              {
                borderTopRightRadius: styles.list.card.borderRadius,
                borderBottomRightRadius: styles.list.card.borderRadius,
                width: MAX_SWIPE,
                justifyContent: 'center',
                alignItems: 'center',
              },
            ]}
          >
            <FontAwesomeIcon icon={faInventory} size={20} color="#615FFF" />
          </View>
        </View>

        {/* Swipeable Foreground */}
        <Animated.View
          {...panResponder.panHandlers}
          style={[
            {
              transform: [{ translateX }],
              zIndex: 2,
            },
            styles.list.card,
          ]}
        >
          <View style={styles.list.container}>
            <View style={styles.list.avatarContainer}>
              {item?.state_icon && (
                <FontAwesomeIcon icon={item.state_icon.icon} size={24} color={item.state_icon.color} />
              )}
            </View>

            <View style={styles.list.textContainer}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                <Text style={styles.list.title}>{item.customer_reference || 'N/A'}</Text>
                <Text style={styles.list.title}>{item.location_code || '-'}</Text>
              </View>
              <Text style={styles.list.description}>{item.reference}</Text>
            </View>
          </View>
        </Animated.View>
      </View>

      {/* Modal Move Pallet */}
      <SimpleModal isVisible={showModal} title="Move Pallet" onClose={() => setShowModal(false)}>
        <Text>Feature for moving to location will appear here.</Text>
      </SimpleModal>
    </View>
  );
};

export default GroupItem;
