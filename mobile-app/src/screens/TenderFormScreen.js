import React, { useState, useEffect, useRef } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Alert, ActivityIndicator, Platform, Animated, Easing, KeyboardAvoidingView } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import api from '../api/axios';
import { FormField, DatePickerInput } from '../components';
import { Picker } from '@react-native-picker/picker';
import * as DocumentPicker from 'expo-document-picker';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';

export default function TenderFormScreen({ route, navigation }) {
    const { tenderId } = route.params || {};
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    
    const [formData, setFormData] = useState({
        institution_name: '',
        tender_date: new Date(),
        tender_registration_number: '',
        vehicle_details: '',
        duration_days: '',
        approximate_cost: '',
        our_bid: '',
        winning_company: '',
        winning_amount: '',
        status: 'Değerlendirmede',
        notes: ''
    });

    const [file, setFile] = useState(null);

    // Animations
    const blob1Anim = useRef(new Animated.Value(0)).current;
    const blob2Anim = useRef(new Animated.Value(0)).current;

    useEffect(() => {
        const loop = Animated.loop(Animated.sequence([
            Animated.timing(blob1Anim, { toValue: 1, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
            Animated.timing(blob1Anim, { toValue: 0, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
        ]));
        const loop2 = Animated.loop(Animated.sequence([
            Animated.timing(blob2Anim, { toValue: 1, duration: 11000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
            Animated.timing(blob2Anim, { toValue: 0, duration: 11000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
        ]));
        loop.start(); loop2.start();
        return () => { loop.stop(); loop2.stop(); };
    }, []);

    useEffect(() => {
        if (tenderId) {
            fetchTender();
        }
    }, [tenderId]);

    const fetchTender = async () => {
        setLoading(true);
        try {
            const r = await api.get(`/v1/tenders/${tenderId}`);
            if (r.data.success) {
                const d = r.data.data;
                setFormData({
                    institution_name: d.institution_name || '',
                    tender_date: d.tender_date ? new Date(d.tender_date) : new Date(),
                    tender_registration_number: d.tender_registration_number || '',
                    vehicle_details: d.vehicle_details || '',
                    duration_days: d.duration_days ? String(d.duration_days) : '',
                    approximate_cost: d.approximate_cost ? String(d.approximate_cost) : '',
                    our_bid: d.our_bid ? String(d.our_bid) : '',
                    winning_company: d.winning_company || '',
                    winning_amount: d.winning_amount ? String(d.winning_amount) : '',
                    status: d.status || 'Değerlendirmede',
                    notes: d.notes || ''
                });
            }
        } catch (e) {
            Alert.alert('Hata', 'İhale bilgileri alınamadı.');
            navigation.goBack();
        } finally {
            setLoading(false);
        }
    };

    const pickDocument = async () => {
        try {
            const result = await DocumentPicker.getDocumentAsync({
                type: 'application/pdf',
                copyToCacheDirectory: true,
            });
            if (!result.canceled && result.assets && result.assets.length > 0) {
                setFile(result.assets[0]);
            }
        } catch (err) {
            console.error(err);
        }
    };

    const handleSave = async () => {
        if (!formData.institution_name || !formData.tender_date || !formData.status) {
            Alert.alert('Eksik Bilgi', 'Kurum Adı, İhale Tarihi ve Durum zorunludur.');
            return;
        }

        setSaving(true);
        try {
            const data = new FormData();
            Object.keys(formData).forEach(key => {
                if (key === 'tender_date') {
                    data.append('tender_date', formData[key].toISOString().split('T')[0]);
                } else if (formData[key]) {
                    data.append(key, formData[key]);
                }
            });

            if (tenderId) {
                data.append('_method', 'PUT');
            }

            if (file) {
                const fName = file.name || file.uri.split('/').pop();
                data.append('document', { uri: file.uri, name: fName, type: 'application/pdf' });
            }

            if (tenderId) {
                await api.post(`/v1/tenders/${tenderId}`, data, { headers: { 'Content-Type': 'multipart/form-data' }});
                Alert.alert('Başarılı', 'İhale güncellendi.');
            } else {
                await api.post('/v1/tenders', data, { headers: { 'Content-Type': 'multipart/form-data' }});
                Alert.alert('Başarılı', 'Yeni ihale eklendi.');
            }
            navigation.goBack();
        } catch (e) {
            Alert.alert('Hata', 'Kaydedilemedi: ' + (e.response?.data?.message || e.message));
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <View style={st.loader}><ActivityIndicator size="large" color="#38BDF8" /></View>
        );
    }

    return (
        <View style={st.container}>
            {/* Animated Background */}
            <Animated.View style={StyleSheet.absoluteFill}>
                <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
                <Animated.View style={[st.bgBlob1, { transform: [{ translateY: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[0, 60] }) }] }]} />
                <Animated.View style={[st.bgBlob2, { transform: [{ translateX: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[0, -60] }) }] }]} />
            </Animated.View>

            <SafeAreaView style={{ flex: 1 }} edges={['top']}>
                <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : 'height'} keyboardVerticalOffset={0}>
                <View style={st.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={st.backBtn}>
                        <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                        <Icon name="chevron-left" size={28} color="#FFF" />
                    </TouchableOpacity>
                    <Text style={st.headerTitle}>{tenderId ? 'İhale Düzenle' : 'Yeni İhale Ekle'}</Text>
                    <View style={{ width: 44 }} />
                </View>

                <ScrollView contentContainerStyle={st.formContainer} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
                    
                    <BlurView intensity={30} tint="dark" style={st.card}>
                        <Text style={st.sectionTitle}>Genel Bilgiler</Text>
                        
                        <Text style={st.label}>KURUM / İHALE ADI *</Text>
                        <View style={st.inputWrapper}>
                            <FormField value={formData.institution_name} onChangeText={t => setFormData({...formData, institution_name: t})} placeholder="Örn: DSİ Taşımacılık İhalesi" />
                        </View>

                        <Text style={st.label}>İHALE TARİHİ *</Text>
                        <View style={st.inputWrapper}>
                            <DatePickerInput value={formData.tender_date} onChange={d => setFormData({...formData, tender_date: d})} />
                        </View>

                        <Text style={st.label}>İHALE KAYIT NO (İKN)</Text>
                        <View style={st.inputWrapper}>
                            <FormField value={formData.tender_registration_number} onChangeText={t => setFormData({...formData, tender_registration_number: t})} placeholder="Örn: 2025/12345" />
                        </View>

                        <Text style={st.label}>ARAÇ İHTİYACI</Text>
                        <View style={st.inputWrapper}>
                            <FormField value={formData.vehicle_details} onChangeText={t => setFormData({...formData, vehicle_details: t})} placeholder="Örn: 2 Minibüs, 1 Otobüs" />
                        </View>

                        <View style={{ flexDirection: 'row', gap: 10 }}>
                            <View style={{ flex: 1 }}>
                                <Text style={st.label}>SÜRE (GÜN)</Text>
                                <View style={st.inputWrapper}>
                                    <FormField value={formData.duration_days} onChangeText={t => setFormData({...formData, duration_days: t})} placeholder="365" keyboardType="numeric" />
                                </View>
                            </View>
                            <View style={{ flex: 1 }}>
                                <Text style={st.label}>DURUM *</Text>
                                <View style={st.pickerContainer}>
                                    <Picker
                                        selectedValue={formData.status}
                                        onValueChange={(v) => setFormData({...formData, status: v})}
                                        style={{ height: 50, color: '#F8FAFC' }}
                                        dropdownIconColor="#94A3B8"
                                    >
                                        <Picker.Item label="Değerlendirmede" value="Değerlendirmede" />
                                        <Picker.Item label="Kazanıldı" value="Kazanıldı" />
                                        <Picker.Item label="Kaybedildi" value="Kaybedildi" />
                                        <Picker.Item label="İptal Edildi" value="İptal" />
                                    </Picker>
                                </View>
                            </View>
                        </View>
                    </BlurView>

                    <BlurView intensity={30} tint="dark" style={st.card}>
                        <Text style={st.sectionTitle}>Maliyet & Sonuçlar</Text>

                        <View style={{ flexDirection: 'row', gap: 10 }}>
                            <View style={{ flex: 1 }}>
                                <Text style={st.label}>YAKLAŞIK MALİYET (₺)</Text>
                                <View style={st.inputWrapper}>
                                    <FormField value={formData.approximate_cost} onChangeText={t => setFormData({...formData, approximate_cost: t})} placeholder="0.00" keyboardType="numeric" />
                                </View>
                            </View>
                            <View style={{ flex: 1 }}>
                                <Text style={[st.label, { color: '#38BDF8' }]}>BİZİM TEKLİF (₺)</Text>
                                <View style={[st.inputWrapper, { borderColor: 'rgba(56,189,248,0.3)' }]}>
                                    <FormField value={formData.our_bid} onChangeText={t => setFormData({...formData, our_bid: t})} placeholder="0.00" keyboardType="numeric" />
                                </View>
                            </View>
                        </View>

                        <View style={{ flexDirection: 'row', gap: 10 }}>
                            <View style={{ flex: 1 }}>
                                <Text style={st.label}>KAZANAN FİRMA</Text>
                                <View style={st.inputWrapper}>
                                    <FormField value={formData.winning_company} onChangeText={t => setFormData({...formData, winning_company: t})} placeholder="Firma Adı" />
                                </View>
                            </View>
                            <View style={{ flex: 1 }}>
                                <Text style={[st.label, { color: '#FBBF24' }]}>KAZANAN TUTAR</Text>
                                <View style={[st.inputWrapper, { borderColor: 'rgba(251,191,36,0.3)' }]}>
                                    <FormField value={formData.winning_amount} onChangeText={t => setFormData({...formData, winning_amount: t})} placeholder="0.00" keyboardType="numeric" />
                                </View>
                            </View>
                        </View>
                    </BlurView>

                    <BlurView intensity={30} tint="dark" style={st.card}>
                        <Text style={st.sectionTitle}>Evrak & Notlar</Text>

                        <Text style={st.label}>İHALE DOKÜMANI (PDF)</Text>
                        <TouchableOpacity style={st.fileBtn} onPress={pickDocument}>
                            {file ? (
                                <>
                                    <Icon name="file-check" size={24} color="#34D399" />
                                    <Text style={[st.fileBtnText, { color: '#F8FAFC' }]} numberOfLines={1}>{file.name || 'PDF Seçildi'}</Text>
                                </>
                            ) : (
                                <>
                                    <Icon name="file-upload" size={24} color="#38BDF8" />
                                    <Text style={st.fileBtnText}>PDF Dosyası Seç</Text>
                                </>
                            )}
                        </TouchableOpacity>

                        <Text style={st.label}>NOTLAR</Text>
                        <View style={st.inputWrapper}>
                            <FormField 
                                value={formData.notes} 
                                onChangeText={t => setFormData({...formData, notes: t})} 
                                placeholder="Gelecek yıl için notlar..." 
                                multiline 
                                numberOfLines={4} 
                                style={{ height: 100, textAlignVertical: 'top', color: '#F8FAFC' }} 
                            />
                        </View>
                    </BlurView>

                    <TouchableOpacity style={[st.saveBtn, saving && { opacity: 0.7 }]} onPress={handleSave} disabled={saving}>
                        <LinearGradient colors={['#3B82F6', '#2563EB']} style={StyleSheet.absoluteFillObject} />
                        {saving ? <ActivityIndicator color="#fff" /> : <Text style={st.saveBtnText}>Kaydet</Text>}
                    </TouchableOpacity>

                </ScrollView>
                </KeyboardAvoidingView>
            </SafeAreaView>
        </View>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    bgBlob1: { position: 'absolute', top: -50, left: -50, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(56,189,248,0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, right: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(59,130,246,0.15)', filter: 'blur(40px)' },
    
    loader: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#020617' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    backBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', backgroundColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    headerTitle: { fontSize: 18, fontWeight: '800', color: '#F8FAFC' },
    
    formContainer: { padding: 16, paddingBottom: 60, gap: 16 },
    card: { borderRadius: 24, padding: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    sectionTitle: { fontSize: 16, fontWeight: '900', color: '#F8FAFC', marginBottom: 16 },
    label: { fontSize: 11, fontWeight: '800', color: '#94A3B8', marginBottom: 8, letterSpacing: 0.5 },
    
    inputWrapper: { backgroundColor: 'rgba(0,0,0,0.3)', borderRadius: 16, padding: 4, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', marginBottom: 16 },
    pickerContainer: { borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', borderRadius: 16, backgroundColor: 'rgba(0,0,0,0.3)', overflow: 'hidden', marginBottom: 16 },
    
    fileBtn: { flexDirection: 'row', alignItems: 'center', padding: 16, backgroundColor: 'rgba(56,189,248,0.1)', borderRadius: 16, borderWidth: 1, borderColor: 'rgba(56,189,248,0.3)', borderStyle: 'dashed', gap: 10, marginBottom: 16 },
    fileBtnText: { fontSize: 14, fontWeight: '600', color: '#38BDF8', flex: 1 },
    
    saveBtn: { borderRadius: 20, paddingVertical: 18, alignItems: 'center', marginTop: 10, overflow: 'hidden' },
    saveBtnText: { color: '#fff', fontSize: 16, fontWeight: '800' }
});
