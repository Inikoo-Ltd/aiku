import AsyncStorage from '@react-native-async-storage/async-storage';

// export const storeData = async (key, value) => {
//   try {
//     await AsyncStorage.setItem(key, value);
//   } catch (e) {
//     console.error('Error storing data', e);
//   }
// };


export const getData = async (key) => {
  try {
    const jsonValue = await AsyncStorage.getItem(key);
    return jsonValue != null ? JSON.parse(jsonValue) : null;
  } catch (e) {
    console.error('Failed to load from storage:', e);
    return null;
  }
};

export const storeData = async (key, value) => {
  try {
    const jsonValue = JSON.stringify(value); // ✅ serialize it
    await AsyncStorage.setItem(key, jsonValue);
  } catch (e) {
    console.error('Error storing data', e);
  }
};
