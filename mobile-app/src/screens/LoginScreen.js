import React, { useContext, useState, useRef, useEffect } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, ActivityIndicator, KeyboardAvoidingView, Platform, Alert, Dimensions, Animated, Keyboard, Easing, Linking } from 'react-native';
import { AuthContext } from '../context/AuthContext';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import SpaceWaves from '../components/SpaceWaves';
import { Image } from 'react-native';

const { width } = Dimensions.get('window');

export default function LoginScreen({ navigation }) {
    const [loginStep, setLoginStep] = useState('choose'); // 'choose' | 'form'
    const [loginType, setLoginType] = useState(null); // 'personnel' | 'parent' | 'driver'
    
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [rememberMe, setRememberMe] = useState(false);
    const [focusedInput, setFocusedInput] = useState(null);
    const { login, isLoading } = useContext(AuthContext);

    // Animasyonlar
    const fadeAnim = useRef(new Animated.Value(0)).current;
    const slideAnim = useRef(new Animated.Value(30)).current;
    const formFadeAnim = useRef(new Animated.Value(0)).current;
    const formSlideAnim = useRef(new Animated.Value(20)).current;
    
    // Bounce animasyonları için
    const scaleAnim1 = useRef(new Animated.Value(1)).current;
    const scaleAnim2 = useRef(new Animated.Value(1)).current;
    const scaleAnim3 = useRef(new Animated.Value(1)).current;

    const [isKeyboardVisible, setKeyboardVisible] = useState(false);

    useEffect(() => {
        Animated.parallel([
            Animated.timing(fadeAnim, { toValue: 1, duration: 800, useNativeDriver: true }),
            Animated.timing(slideAnim, { toValue: 0, duration: 800, useNativeDriver: true })
        ]).start();

        const kS = Keyboard.addListener(Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow', () => setKeyboardVisible(true));
        const kH = Keyboard.addListener(Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide', () => setKeyboardVisible(false));

        return () => { kS.remove(); kH.remove(); };
    }, []);

    const handleSelectType = (type, scaleAnim) => {
        Animated.sequence([
            Animated.timing(scaleAnim, { toValue: 0.95, duration: 100, useNativeDriver: true }),
            Animated.timing(scaleAnim, { toValue: 1, duration: 100, useNativeDriver: true })
        ]).start(() => {
            setLoginType(type);
            setEmail('');
            setPassword('');
            setLoginStep('form');
            
            // Formu gösterirken animasyon
            formFadeAnim.setValue(0);
            formSlideAnim.setValue(20);
            Animated.parallel([
                Animated.timing(formFadeAnim, { toValue: 1, duration: 400, useNativeDriver: true }),
                Animated.timing(formSlideAnim, { toValue: 0, duration: 400, easing: Easing.out(Easing.ease), useNativeDriver: true })
            ]).start();
        });
    };

    const handleBack = () => {
        setLoginStep('choose');
    };

    const handleLogin = async () => {
        if (!email || !password) {
            Alert.alert('Uyarı', 'Lütfen kimlik bilgilerinizi girin.');
            return;
        }
        try {
            await login(email, password);
        } catch (error) {
            Alert.alert('Giriş Başarısız', error.response?.data?.message || 'Bir hata oluştu.');
        }
    };

    const getFormTitle = () => {
        if (loginType === 'personnel') return 'Kurumsal Giriş';
        if (loginType === 'parent') return 'Öğrenci / Veli Girişi';
        if (loginType === 'driver') return 'Araç Girişi';
    };

    const getInputLabel = () => {
        if (loginType === 'parent' || loginType === 'driver') return 'Telefon Numaranız';
        return 'E-posta veya Kullanıcı Adı';
    };

    return (
        <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : null}>
            <View style={styles.container}>
                <SpaceWaves />
                
                <SafeAreaView style={styles.safeArea}>
                    <Animated.View style={[styles.contentWrap, { opacity: fadeAnim, transform: [{ translateY: slideAnim }] }]}>
                        
                        {/* Başlık ve Logo Alanı */}
                        {!isKeyboardVisible && (
                            <View style={styles.headerArea}>
                                <View style={styles.logoWrap}>
                                    <Image source={require('../../assets/icon.png')} style={styles.logoImage} />
                                    <Text style={styles.brandWhite}>Filo<Text style={styles.brandPurple}>MERKEZ</Text></Text>
                                </View>
                            </View>
                        )}

                        {loginStep === 'choose' ? (
                            <View style={styles.chooseCard}>
                                <Text style={styles.chooseTitle}>Giriş Yöntemi Seçin</Text>
                                <Text style={styles.chooseDesc}>Uygulamaya hangi profille giriş yapmak istediğinizi seçin.</Text>
                                
                                <View style={styles.optionsWrap}>
                                    <Animated.View style={{ transform: [{ scale: scaleAnim1 }] }}>
                                        <TouchableOpacity activeOpacity={1} onPress={() => handleSelectType('personnel', scaleAnim1)} style={styles.optionBox}>
                                            <LinearGradient colors={['#6366F1', '#4F46E5']} style={styles.optionIconWrap}>
                                                <Icon name="domain" size={28} color="#fff" />
                                            </LinearGradient>
                                            <View style={styles.optionTextWrap}>
                                                <Text style={styles.optionTitle}>Kurumsal Giriş</Text>
                                                <Text style={styles.optionSub}>Yönetici ve Şirket personeli</Text>
                                            </View>
                                            <Icon name="chevron-right" size={24} color="#64748B" />
                                        </TouchableOpacity>
                                    </Animated.View>

                                    <Animated.View style={{ transform: [{ scale: scaleAnim2 }] }}>
                                        <TouchableOpacity activeOpacity={1} onPress={() => handleSelectType('parent', scaleAnim2)} style={styles.optionBox}>
                                            <LinearGradient colors={['#8B5CF6', '#7C3AED']} style={styles.optionIconWrap}>
                                                <Icon name="account-school" size={28} color="#fff" />
                                            </LinearGradient>
                                            <View style={styles.optionTextWrap}>
                                                <Text style={styles.optionTitle}>Öğrenci Girişi</Text>
                                                <Text style={styles.optionSub}>Veliler ve Öğrenciler için</Text>
                                            </View>
                                            <Icon name="chevron-right" size={24} color="#64748B" />
                                        </TouchableOpacity>
                                    </Animated.View>

                                    <Animated.View style={{ transform: [{ scale: scaleAnim3 }] }}>
                                        <TouchableOpacity activeOpacity={1} onPress={() => handleSelectType('driver', scaleAnim3)} style={styles.optionBox}>
                                            <LinearGradient colors={['#38BDF8', '#0284C7']} style={styles.optionIconWrap}>
                                                <Icon name="bus" size={28} color="#fff" />
                                            </LinearGradient>
                                            <View style={styles.optionTextWrap}>
                                                <Text style={styles.optionTitle}>Araç Girişi</Text>
                                                <Text style={styles.optionSub}>Şoför ve Hostesler için</Text>
                                            </View>
                                            <Icon name="chevron-right" size={24} color="#64748B" />
                                        </TouchableOpacity>
                                    </Animated.View>
                                </View>

                                <TouchableOpacity 
                                    onPress={() => Linking.openURL('https://mehmetcelebiturizm.com/app/privacy-policy')} 
                                    style={{ marginTop: 20, alignItems: 'center' }}
                                >
                                    <Text style={{ color: '#94A3B8', fontSize: 12, textDecorationLine: 'underline' }}>
                                        Gizlilik Politikası ve KVKK Aydınlatma Metni
                                    </Text>
                                </TouchableOpacity>
                            </View>
                        ) : (
                            <Animated.View style={[styles.card, { opacity: formFadeAnim, transform: [{ translateY: formSlideAnim }] }]}>
                                <TouchableOpacity onPress={handleBack} style={styles.backBtn}>
                                    <Icon name="arrow-left" size={20} color="#94A3B8" />
                                    <Text style={styles.backTxt}>Geri Dön</Text>
                                </TouchableOpacity>

                                <Text style={styles.cardTitle}>{getFormTitle()}</Text>
                                <Text style={styles.cardDesc}>Devam etmek için giriş bilgilerinizi yazın.</Text>

                                <Text style={styles.inputLabel}>{getInputLabel()}</Text>
                                <View style={[styles.inputWrap, focusedInput === 'email' && styles.inputWrapFocused]}>
                                    <TextInput 
                                        style={styles.input} 
                                        placeholder={loginType === 'parent' || loginType === 'driver' ? 'Örn: 0555...' : 'E-posta veya Kullanıcı Adı'} 
                                        placeholderTextColor="#64748B"
                                        value={email} 
                                        onChangeText={setEmail} 
                                        autoCapitalize="none"
                                        keyboardType={loginType === 'parent' || loginType === 'driver' ? 'phone-pad' : 'email-address'}
                                        onFocus={() => setFocusedInput('email')}
                                        onBlur={() => setFocusedInput(null)}
                                    />
                                </View>

                                <View style={styles.labelRow}>
                                    <Text style={styles.inputLabel}>Şifre</Text>
                                    <TouchableOpacity onPress={() => navigation.navigate('ForgotPassword')} activeOpacity={0.8}>
                                        <Text style={styles.forgotTxt}>Şifremi Unuttum</Text>
                                    </TouchableOpacity>
                                </View>
                                <View style={[styles.inputWrap, focusedInput === 'password' && styles.inputWrapFocused]}>
                                    <TextInput 
                                        style={styles.input} 
                                        placeholder="••••••••" 
                                        placeholderTextColor="#64748B"
                                        value={password} 
                                        onChangeText={setPassword} 
                                        secureTextEntry={!showPassword}
                                        onFocus={() => setFocusedInput('password')}
                                        onBlur={() => setFocusedInput(null)}
                                    />
                                    <TouchableOpacity onPress={() => setShowPassword(!showPassword)} style={styles.eyeBtn}>
                                        <Icon name={showPassword ? "eye-off-outline" : "eye-outline"} size={20} color="#64748B" />
                                    </TouchableOpacity>
                                </View>

                                <TouchableOpacity style={styles.rememberRow} onPress={() => setRememberMe(!rememberMe)} activeOpacity={0.8}>
                                    <View style={[styles.checkbox, rememberMe && styles.checkboxActive]}>
                                        {rememberMe && <Icon name="check" size={14} color="#fff" />}
                                    </View>
                                    <Text style={styles.rememberTxt}>Beni hatırla</Text>
                                </TouchableOpacity>

                                <TouchableOpacity 
                                    style={[styles.loginBtn, isLoading && styles.loginBtnDisabled]} 
                                    onPress={handleLogin} 
                                    disabled={isLoading}
                                    activeOpacity={0.8}
                                >
                                    {isLoading ? <ActivityIndicator color="#fff" /> : <Text style={styles.loginBtnTxt}>Giriş Yap</Text>}
                                </TouchableOpacity>

                                <TouchableOpacity 
                                    onPress={() => Linking.openURL('https://mehmetcelebiturizm.com/app/privacy-policy')} 
                                    style={{ marginTop: 20, alignItems: 'center' }}
                                >
                                    <Text style={{ color: '#94A3B8', fontSize: 12, textDecorationLine: 'underline' }}>
                                        Gizlilik Politikası ve KVKK Aydınlatma Metni
                                    </Text>
                                </TouchableOpacity>
                            </Animated.View>
                        )}
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
    logoImage: { width: 52, height: 52, borderRadius: 16, borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.4)' },
    brandWhite: { fontSize: 32, fontWeight: '900', color: '#fff', marginLeft: 14, letterSpacing: -1 },
    brandPurple: { color: '#8B5CF6' },

    chooseCard: { backgroundColor: 'rgba(15, 23, 42, 0.8)', borderRadius: 32, padding: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)', shadowColor: '#000', shadowOffset: { width: 0, height: 20 }, shadowOpacity: 0.4, shadowRadius: 30, elevation: 20 },
    chooseTitle: { fontSize: 22, fontWeight: '900', color: '#fff', marginBottom: 6, textAlign: 'center' },
    chooseDesc: { fontSize: 13, color: '#94A3B8', marginBottom: 24, textAlign: 'center', paddingHorizontal: 10 },
    optionsWrap: { gap: 12 },
    optionBox: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(2, 6, 23, 0.5)', padding: 12, borderRadius: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    optionIconWrap: { width: 52, height: 52, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
    optionTextWrap: { flex: 1, marginLeft: 16 },
    optionTitle: { color: '#fff', fontSize: 16, fontWeight: '800', marginBottom: 2 },
    optionSub: { color: '#94A3B8', fontSize: 12, fontWeight: '500' },

    card: { backgroundColor: 'rgba(15, 23, 42, 0.8)', borderRadius: 32, padding: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)', shadowColor: '#000', shadowOffset: { width: 0, height: 20 }, shadowOpacity: 0.4, shadowRadius: 30, elevation: 20 },
    backBtn: { flexDirection: 'row', alignItems: 'center', marginBottom: 20, alignSelf: 'flex-start', paddingVertical: 4, paddingRight: 10 },
    backTxt: { color: '#94A3B8', fontSize: 14, fontWeight: '700', marginLeft: 6 },
    cardTitle: { fontSize: 24, fontWeight: '900', color: '#fff', marginBottom: 6, letterSpacing: -0.5 },
    cardDesc: { fontSize: 13, color: '#94A3B8', marginBottom: 24 },

    labelRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8, marginTop: 16 },
    inputLabel: { fontSize: 12, fontWeight: '700', color: '#94A3B8', letterSpacing: 0.5, marginBottom: 8 },
    forgotTxt: { fontSize: 12, fontWeight: '700', color: '#8B5CF6', letterSpacing: 0.2 },
    
    inputWrap: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(2, 6, 23, 0.6)', borderRadius: 16, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    inputWrapFocused: { borderColor: '#8B5CF6', backgroundColor: 'rgba(139, 92, 246, 0.05)' },
    input: { flex: 1, paddingHorizontal: 16, paddingVertical: 16, color: '#fff', fontSize: 15 },
    eyeBtn: { padding: 16 },

    rememberRow: { flexDirection: 'row', alignItems: 'center', marginTop: 16, marginBottom: 24 },
    checkbox: { width: 20, height: 20, borderRadius: 6, borderWidth: 1, borderColor: '#334155', backgroundColor: 'rgba(2, 6, 23, 0.6)', alignItems: 'center', justifyContent: 'center', marginRight: 10 },
    checkboxActive: { backgroundColor: '#8B5CF6', borderColor: '#8B5CF6' },
    rememberTxt: { color: '#E2E8F0', fontSize: 13, fontWeight: '500' },

    loginBtn: { backgroundColor: '#6366F1', paddingVertical: 16, borderRadius: 16, alignItems: 'center', shadowColor: '#6366F1', shadowOffset: { width: 0, height: 8 }, shadowOpacity: 0.4, shadowRadius: 15, elevation: 8 },
    loginBtnDisabled: { opacity: 0.7 },
    loginBtnTxt: { color: '#fff', fontSize: 15, fontWeight: '800', letterSpacing: 0.5 }
});
