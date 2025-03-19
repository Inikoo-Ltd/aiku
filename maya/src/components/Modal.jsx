import React from 'react';
import { View, Text, TouchableOpacity, Keyboard, Modal, ScrollView  } from 'react-native';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { faTimesCircle } from '@/private/fa/pro-light-svg-icons';

const CustomModal = ({ isVisible, onClose = () => null, title = '', children }) => {
  return (
    <Modal
      visible={isVisible}
      transparent
      animationType="fade"
      onRequestClose={()=>{onClose(), Keyboard.dismiss()}}
      statusBarTranslucent={true}
    >
      <View className="flex-1 justify-center items-center bg-black/50">
        <View className="bg-white p-5 rounded-lg items-center w-4/5 relative">
          {/* Close Button */}
          <TouchableOpacity onPress={onClose} className="absolute top-2 right-2 p-1">
            <FontAwesomeIcon icon={faTimesCircle} />
          </TouchableOpacity>
          
          {/* Title */}
          {title ? <Text className="text-lg font-bold mb-2">{title}</Text> : null}
          
          {/* Content */}
         {children}
        </View>
      </View>
    </Modal>
  );
};

export default CustomModal;
