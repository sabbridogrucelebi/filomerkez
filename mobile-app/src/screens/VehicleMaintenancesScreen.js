import React, { useState, useEffect, useContext } from 'react';
import { View, StyleSheet, FlatList, ActivityIndicator, Alert, Text, Platform, TouchableOpacity, RefreshControl, Modal, ScrollView } from 'react-native';
import { MaterialCommunityIcons as Icon } from '@expo/vector-icons';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import { EmptyState, FormField } from '../components';
import DatePickerInput from '../components/DatePickerInput';

const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 2 }).format(v || 0);
const fmtKm = (v) => new Intl.NumberFormat('tr-TR').format(v || 0);

export default function VehicleMaintenancesScreen({ route, navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const { vehicleId, vehicle } = route.params || {};
    const [maintenances, setMaintenances] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    const [modalVisible, setModalVisible] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [saving, setSaving] = useState(false);
    const [formData, setFormData] = useState({
        service_date: new Date().toISOString().split('T')[0],
        maintenance_type: 'Periyodik',
        title: '',
        km: '',
        amount: '',
        service_name: '',
        description: ''
    });

    const [showCategorySelect, setShowCategorySelect] = useState(false);
    const categories = ['YAĞ BAKIMI', 'ALT YAĞLAMA', 'LASTİK BAKIMI', 'AKÜ BAKIMI', 'AĞIR BAKIM', 'ANTFRİZ BAKIMI', 'ARIZA/ONARIM', 'MUAYENE', 'DİĞER BAKIMLAR'];

    const fetchMaintenances = async (isRefreshing = false) => {
        if (!vehicleId) return;
        if (!isRefreshing) setLoading(true);
        try {
            const r = await api.get(`/vehicles/${vehicleId}/maintenances`);
            if (r.data) {
                setMaintenances(r.data.maintenances || []);
            }
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => { fetchMaintenances(); }, [vehicleId]);

    const openAdd = () => {
        if (!hasPermission('maintenances.create')) {
            Alert.alert('Yetki Yok', 'Bakım kaydı ekleme yetkiniz bulunmuyor.');
            return;
        }
        setEditingId(null);
        setFormData({
            service_date: new Date().toISOString().split('T')[0],
            maintenance_type: '',
            title: '',
            km: '',
            next_service_km: '',
            amount: '',
            service_name: '',
            description: ''
        });
        setModalVisible(true);
    };

    const openEdit = (item) => {
        if (!hasPermission('maintenances.edit')) {
            Alert.alert('Yetki Yok', 'Bakım kaydı düzenleme yetkiniz bulunmuyor.');
            return;
        }
        setEditingId(item.id);
        setFormData({
            service_date: item.date ? item.date.split('T')[0] : new Date().toISOString().split('T')[0],
            maintenance_type: item.type || '',
            title: item.title || '',
            km: item.km ? item.km.toString() : '',
            next_service_km: item.next_km ? item.next_km.toString() : '',
            amount: item.amount ? item.amount.toString() : '',
            service_name: item.service_name || '',
            description: item.description || ''
        });
        setModalVisible(true);
    };

    const handleSave = async () => {
        if (!formData.title || !formData.service_date || !formData.maintenance_type) {
            Alert.alert('Eksik Bilgi', 'Tarih, Kategori ve İşlem Adı zorunludur.'); return;
        }

        setSaving(true);
        try {
            const url = editingId ? `/v1/maintenances/${editingId}` : '/v1/maintenances';
            const method = editingId ? 'PUT' : 'POST';
            await api({ method, url, data: { ...formData, vehicle_id: vehicleId } });
            setModalVisible(false);
            fetchMaintenances();
            Alert.alert('Başarılı', 'Bakım kaydı kaydedildi.');
        } catch (e) {
            Alert.alert('Hata', 'Kaydedilemedi.');
        } finally { setSaving(false); }
    };

    const confirmDelete = (id) => {
        if (!hasPermission('maintenances.delete')) {
            Alert.alert('Yetki Yok', 'Bakım kaydı silme yetkiniz bulunmuyor.');
            return;
        }
        Alert.alert('Silinecek', 'Bu bakım kaydını silmek istediğinize emin misiniz?', [
            { text: 'İptal', style: 'cancel' },
            { text: 'Sil', style: 'destructive', onPress: async () => {
                try { await api.delete(`/v1/maintenances/${id}`); fetchMaintenances(); }
                catch (e) { Alert.alert('Hata', 'Silinemedi.'); }
            }}
        ]);
    };

    const getTypeStyle = (type) => {
        const t = (type || '').toUpperCase();
        if (t === 'ARIZA/ONARIM' || t === 'ARIZA / ONARIM') return { color: '#EF4444', icon: 'alert-octagon-outline', bg: '#FEF2F2' };
        if (t === 'LASTİK BAKIMI' || t === 'LASTİK') return { color: '#10B981', icon: 'tire', bg: '#ECFDF5' };
        if (t === 'MUAYENE') return { color: '#F59E0B', icon: 'shield-check-outline', bg: '#FFFBEB' };
        return { color: '#3B82F6', icon: 'wrench-outline', bg: '#EFF6FF' };
    };

    const toTitleCase = (str) => {
        if (!str) return '';
        return str.toString().split(' ').map(word => {
            if (!word) return '';
            const first = word.charAt(0).toLocaleUpperCase('tr-TR');
            const rest = word.slice(1).toLocaleLowerCase('tr-TR');
            return first + rest;
        }).join(' ');
    };

    const renderItem = ({ item }) => {
        const ts = getTypeStyle(item.type);
        const hasDesc = item.description && item.description.trim() !== '';

        return (
            <View style={[st.card, { borderLeftColor: ts.color }]}>
                <View style={st.cardHeader}>
                    <View style={[st.iconBox, { backgroundColor: ts.bg }]}>
                        <Icon name={ts.icon} size={24} color={ts.color} />
                    </View>
                    <View style={{ flex: 1, paddingLeft: 12, paddingRight: 8 }}>
                        <Text style={st.cardTitle}>{toTitleCase(item.title)}</Text>
                        <Text style={[st.cardDesc, hasDesc && { color: '#EF4444' }]}>
                            {hasDesc ? toTitleCase(item.description) : 'Açıklama yok'}
                        </Text>
                    </View>
                    <Text style={st.amountText}>{fmtMoney(item.amount)}</Text>
                </View>

                {/* 2x2 Grid for much better readability */}
                <View style={st.cardGrid}>
                    <View style={st.gridRow}>
                        <View style={st.gridCol}>
                            <View style={st.gridLabelRow}>
                                <Icon name="tag-outline" size={14} color="#64748B" />
                                <Text style={st.gridLabel}>TÜR</Text>
                            </View>
                            <Text style={st.gridValue}>{toTitleCase(item.type) || '-'}</Text>
                        </View>
                        <View style={st.gridDivider} />
                        <View style={st.gridCol}>
                            <View style={st.gridLabelRow}>
                                <Icon name="calendar-blank-outline" size={14} color="#F59E0B" />
                                <Text style={[st.gridLabel, { color: '#F59E0B' }]}>TARİH</Text>
                            </View>
                            <Text style={[st.gridValue, { color: '#D97706' }]}>{item.date ? new Date(item.date).toLocaleDateString('tr-TR') : '-'}</Text>
                            <Text style={[st.gridSubValue, { color: '#FBBF24' }]}>{item.next_date ? `Sonraki: ${new Date(item.next_date).toLocaleDateString('tr-TR')}` : 'Sonraki tarih yok'}</Text>
                        </View>
                    </View>
                    
                    <View style={st.gridHorizontalDivider} />
                    
                    <View style={st.gridRow}>
                        <View style={st.gridCol}>
                            <View style={st.gridLabelRow}>
                                <Icon name="store-outline" size={14} color="#64748B" />
                                <Text style={st.gridLabel}>SERVİS</Text>
                            </View>
                            <Text style={st.gridValue}>{toTitleCase(item.service_name) || '-'}</Text>
                        </View>
                        <View style={st.gridDivider} />
                        <View style={st.gridCol}>
                            <View style={st.gridLabelRow}>
                                <Icon name="speedometer" size={14} color="#10B981" />
                                <Text style={[st.gridLabel, { color: '#10B981' }]}>KİLOMETRE</Text>
                            </View>
                            <Text style={[st.gridValue, { color: '#059669' }]}>{item.km ? `${fmtKm(item.km)} KM` : '-'}</Text>
                            <Text style={[st.gridSubValue, { color: '#34D399' }]}>{item.next_km ? `Sonraki: ${fmtKm(item.next_km)} KM` : 'Sonraki KM yok'}</Text>
                        </View>
                    </View>
                </View>

                {/* ── ACTION BUTTONS ── */}
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
            </View>
        );
    };

    return (
        <View style={st.container}>
            <View style={{ backgroundColor: '#fff', zIndex: 10, paddingTop: Platform.OS === 'android' ? 44 : 54, paddingBottom: 12 }}>
                <View style={st.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={st.backBtn}>
                        <Icon name="chevron-left" size={26} color="#0F172A" />
                    </TouchableOpacity>
                    <View style={st.headerCenter}>
                        <Text style={st.headerTitle}>Araç Bakımları</Text>
                        <Text style={st.headerSubtitle}>{vehicle?.plate || 'Servis Geçmişi'}</Text>
                    </View>
                    <TouchableOpacity style={st.addHeaderBtn} onPress={openAdd}>
                        <Icon name="plus" size={24} color="#fff" />
                    </TouchableOpacity>
                </View>
            </View>

            {loading ? (
                <View style={st.loader}><ActivityIndicator size="large" color="#3B82F6" /></View>
            ) : (
                <FlatList
                    data={maintenances}
                    renderItem={renderItem}
                    keyExtractor={item => item.id.toString()}
                    contentContainerStyle={st.listContent}
                    showsVerticalScrollIndicator={false}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => fetchMaintenances(true)} tintColor="#3B82F6" />}
                    ListEmptyComponent={<EmptyState title="Bakım Kaydı Yok" message="Bu araç için servis geçmişi bulunmuyor." icon="wrench-outline" />}
                />
            )}

            {/* Main Form Modal */}
            <Modal visible={modalVisible} animationType="slide" transparent>
                <View style={st.modalOverlay}>
                    <View style={st.modalContent}>
                        <View style={st.modalHeader}>
                            <Text style={st.modalTitle}>Yeni Bakım Kaydı Ekle</Text>
                            <TouchableOpacity onPress={() => setModalVisible(false)} style={st.modalClose}>
                                <Icon name="close" size={24} color="#64748B" />
                            </TouchableOpacity>
                        </View>
                        
                        <ScrollView style={{ padding: 20 }}>
                            <View style={{ flexDirection: 'row', gap: 10 }}>
                                <View style={{ flex: 1 }}>
                                    <DatePickerInput 
                                        label="TARİH *" 
                                        value={formData.service_date} 
                                        onChange={(d) => setFormData({...formData, service_date: d})}
                                    />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>KATEGORİ *</Text>
                                    <TouchableOpacity 
                                        style={st.selectBtn} 
                                        onPress={() => setShowCategorySelect(true)}
                                    >
                                        <Text style={[st.selectBtnText, !formData.maintenance_type && {color: '#94A3B8'}]} numberOfLines={1}>
                                            {formData.maintenance_type || 'Kategori Seçiniz'}
                                        </Text>
                                        <Icon name="chevron-down" size={20} color="#64748B" />
                                    </TouchableOpacity>
                                </View>
                            </View>

                            <Text style={[st.inputLabel, { marginTop: 16 }]}>ARACA YAPILAN İŞLEM ADI *</Text>
                            <FormField 
                                value={formData.title} 
                                onChangeText={(t) => setFormData({...formData, title: t})}
                                placeholder="Örn: Yağ Değişimi, Kışlık Lastik..."
                                autoCapitalize="words"
                            />

                            <View style={{ flexDirection: 'row', gap: 10, marginTop: 16 }}>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>ARAÇ BAKIM KM'Sİ</Text>
                                    <FormField 
                                        value={formData.km} 
                                        onChangeText={(t) => setFormData({...formData, km: t})}
                                        keyboardType="numeric"
                                        placeholder="Örn: 150000"
                                    />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>BİR SONRAKİ BAKIM KM</Text>
                                    <FormField 
                                        value={formData.next_service_km} 
                                        onChangeText={(t) => setFormData({...formData, next_service_km: t})}
                                        keyboardType="numeric"
                                        placeholder="Örn: 160000"
                                    />
                                </View>
                            </View>

                            <View style={{ flexDirection: 'row', gap: 10, marginTop: 16 }}>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>TUTAR (₺)</Text>
                                    <FormField 
                                        value={formData.amount} 
                                        onChangeText={(t) => setFormData({...formData, amount: t})}
                                        keyboardType="numeric"
                                        placeholder="0.00"
                                    />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>USTA / SERVİS</Text>
                                    <FormField 
                                        value={formData.service_name} 
                                        onChangeText={(t) => setFormData({...formData, service_name: t})}
                                        placeholder="Usta veya servis adı"
                                        autoCapitalize="words"
                                    />
                                </View>
                            </View>

                            <Text style={[st.inputLabel, { marginTop: 16 }]}>NOT</Text>
                            <FormField 
                                value={formData.description} 
                                onChangeText={(t) => setFormData({...formData, description: t})}
                                placeholder="Yapılan işlemler hakkında detaylı bilgi..."
                                multiline 
                                numberOfLines={2} 
                                style={{ height: 60, textAlignVertical: 'top' }}
                                autoCapitalize="sentences"
                            />

                            <TouchableOpacity style={[st.saveBtn, saving && { opacity: 0.7 }]} onPress={handleSave} disabled={saving}>
                                {saving ? <ActivityIndicator color="#fff" /> : <Text style={st.saveBtnText}>Bakım Kaydını Kaydet</Text>}
                            </TouchableOpacity>
                            <View style={{ height: 40 }} />
                        </ScrollView>
                    </View>
                </View>
            </Modal>

            {/* Category Select Modal */}
            <Modal visible={showCategorySelect} animationType="fade" transparent>
                <TouchableOpacity style={st.modalOverlay} onPress={() => setShowCategorySelect(false)} activeOpacity={1}>
                    <View style={[st.modalContent, { maxHeight: '60%' }]}>
                        <View style={st.modalHeader}>
                            <Text style={st.modalTitle}>Kategori Seçiniz</Text>
                            <TouchableOpacity onPress={() => setShowCategorySelect(false)} style={st.modalClose}>
                                <Icon name="close" size={24} color="#64748B" />
                            </TouchableOpacity>
                        </View>
                        <ScrollView style={{ padding: 16 }}>
                            {categories.map((cat, idx) => (
                                <TouchableOpacity 
                                    key={idx} 
                                    style={[st.categoryOption, formData.maintenance_type === cat && st.categoryOptionActive]}
                                    onPress={() => {
                                        // Generate auto title based on category
                                        let newTitle = formData.title;
                                        if (!formData.title || formData.title.endsWith('Yapıldı') || formData.title.endsWith('Bakımı')) {
                                            if (cat === 'YAĞ BAKIMI') newTitle = 'Yağ Bakımı Yapıldı';
                                            else if (cat === 'ALT YAĞLAMA') newTitle = 'Alt Yağlama Yapıldı';
                                            else if (cat === 'LASTİK BAKIMI') newTitle = 'Lastik Bakımı Yapıldı';
                                            else if (cat === 'AKÜ BAKIMI') newTitle = 'Akü Bakımı Yapıldı';
                                            else if (cat === 'AĞIR BAKIM') newTitle = 'Ağır Bakım Yapıldı';
                                            else if (cat === 'ANTFRİZ BAKIMI') newTitle = 'Antfriz Bakımı Yapıldı';
                                            else if (cat === 'ARIZA/ONARIM') newTitle = 'Arıza Onarım Yapıldı';
                                            else if (cat === 'MUAYENE') newTitle = 'Muayene Yapıldı';
                                        }

                                        setFormData({...formData, maintenance_type: cat, title: newTitle});
                                        setShowCategorySelect(false);
                                    }}
                                >
                                    <Text style={[st.categoryOptionText, formData.maintenance_type === cat && st.categoryOptionTextActive]}>
                                        {cat}
                                    </Text>
                                    {formData.maintenance_type === cat && <Icon name="check-circle" size={20} color="#3B82F6" />}
                                </TouchableOpacity>
                            ))}
                            <View style={{height:30}}/>
                        </ScrollView>
                    </View>
                </TouchableOpacity>
            </Modal>


        </View>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F8FAFC' },
    loader: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16 },
    backBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#F8FAFC', alignItems: 'center', justifyContent: 'center' },
    headerCenter: { flex: 1, alignItems: 'center' },
    headerTitle: { fontSize: 18, fontWeight: '800', color: '#0F172A', marginTop: 8 },
    headerSubtitle: { fontSize: 12, fontWeight: '600', color: '#64748B', marginTop: 2 },
    addHeaderBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#3B82F6', alignItems: 'center', justifyContent: 'center', shadowColor: '#3B82F6', shadowOffset: { width: 0, height: 4 }, shadowopacity: 1, shadowRadius: 6, elevation: 4 },
    
    listContent: { padding: 16, paddingBottom: 120 },
    card: { backgroundColor: '#fff', borderRadius: 24, padding: 16, marginBottom: 16, shadowColor: '#94A3B8', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.15, shadowRadius: 12, elevation: 4, borderLeftWidth: 5 },
    cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 },
    iconBox: { width: 44, height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
    cardTitle: { fontSize: 16, fontWeight: '800', color: '#0F172A', marginBottom: 2 },
    cardDesc: { fontSize: 12, color: '#64748B', fontWeight: '500' },
    amountText: { fontSize: 16, fontWeight: '900', color: '#0F172A' },
    
    cardGrid: { backgroundColor: '#F8FAFC', borderRadius: 16, padding: 12, borderWidth: 1, borderColor: '#F1F5F9' },
    gridRow: { flexDirection: 'row', alignItems: 'flex-start' },
    gridCol: { flex: 1, paddingVertical: 4 },
    gridDivider: { width: 1, backgroundColor: '#E2E8F0', marginHorizontal: 12 },
    gridHorizontalDivider: { height: 1, backgroundColor: '#E2E8F0', marginVertical: 8 },
    gridLabelRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 6, gap: 4 },
    gridLabel: { fontSize: 10, fontWeight: '800', color: '#64748B', letterSpacing: 0.5 },
    gridValue: { fontSize: 13, fontWeight: '800', color: '#1E293B', marginBottom: 2 },
    gridSubValue: { fontSize: 10, color: '#94A3B8', fontWeight: '600' },

    actionRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', marginTop: 16, borderTopWidth: 1, borderTopColor: '#F1F5F9', paddingTop: 16 },
    actionBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16, paddingVertical: 10, borderRadius: 12 },
    actionText: { fontSize: 12, fontWeight: '800', marginLeft: 4 },

    // Modal
    modalOverlay: { flex: 1, backgroundColor: 'rgba(15,23,42,0.6)', justifyContent: 'flex-end' },
    modalContent: { backgroundColor: '#fff', borderTopLeftRadius: 24, borderTopRightRadius: 24, maxHeight: '90%' },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 20, borderBottomWidth: 1, borderBottomColor: '#F1F5F9' },
    modalTitle: { fontSize: 18, fontWeight: '800', color: '#0F172A' },
    modalClose: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
    inputLabel: { fontSize: 11, fontWeight: '800', color: '#64748B', marginBottom: 8, marginLeft: 4, letterSpacing: 0.5 },
    saveBtn: { backgroundColor: '#3B82F6', borderRadius: 12, paddingVertical: 16, alignItems: 'center', marginTop: 24 },
    saveBtnText: { color: '#fff', fontSize: 15, fontWeight: '800' },

    // Dummy Tab
    dummyTabBar: { position: 'absolute', bottom: 0, left: 0, right: 0, backgroundColor: '#fff', borderTopWidth: 1, borderTopColor: '#E2E8F0', paddingBottom: Platform.OS === 'ios' ? 20 : 0, flexDirection: 'row', height: Platform.OS === 'ios' ? 85 : 65, alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 10 },
    dummyTab: { flex: 1, alignItems: 'center', justifyContent: 'center', height: '100%' },
    dummyTabLabel: { fontSize: 10, fontWeight: '600', marginTop: 4, color: '#94A3B8' },
    dummyTabActiveText: { fontSize: 10, color: '#3B82F6', fontWeight: '700' },
    dummyCenterBtn: { width: 56, height: 56, borderRadius: 28, backgroundColor: '#3B82F6', alignItems: 'center', justifyContent: 'center', marginTop: -20, shadowColor: '#3B82F6', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.4, shadowRadius: 10, elevation: 6 },
    
    // Select button styles
    selectBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: '#F8FAFC', borderWidth: 1, borderColor: '#E2E8F0', borderRadius: 12, paddingHorizontal: 16, height: 48 },
    selectBtnText: { fontSize: 13, color: '#1E293B', fontWeight: '500', flex: 1 },
    
    // Category modal options
    categoryOption: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 14, paddingHorizontal: 16, borderBottomWidth: 1, borderBottomColor: '#F1F5F9' },
    categoryOptionActive: { backgroundColor: '#EFF6FF', borderRadius: 8, borderBottomWidth: 0 },
    categoryOptionText: { fontSize: 14, color: '#334155', fontWeight: '500' },
    categoryOptionTextActive: { color: '#3B82F6', fontWeight: '700' },
    
    dummyTabCenter: { flex: 1, alignItems: 'center' },
    dummyTabCenterInner: { width: 56, height: 56, borderRadius: 28, backgroundColor: '#2563EB', alignItems: 'center', justifyContent: 'center', marginTop: -35, shadowColor: '#2563EB', shadowOffset: {width:0, height:4}, shadowOpacity:0.3, shadowRadius:8, elevation: 5 },
});
