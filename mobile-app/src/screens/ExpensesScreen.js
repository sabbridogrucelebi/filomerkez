import React, { useState, useEffect, useContext } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, Alert, RefreshControl, Modal, ScrollView, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons as Icon } from '@expo/vector-icons';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import { EmptyState, FormField, Header } from '../components';
import DatePickerInput from '../components/DatePickerInput';

const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 2 }).format(v || 0);

export default function ExpensesScreen({ navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const [expenses, setExpenses] = useState([]);
    const [types, setTypes] = useState({});
    const [vehicles, setVehicles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    // Form
    const [modalVisible, setModalVisible] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [saving, setSaving] = useState(false);
    const [formData, setFormData] = useState({ vehicle_id: '', type: '', amount: '', date: new Date().toISOString().split('T')[0], description: '' });
    
    // Select Modals
    const [showTypeSelect, setShowTypeSelect] = useState(false);
    const [showVehicleSelect, setShowVehicleSelect] = useState(false);

    const fetchData = async (isRefreshing = false) => {
        if (!isRefreshing) setLoading(true);
        try {
            const [expRes, vehRes] = await Promise.all([
                api.get('/v1/expenses').catch(() => ({ data: { expenses: [], types: {} } })),
                api.get('/v1/vehicles').catch(() => ({ data: { data: { vehicles: [] } } }))
            ]);
            
            if (expRes.data) {
                setExpenses(expRes.data.expenses || []);
                setTypes(expRes.data.types || {});
            }
            if (vehRes.data?.data?.vehicles) {
                setVehicles(vehRes.data.data.vehicles);
            }
        } catch (e) {
            console.error(e);
            if (e.response?.status === 403) {
                Alert.alert('Yetki Yok', 'Giderleri görüntüleme yetkiniz bulunmuyor.');
                navigation.goBack();
            }
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => { fetchData(); }, []);

    const openAdd = () => {
        if (!hasPermission('expenses.create') && !hasPermission('expenses.view')) { 
            Alert.alert('Yetki Yok', 'Gider ekleme yetkiniz bulunmuyor.'); 
            return; 
        }
        setEditingId(null);
        setFormData({ vehicle_id: '', type: '', amount: '', date: new Date().toISOString().split('T')[0], description: '' });
        setModalVisible(true);
    };

    const openEdit = (item) => {
        if (!hasPermission('expenses.edit') && !hasPermission('expenses.view')) { 
            Alert.alert('Yetki Yok', 'Gider düzenleme yetkiniz bulunmuyor.'); 
            return; 
        }
        setEditingId(item.id);
        setFormData({
            vehicle_id: item.vehicle_id?.toString() || '',
            type: item.type || '',
            amount: item.amount ? item.amount.toString() : '',
            date: item.date ? item.date.split('T')[0] : new Date().toISOString().split('T')[0],
            description: item.description || ''
        });
        setModalVisible(true);
    };

    const handleSave = async () => {
        if (!formData.vehicle_id || !formData.type || !formData.amount || !formData.date) {
            Alert.alert('Eksik Bilgi', 'Araç, Kategori, Tutar ve Tarih alanları zorunludur.');
            return;
        }

        setSaving(true);
        try {
            if (editingId) {
                await api.put(`/v1/expenses/${editingId}`, formData);
            } else {
                await api.post('/v1/expenses', formData);
            }
            setModalVisible(false);
            fetchData();
            Alert.alert('Başarılı', 'Gider kaydedildi.');
        } catch (e) {
            Alert.alert('Hata', 'Kaydedilemedi: ' + (e.response?.data?.message || e.message));
        } finally {
            setSaving(false);
        }
    };

    const confirmDelete = (id) => {
        if (!hasPermission('expenses.delete') && !hasPermission('expenses.view')) { 
            Alert.alert('Yetki Yok', 'Silme yetkiniz bulunmuyor.'); 
            return; 
        }
        Alert.alert('Silinecek', 'Bu gider kaydını silmek istediğinize emin misiniz?', [
            { text: 'İptal', style: 'cancel' },
            { text: 'Sil', style: 'destructive', onPress: async () => {
                try {
                    await api.delete(`/v1/expenses/${id}`);
                    fetchData();
                } catch (e) {
                    Alert.alert('Hata', 'Silinemedi.');
                }
            }}
        ]);
    };

    const getVehiclePlate = (id) => {
        const v = vehicles.find(x => x.id.toString() === id.toString());
        return v ? v.plate : 'Araç Seçiniz';
    };

    const renderItem = ({ item }) => {
        return (
            <View style={st.card}>
                <View style={st.cardHeader}>
                    <View style={st.iconBox}>
                        <Icon name="cash-multiple" size={24} color="#10B981" />
                    </View>
                    <View style={st.cardInfo}>
                        <Text style={st.cardTitle}>{item.type_name || item.type}</Text>
                        <Text style={st.cardSubtitle}>{item.vehicle_plate || 'Araç Bulunamadı'}</Text>
                    </View>
                    <Text style={st.amountText}>{fmtMoney(item.amount)}</Text>
                </View>

                <View style={st.cardDetails}>
                    <View style={st.detailRow}>
                        <Icon name="calendar-outline" size={14} color="#64748B" />
                        <Text style={st.detailText}>{item.date ? new Date(item.date).toLocaleDateString('tr-TR') : '-'}</Text>
                    </View>
                    {item.description ? (
                        <View style={st.detailRow}>
                            <Icon name="text" size={14} color="#64748B" />
                            <Text style={st.detailText}>{item.description}</Text>
                        </View>
                    ) : null}
                </View>

                <View style={st.actionRow}>
                    <TouchableOpacity style={[st.actionBtn, { backgroundColor: '#EFF6FF', marginRight: 8 }]} onPress={() => openEdit(item)}>
                        <Icon name="pencil-outline" size={16} color="#3B82F6" />
                        <Text style={[st.actionText, { color: '#3B82F6' }]}>Düzenle</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={[st.actionBtn, { backgroundColor: '#FEF2F2' }]} onPress={() => confirmDelete(item.id)}>
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
                        <Text style={st.headerTitle}>Masraflar</Text>
                        <Text style={st.headerSubtitle}>Tüm Harcamalarınız</Text>
                    </View>
                    <TouchableOpacity style={st.addHeaderBtn} onPress={openAdd}>
                        <Icon name="plus" size={24} color="#fff" />
                    </TouchableOpacity>
                </View>
            </View>

            {loading ? (
                <View style={st.loader}><ActivityIndicator size="large" color="#10B981" /></View>
            ) : (
                <FlatList
                    data={expenses}
                    renderItem={renderItem}
                    keyExtractor={item => item.id.toString()}
                    contentContainerStyle={st.listContent}
                    showsVerticalScrollIndicator={false}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => fetchData(true)} tintColor="#10B981" />}
                    ListEmptyComponent={<EmptyState title="Masraf Bulunamadı" message="Kayıtlı hiçbir masrafınız yok." icon="cash-register" />}
                />
            )}

            {/* Main Form Modal */}
            <Modal visible={modalVisible} animationType="slide" transparent>
                <View style={st.modalOverlay}>
                    <View style={st.modalContent}>
                        <View style={st.modalHeader}>
                            <Text style={st.modalTitle}>{editingId ? 'Masraf Düzenle' : 'Yeni Masraf Ekle'}</Text>
                            <TouchableOpacity onPress={() => setModalVisible(false)} style={st.modalClose}>
                                <Icon name="close" size={24} color="#64748B" />
                            </TouchableOpacity>
                        </View>
                        
                        <ScrollView style={{ padding: 20 }}>
                            <Text style={st.inputLabel}>İLGİLİ ARAÇ *</Text>
                            <TouchableOpacity style={st.selectBtn} onPress={() => setShowVehicleSelect(true)}>
                                <Text style={[st.selectBtnText, !formData.vehicle_id && {color: '#94A3B8'}]} numberOfLines={1}>
                                    {formData.vehicle_id ? getVehiclePlate(formData.vehicle_id) : 'Araç Seçiniz'}
                                </Text>
                                <Icon name="chevron-down" size={20} color="#64748B" />
                            </TouchableOpacity>

                            <View style={{ flexDirection: 'row', gap: 10, marginTop: 16 }}>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>KATEGORİ *</Text>
                                    <TouchableOpacity style={st.selectBtn} onPress={() => setShowTypeSelect(true)}>
                                        <Text style={[st.selectBtnText, !formData.type && {color: '#94A3B8'}]} numberOfLines={1}>
                                            {formData.type ? (types[formData.type] || formData.type) : 'Kategori'}
                                        </Text>
                                        <Icon name="chevron-down" size={20} color="#64748B" />
                                    </TouchableOpacity>
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>TARİH *</Text>
                                    <DatePickerInput value={formData.date} onChange={(d) => setFormData({...formData, date: d})} />
                                </View>
                            </View>

                            <Text style={[st.inputLabel, { marginTop: 16 }]}>TUTAR (₺) *</Text>
                            <FormField 
                                value={formData.amount} 
                                onChangeText={(t) => setFormData({...formData, amount: t})}
                                keyboardType="numeric"
                                placeholder="0.00"
                            />

                            <Text style={[st.inputLabel, { marginTop: 16 }]}>AÇIKLAMA</Text>
                            <FormField 
                                value={formData.description} 
                                onChangeText={(t) => setFormData({...formData, description: t})}
                                placeholder="Masraf detayı..."
                                multiline 
                                numberOfLines={2} 
                                style={{ height: 60, textAlignVertical: 'top' }}
                            />

                            <TouchableOpacity style={[st.saveBtn, saving && { opacity: 0.7 }]} onPress={handleSave} disabled={saving}>
                                {saving ? <ActivityIndicator color="#fff" /> : <Text style={st.saveBtnText}>Kaydet</Text>}
                            </TouchableOpacity>
                            <View style={{ height: 40 }} />
                        </ScrollView>
                    </View>
                </View>
            </Modal>

            {/* Type Select Modal */}
            <Modal visible={showTypeSelect} animationType="fade" transparent>
                <TouchableOpacity style={st.modalOverlay} onPress={() => setShowTypeSelect(false)} activeOpacity={1}>
                    <View style={[st.modalContent, { maxHeight: '60%' }]}>
                        <View style={st.modalHeader}>
                            <Text style={st.modalTitle}>Kategori Seçiniz</Text>
                            <TouchableOpacity onPress={() => setShowTypeSelect(false)} style={st.modalClose}>
                                <Icon name="close" size={24} color="#64748B" />
                            </TouchableOpacity>
                        </View>
                        <ScrollView style={{ padding: 16 }}>
                            {Object.entries(types).map(([key, val]) => (
                                <TouchableOpacity 
                                    key={key} 
                                    style={[st.categoryOption, formData.type === key && st.categoryOptionActive]}
                                    onPress={() => {
                                        setFormData({...formData, type: key});
                                        setShowTypeSelect(false);
                                    }}
                                >
                                    <Text style={[st.categoryOptionText, formData.type === key && st.categoryOptionTextActive]}>{val}</Text>
                                    {formData.type === key && <Icon name="check-circle" size={20} color="#10B981" />}
                                </TouchableOpacity>
                            ))}
                            <View style={{height:30}}/>
                        </ScrollView>
                    </View>
                </TouchableOpacity>
            </Modal>

            {/* Vehicle Select Modal */}
            <Modal visible={showVehicleSelect} animationType="fade" transparent>
                <TouchableOpacity style={st.modalOverlay} onPress={() => setShowVehicleSelect(false)} activeOpacity={1}>
                    <View style={[st.modalContent, { maxHeight: '70%' }]}>
                        <View style={st.modalHeader}>
                            <Text style={st.modalTitle}>Araç Seçiniz</Text>
                            <TouchableOpacity onPress={() => setShowVehicleSelect(false)} style={st.modalClose}>
                                <Icon name="close" size={24} color="#64748B" />
                            </TouchableOpacity>
                        </View>
                        <ScrollView style={{ padding: 16 }}>
                            {vehicles.map((v) => (
                                <TouchableOpacity 
                                    key={v.id} 
                                    style={[st.categoryOption, formData.vehicle_id?.toString() === v.id.toString() && st.categoryOptionActive]}
                                    onPress={() => {
                                        setFormData({...formData, vehicle_id: v.id.toString()});
                                        setShowVehicleSelect(false);
                                    }}
                                >
                                    <Text style={[st.categoryOptionText, formData.vehicle_id?.toString() === v.id.toString() && st.categoryOptionTextActive]}>{v.plate}</Text>
                                    {formData.vehicle_id?.toString() === v.id.toString() && <Icon name="check-circle" size={20} color="#10B981" />}
                                </TouchableOpacity>
                            ))}
                            {vehicles.length === 0 && <Text style={{textAlign:'center', color:'#64748B', marginTop:20}}>Araç bulunamadı.</Text>}
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
    addHeaderBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#10B981', alignItems: 'center', justifyContent: 'center', shadowColor: '#10B981', shadowOffset: { width: 0, height: 4 }, shadowopacity: 1, shadowRadius: 6, elevation: 4 },
    
    listContent: { padding: 16, paddingBottom: 120 },
    card: { backgroundColor: '#fff', borderRadius: 20, padding: 16, marginBottom: 16, shadowColor: '#94A3B8', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.1, shadowRadius: 8, elevation: 3 },
    cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 },
    iconBox: { width: 44, height: 44, borderRadius: 12, backgroundColor: '#ECFDF5', alignItems: 'center', justifyContent: 'center' },
    cardInfo: { flex: 1, paddingLeft: 12, paddingRight: 8 },
    cardTitle: { fontSize: 15, fontWeight: '800', color: '#0F172A', marginBottom: 2 },
    cardSubtitle: { fontSize: 12, color: '#64748B', fontWeight: '600' },
    amountText: { fontSize: 17, fontWeight: '900', color: '#10B981' },
    
    cardDetails: { backgroundColor: '#F8FAFC', borderRadius: 12, padding: 12, marginBottom: 12 },
    detailRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 4, gap: 6 },
    detailText: { fontSize: 13, color: '#334155', fontWeight: '500' },

    actionRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderTopWidth: 1, borderTopColor: '#F1F5F9', paddingTop: 12 },
    actionBtn: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingVertical: 10, borderRadius: 10, gap: 6 },
    actionText: { fontSize: 13, fontWeight: '700' },

    // Modal
    modalOverlay: { flex: 1, backgroundColor: 'rgba(15,23,42,0.6)', justifyContent: 'flex-end' },
    modalContent: { backgroundColor: '#fff', borderTopLeftRadius: 24, borderTopRightRadius: 24, maxHeight: '90%' },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 20, borderBottomWidth: 1, borderBottomColor: '#F1F5F9' },
    modalTitle: { fontSize: 18, fontWeight: '800', color: '#0F172A' },
    modalClose: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
    inputLabel: { fontSize: 11, fontWeight: '800', color: '#64748B', marginBottom: 8, marginLeft: 4, letterSpacing: 0.5 },
    saveBtn: { backgroundColor: '#10B981', borderRadius: 14, paddingVertical: 16, alignItems: 'center', marginTop: 24 },
    saveBtnText: { color: '#fff', fontSize: 15, fontWeight: '800' },
    
    selectBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: '#F8FAFC', borderWidth: 1, borderColor: '#E2E8F0', borderRadius: 12, paddingHorizontal: 16, height: 48 },
    selectBtnText: { fontSize: 13, color: '#1E293B', fontWeight: '500', flex: 1 },
    
    categoryOption: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 14, paddingHorizontal: 16, borderBottomWidth: 1, borderBottomColor: '#F1F5F9' },
    categoryOptionActive: { backgroundColor: '#ECFDF5', borderRadius: 8, borderBottomWidth: 0 },
    categoryOptionText: { fontSize: 14, color: '#334155', fontWeight: '500' },
    categoryOptionTextActive: { color: '#10B981', fontWeight: '700' },
});
