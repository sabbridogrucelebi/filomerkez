import React, { useState, useEffect, useContext, useRef } from 'react';
import { KeyboardAvoidingView, View, StyleSheet, FlatList, ActivityIndicator, Alert, Text, Platform, TouchableOpacity, RefreshControl, Modal, ScrollView, Animated } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { BlurView } from 'expo-blur';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import { EmptyState, FormField } from '../components';
import DatePickerInput from '../components/DatePickerInput';
import { LinearGradient } from 'expo-linear-gradient';

const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 2 }).format(v || 0);
const fmtNum = (v) => new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2 }).format(v || 0);

export default function VehicleFuelsScreen({ route, navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const { vehicleId, vehicle } = route.params || {};
    const [fuels, setFuels] = useState([]);
    const [summary, setSummary] = useState({});
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    const [modalVisible, setModalVisible] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [saving, setSaving] = useState(false);
    const [formData, setFormData] = useState({
        date: new Date().toISOString().split('T')[0],
        km: '', liters: '', price_per_liter: '', station_name: '', fuel_type: 'Dizel', notes: ''
    });

    const fetchFuels = async (isRefreshing = false) => {
        if (!vehicleId) return;
        if (!isRefreshing) setLoading(true);
        try {
            const r = await api.get(`/vehicles/${vehicleId}/fuels`);
            if (r.data) {
                setFuels(r.data.fuels || []);
                setSummary(r.data.summary || {});
            }
        } catch (e) {
            if (e.response?.status === 403) {
                Alert.alert('Erişim Engellendi', 'Yakıt kayıtlarını görüntüleme yetkiniz bulunmuyor.');
                navigation.goBack();
            } else if (e.response?.status === 404) {
                Alert.alert('Bulunamadı', 'Araç bulunamadı.');
                navigation.goBack();
            } else {
                Alert.alert('Hata', 'Veriler alınırken bir hata oluştu.');
            }
        } 
        finally { setLoading(false); setRefreshing(false); }
    };

    useEffect(() => { fetchFuels(); }, [vehicleId]);

    const openAdd = () => {
        if (!hasPermission('fuels.create')) { Alert.alert('Yetki Yok', 'Yakıt kaydı ekleme yetkiniz bulunmuyor.'); return; }
        setEditingId(null);
        setFormData({
            date: new Date().toISOString().split('T')[0],
            km: '', liters: '', price_per_liter: '', station_name: '', fuel_type: 'Dizel', notes: ''
        });
        setModalVisible(true);
    };

    const openEdit = (item) => {
        if (!hasPermission('fuels.edit')) { Alert.alert('Yetki Yok', 'Yakıt kaydı düzenleme yetkiniz bulunmuyor.'); return; }
        setEditingId(item.id);
        setFormData({
            date: item.date ? item.date.split('T')[0] : new Date().toISOString().split('T')[0],
            km: item.km ? item.km.toString() : '', 
            liters: item.liters ? item.liters.toString() : '', 
            price_per_liter: item.price_per_liter ? item.price_per_liter.toString() : '', 
            station_name: item.station_name || '', 
            fuel_type: item.fuel_type || 'Dizel', 
            notes: item.notes || ''
        });
        setModalVisible(true);
    };

    const handleSave = async () => {
        if (!formData.liters || !formData.date) {
            Alert.alert('Eksik Bilgi', 'Tarih ve litre alanları zorunludur.'); return;
        }
        setSaving(true);
        try {
            const url = editingId ? `/v1/fuels/${editingId}` : '/v1/fuels';
            const method = editingId ? 'PUT' : 'POST';
            
            // Calculate total cost if missing
            const dataToSubmit = { ...formData, vehicle_id: vehicleId };
            if (!dataToSubmit.total_cost && dataToSubmit.liters && dataToSubmit.price_per_liter) {
                dataToSubmit.total_cost = parseFloat(dataToSubmit.liters) * parseFloat(dataToSubmit.price_per_liter);
            } else if (!dataToSubmit.total_cost) {
                dataToSubmit.total_cost = 0; // fallback
            }

            await api({ method, url, data: dataToSubmit });
            setModalVisible(false);
            fetchFuels();
        } catch (e) { Alert.alert('Hata', 'Kaydedilemedi.'); } 
        finally { setSaving(false); }
    };

    const confirmDelete = (id) => {
        if (!hasPermission('fuels.delete')) { Alert.alert('Yetki Yok', 'Yakıt kaydı silme yetkiniz bulunmuyor.'); return; }
        Alert.alert('Silinecek', 'Bu yakıt kaydını silmek istediğinize emin misiniz?', [
            { text: 'İptal', style: 'cancel' },
            { text: 'Sil', style: 'destructive', onPress: async () => {
                try { await api.delete(`/v1/fuels/${id}`); fetchFuels(); }
                catch (e) { Alert.alert('Hata', 'Silinemedi.'); }
            }}
        ]);
    };

    const monthName = new Intl.DateTimeFormat('tr-TR', { month: 'long' }).format(new Date()).toUpperCase();

    const AnimatedFuelRow = ({ item, index }) => {
        const slideAnim = useRef(new Animated.Value(50)).current;
        const opacityAnim = useRef(new Animated.Value(0)).current;

        useEffect(() => {
            Animated.parallel([
                Animated.timing(opacityAnim, { toValue: 1, duration: 400, delay: index * 100, useNativeDriver: true }),
                Animated.spring(slideAnim, { toValue: 0, friction: 6, tension: 40, delay: index * 100, useNativeDriver: true })
            ]).start();
        }, []);

        const isPaid = item.is_paid;
        return (
            <Animated.View style={{ opacity: opacityAnim, transform: [{ translateY: slideAnim }], marginBottom: 16 }}>
                <BlurView intensity={30} tint="dark" style={[st.card, { borderLeftWidth: 4, borderLeftColor: isPaid ? '#34D399' : '#F87171' }]}>
                    <View style={st.cardTop}>
                        <View style={st.cardInfo}>
                            <Text style={st.dateText}>{new Date(item.date).toLocaleDateString('tr-TR')}</Text>
                            <Text style={st.stationName}>⛽ {item.station_name || 'İstasyon Belirtilmedi'}</Text>
                            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 6 }}>
                                <View style={{ backgroundColor: 'rgba(59, 130, 246, 0.15)', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6, borderWidth: 1, borderColor: 'rgba(59, 130, 246, 0.4)' }}>
                                    <Text style={{ fontSize: 11, fontWeight: '900', color: '#60A5FA', letterSpacing: 0.5 }}>
                                        {item.km ? `${fmtNum(item.km)} KM` : 'KM BELİRTİLMEDİ'}
                                    </Text>
                                </View>
                                <Text style={st.fuelTypeText}>{item.fuel_type || 'Dizel'}</Text>
                            </View>
                        </View>
                        <View style={{ alignItems: 'flex-end' }}>
                            <Text style={st.amountText}>{fmtMoney(item.gross_total_cost || item.total_cost || item.total_amount)}</Text>
                            <View style={[st.paidBadge, isPaid ? {backgroundColor: 'rgba(16, 185, 129, 0.2)'} : {backgroundColor: 'rgba(239, 68, 68, 0.2)'}]}>
                                <Text style={[st.paidText, isPaid ? {color: '#34D399'} : {color: '#F87171'}]}>
                                    {isPaid ? 'Ödendi' : 'Bekliyor'}
                                </Text>
                            </View>
                        </View>
                    </View>

                    <View style={st.cardDates}>
                        <View style={st.dateGroup}>
                            <Text style={st.dateLabel}>FARK</Text>
                            <Text style={st.dateValue} numberOfLines={1} adjustsFontSizeToFit>{fmtNum(item.km_diff)}</Text>
                        </View>
                        <View style={st.dateDivider} />
                        <View style={st.dateGroup}>
                            <Text style={st.dateLabel}>LİTRE</Text>
                            <Text style={st.dateValue} numberOfLines={1} adjustsFontSizeToFit>{fmtNum(item.liters)}</Text>
                        </View>
                        <View style={st.dateDivider} />
                        <View style={st.dateGroup}>
                            <Text style={st.dateLabel}>B.FİYAT</Text>
                            <Text style={st.dateValue} numberOfLines={1} adjustsFontSizeToFit>{fmtMoney(item.price_per_liter)}</Text>
                        </View>
                        <View style={st.dateDivider} />
                        <View style={st.dateGroup}>
                            <Text style={st.dateLabel}>KM/L</Text>
                            <Text style={st.dateValue} numberOfLines={1} adjustsFontSizeToFit>{fmtNum(item.km_per_liter)}</Text>
                        </View>
                    </View>

                    {item.notes ? (
                        <View style={st.notesBox}>
                            <Icon name="note-text-outline" size={14} color="#94A3B8" />
                            <Text style={st.notesText}>{item.notes}</Text>
                        </View>
                    ) : null}

                    <View style={st.actionRow}>
                        <TouchableOpacity style={[st.actionBtn, { backgroundColor: 'rgba(59, 130, 246, 0.15)', flex: 1, marginRight: 8 }]} onPress={() => openEdit(item)}>
                            <Icon name="pencil-outline" size={16} color="#60A5FA" />
                            <Text style={[st.actionText, { color: '#60A5FA' }]}>Düzenle</Text>
                        </TouchableOpacity>
                        <TouchableOpacity style={[st.actionBtn, { backgroundColor: 'rgba(239, 68, 68, 0.15)', flex: 1 }]} onPress={() => confirmDelete(item.id)}>
                            <Icon name="trash-can-outline" size={16} color="#F87171" />
                            <Text style={[st.actionText, { color: '#F87171' }]}>Sil</Text>
                        </TouchableOpacity>
                    </View>
                </BlurView>
            </Animated.View>
        );
    };

    const renderItem = ({ item, index }) => <AnimatedFuelRow item={item} index={index} />;

    return (
        <View style={st.container}>
            <SafeAreaView style={{ backgroundColor: 'transparent', zIndex: 10 }} edges={['top']}>
                <View style={st.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={st.backBtn}>
                        <Icon name="chevron-left" size={26} color="#F8FAFC" />
                    </TouchableOpacity>
                    <View style={st.headerCenter}>
                        <Text style={st.headerTitle}>Araç Yakıtları</Text>
                        <Text style={st.headerSubtitle}>{vehicle?.plate || 'Maliyet Takibi'}</Text>
                    </View>
                    <TouchableOpacity style={st.addHeaderBtn} onPress={openAdd}>
                        <Icon name="plus" size={24} color="#fff" style={{ textShadowColor: 'rgba(255,255,255,0.5)', textShadowRadius: 8 }} />
                    </TouchableOpacity>
                </View>

                {/* Web Panel Summary Mirror (3D Premium Grid) */}
                <View style={st.summaryWrapper}>
                    <View style={st.summaryGrid}>
                        {/* Orange Card (Month Cost) */}
                        <View style={[st.statCardContainer, { width: '48%' }]}>
                            <LinearGradient colors={['#F59E0B', '#D97706']} start={{x:0, y:0}} end={{x:1, y:1}} style={st.statCard}>
                                <Icon name="cash" size={60} color="rgba(255,255,255,0.15)" style={st.statCardBgIcon} />
                                <View style={st.statCardInner}>
                                    <Text style={st.statCardLabel}>{monthName} AYI GİDERİ</Text>
                                    <Text style={st.statCardVal} numberOfLines={1} adjustsFontSizeToFit>{fmtMoney(summary.month_total)}</Text>
                                    <View style={[st.summaryBoxFooter, { borderTopColor: 'rgba(255,255,255,0.2)' }]}>
                                        <Text style={{fontSize: 9, color: 'rgba(255,255,255,0.8)', fontWeight: '600'}} numberOfLines={1}>T: {fmtMoney(summary.all_time_total)}</Text>
                                    </View>
                                </View>
                            </LinearGradient>
                        </View>

                        {/* Blue Card (Month KM) */}
                        <View style={[st.statCardContainer, { width: '48%' }]}>
                            <LinearGradient colors={['#3B82F6', '#2563EB']} start={{x:0, y:0}} end={{x:1, y:1}} style={st.statCard}>
                                <Icon name="map-marker-distance" size={60} color="rgba(255,255,255,0.15)" style={st.statCardBgIcon} />
                                <View style={st.statCardInner}>
                                    <Text style={st.statCardLabel}>{monthName} AYI KM</Text>
                                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                                        <Text style={[st.statCardVal, { flexShrink: 1 }]} numberOfLines={1} adjustsFontSizeToFit>{fmtNum(summary.month_km)}</Text>
                                        <View style={{ backgroundColor: 'rgba(255,255,255,0.25)', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 6, borderWidth: 1, borderColor: 'rgba(255,255,255,0.6)' }}>
                                            <Text style={{ fontSize: 10, color: '#FFF', fontWeight: '900', letterSpacing: 0.5 }}>KM</Text>
                                        </View>
                                    </View>
                                    <View style={[st.summaryBoxFooter, { borderTopColor: 'rgba(255,255,255,0.2)' }]}>
                                        <Text style={{fontSize: 9, color: 'rgba(255,255,255,0.8)', fontWeight: '600'}} numberOfLines={1}>İ:{fmtNum(summary.month_first_km)} S:{fmtNum(summary.month_last_km)}</Text>
                                    </View>
                                </View>
                            </LinearGradient>
                        </View>
                    </View>

                    <View style={[st.summaryGrid, { marginTop: 12 }]}>
                        {/* Green Card (Month Liters) */}
                        <View style={[st.statCardContainer, { width: '48%' }]}>
                            <LinearGradient colors={['#10B981', '#059669']} start={{x:0, y:0}} end={{x:1, y:1}} style={st.statCard}>
                                <Icon name="water-outline" size={60} color="rgba(255,255,255,0.15)" style={st.statCardBgIcon} />
                                <View style={st.statCardInner}>
                                    <Text style={st.statCardLabel}>{monthName} AYI LİTRE</Text>
                                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                                        <Text style={[st.statCardVal, { flexShrink: 1 }]} numberOfLines={1} adjustsFontSizeToFit>{fmtNum(summary.month_liters)}</Text>
                                        <View style={{ backgroundColor: 'rgba(255,255,255,0.25)', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 6, borderWidth: 1, borderColor: 'rgba(255,255,255,0.6)' }}>
                                            <Text style={{ fontSize: 10, color: '#FFF', fontWeight: '900', letterSpacing: 0.5 }}>L</Text>
                                        </View>
                                    </View>
                                    <View style={[st.summaryBoxFooter, { borderTopColor: 'rgba(255,255,255,0.2)' }]}>
                                        <Text style={{fontSize: 9, color: 'rgba(255,255,255,0.8)', fontWeight: '700'}} numberOfLines={1}>FİŞ: {summary.month_count || 0}</Text>
                                    </View>
                                </View>
                            </LinearGradient>
                        </View>

                        {/* Purple Card (Last KM) */}
                        <View style={[st.statCardContainer, { width: '48%' }]}>
                            <LinearGradient colors={['#8B5CF6', '#6D28D9']} start={{x:0, y:0}} end={{x:1, y:1}} style={st.statCard}>
                                <Icon name="speedometer" size={60} color="rgba(255,255,255,0.15)" style={st.statCardBgIcon} />
                                <View style={st.statCardInner}>
                                    <Text style={st.statCardLabel}>SON KM</Text>
                                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                                        <Text style={[st.statCardVal, { flexShrink: 1 }]} numberOfLines={1} adjustsFontSizeToFit>{fmtNum(summary.last_km)}</Text>
                                        <View style={{ backgroundColor: 'rgba(255,255,255,0.25)', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 6, borderWidth: 1, borderColor: 'rgba(255,255,255,0.6)' }}>
                                            <Text style={{ fontSize: 10, color: '#FFF', fontWeight: '900', letterSpacing: 0.5 }}>KM</Text>
                                        </View>
                                    </View>
                                    <View style={[st.summaryBoxFooter, { borderTopColor: 'rgba(255,255,255,0.2)' }]}>
                                        <View style={{ flexDirection: 'row', gap: 4, alignItems: 'center' }}>
                                            <View style={{ width: 6, height: 6, borderRadius: 3, backgroundColor: '#10B981', borderWidth: 1, borderColor: 'rgba(255,255,255,0.5)' }} />
                                            <Text style={{ fontSize: 8, color: 'rgba(255,255,255,0.8)', fontWeight: '700' }}>ÖDENDİ</Text>
                                            <View style={{ width: 6, height: 6, borderRadius: 3, backgroundColor: '#EF4444', marginLeft: 2, borderWidth: 1, borderColor: 'rgba(255,255,255,0.5)' }} />
                                            <Text style={{ fontSize: 8, color: 'rgba(255,255,255,0.8)', fontWeight: '700' }}>BEKLİYOR</Text>
                                        </View>
                                    </View>
                                </View>
                            </LinearGradient>
                        </View>
                    </View>
                </View>
            </SafeAreaView>

            {loading ? (
                <View style={st.loader}><ActivityIndicator size="large" color="#F59E0B" /></View>
            ) : (
                <FlatList
                    data={fuels}
                    renderItem={renderItem}
                    keyExtractor={item => item.id.toString()}
                    contentContainerStyle={st.listContent}
                    showsVerticalScrollIndicator={false}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => fetchFuels(true)} tintColor="#F59E0B" />}
                    ListEmptyComponent={<EmptyState title="Yakıt Kaydı Yok" message="Bu araç için henüz yakıt girişi yapılmamış." icon="gas-station-outline" />}
                />
            )}

            {/* Modal */}
            <Modal visible={modalVisible} animationType="slide" transparent>
                <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={st.modalOverlay}>
                    <View style={st.modalContent}>
                        <View style={st.modalHeader}>
                            <Text style={st.modalTitle}>Yeni Yakıt Kaydı</Text>
                            <TouchableOpacity onPress={() => setModalVisible(false)} style={st.modalClose}>
                                <Icon name="close" size={24} color="#64748B" />
                            </TouchableOpacity>
                        </View>
                        
                        <ScrollView style={{ padding: 20 }}>
                            <DatePickerInput label="TARİH" value={formData.date} onChange={(d) => setFormData({...formData, date: d})} />
                            
                            <View style={{ flexDirection: 'row', gap: 10, marginTop: 16 }}>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>LİTRE</Text>
                                    <FormField value={formData.liters} onChangeText={t => setFormData({...formData, liters: t})} placeholder="0.00" keyboardType="numeric" />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>BİRİM FİYAT</Text>
                                    <FormField value={formData.price_per_liter} onChangeText={t => setFormData({...formData, price_per_liter: t})} placeholder="0.00" keyboardType="numeric" />
                                </View>
                            </View>

                            <View style={{ flexDirection: 'row', gap: 10, marginTop: 16 }}>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>KİLOMETRE</Text>
                                    <FormField value={formData.km} onChangeText={t => setFormData({...formData, km: t})} placeholder="150000" keyboardType="numeric" />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>YAKIT TÜRÜ</Text>
                                    <FormField value={formData.fuel_type} onChangeText={t => setFormData({...formData, fuel_type: t})} placeholder="Örn: Dizel" />
                                </View>
                            </View>

                            <Text style={st.inputLabel}>İSTASYON ADI</Text>
                            <FormField value={formData.station_name} onChangeText={t => setFormData({...formData, station_name: t})} placeholder="Örn: Shell, Opet..." />

                            <Text style={st.inputLabel}>NOTLAR</Text>
                            <FormField value={formData.notes} onChangeText={t => setFormData({...formData, notes: t})} placeholder="Açıklama..." multiline numberOfLines={2} style={{ height: 60, textAlignVertical: 'top' }} />

                            <TouchableOpacity style={[st.saveBtn, saving && { opacity: 0.7 }]} onPress={handleSave} disabled={saving}>
                                {saving ? <ActivityIndicator color="#fff" /> : <Text style={st.saveBtnText}>Kaydet</Text>}
                            </TouchableOpacity>
                            <View style={{ height: 40 }} />
                        </ScrollView>
                    </View>
                </KeyboardAvoidingView>
            </Modal>


        </View>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    loader: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingBottom: 12 },
    backBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center' },
    headerCenter: { flex: 1, alignItems: 'center' },
    headerTitle: { fontSize: 18, fontWeight: '800', color: '#F8FAFC', marginTop: 8, textShadowColor: 'rgba(255,255,255,0.2)', textShadowRadius: 10 },
    headerSubtitle: { fontSize: 12, fontWeight: '600', color: '#94A3B8', marginTop: 2 },
    addHeaderBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(245, 158, 11, 0.2)', borderWidth: 1, borderColor: '#F59E0B', alignItems: 'center', justifyContent: 'center', shadowColor: '#F59E0B', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.8, shadowRadius: 10, elevation: 8 },
    
    summaryWrapper: { paddingHorizontal: 16, paddingBottom: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)', marginTop: 8 },
    summaryGrid: { flexDirection: 'row', justifyContent: 'space-between' },
    summaryBoxFooter: { borderTopWidth: 1, paddingTop: 8, marginTop: 'auto' },

    // 3D Premium KPI Grid
    statCardContainer: { 
        height: 105, 
        borderRadius: 20,
        shadowColor: '#000', 
        shadowOffset: { width: 0, height: 6 }, 
        shadowOpacity: 0.25, 
        shadowRadius: 10, 
        elevation: 8,
        backgroundColor: 'rgba(0,0,0,0.5)' 
    },
    statCard: { 
        flex: 1, 
        borderRadius: 20, 
        overflow: 'hidden',
        borderTopWidth: 1.5,
        borderTopColor: 'rgba(255,255,255,0.4)',
        borderBottomWidth: 4,
        borderBottomColor: 'rgba(0,0,0,0.4)',
        borderLeftWidth: 0.5,
        borderRightWidth: 0.5,
        borderColor: 'rgba(0,0,0,0.2)'
    },
    statCardBgIcon: { position: 'absolute', right: -10, bottom: -10, transform: [{ rotate: '-15deg' }] },
    statCardInner: { flex: 1, padding: 12, justifyContent: 'center', backgroundColor: 'rgba(0,0,0,0.1)' },
    statCardLabel: { fontSize: 10, color: 'rgba(255,255,255,0.9)', fontWeight: '800', letterSpacing: 0.5, textShadowColor: 'rgba(0,0,0,0.2)', textShadowOffset: { width: 0, height: 1 }, textShadowRadius: 2, marginBottom: 4 },
    statCardVal: { fontSize: 18, color: '#FFF', fontWeight: '900', textShadowColor: 'rgba(0,0,0,0.2)', textShadowOffset: { width: 0, height: 2 }, textShadowRadius: 4 },

    listContent: { padding: 16, paddingBottom: 120 },
    card: { 
        borderRadius: 20, 
        padding: 16, 
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.05)',
        overflow: 'hidden'
    },
    cardTop: { flexDirection: 'row', alignItems: 'flex-start', marginBottom: 12 },
    cardInfo: { flex: 1, justifyContent: 'center' },
    dateText: { fontSize: 11, fontWeight: '800', color: '#64748B', letterSpacing: 0.5, marginBottom: 2 },
    stationName: { fontSize: 15, fontWeight: '800', color: '#F8FAFC', letterSpacing: -0.2 },
    fuelTypeText: { fontSize: 11, fontWeight: '700', color: '#94A3B8' },
    amountText: { fontSize: 16, fontWeight: '800', color: '#F8FAFC' },
    paidBadge: { paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6, marginTop: 4 },
    paidText: { fontSize: 10, fontWeight: '800' },
    
    cardDates: { flexDirection: 'row', alignItems: 'center', marginTop: 8, padding: 12, backgroundColor: 'rgba(0,0,0,0.2)', borderRadius: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.03)' },
    dateGroup: { flex: 1, alignItems: 'center' },
    dateDivider: { width: 1, height: 24, backgroundColor: 'rgba(255,255,255,0.1)', marginHorizontal: 8 },
    dateLabel: { fontSize: 9, fontWeight: '800', color: '#64748B', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 4 },
    dateValue: { fontSize: 11, fontWeight: '800', color: '#E2E8F0' },

    notesBox: { flexDirection: 'row', alignItems: 'center', marginTop: 12, paddingHorizontal: 4 },
    notesText: { fontSize: 12, color: '#94A3B8', marginLeft: 6, flex: 1 },

    actionRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', marginTop: 16, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)', paddingTop: 16, gap: 8 },
    actionBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16, paddingVertical: 10, borderRadius: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    actionText: { fontSize: 12, fontWeight: '800' },

    // Modal
    modalOverlay: { flex: 1, backgroundColor: 'rgba(15,23,42,0.6)', justifyContent: 'flex-end' },
    modalContent: { backgroundColor: '#fff', borderTopLeftRadius: 24, borderTopRightRadius: 24, maxHeight: '90%' },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 20, borderBottomWidth: 1, borderBottomColor: '#F1F5F9' },
    modalTitle: { fontSize: 18, fontWeight: '800', color: '#0F172A' },
    modalClose: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
    inputLabel: { fontSize: 11, fontWeight: '800', color: '#64748B', marginBottom: 8, marginLeft: 4, letterSpacing: 0.5 },
    saveBtn: { backgroundColor: '#F59E0B', borderRadius: 12, paddingVertical: 16, alignItems: 'center', marginTop: 24 },
    saveBtnText: { color: '#fff', fontSize: 15, fontWeight: '800' },

    // Dummy Tab
    dummyTabBar: { position: 'absolute', bottom: 0, left: 0, right: 0, backgroundColor: '#fff', borderTopWidth: 1, borderTopColor: '#E2E8F0', paddingBottom: Platform.OS === 'ios' ? 20 : 0, flexDirection: 'row', height: Platform.OS === 'ios' ? 85 : 65, alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 10 },
    dummyTab: { flex: 1, alignItems: 'center', justifyContent: 'center', height: '100%' },
    dummyTabLabel: { fontSize: 10, fontWeight: '600', marginTop: 4, color: '#94A3B8' },
    dummyTabCenter: { flex: 1, alignItems: 'center' },
    dummyTabCenterInner: { width: 56, height: 56, borderRadius: 28, backgroundColor: '#2563EB', alignItems: 'center', justifyContent: 'center', marginTop: -35, shadowColor: '#2563EB', shadowOffset: {width:0, height:4}, shadowOpacity:0.3, shadowRadius:8, elevation: 5 },
});
