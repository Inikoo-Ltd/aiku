import { StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';

const FormTextInput = ({
  label,
  value,
  onChangeText,
  placeholder,
  keyboardType = 'default',
  autoCapitalize = 'none',
  secure = false,
  showToggle = false,
  isPasswordVisible = false,
  onToggleVisibility = () => {},
}) => {
  return (
    <View style={styles.inputGroup}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        style={styles.input}
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        keyboardType={keyboardType}
        autoCapitalize={autoCapitalize}
        secureTextEntry={secure && !isPasswordVisible}
      />
      {showToggle && (
        <TouchableOpacity style={styles.toggle} onPress={onToggleVisibility}>
          <Text style={styles.toggleText}>
            {isPasswordVisible ? 'Hide' : 'Show'}
          </Text>
        </TouchableOpacity>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  inputGroup: {
    marginBottom: 16,
    position: 'relative',
  },
  label: {
    color: '#4B5563',
    marginBottom: 4,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  toggle: {
    position: 'absolute',
    right: 12,
    bottom: 12,
  },
  toggleText: {
    color: '#007BFF',
    fontSize: 12,
  },
});

export default FormTextInput;
