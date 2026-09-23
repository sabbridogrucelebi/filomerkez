import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, ActivityIndicator, KeyboardAvoidingView, Platform, Alert, Dimensions, Animated, Keyboard } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import SpaceWaves from '../components/SpaceWaves';

const { width } = Dimensions.get('window');

export default function ForgotPasswordScreen({ navigation }) {
    const [email, setEmail] = useState('');
    const [focusedInput, setFocusedInput] = useState(null);
    const [isLoading, setIsLoading] = useState(false);

    const handleReset = async () => {
        if (!email) {
            Alert.alert('Uyarı', 'Lütfen e-posta adresinizi girin.');
            return;
        }
        setIsLoading(true);
        // Simulate API call
        setTimeout(() => {
            setIsLoading(false);
            Alert.alert('Başarılı', 'Şifre sıfırlama bağlantısı e-posta adresinize gönderildi.', [
                { text: 'Tamam', onPress: () => navigation.goBack() }
            ]);
        }, 1500);
    };

    return (
        <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : null}>
            <View style={styles.container}>
                <SpaceWaves />
                
                <SafeAreaView style={styles.safeArea}>
                    <Animated.View style={styles.contentWrap}>
                        
                        <View style={styles.headerArea}>
                            <View style={styles.logoWrap}>
                                <View style={styles.logoIcon}><Text style={{fontSize: 24}}>🚀</Text></View>
                                <Text style={styles.brandWhite}>Servis<Text style={styles.brandPurple}>Pilot</Text></Text>
                            </View>
                        </View>

                        <View style={styles.card}>
                            <TouchableOpacity style={styles.backBtn} onPress={() => navigation.goBack()}>
                                <Icon name="arrow-left" size={24} color="#94A3B8" />
                            </TouchableOpacity>

                            <Text style={styles.cardTitle}>Şifrenizi Mi Unuttunuz?</Text>
                            <Text style={styles.cardDesc}>E-posta adresinizi girin, size şifrenizi sıfırlamanız için bir bağlantı gönderelim.</Text>

                            <Text style={styles.inputLabel}>E-posta Adresi</Text>
                            <View style={[styles.inputWrap, focusedInput === 'email' && styles.inputWrapFocused]}>
                                <TextInput 
                                    style={styles.input} 
                                    placeholder="E-posta adresiniz..." 
                                    placeholderTextColor="#64748B"
                                    value={email} 
                                    onChangeText={setEmail} 
                                    autoCapitalize="none"
                                    keyboardType="email-address"
                                    onFocus={() => setFocusedInput('email')}
                                    onBlur={() => setFocusedInput(null)}
                                />
                            </View>

                            <TouchableOpacity 
                                style={[styles.resetBtn, isLoading && styles.resetBtnDisabled]} 
                                onPress={handleReset} 
                                disabled={isLoading}
                                activeOpacity={0.8}
                            >
                                {isLoading ? <ActivityIndicator color="#fff" /> : <Text style={styles.resetBtnTxt}>Sıfırlama Bağlantısı Gönder</Text>}
                            </TouchableOpacity>

                            <TouchableOpacity style={styles.bottomLink} onPress={() => navigation.goBack()}>
                                <Text style={styles.bottomLinkTxt}>Giriş ekranına dön</Text>
                            </TouchableOpacity>
                        </View>
                    </Animated.View>
                </SafeAreaView>
            </View>
        </KeyboardAvoidingView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    safeArea: { flex: 1, justifyContent: 'center', paddingHorizontal: 20 },
    contentWrap: { flex: 1, justifyContent: 'center' },
    
    headerArea: { marginBottom: 30, alignItems: 'center' },
    logoWrap: { flexDirection: 'row', alignItems: 'center' },
    logoIcon: { width: 52, height: 52, backgroundColor: '#1E293B', borderRadius: 16, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.4)', shadowColor: '#8B5CF6', shadowOffset: { width: 0, height: 4 }, shadowopacity: 1, shadowRadius: 10, elevation: 8 },
    brandWhite: { fontSize: 32, fontWeight: '900', color: '#fff', marginLeft: 14, letterSpacing: -1 },
    brandPurple: { color: '#8B5CF6' },

    card: { backgroundColor: 'rgba(15, 23, 42, 0.8)', borderRadius: 32, padding: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)', shadowColor: '#000', shadowOffset: { width: 0, height: 20 }, shadowOpacity: 0.4, shadowRadius: 30, elevation: 20 },
    backBtn: { width: 40, height: 40, borderRadius: 12, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', justifyContent: 'center', marginBottom: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    cardTitle: { fontSize: 24, fontWeight: '900', color: '#fff', marginBottom: 8, letterSpacing: -0.5 },
    cardDesc: { fontSize: 13, color: '#94A3B8', marginBottom: 28, lineHeight: 20 },

    inputLabel: { fontSize: 12, fontWeight: '700', color: '#94A3B8', letterSpacing: 0.5, marginBottom: 8 },
    inputWrap: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(2, 6, 23, 0.6)', borderRadius: 16, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)', marginBottom: 24 },
    inputWrapFocused: { borderColor: '#8B5CF6', backgroundColor: 'rgba(139, 92, 246, 0.05)' },
    input: { flex: 1, paddingHorizontal: 16, paddingVertical: 16, color: '#fff', fontSize: 15 },

    resetBtn: { backgroundColor: '#6366F1', paddingVertical: 16, borderRadius: 16, alignItems: 'center', shadowColor: '#6366F1', shadowOffset: { width: 0, height: 8 }, shadowOpacity: 0.4, shadowRadius: 15, elevation: 8, marginBottom: 20 },
    resetBtnDisabled: { opacity: 0.7 },
    resetBtnTxt: { color: '#fff', fontSize: 15, fontWeight: '800', letterSpacing: 0.5 },

    bottomLink: { alignItems: 'center', paddingVertical: 10 },
    bottomLinkTxt: { fontSize: 14, fontWeight: '700', color: '#8B5CF6' }
});
