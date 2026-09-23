import React, { useState, useEffect, useContext, useCallback } from 'react';
import { Platform, KeyboardAvoidingView, View, Text, StyleSheet, ScrollView, ActivityIndicator, RefreshControl, TouchableOpacity, TextInput, Modal, Alert } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { AuthContext } from '../context/AuthContext';
import api from '../api/axios';
import SpaceWaves from '../components/SpaceWaves';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { useFocusEffect } from '@react-navigation/native';
import { LinearGradient } from 'expo-linear-gradient';

export default function ParentSettingsScreen() {
    const { logout } = useContext(AuthContext);
    const [isLoading, setIsLoading] = useState(true);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [data, setData] = useState(null);
    const [isSaving, setIsSaving] = useState(false);

    // Modal state
    const [editModalVisible, setEditModalVisible] = useState(false);
    const [editType, setEditType] = useState(null); // 'address' or 'parents'
    const [editData, setEditData] = useState({});

    const fetchData = async () => {
        try {
            const response = await api.get('/v1/pilotcell/parent/student-info');
            setData(response.data);
        } catch (error) {
            console.log('Error fetching student info:', error);
        } finally {
            setIsLoading(false);
            setIsRefreshing(false);
        }
    };

    useFocusEffect(
        useCallback(() => {
            fetchData();
        }, [])
    );

    const onRefresh = () => {
        setIsRefreshing(true);
        fetchData();
    };

    const handleEdit = (type) => {
        setEditType(type);
        if (type === 'address') {
            setEditData({ address: data.student.address });
        } else if (type === 'parents') {
            setEditData({
                parent1_name: data.student.parent1_name,
                parent1_phone: data.student.parent1_phone,
                parent2_name: data.student.parent2_name,
                parent2_phone: data.student.parent2_phone,
            });
        } else if (type === 'password') {
            setEditData({
                current_password: '',
                password: '',
                password_confirmation: ''
            });
        }
        setEditModalVisible(true);
    };

    const handleSave = async () => {
        setIsSaving(true);
        try {
            if (editType === 'password') {
                if (editData.password !== editData.password_confirmation) {
                    Alert.alert("Hata", "Yeni şifreler eşleşmiyor.");
                    setIsSaving(false);
                    return;
                }
                await api.put('/user/password', editData);
                setEditModalVisible(false);
                Alert.alert("Başarılı", "Şifreniz güncellendi.");
            } else {
                const response = await api.put('/v1/pilotcell/parent/student-info', editData);
                setData(prev => ({ ...prev, student: response.data.student }));
                setEditModalVisible(false);
                Alert.alert("Başarılı", "Bilgiler güncellendi.");
            }
        } catch (e) {
            if (e.response && e.response.status === 422) {
                Alert.alert("Hata", e.response.data.message || "Girdiğiniz bilgiler geçersiz.");
            } else {
                Alert.alert("Hata", "İşlem sırasında bir hata oluştu.");
            }
        } finally {
            setIsSaving(false);
        }
    };

    if (isLoading) {
        return (
            <SafeAreaView style={styles.container} edges={['top']}>
                <SpaceWaves />
                <View style={styles.centerContent}>
                    <ActivityIndicator size="large" color="#8B5CF6" />
                </View>
            </SafeAreaView>
        );
    }

    if (!data || !data.student) {
        return (
            <SafeAreaView style={styles.container} edges={['top']}>
                <SpaceWaves />
                <View style={styles.centerContent}>
                    <Text style={{color: '#94A3B8'}}>Bilgiler alınamadı.</Text>
                </View>
            </SafeAreaView>
        );
    }

    const { student } = data;
    const initial = student.name ? student.name.charAt(0) : '?';
    const route = student.routes && student.routes.length > 0 ? student.routes[0] : null;

    return (
        <SafeAreaView style={styles.container} edges={['top']}>
            <SpaceWaves />
            
            <View style={styles.header}>
                <Text style={styles.headerTitle}>Öğrenci Detay</Text>
                <TouchableOpacity onPress={logout} style={styles.logoutBtn}>
                    <Icon name="logout" size={22} color="#EF4444" />
                </TouchableOpacity>
            </View>

            <ScrollView 
                contentContainerStyle={styles.scrollContent}
                refreshControl={<RefreshControl refreshing={isRefreshing} onRefresh={onRefresh} tintColor="#8B5CF6" />}
            >
                {/* Öğrenci Temel Kartı - Tam Genişlik, Premium Görünüm */}
                <View style={styles.studentCardPremium}>
                    <LinearGradient 
                        colors={['rgba(139, 92, 246, 0.2)', 'rgba(2, 6, 23, 0)']} 
                        style={StyleSheet.absoluteFill} 
                        start={{x: 0, y: 0}} end={{x: 0, y: 1}} 
                    />
                    <View style={styles.studentCardInner}>
                        <View style={styles.avatarPremiumWrap}>
                            <LinearGradient colors={['#8B5CF6', '#6D28D9']} style={styles.avatarGradient}>
                                <Text style={styles.avatarPremiumText}>{initial}</Text>
                            </LinearGradient>
                        </View>
                        <View style={styles.studentPremiumInfo}>
                            <Text style={styles.studentPremiumName}>{student.name}</Text>
                            <View style={styles.gradeBadge}>
                                <Icon name="school-outline" size={12} color="#A78BFA" />
                                <Text style={styles.gradeBadgeText}>Sınıf: {student.grade || 'Belirtilmedi'}</Text>
                            </View>
                        </View>
                    </View>
                </View>

                {/* Adres ve İletişim */}
                <View style={styles.premiumSection}>
                    <View style={styles.premiumHeader}>
                        <View style={styles.premiumTitleRow}>
                            <View style={[styles.iconWrap, { backgroundColor: 'rgba(139, 92, 246, 0.15)' }]}>
                                <Icon name="map-marker-outline" size={18} color="#A78BFA" />
                            </View>
                            <Text style={styles.premiumTitle}>ADRES VE İLETİŞİM</Text>
                        </View>
                        <TouchableOpacity onPress={() => handleEdit('address')} style={styles.premiumEditBtn}>
                            <Icon name="pencil-outline" size={18} color="#A78BFA" />
                        </TouchableOpacity>
                    </View>
                    <View style={styles.premiumBody}>
                        <Text style={styles.premiumAddressText}>{student.address || 'Adres bilgisi girilmemiş.'}</Text>
                    </View>
                </View>

                {/* Veli Bilgileri */}
                <View style={styles.premiumSection}>
                    <View style={styles.premiumHeader}>
                        <View style={styles.premiumTitleRow}>
                            <View style={[styles.iconWrap, { backgroundColor: 'rgba(245, 158, 11, 0.15)' }]}>
                                <Icon name="account-group-outline" size={18} color="#FBBF24" />
                            </View>
                            <Text style={[styles.premiumTitle, { color: '#FBBF24' }]}>VELİ BİLGİLERİ</Text>
                        </View>
                        <TouchableOpacity onPress={() => handleEdit('parents')} style={styles.premiumEditBtn}>
                            <Icon name="pencil-outline" size={18} color="#FBBF24" />
                        </TouchableOpacity>
                    </View>
                    <View style={styles.premiumBodyRow}>
                        <View style={styles.premiumHalfBox}>
                            <View style={styles.parentBadge}>
                                <Text style={styles.parentBadgeText}>1. VELİ</Text>
                            </View>
                            <Text style={styles.premiumParentName} numberOfLines={1} adjustsFontSizeToFit>{student.parent1_name || '-'}</Text>
                            <View style={styles.premiumPhoneRow}>
                                <Icon name="phone-outline" size={12} color="#94A3B8" />
                                <Text style={styles.premiumParentPhone} numberOfLines={1} adjustsFontSizeToFit>{student.parent1_phone || '-'}</Text>
                            </View>
                        </View>
                        <View style={styles.premiumHalfBox}>
                            <View style={styles.parentBadge}>
                                <Text style={styles.parentBadgeText}>2. VELİ</Text>
                            </View>
                            <Text style={styles.premiumParentName} numberOfLines={1} adjustsFontSizeToFit>{student.parent2_name || '-'}</Text>
                            <View style={styles.premiumPhoneRow}>
                                <Icon name="phone-outline" size={12} color="#94A3B8" />
                                <Text style={styles.premiumParentPhone} numberOfLines={1} adjustsFontSizeToFit>{student.parent2_phone || '-'}</Text>
                            </View>
                        </View>
                    </View>
                </View>

                {/* Araç ve Servis Bilgileri */}
                <View style={styles.premiumSection}>
                    <View style={styles.premiumHeader}>
                        <View style={styles.premiumTitleRow}>
                            <View style={[styles.iconWrap, { backgroundColor: 'rgba(56, 189, 248, 0.15)' }]}>
                                <Icon name="bus-school" size={18} color="#38BDF8" />
                            </View>
                            <Text style={[styles.premiumTitle, { color: '#38BDF8' }]}>ARAÇ VE SERVİS BİLGİLERİ</Text>
                        </View>
                    </View>
                    {route ? (
                        <View style={styles.premiumRouteBody}>
                            <View style={styles.premiumRouteBoxFull}>
                                <View style={styles.routeHeaderRow}>
                                    <Text style={styles.routeLabelPrimary}>GÜZERGAH & SERVİS NO</Text>
                                    <View style={styles.plateBadge}>
                                        <Text style={styles.plateText}>{route.vehicle?.plate || '-'}</Text>
                                    </View>
                                </View>
                                <Text style={styles.routeValuePrimary}>{route.name}</Text>
                                <Text style={styles.routeSubPrimary}>Servis No: <Text style={{color:'#fff'}}>{route.service_no || '-'}</Text></Text>
                            </View>
                            <View style={styles.premiumBodyRow}>
                                <View style={[styles.premiumHalfBox, { backgroundColor: 'rgba(30, 41, 59, 0.4)' }]}>
                                    <Text style={styles.personnelLabel} numberOfLines={1} adjustsFontSizeToFit>ŞOFÖR</Text>
                                    <Text style={styles.personnelName} numberOfLines={1} adjustsFontSizeToFit>{route.driver_name || route.vehicle?.driver?.name || '-'}</Text>
                                    <View style={styles.premiumPhoneRow}>
                                        <Icon name="phone-outline" size={12} color="#94A3B8" />
                                        <Text style={styles.premiumParentPhone} numberOfLines={1} adjustsFontSizeToFit>{route.driver_phone || route.vehicle?.driver?.phone || '-'}</Text>
                                    </View>
                                </View>
                                <View style={[styles.premiumHalfBox, { backgroundColor: 'rgba(30, 41, 59, 0.4)' }]}>
                                    <Text style={styles.personnelLabel} numberOfLines={1} adjustsFontSizeToFit>REHBER / HOSTES</Text>
                                    <Text style={styles.personnelName} numberOfLines={1} adjustsFontSizeToFit>{route.hostess_name || '-'}</Text>
                                    <View style={styles.premiumPhoneRow}>
                                        <Icon name="phone-outline" size={12} color="#94A3B8" />
                                        <Text style={styles.premiumParentPhone} numberOfLines={1} adjustsFontSizeToFit>{route.hostess_phone || '-'}</Text>
                                    </View>
                                </View>
                            </View>
                        </View>
                    ) : (
                        <View style={styles.premiumBody}>
                            <View style={styles.emptyState}>
                                <Icon name="bus-alert" size={32} color="#64748B" />
                                <Text style={styles.emptyStateText}>Herhangi bir servise atanmamış.</Text>
                            </View>
                        </View>
                    )}
                </View>

                {/* Şifre ve Hesap Ayarları */}
                <View style={styles.premiumSection}>
                    <TouchableOpacity onPress={() => handleEdit('password')} style={[styles.premiumHeader, { borderBottomWidth: 0 }]}>
                        <View style={styles.premiumTitleRow}>
                            <View style={[styles.iconWrap, { backgroundColor: 'rgba(239, 68, 68, 0.15)' }]}>
                                <Icon name="lock-outline" size={18} color="#EF4444" />
                            </View>
                            <Text style={[styles.premiumTitle, { color: '#EF4444' }]}>ŞİFREYİ DEĞİŞTİR</Text>
                        </View>
                        <View style={styles.premiumEditBtn}>
                            <Icon name="chevron-right" size={20} color="#EF4444" />
                        </View>
                    </TouchableOpacity>
                </View>

                <View style={{ height: 100 }} />
            </ScrollView>

            {/* Düzenleme Modalı */}
            <Modal visible={editModalVisible} animationType="slide" transparent={true}>
                <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={styles.modalOverlay}>
                    <View style={styles.modalContent}>
                        <View style={styles.modalHeader}>
                            <Text style={styles.modalTitle}>
                                {editType === 'address' ? 'Adresi Güncelle' : 
                                 editType === 'password' ? 'Şifre Değiştir' : 'Velileri Güncelle'}
                            </Text>
                            <TouchableOpacity onPress={() => setEditModalVisible(false)} style={styles.closeBtn}>
                                <Icon name="close" size={20} color="#94A3B8" />
                            </TouchableOpacity>
                        </View>
                        
                        {editType === 'address' && (
                            <View style={styles.inputWrap}>
                                <Text style={styles.inputLabel}>Açık Adres</Text>
                                <TextInput
                                    style={[styles.input, {height: 100, textAlignVertical: 'top'}]}
                                    value={editData.address}
                                    onChangeText={(t) => setEditData({...editData, address: t})}
                                    multiline
                                    placeholder="Mahalle, sokak, no..."
                                    placeholderTextColor="#475569"
                                />
                            </View>
                        )}

                        {editType === 'parents' && (
                            <ScrollView showsVerticalScrollIndicator={false}>
                                <View style={styles.parentEditSection}>
                                    <Text style={styles.parentEditTitle}>1. VELİ BİLGİLERİ</Text>
                                    <View style={styles.inputWrap}>
                                        <Text style={styles.inputLabel}>Ad Soyad</Text>
                                        <TextInput style={styles.input} value={editData.parent1_name} onChangeText={(t) => setEditData({...editData, parent1_name: t})} placeholder="Ad Soyad" placeholderTextColor="#475569" />
                                    </View>
                                    <View style={styles.inputWrap}>
                                        <Text style={styles.inputLabel}>Telefon</Text>
                                        <TextInput style={styles.input} value={editData.parent1_phone} onChangeText={(t) => setEditData({...editData, parent1_phone: t})} keyboardType="phone-pad" placeholder="05XX XXX XX XX" placeholderTextColor="#475569" />
                                    </View>
                                </View>

                                <View style={styles.parentEditSection}>
                                    <Text style={styles.parentEditTitle}>2. VELİ BİLGİLERİ</Text>
                                    <View style={styles.inputWrap}>
                                        <Text style={styles.inputLabel}>Ad Soyad</Text>
                                        <TextInput style={styles.input} value={editData.parent2_name} onChangeText={(t) => setEditData({...editData, parent2_name: t})} placeholder="Ad Soyad" placeholderTextColor="#475569" />
                                    </View>
                                    <View style={styles.inputWrap}>
                                        <Text style={styles.inputLabel}>Telefon</Text>
                                        <TextInput style={styles.input} value={editData.parent2_phone} onChangeText={(t) => setEditData({...editData, parent2_phone: t})} keyboardType="phone-pad" placeholder="05XX XXX XX XX" placeholderTextColor="#475569" />
                                    </View>
                                </View>
                            </ScrollView>
                        )}

                        {editType === 'password' && (
                            <View>
                                <View style={styles.inputWrap}>
                                    <Text style={styles.inputLabel}>Mevcut Şifreniz</Text>
                                    <TextInput style={styles.input} value={editData.current_password} onChangeText={(t) => setEditData({...editData, current_password: t})} secureTextEntry placeholder="***" placeholderTextColor="#475569" />
                                </View>
                                <View style={styles.inputWrap}>
                                    <Text style={styles.inputLabel}>Yeni Şifre</Text>
                                    <TextInput style={styles.input} value={editData.password} onChangeText={(t) => setEditData({...editData, password: t})} secureTextEntry placeholder="***" placeholderTextColor="#475569" />
                                </View>
                                <View style={styles.inputWrap}>
                                    <Text style={styles.inputLabel}>Yeni Şifre (Tekrar)</Text>
                                    <TextInput style={styles.input} value={editData.password_confirmation} onChangeText={(t) => setEditData({...editData, password_confirmation: t})} secureTextEntry placeholder="***" placeholderTextColor="#475569" />
                                </View>
                            </View>
                        )}

                        <TouchableOpacity style={styles.saveBtn} onPress={handleSave} disabled={isSaving}>
                            <LinearGradient colors={['#8B5CF6', '#6D28D9']} style={styles.saveBtnGradient}>
                                {isSaving ? <ActivityIndicator color="#fff" /> : <Text style={styles.saveBtnText}>Değişiklikleri Kaydet</Text>}
                            </LinearGradient>
                        </TouchableOpacity>
                    </View>
                </KeyboardAvoidingView>
            </Modal>
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    centerContent: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 20, paddingTop: 20, paddingBottom: 15, zIndex: 10 },
    headerTitle: { color: '#fff', fontSize: 24, fontWeight: '900', letterSpacing: 0.5 },
    logoutBtn: { padding: 10, backgroundColor: 'rgba(239, 68, 68, 0.1)', borderRadius: 14, borderWidth: 1, borderColor: 'rgba(239, 68, 68, 0.2)' },
    scrollContent: { padding: 20, paddingBottom: 120, zIndex: 10 },
    
    studentCardPremium: { backgroundColor: 'rgba(30, 41, 59, 0.7)', borderRadius: 24, overflow: 'hidden', marginBottom: 20, borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.3)' },
    studentCardInner: { padding: 24, flexDirection: 'row', alignItems: 'center' },
    avatarPremiumWrap: { width: 70, height: 70, borderRadius: 24, overflow: 'hidden', marginRight: 16, shadowColor: '#8B5CF6', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.4, shadowRadius: 10, elevation: 8 },
    avatarGradient: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    avatarPremiumText: { fontSize: 32, fontWeight: '900', color: '#fff' },
    studentPremiumInfo: { flex: 1, justifyContent: 'center' },
    studentPremiumName: { fontSize: 20, fontWeight: '800', color: '#fff', marginBottom: 6 },
    gradeBadge: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(139, 92, 246, 0.15)', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8, alignSelf: 'flex-start' },
    gradeBadgeText: { fontSize: 12, fontWeight: '700', color: '#A78BFA', marginLeft: 4 },
    
    premiumSection: { backgroundColor: 'rgba(15, 23, 42, 0.7)', borderRadius: 24, marginBottom: 16, borderWidth: 1, borderColor: 'rgba(255, 255, 255, 0.05)' },
    premiumHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255, 255, 255, 0.05)' },
    premiumTitleRow: { flexDirection: 'row', alignItems: 'center' },
    iconWrap: { width: 32, height: 32, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
    premiumTitle: { color: '#A78BFA', fontSize: 13, fontWeight: '800', marginLeft: 12, letterSpacing: 1 },
    premiumEditBtn: { padding: 8, backgroundColor: 'rgba(255, 255, 255, 0.05)', borderRadius: 10 },
    premiumBody: { padding: 20 },
    premiumAddressText: { color: '#E2E8F0', fontSize: 14, lineHeight: 22, fontWeight: '500' },
    
    premiumBodyRow: { flexDirection: 'row', padding: 12 },
    premiumHalfBox: { flex: 1, backgroundColor: 'rgba(255, 255, 255, 0.03)', padding: 12, borderRadius: 16, marginHorizontal: 4, borderWidth: 1, borderColor: 'rgba(255, 255, 255, 0.03)' },
    parentBadge: { alignSelf: 'flex-start', backgroundColor: 'rgba(255, 255, 255, 0.1)', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6, marginBottom: 8 },
    parentBadgeText: { fontSize: 10, fontWeight: '800', color: '#94A3B8' },
    premiumParentName: { fontSize: 14, fontWeight: '700', color: '#fff', marginBottom: 6 },
    premiumPhoneRow: { flexDirection: 'row', alignItems: 'center', marginTop: 2 },
    premiumParentPhone: { fontSize: 12, color: '#94A3B8', marginLeft: 4, fontWeight: '600' },
    
    premiumRouteBody: { padding: 16 },
    premiumRouteBoxFull: { backgroundColor: 'rgba(56, 189, 248, 0.05)', padding: 16, borderRadius: 16, marginBottom: 8, borderWidth: 1, borderColor: 'rgba(56, 189, 248, 0.1)' },
    routeHeaderRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 },
    routeLabelPrimary: { fontSize: 10, fontWeight: '800', color: '#38BDF8' },
    plateBadge: { backgroundColor: '#38BDF8', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6 },
    plateText: { color: '#0F172A', fontSize: 11, fontWeight: '900' },
    routeValuePrimary: { fontSize: 16, fontWeight: '800', color: '#fff', marginBottom: 4 },
    routeSubPrimary: { fontSize: 12, color: '#94A3B8', fontWeight: '500' },
    personnelLabel: { fontSize: 10, fontWeight: '800', color: '#38BDF8', marginBottom: 6 },
    personnelName: { fontSize: 13, fontWeight: '700', color: '#fff', marginBottom: 4 },
    emptyState: { alignItems: 'center', paddingVertical: 20 },
    emptyStateText: { color: '#64748B', fontSize: 14, fontWeight: '600', marginTop: 12 },
    
    modalOverlay: { flex: 1, backgroundColor: 'rgba(2, 6, 23, 0.8)', justifyContent: 'flex-end' },
    modalContent: { backgroundColor: '#0F172A', borderTopLeftRadius: 32, borderTopRightRadius: 32, padding: 24, maxHeight: '85%', borderWidth: 1, borderColor: 'rgba(255, 255, 255, 0.1)' },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 },
    modalTitle: { color: '#fff', fontSize: 20, fontWeight: '800' },
    closeBtn: { padding: 8, backgroundColor: 'rgba(255, 255, 255, 0.05)', borderRadius: 12 },
    
    parentEditSection: { marginBottom: 20, backgroundColor: 'rgba(255, 255, 255, 0.02)', padding: 16, borderRadius: 16, borderWidth: 1, borderColor: 'rgba(255, 255, 255, 0.05)' },
    parentEditTitle: { color: '#8B5CF6', fontSize: 12, fontWeight: '800', marginBottom: 12 },
    
    inputLabel: { color: '#94A3B8', fontSize: 12, fontWeight: '600', marginBottom: 6, marginLeft: 4 },
    inputWrap: { backgroundColor: 'rgba(2, 6, 23, 0.5)', borderRadius: 16, borderWidth: 1, borderColor: 'rgba(255, 255, 255, 0.1)', marginBottom: 16 },
    input: { color: '#fff', fontSize: 15, padding: 16, fontWeight: '500' },
    
    saveBtn: { marginTop: 10, overflow: 'hidden', borderRadius: 16 },
    saveBtnGradient: { paddingVertical: 18, alignItems: 'center', justifyContent: 'center' },
    saveBtnText: { color: '#fff', fontSize: 16, fontWeight: '800', letterSpacing: 0.5 }
});
