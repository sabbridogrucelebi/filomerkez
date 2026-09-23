import React, { useState, useEffect, useContext } from 'react';
import { View, StyleSheet, ScrollView, Text, TouchableOpacity, ActivityIndicator, Alert, Platform, Modal, FlatList, KeyboardAvoidingView } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import api from '../api/axios';
import { FormField } from '../components';
import DatePickerInput from '../components/DatePickerInput';
import * as DocumentPicker from 'expo-document-picker';
import { AuthContext } from '../context/AuthContext';
import useStatusBarStyle from '../hooks/useStatusBarStyle';

const toTitleCase = (str) => {
    if (!str) return '';
    return str.toString().split(' ').map(word => {
        if (!word) return '';
        const first = word.charAt(0).toLocaleUpperCase('tr-TR');
        const rest = word.slice(1).toLocaleLowerCase('tr-TR');
        return first + rest;
    }).join(' ');
};

const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 2 }).format(v || 0);

export default function PenaltyFormScreen({ route, navigation }) {
    useStatusBarStyle('dark-content');
    const { penaltyId, penalty } = route.params || {};
    const { hasPermission } = useContext(AuthContext);
    
    const [vehicles, setVehicles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [vehicleModalVisible, setVehicleModalVisible] = useState(false);
    
    const [formData, setFormData] = useState({
        vehicle_id: '',
        penalty_no: '',
        penalty_date: new Date().toISOString().split('T')[0],
        penalty_time: '',
        penalty_article: '',
        penalty_amount: '',
        penalty_location: '',
        driver_name: '',
        payment_date: '',
        notes: '',
        traffic_penalty_document: null,
        payment_receipt: null
    });

    useEffect(() => {
        const fetchOptions = async () => {
            try {
                const res = await api.get('/v1/penalties/options');
                if (res.data && res.data.success && res.data.data.vehicles) {
                    setVehicles(res.data.data.vehicles);
                }
                
                if (penaltyId && penalty) {
                    setFormData({
                        vehicle_id: penalty.vehicle_id || '',
                        penalty_no: penalty.penalty_no || '',
                        penalty_date: penalty.penalty_date || new Date().toISOString().split('T')[0],
                        penalty_time: penalty.penalty_time || '',
                        penalty_article: penalty.penalty_article || '',
                        penalty_amount: penalty.penalty_amount ? penalty.penalty_amount.toString() : '',
                        penalty_location: penalty.penalty_location || '',
                        driver_name: penalty.driver_name || '',
                        payment_date: penalty.payment_date || '',
                        notes: penalty.notes || '',
                        traffic_penalty_document: null,
                        payment_receipt: null
                    });
                }
            } catch (e) {
                console.error("Options error:", e);
                Alert.alert('Hata', 'Araç listesi yüklenemedi.');
            } finally {
                setLoading(false);
            }
        };
        fetchOptions();
    }, [penaltyId]);

    const pickDocument = async (field) => {
        try {
            const result = await DocumentPicker.getDocumentAsync({
                type: ['application/pdf', 'image/*'],
                copyToCacheDirectory: true
            });
            if (result.canceled === false && result.assets && result.assets.length > 0) {
                setFormData(prev => ({ ...prev, [field]: result.assets[0] }));
            }
        } catch (e) {
            console.error(e);
        }
    };

    const handleSave = async () => {
        if (!formData.penalty_no || !formData.penalty_amount || !formData.penalty_date || !formData.driver_name || !formData.vehicle_id) {
            Alert.alert('Eksik Bilgi', 'Araç, Ceza No, Tarih, Tutar ve Şoför Adı alanları zorunludur.'); 
            return;
        }

        setSaving(true);
        try {
            const url = penaltyId ? `/v1/penalties/${penaltyId}` : `/v1/penalties`;
            
            const formDataObj = new FormData();
            formDataObj.append('vehicle_id', formData.vehicle_id);
            formDataObj.append('penalty_no', formData.penalty_no);
            formDataObj.append('penalty_date', formData.penalty_date);
            
            const timeVal = formData.penalty_time?.trim() || '';
            if (timeVal && timeVal !== '--:--') {
                // Ensure it's exactly HH:MM
                if (timeVal.length === 5) {
                    formDataObj.append('penalty_time', timeVal);
                } else {
                    Alert.alert('Hata', 'Lütfen ceza saatini 14:30 formatında tam olarak girin veya boş bırakın.');
                    setSaving(false);
                    return;
                }
            }
            
            formDataObj.append('penalty_article', formData.penalty_article);
            formDataObj.append('penalty_amount', formData.penalty_amount);
            formDataObj.append('penalty_location', formData.penalty_location);
            formDataObj.append('driver_name', formData.driver_name);
            if (formData.payment_date) formDataObj.append('payment_date', formData.payment_date);
            if (formData.notes) formDataObj.append('notes', formData.notes);

            if (formData.traffic_penalty_document) {
                formDataObj.append('traffic_penalty_document', {
                    uri: formData.traffic_penalty_document.uri,
                    name: formData.traffic_penalty_document.name,
                    type: formData.traffic_penalty_document.mimeType || 'application/pdf'
                });
            }

            if (formData.payment_receipt) {
                formDataObj.append('payment_receipt', {
                    uri: formData.payment_receipt.uri,
                    name: formData.payment_receipt.name,
                    type: formData.payment_receipt.mimeType || 'application/pdf'
                });
            }

            await api.post(url, formDataObj, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            
            Alert.alert('Başarılı', penaltyId ? 'Ceza güncellendi.' : 'Yeni ceza eklendi.', [
                { text: 'Tamam', onPress: () => navigation.goBack() }
            ]);
        } catch (e) {
            const msg = e.response?.data?.message || 'Kaydedilemedi.';
            Alert.alert('Hata', msg);
            console.error('Save error:', e);
        } finally { 
            setSaving(false); 
        }
    };

    // Hesaplamalar
    const baseAmount = parseFloat(formData.penalty_amount) || 0;
    const discountedAmount = baseAmount * 0.75;
    
    // Uygulanacak tutarı hesapla
    let appliedAmount = baseAmount;
    let paymentStatusPreview = "Ödenmedi";
    
    if (formData.payment_date && formData.penalty_date) {
        const pDate = new Date(formData.payment_date);
        const deadline = new Date(formData.penalty_date);
        deadline.setDate(deadline.getDate() + 30); // 1 ay (yaklaşık 30 gün) indirim süresi
        
        if (pDate <= deadline) {
            appliedAmount = discountedAmount;
            paymentStatusPreview = "İndirimli Ödenecek";
        } else {
            appliedAmount = baseAmount;
            paymentStatusPreview = "Cezalı / Tam Ödenecek";
        }
    }

    if (loading) {
        return (
            <SafeAreaView style={st.container} edges={['top']}>
                <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
                    <ActivityIndicator size="large" color="#E11D48" />
                </View>
            </SafeAreaView>
        );
    }

    return (
        <SafeAreaView style={st.container} edges={['top']}>
            <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : 'height'} keyboardVerticalOffset={0}>
            <View style={st.header}>
                <View style={st.headerTitleRow}>
                    <View style={st.headerLine} />
                    <View>
                        <Text style={st.headerTitle}>{penaltyId ? 'Trafik Cezasını Düzenle' : 'Yeni Trafik Cezası'}</Text>
                        <Text style={st.headerSubtitle}>
                            <View style={st.dot} /> YENİ TRAFİK CEZASI KAYDI OLUŞTURUN
                        </Text>
                    </View>
                </View>
                <TouchableOpacity onPress={() => navigation.goBack()} style={st.closeBtn}>
                    <Icon name="close" size={24} color="#0F172A" />
                </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={st.scrollContent} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
                
                {/* 1. KART: Ceza Bilgileri */}
                <View style={st.card}>
                    <Text style={st.cardTitle}>Ceza Bilgileri</Text>
                    <Text style={st.cardSubtitle}>Trafik cezasının temel detaylarını girin.</Text>
                    
                    <View style={st.row}>
                        <View style={st.col}>
                            <Text style={st.inputLabel}>Araç *</Text>
                            <TouchableOpacity 
                                style={st.customPicker} 
                                onPress={() => setVehicleModalVisible(true)}
                                activeOpacity={0.7}
                            >
                                <Text style={[st.customPickerText, !formData.vehicle_id && {color: '#94A3B8'}]}>
                                    {formData.vehicle_id ? (vehicles.find(v => v.id === formData.vehicle_id)?.plate || 'Seçildi') : 'Araç seçiniz'}
                                </Text>
                                <Icon name="chevron-down" size={20} color="#64748B" />
                            </TouchableOpacity>
                        </View>
                        <View style={st.col}>
                            <Text style={st.inputLabel}>Ceza No *</Text>
                            <FormField 
                                value={formData.penalty_no} 
                                onChangeText={(t) => setFormData({...formData, penalty_no: t.toUpperCase()})}
                                placeholder="Örn: MB92..."
                                autoCapitalize="characters"
                            />
                        </View>
                    </View>

                    <View style={st.row}>
                        <View style={st.col}>
                            <DatePickerInput 
                                label="Ceza Tarihi *" 
                                value={formData.penalty_date} 
                                onChange={(d) => setFormData({...formData, penalty_date: d})}
                            />
                        </View>
                        <View style={st.col}>
                            <Text style={st.inputLabel}>Ceza Saati</Text>
                            <FormField 
                                value={formData.penalty_time} 
                                onChangeText={(t) => {
                                    let cleaned = t.replace(/[^0-9]/g, '');
                                    if (cleaned.length > 4) cleaned = cleaned.substring(0, 4);
                                    if (cleaned.length >= 3) {
                                        cleaned = cleaned.substring(0, 2) + ':' + cleaned.substring(2);
                                    }
                                    setFormData({...formData, penalty_time: cleaned});
                                }}
                                placeholder="00:00"
                                keyboardType="numeric"
                                maxLength={5}
                            />
                        </View>
                    </View>

                    <View style={st.row}>
                        <View style={st.col}>
                            <Text style={st.inputLabel}>Ceza Maddesi *</Text>
                            <FormField 
                                value={formData.penalty_article} 
                                onChangeText={(t) => setFormData({...formData, penalty_article: t.toUpperCase()})}
                                placeholder="Örn: 47/1-b"
                                autoCapitalize="characters"
                            />
                        </View>
                        <View style={st.col}>
                            <Text style={st.inputLabel}>Ceza Yeri</Text>
                            <FormField 
                                value={formData.penalty_location} 
                                onChangeText={(t) => setFormData({...formData, penalty_location: toTitleCase(t)})}
                                placeholder="Örn: Konya Yolu / Ankara"
                                autoCapitalize="words"
                            />
                        </View>
                    </View>

                    <View style={st.row}>
                        <View style={st.col}>
                            <Text style={st.inputLabel}>Ceza Tutarı *</Text>
                            <FormField 
                                value={formData.penalty_amount} 
                                onChangeText={(t) => setFormData({...formData, penalty_amount: t.replace(',', '.')})}
                                keyboardType="numeric"
                                placeholder="0.00"
                            />
                        </View>
                        <View style={st.col}>
                            <Text style={st.inputLabel}>%25 İndirimli Tutar</Text>
                            <View style={st.readonlyField}>
                                <Text style={st.readonlyText}>{fmtMoney(discountedAmount)}</Text>
                            </View>
                            <Text style={st.fieldHint}>Bu tutar otomatik hesaplanır.</Text>
                        </View>
                    </View>

                    <Text style={st.inputLabel}>Şoför Ad Soyad *</Text>
                    <FormField 
                        value={formData.driver_name} 
                        onChangeText={(t) => setFormData({...formData, driver_name: toTitleCase(t)})}
                        placeholder="Cezayı yiyen şoför"
                        autoCapitalize="words"
                    />

                    <Text style={[st.inputLabel, { marginTop: 16 }]}>Notlar</Text>
                    <FormField 
                        value={formData.notes} 
                        onChangeText={(t) => setFormData({...formData, notes: t})}
                        placeholder=""
                        multiline 
                        numberOfLines={3} 
                        style={{ height: 80, textAlignVertical: 'top' }}
                    />
                </View>

                {/* 2. KART: Trafik Cezası Belgesi ve Ödeme Dekontu */}
                <View style={st.card}>
                    <Text style={st.cardTitle}>Trafik Cezası Belgesi ve Ödeme Dekontu</Text>
                    <Text style={st.cardSubtitle}>Ceza belgesini ve ödeme dekontunu yükleyebilirsiniz. Ödeme tarihi girildiğinde sistem indirimi otomatik uygular.</Text>
                    
                    <View style={st.row}>
                        <View style={st.col}>
                            <Text style={st.inputLabel}>Trafik Cezası Belgesi</Text>
                            <TouchableOpacity style={st.fileBtn} onPress={() => pickDocument('traffic_penalty_document')}>
                                <View style={st.fileBtnLabel}><Text style={st.fileBtnLabelText}>Dosya Seç</Text></View>
                                <Text style={st.fileBtnText} numberOfLines={1}>
                                    {formData.traffic_penalty_document ? formData.traffic_penalty_document.name : 'Dosya seçilmedi'}
                                </Text>
                            </TouchableOpacity>
                        </View>
                        <View style={st.col}>
                            <Text style={st.inputLabel}>Ödeme Dekontu</Text>
                            <TouchableOpacity style={st.fileBtn} onPress={() => pickDocument('payment_receipt')}>
                                <View style={st.fileBtnLabel}><Text style={st.fileBtnLabelText}>Dosya Seç</Text></View>
                                <Text style={st.fileBtnText} numberOfLines={1}>
                                    {formData.payment_receipt ? formData.payment_receipt.name : 'Dosya seçilmedi'}
                                </Text>
                            </TouchableOpacity>
                        </View>
                    </View>

                    <View style={st.row}>
                        <View style={st.col}>
                            <DatePickerInput 
                                label="Ceza Ödeme Tarihi" 
                                value={formData.payment_date} 
                                onChange={(d) => setFormData({...formData, payment_date: d})}
                            />
                        </View>
                        <View style={st.col}>
                            <Text style={st.inputLabel}>Ödeme Durumu Önizleme</Text>
                            <View style={st.readonlyField}>
                                <Text style={st.readonlyText}>{paymentStatusPreview}</Text>
                            </View>
                        </View>
                    </View>
                </View>

                {/* 3. KART: Akıllı Tutar Kartı */}
                <View style={st.smartCard}>
                    <Text style={st.smartCardTitle}>Akıllı Tutar Kartı</Text>
                    
                    <View style={st.smartBox}>
                        <Text style={st.smartBoxLabel}>NORMAL CEZA</Text>
                        <Text style={st.smartBoxValue}>{fmtMoney(baseAmount)}</Text>
                    </View>
                    
                    <View style={[st.smartBox, { backgroundColor: '#ECFDF5' }]}>
                        <Text style={[st.smartBoxLabel, { color: '#10B981' }]}>%25 İNDİRİMLİ TUTAR</Text>
                        <Text style={[st.smartBoxValue, { color: '#059669' }]}>{fmtMoney(discountedAmount)}</Text>
                    </View>
                    
                    <View style={[st.smartBox, { backgroundColor: '#EEF2FF', marginBottom: 0 }]}>
                        <Text style={[st.smartBoxLabel, { color: '#4F46E5' }]}>UYGULANACAK TUTAR</Text>
                        <Text style={[st.smartBoxValue, { color: '#4338CA' }]}>{fmtMoney(appliedAmount)}</Text>
                        <Text style={st.smartBoxHint}>Ceza tarihi olmadan ödeme kuralı hesaplanamaz.</Text>
                    </View>
                </View>

                {/* Buttons */}
                <View style={st.actionButtons}>
                    <TouchableOpacity style={st.saveBtn} onPress={handleSave} disabled={saving}>
                        {saving ? <ActivityIndicator color="#fff" /> : <Text style={st.saveBtnText}>Cezayı Kaydet</Text>}
                    </TouchableOpacity>
                    <TouchableOpacity style={st.cancelBtn} onPress={() => navigation.goBack()} disabled={saving}>
                        <Text style={st.cancelBtnText}>Vazgeç</Text>
                    </TouchableOpacity>
                </View>

                <View style={{ height: 120 }} />
            </ScrollView>

            <Modal visible={vehicleModalVisible} transparent animationType="slide">
                <View style={st.modalOverlay}>
                    <View style={st.modalContent}>
                        <View style={st.modalHeader}>
                            <Text style={st.modalTitle}>Araç Seçin</Text>
                            <TouchableOpacity onPress={() => setVehicleModalVisible(false)} style={st.modalCloseBtn}>
                                <Icon name="close" size={20} color="#64748B" />
                            </TouchableOpacity>
                        </View>
                        <FlatList 
                            data={vehicles}
                            keyExtractor={item => item.id.toString()}
                            contentContainerStyle={{ padding: 16 }}
                            renderItem={({item}) => (
                                <TouchableOpacity 
                                    style={[st.modalItem, formData.vehicle_id === item.id && st.modalItemActive]} 
                                    onPress={() => {
                                        setFormData({...formData, vehicle_id: item.id});
                                        setVehicleModalVisible(false);
                                    }}
                                >
                                    <Icon name={formData.vehicle_id === item.id ? "check-circle" : "car-outline"} size={20} color={formData.vehicle_id === item.id ? "#10B981" : "#64748B"} />
                                    <Text style={[st.modalItemText, formData.vehicle_id === item.id && {color: '#10B981', fontWeight: '800'}]}>
                                        {item.plate}
                                    </Text>
                                </TouchableOpacity>
                            )}
                        />
                    </View>
                </View>
            </Modal>
            </KeyboardAvoidingView>
        </SafeAreaView>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F8FAFC' },
    
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, paddingTop: Platform.OS === 'ios' ? 10 : 20, paddingBottom: 20, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#E2E8F0' },
    headerTitleRow: { flexDirection: 'row', alignItems: 'center' },
    headerLine: { width: 4, height: 36, backgroundColor: '#8B5CF6', borderRadius: 2, marginRight: 12 },
    headerTitle: { fontSize: 22, fontWeight: '900', color: '#0F172A' },
    headerSubtitle: { fontSize: 11, color: '#10B981', fontWeight: '800', marginTop: 2, letterSpacing: 0.5, flexDirection: 'row', alignItems: 'center' },
    dot: { width: 6, height: 6, borderRadius: 3, backgroundColor: '#10B981', marginRight: 4, marginTop: 2 },
    closeBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
    
    scrollContent: { padding: 16 },
    
    // Card General
    card: { backgroundColor: '#fff', borderRadius: 16, padding: 20, marginBottom: 16, borderWidth: 1, borderColor: '#E2E8F0' },
    cardTitle: { fontSize: 16, fontWeight: '800', color: '#1E293B', marginBottom: 4 },
    cardSubtitle: { fontSize: 12, color: '#64748B', marginBottom: 20, fontWeight: '500' },
    
    row: { flexDirection: 'row', gap: 12, marginBottom: 16 },
    col: { flex: 1 },
    
    inputLabel: { fontSize: 12, fontWeight: '700', color: '#1E293B', marginBottom: 6 },
    pickerWrapper: { borderWidth: 1, borderColor: '#E2E8F0', borderRadius: 8, backgroundColor: '#fff', height: 48, justifyContent: 'center', overflow: 'hidden' },
    
    readonlyField: { backgroundColor: '#ECFDF5', borderWidth: 1, borderColor: '#D1FAE5', borderRadius: 8, height: 48, justifyContent: 'center', paddingHorizontal: 16 },
    readonlyText: { fontSize: 14, fontWeight: '700', color: '#10B981' },
    fieldHint: { fontSize: 10, color: '#94A3B8', marginTop: 4, fontStyle: 'italic' },
    
    fileBtn: { flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderColor: '#E2E8F0', borderRadius: 8, backgroundColor: '#fff', height: 48, overflow: 'hidden' },
    fileBtnLabel: { backgroundColor: '#F1F5F9', height: '100%', justifyContent: 'center', paddingHorizontal: 12, borderRightWidth: 1, borderRightColor: '#E2E8F0' },
    fileBtnLabelText: { fontSize: 12, fontWeight: '600', color: '#475569' },
    fileBtnText: { flex: 1, fontSize: 13, color: '#64748B', paddingHorizontal: 12 },
    
    // Smart Card
    smartCard: { backgroundColor: '#fff', borderRadius: 16, padding: 20, marginBottom: 24, borderWidth: 1, borderColor: '#E2E8F0' },
    smartCardTitle: { fontSize: 16, fontWeight: '800', color: '#1E293B', marginBottom: 16 },
    smartBox: { backgroundColor: '#F8FAFC', borderRadius: 12, padding: 16, marginBottom: 12 },
    smartBoxLabel: { fontSize: 11, fontWeight: '800', color: '#64748B', letterSpacing: 0.5, marginBottom: 4 },
    smartBoxValue: { fontSize: 24, fontWeight: '900', color: '#0F172A' },
    smartBoxHint: { fontSize: 10, color: '#94A3B8', marginTop: 8 },
    
    // Buttons
    actionButtons: { gap: 12 },
    saveBtn: { backgroundColor: '#E11D48', height: 56, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
    saveBtnText: { color: '#fff', fontSize: 16, fontWeight: '800' },
    cancelBtn: { backgroundColor: '#fff', height: 56, borderRadius: 12, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: '#E2E8F0' },
    cancelBtnText: { color: '#0F172A', fontSize: 16, fontWeight: '700' },
    
    // Custom Picker & Modal
    customPicker: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderWidth: 1, borderColor: '#E2E8F0', borderRadius: 8, backgroundColor: '#fff', height: 48, paddingHorizontal: 16 },
    customPickerText: { fontSize: 14, fontWeight: '600', color: '#0F172A' },
    modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end' },
    modalContent: { backgroundColor: '#fff', borderTopLeftRadius: 24, borderTopRightRadius: 24, maxHeight: '80%' },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 20, borderBottomWidth: 1, borderBottomColor: '#E2E8F0' },
    modalTitle: { fontSize: 18, fontWeight: '800', color: '#0F172A' },
    modalCloseBtn: { width: 32, height: 32, borderRadius: 16, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
    modalItem: { flexDirection: 'row', alignItems: 'center', paddingVertical: 16, borderBottomWidth: 1, borderBottomColor: '#F1F5F9', gap: 12 },
    modalItemActive: { backgroundColor: '#F0FDF4', borderRadius: 12, paddingHorizontal: 12, borderBottomWidth: 0 },
    modalItemText: { fontSize: 15, fontWeight: '600', color: '#1E293B' },
});
