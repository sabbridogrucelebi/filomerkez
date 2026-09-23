import React, { useState, useContext, useEffect } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity, ActivityIndicator, KeyboardAvoidingView, Platform, ScrollView, Modal } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import axios from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import SpaceWaves from '../components/SpaceWaves';

export default function AccountInfoScreen({ navigation }) {
    const { userInfo, refreshMe } = useContext(AuthContext);

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    
    const [isLoading, setIsLoading] = useState(false);
    const [errorMsg, setErrorMsg] = useState('');
    const [showSuccessModal, setShowSuccessModal] = useState(false);

    useEffect(() => {
        if (userInfo) {
            setName(userInfo.name || '');
            setEmail(userInfo.email || '');
        }
    }, [userInfo]);

    const handleUpdate = async () => {
        setErrorMsg('');
        if (!name || !email) {
            setErrorMsg('Lütfen ad ve e-posta alanlarını doldurun.');
            return;
        }

        setIsLoading(true);
        try {
            await axios.put('/user/profile', {
                name: name,
                email: email
            });
            await refreshMe(); // Update the global state with new info
            setShowSuccessModal(true);
        } catch (error) {
            setErrorMsg(error.response?.data?.message || 'Hesap bilgileri güncellenirken bir hata oluştu.');
        } finally {
            setIsLoading(false);
        }
    };

    const handleModalClose = () => {
        setShowSuccessModal(false);
        navigation.goBack();
    };

    const renderInput = (label, icon, value, setValue, placeholder, keyboardType = "default") => (
        <View style={s.inputGroup}>
            <Text style={s.inputLabel}>{label}</Text>
            <View style={s.inputWrap}>
                <View style={s.inputIconWrap}>
                    <Icon name={icon} size={20} color="#64748B" />
                </View>
                <TextInput
                    style={s.input}
                    placeholder={placeholder}
                    placeholderTextColor="#475569"
                    value={value}
                    onChangeText={(val) => { setErrorMsg(''); setValue(val); }}
                    autoCapitalize={keyboardType === 'email-address' ? 'none' : 'words'}
                    keyboardType={keyboardType}
                />
            </View>
        </View>
    );

    const renderReadonly = (label, icon, value) => (
        <View style={s.inputGroup}>
            <Text style={s.inputLabel}>{label}</Text>
            <View style={[s.inputWrap, { backgroundColor: 'rgba(255,255,255,0.02)', borderColor: 'transparent' }]}>
                <View style={s.inputIconWrap}>
                    <Icon name={icon} size={20} color="#64748B" />
                </View>
                <TextInput
                    style={[s.input, { color: '#94A3B8' }]}
                    value={value}
                    editable={false}
                />
            </View>
        </View>
    );

    return (
        <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : null}>
            <View style={s.container}>
                <SpaceWaves />
                <SafeAreaView style={{ flex: 1, paddingTop: Platform.OS === 'android' ? 30 : 40 }}>
                    {/* Header */}
                    <View style={s.header}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={s.backBtn}>
                            <Icon name="arrow-back" size={24} color="#FFF" />
                        </TouchableOpacity>
                        <View style={s.headerTitleWrap}>
                            <Text style={s.headerTitle}>Hesap Bilgileri</Text>
                            <Text style={s.headerSub}>Kişisel bilgilerinizi yönetin</Text>
                        </View>
                    </View>

                    <ScrollView contentContainerStyle={s.scrollContent} keyboardShouldPersistTaps="handled">
                        
                        <View style={s.warningCard}>
                            <View style={s.warningIconWrap}>
                                <Icon name="person-circle-outline" size={24} color="#38BDF8" />
                            </View>
                            <View style={{ flex: 1 }}>
                                <Text style={s.warningTitle}>Profil Detayları</Text>
                                <Text style={s.warningText}>
                                    İsim ve E-posta değişikliğiniz anında sisteme yansır. Yeni e-postanız ile panele giriş yapabilirsiniz.
                                </Text>
                            </View>
                        </View>

                        {errorMsg ? (
                            <View style={s.errorBox}>
                                <Icon name="alert-circle" size={20} color="#EF4444" />
                                <Text style={s.errorText}>{errorMsg}</Text>
                            </View>
                        ) : null}

                        <View style={s.formCard}>
                            {renderReadonly('Bağlı Olduğunuz Firma', 'business-outline', userInfo?.company_name || 'Yükleniyor...')}
                            <View style={s.divider} />
                            {renderInput('Ad Soyad', 'person-outline', name, setName, 'Adınız ve Soyadınız')}
                            <View style={s.divider} />
                            {renderInput('E-posta Adresi', 'mail-outline', email, setEmail, 'ornek@sirket.com', 'email-address')}
                        </View>

                        <TouchableOpacity style={s.submitBtn} onPress={handleUpdate} disabled={isLoading} activeOpacity={0.8}>
                            <LinearGradient colors={['#6366F1', '#4F46E5']} style={s.submitGradient}>
                                {isLoading ? (
                                    <ActivityIndicator color="#FFF" />
                                ) : (
                                    <>
                                        <Icon name="save-outline" size={22} color="#FFF" style={{ marginRight: 8 }} />
                                        <Text style={s.submitText}>Bilgileri Güncelle</Text>
                                    </>
                                )}
                            </LinearGradient>
                        </TouchableOpacity>

                        <View style={{ height: 120 }} />
                    </ScrollView>

                    {/* Premium Success Modal */}
                    <Modal visible={showSuccessModal} transparent animationType="fade">
                        <View style={s.modalOverlay}>
                            <View style={s.modalCard}>
                                <View style={s.modalIconWrap}>
                                    <View style={s.modalIconBg}>
                                        <Icon name="checkmark-done" size={40} color="#10B981" />
                                    </View>
                                </View>
                                <Text style={s.modalTitle}>Tebrikler!</Text>
                                <Text style={s.modalDesc}>Hesap bilgileriniz başarıyla güncellendi.</Text>
                                
                                <TouchableOpacity style={s.modalBtn} onPress={handleModalClose} activeOpacity={0.8}>
                                    <LinearGradient colors={['#6366F1', '#4F46E5']} style={s.modalBtnGradient}>
                                        <Text style={s.modalBtnText}>Harika, Devam Et</Text>
                                    </LinearGradient>
                                </TouchableOpacity>
                            </View>
                        </View>
                    </Modal>

                </SafeAreaView>
            </View>
        </KeyboardAvoidingView>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    
    header: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 20, paddingTop: 20, paddingBottom: 20 },
    backBtn: { width: 44, height: 44, borderRadius: 22, backgroundColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)', marginRight: 15 },
    headerTitleWrap: { flex: 1 },
    headerTitle: { fontSize: 24, fontWeight: '900', color: '#FFFFFF', letterSpacing: -0.5 },
    headerSub: { fontSize: 12, color: '#94A3B8', fontWeight: '600', marginTop: 2, letterSpacing: 0.2 },

    scrollContent: { paddingHorizontal: 20, paddingTop: 10 },

    warningCard: { flexDirection: 'row', backgroundColor: 'rgba(56, 189, 248, 0.1)', padding: 16, borderRadius: 20, marginBottom: 24, borderWidth: 1, borderColor: 'rgba(56, 189, 248, 0.2)' },
    warningIconWrap: { width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(56, 189, 248, 0.2)', alignItems: 'center', justifyContent: 'center', marginRight: 12 },
    warningTitle: { fontSize: 14, fontWeight: '800', color: '#7DD3FC', marginBottom: 4 },
    warningText: { fontSize: 13, color: '#BAE6FD', lineHeight: 18 },

    errorBox: { flexDirection: 'row', backgroundColor: 'rgba(239, 68, 68, 0.1)', padding: 16, borderRadius: 16, marginBottom: 20, alignItems: 'center', borderWidth: 1, borderColor: 'rgba(239, 68, 68, 0.3)' },
    errorText: { marginLeft: 10, fontSize: 13, color: '#FCA5A5', fontWeight: '600', flex: 1 },

    formCard: { backgroundColor: 'rgba(15, 23, 42, 0.7)', borderRadius: 24, padding: 20, shadowColor: '#000', shadowOffset: { width: 0, height: 20 }, shadowopacity: 1, shadowRadius: 40, elevation: 15, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', marginBottom: 24 },
    divider: { height: 1, backgroundColor: 'rgba(255,255,255,0.05)', marginVertical: 20, marginHorizontal: -20 },
    
    inputGroup: { marginBottom: 16 },
    inputLabel: { fontSize: 13, fontWeight: '700', color: '#94A3B8', marginBottom: 8, marginLeft: 4 },
    inputWrap: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(2, 6, 23, 0.6)', borderRadius: 16, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)', height: 56 },
    inputIconWrap: { width: 48, alignItems: 'center', justifyContent: 'center' },
    input: { flex: 1, fontSize: 15, color: '#FFFFFF', fontWeight: '500' },

    submitBtn: { borderRadius: 20, overflow: 'hidden', shadowColor: '#6366F1', shadowOffset: { width: 0, height: 8 }, shadowOpacity: 0.4, shadowRadius: 15, elevation: 8 },
    submitGradient: { flexDirection: 'row', paddingVertical: 18, alignItems: 'center', justifyContent: 'center' },
    submitText: { fontSize: 16, fontWeight: '800', color: '#FFF', letterSpacing: 0.5 },

    modalOverlay: { flex: 1, backgroundColor: 'rgba(2, 6, 23, 0.8)', justifyContent: 'center', alignItems: 'center', padding: 20 },
    modalCard: { width: '100%', maxWidth: 360, backgroundColor: '#0F172A', borderRadius: 32, padding: 30, alignItems: 'center', shadowColor: '#000', shadowOffset: { width: 0, height: 20 }, shadowopacity: 1, shadowRadius: 40, elevation: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    modalIconWrap: { marginBottom: 20 },
    modalIconBg: { width: 80, height: 80, borderRadius: 40, backgroundColor: 'rgba(16, 185, 129, 0.1)', alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: '#10B981' },
    modalTitle: { fontSize: 26, fontWeight: '900', color: '#FFFFFF', marginBottom: 12, letterSpacing: -0.5 },
    modalDesc: { fontSize: 14, color: '#94A3B8', textAlign: 'center', lineHeight: 22, marginBottom: 30 },
    modalBtn: { width: '100%', borderRadius: 16, overflow: 'hidden' },
    modalBtnGradient: { paddingVertical: 18, alignItems: 'center', justifyContent: 'center' },
    modalBtnText: { color: '#FFF', fontSize: 16, fontWeight: '800' }
});
