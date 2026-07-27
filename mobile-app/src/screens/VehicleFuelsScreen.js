import React, { useState, useEffect, useContext } from 'react';
import { View, StyleSheet, FlatList, ActivityIndicator, Alert, Text, Platform, TouchableOpacity, RefreshControl, Modal, ScrollView } from 'react-native';
import { MaterialCommunityIcons as Icon } from '@expo/vector-icons';
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

    const renderItem = ({ item }) => {
        const isPaid = item.is_paid;
        return (
            <LinearGradient 
                colors={['#FFFFFF', '#F8FAFC']} 
                start={{x:0, y:0}} 
                end={{x:0, y:1}} 
                style={[st.card, { borderLeftWidth: 4, borderLeftColor: isPaid ? '#10B981' : '#EF4444' }]}
            >
                <View style={st.cardTop}>
                    <View style={st.cardInfo}>
                        <Text style={st.dateText}>{new Date(item.date).toLocaleDateString('tr-TR')}</Text>
                        <Text style={st.stationName}>{item.station_name || 'İstasyon Belirtilmedi'}</Text>
                        <Text style={st.fuelTypeText}>{item.fuel_type || 'Dizel'}</Text>
                    </View>
                    <View style={{ alignItems: 'flex-end' }}>
                        <Text style={st.amountText}>{fmtMoney(item.total_cost || item.total_amount)}</Text>
                        <View style={[st.paidBadge, isPaid ? {backgroundColor: '#D1FAE5'} : {backgroundColor: '#FEE2E2'}]}>
                            <Text style={[st.paidText, isPaid ? {color: '#065F46'} : {color: '#991B1B'}]}>
                                {isPaid ? 'Ödendi' : 'Bekliyor'}
                            </Text>
                        </View>
                    </View>
                </View>

                <View style={st.cardDates}>
                    <View style={st.dateGroup}>
                        <Text style={st.dateLabel}>KM</Text>
                        <Text style={st.dateValue}>{item.km ? fmtNum(item.km) : '-'}</Text>
                    </View>
                    <View style={st.dateDivider} />
                    <View style={st.dateGroup}>
                        <Text style={st.dateLabel}>FARK</Text>
                        <Text style={st.dateValue}>{fmtNum(item.km_diff)}</Text>
                    </View>
                    <View style={st.dateDivider} />
                    <View style={st.dateGroup}>
                        <Text style={st.dateLabel}>LİTRE</Text>
                        <Text style={st.dateValue}>{fmtNum(item.liters)}</Text>
                    </View>
                    <View style={st.dateDivider} />
                    <View style={st.dateGroup}>
                        <Text style={st.dateLabel}>B.FİYAT</Text>
                        <Text style={st.dateValue}>{fmtMoney(item.price_per_liter)}</Text>
                    </View>
                    <View style={st.dateDivider} />
                    <View style={st.dateGroup}>
                        <Text style={st.dateLabel}>KM/L</Text>
                        <Text style={st.dateValue}>{fmtNum(item.km_per_liter)}</Text>
                    </View>
                </View>

                {item.notes ? (
                    <View style={st.notesBox}>
                        <Icon name="note-text-outline" size={14} color="#94A3B8" />
                        <Text style={st.notesText}>{item.notes}</Text>
                    </View>
                ) : null}

                <View style={st.actionRow}>
                    <TouchableOpacity style={[st.actionBtn, { backgroundColor: '#EFF6FF', flex: 1, marginRight: 8 }]} onPress={() => openEdit(item)}>
                        <Icon name="pencil-outline" size={16} color="#3B82F6" />
                        <Text style={[st.actionText, { color: '#3B82F6' }]}>Düzenle</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={[st.actionBtn, { backgroundColor: '#FEF2F2', flex: 1 }]} onPress={() => confirmDelete(item.id)}>
                        <Icon name="trash-can-outline" size={16} color="#EF4444" />
                        <Text style={[st.actionText, { color: '#EF4444' }]}>Sil</Text>
                    </TouchableOpacity>
                </View>
            </LinearGradient>
        );
    };

    return (
        <View style={st.container}>
            <View style={{ backgroundColor: '#fff', zIndex: 10, paddingTop: Platform.OS === 'android' ? 44 : 54 }}>
                <View style={st.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={st.backBtn}>
                        <Icon name="chevron-left" size={26} color="#0F172A" />
                    </TouchableOpacity>
                    <View style={st.headerCenter}>
                        <Text style={st.headerTitle}>Araç Yakıtları</Text>
                        <Text style={st.headerSubtitle}>{vehicle?.plate || 'Maliyet Takibi'}</Text>
                    </View>
                    <TouchableOpacity style={st.addHeaderBtn} onPress={openAdd}>
                        <Icon name="plus" size={24} color="#fff" />
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
                                    <Text style={st.statCardVal} numberOfLines={1}>{fmtMoney(summary.month_total)}</Text>
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
                                    <Text style={st.statCardVal} numberOfLines={1}>{fmtNum(summary.month_km)} KM</Text>
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
                                    <Text style={st.statCardVal} numberOfLines={1}>{fmtNum(summary.month_liters)} L</Text>
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
                                    <Text style={st.statCardVal} numberOfLines={1}>{fmtNum(summary.last_km)}</Text>
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
            </View>

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
                <View style={st.modalOverlay}>
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
                </View>
            </Modal>


        </View>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F8FAFC' },
    loader: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingBottom: 12 },
    backBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#F8FAFC', alignItems: 'center', justifyContent: 'center' },
    headerCenter: { flex: 1, alignItems: 'center' },
    headerTitle: { fontSize: 18, fontWeight: '800', color: '#0F172A', marginTop: 8 },
    headerSubtitle: { fontSize: 12, fontWeight: '600', color: '#64748B', marginTop: 2 },
    addHeaderBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#F59E0B', alignItems: 'center', justifyContent: 'center', shadowColor: '#F59E0B', shadowOffset: { width: 0, height: 4 }, shadowopacity: 1, shadowRadius: 6, elevation: 4 },
    
    summaryWrapper: { paddingHorizontal: 16, paddingBottom: 16, borderBottomWidth: 1, borderBottomColor: '#E2E8F0', marginTop: 8 },
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
        backgroundColor: '#fff' 
    },
    statCard: { 
        flex: 1, 
        borderRadius: 20, 
        overflow: 'hidden',
        borderTopWidth: 1.5,
        borderTopColor: 'rgba(255,255,255,0.4)',
        borderBottomWidth: 4,
        borderBottomColor: 'rgba(0,0,0,0.2)',
        borderLeftWidth: 0.5,
        borderRightWidth: 0.5,
        borderColor: 'rgba(0,0,0,0.1)'
    },
    statCardBgIcon: { position: 'absolute', right: -10, bottom: -10, transform: [{ rotate: '-15deg' }] },
    statCardInner: { flex: 1, padding: 12, justifyContent: 'center', backgroundColor: 'rgba(255,255,255,0.05)' },
    statCardLabel: { fontSize: 10, color: 'rgba(255,255,255,0.9)', fontWeight: '800', letterSpacing: 0.5, textShadowColor: 'rgba(0,0,0,0.2)', textShadowOffset: { width: 0, height: 1 }, textShadowRadius: 2, marginBottom: 4 },
    statCardVal: { fontSize: 18, color: '#FFF', fontWeight: '900', textShadowColor: 'rgba(0,0,0,0.2)', textShadowOffset: { width: 0, height: 2 }, textShadowRadius: 4 },

    listContent: { padding: 16, paddingBottom: 120 },
    card: { 
        backgroundColor: '#fff', 
        borderRadius: 20, 
        padding: 16, 
        marginBottom: 16, 
        shadowColor: '#3B82F6', 
        shadowOffset: { width: 0, height: 6 }, 
        shadowOpacity: 0.1, 
        shadowRadius: 10, 
        elevation: 4,
        borderWidth: 1,
        borderColor: '#E2E8F0',
        borderBottomWidth: 4
    },
    cardTop: { flexDirection: 'row', alignItems: 'flex-start', marginBottom: 12 },
    cardInfo: { flex: 1, justifyContent: 'center' },
    dateText: { fontSize: 11, fontWeight: '800', color: '#64748B', letterSpacing: 0.5, marginBottom: 2 },
    stationName: { fontSize: 15, fontWeight: '800', color: '#1E293B', letterSpacing: -0.2 },
    fuelTypeText: { fontSize: 11, fontWeight: '700', color: '#94A3B8', marginTop: 2 },
    amountText: { fontSize: 16, fontWeight: '800', color: '#0F172A' },
    paidBadge: { paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6, marginTop: 4 },
    paidText: { fontSize: 10, fontWeight: '800' },
    
    cardDates: { flexDirection: 'row', alignItems: 'center', marginTop: 8, padding: 12, backgroundColor: '#F8FAFC', borderRadius: 12 },
    dateGroup: { flex: 1, alignItems: 'center' },
    dateDivider: { width: 1, height: 24, backgroundColor: '#E2E8F0', marginHorizontal: 8 },
    dateLabel: { fontSize: 9, fontWeight: '800', color: '#94A3B8', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 4 },
    dateValue: { fontSize: 11, fontWeight: '800', color: '#334155' },

    notesBox: { flexDirection: 'row', alignItems: 'center', marginTop: 12, paddingHorizontal: 4 },
    notesText: { fontSize: 12, color: '#64748B', marginLeft: 6, flex: 1 },

    actionRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', marginTop: 16, borderTopWidth: 1, borderTopColor: '#F1F5F9', paddingTop: 16, gap: 8 },
    actionBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16, paddingVertical: 10, borderRadius: 12 },
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
