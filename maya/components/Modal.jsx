// components/Modal.jsx
import { faTimes } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import {
    Pressable,
    Modal as RNModal,
    Text,
    View,
} from 'react-native';

const Modal = ({ isVisible, onClose, title, children }) => {
  return (
    <RNModal
      visible={isVisible}
      transparent
      animationType="fade"
      onRequestClose={onClose}
    >
      <View className="flex-1 bg-black/50 justify-center items-center px-4">
        <View className="w-full max-w-xl bg-white dark:bg-gray-900 rounded-xl p-6 shadow-lg">
          <View className="flex-row justify-between items-center mb-4">
            <Text className="text-lg font-bold text-gray-900 dark:text-white">
              {title}
            </Text>
            <Pressable onPress={onClose}>
              <FontAwesomeIcon icon={faTimes} size={18} color="#9CA3AF" />
            </Pressable>
          </View>
          <View>{children}</View>
        </View>
      </View>
    </RNModal>
  );
};

export default Modal;
