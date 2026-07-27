import React, { useState, useEffect, useContext } from 'react';
import { View, StyleSheet, FlatList, ActivityIndicator, Alert, Text, Platform, TouchableOpacity, RefreshControl, Modal, ScrollView, Linking } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons as Icon } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import { Header, EmptyState, FormField } from '../components';
import DatePickerInput from '../components/DatePickerInput';
import * as DocumentPicker from 'expo-document-picker';

const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 2 }).format(v || 0);

const toTitleCase = (str) => {
    if (!str) return '';
    return str.toString().split(' ').map(word => {
        if (!word) return '';
        const first = word.charAt(0).toLocaleUpperCase('tr-TR');
        const rest = word.slice(1).toLocaleLowerCase('tr-TR');
        return first + rest;
    }).join(' ');
};

export default function VehiclePenaltiesScreen({ route, navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const { vehicleId, vehicle } = route.params || {};
    const [penalties, setPenalties] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [filter, setFilter] = useState('all');

    const [modalVisible, setModalVisible] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [saving, setSaving] = useState(false);
    const [formData, setFormData] = useState({
        penalty_no: '',
        penalty_date: new Date().toISOString().split('T')[0],
        penalty_time: '',
        penalty_article: '',
        penalty_amount: '',
        penalty_location: '',
        driver_name: '',
        payment_date: '',
        notes: ''
    });

    const fetchPenalties = async (isRefreshing = false) => {
        if (!vehicleId) return;
        if (!isRefreshing) setLoading(true);
        try {
            const r = await api.get(`/v1/vehicles/${vehicleId}/penalties`);
            
            if (r.data && r.data.success && r.data.data) {
                const responseData = r.data.data;
                if (responseData.penalties) {
                    setPenalties(responseData.penalties);
                } else if (Array.isArray(responseData)) {
                    setPenalties(responseData);
                } else {
                    setPenalties([]);
                }
            } else if (r.data && r.data.penalties) {
                // Fallback for non-v1 structured responses
                setPenalties(r.data.penalties);
            } else {
                setPenalties([]);
            }
        } catch (e) {
            console.error('Fetch penalties error:', e);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => { fetchPenalties(); }, [vehicleId]);

    const openAdd = () => {
        if (!hasPermission('penalties.create')) {
            Alert.alert('Yetki Yok', 'Ceza kaydı ekleme yetkiniz bulunmuyor.');
            return;
        }
        setEditingId(null);
        setFormData({
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
            payment_receipt: null,
            discount_amount: ''
        });
        setModalVisible(true);
    };

    const openEdit = (item) => {
        if (!hasPermission('penalties.edit')) {
            Alert.alert('Yetki Yok', 'Ceza kaydı düzenleme yetkiniz bulunmuyor.');
            return;
        }
        setEditingId(item.id);
        setFormData({
            penalty_no: item.penalty_no || '',
            penalty_date: item.date ? item.date.split('T')[0] : new Date().toISOString().split('T')[0],
            penalty_time: item.time || '',
            penalty_article: item.article || '',
            penalty_amount: item.amount ? item.amount.toString() : '',
            penalty_location: item.location || '',
            driver_name: item.driver_name || '',
            notes: item.notes || '',
            is_paid: item.is_paid || false,
            payment_date: item.payment_date ? item.payment_date.split('T')[0] : '',
            discount_amount: item.discount_amount ? item.discount_amount.toString() : ''
        });
        setModalVisible(true);
    };

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
        if (!formData.penalty_no || !formData.penalty_amount || !formData.penalty_date || !formData.driver_name) {
            Alert.alert('Eksik Bilgi', 'Ceza No, Tarih, Tutar ve Şoför Adı alanları zorunludur.'); return;
        }

        setSaving(true);
        try {
            const url = `/v1/vehicles/${vehicleId}/penalties` + (editingId ? `/${editingId}` : '');
            
            const formDataObj = new FormData();
            formDataObj.append('penalty_no', formData.penalty_no);
            formDataObj.append('penalty_date', formData.penalty_date);
            if (formData.penalty_time) formDataObj.append('penalty_time', formData.penalty_time);
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
            
            setModalVisible(false);
            fetchPenalties();
            Alert.alert('Başarılı', 'Ceza kaydı kaydedildi.');
        } catch (e) {
            Alert.alert('Hata', 'Kaydedilemedi.');
            console.error('Save error:', e);
        } finally { setSaving(false); }
    };

    const confirmDelete = (id) => {
        if (!hasPermission('penalties.delete')) {
            Alert.alert('Yetki Yok', 'Ceza kaydı silme yetkiniz bulunmuyor.');
            return;
        }
        Alert.alert('Silinecek', 'Bu ceza kaydını silmek istediğinize emin misiniz?', [
            { text: 'Vazgeç', style: 'cancel' },
            { text: 'Sil', style: 'destructive', onPress: async () => {
                try { await api.delete(`/v1/vehicles/${vehicleId}/penalties/${id}`); fetchPenalties(); }
                catch (e) { Alert.alert('Hata', 'Silinemedi.'); }
            }}
        ]);
    };

    const filteredData = Array.isArray(penalties) ? penalties.filter(p => {
        if (filter === 'paid') return p.payment_date;
        if (filter === 'unpaid') return !p.payment_date;
        return true;
    }) : [];

    const renderItem = ({ item }) => {
        const isPaid = !!item.payment_date;
        const discountDeadline = item.discount_deadline ? new Date(item.discount_deadline) : new Date(new Date(item.date).getTime() + 30 * 24 * 60 * 60 * 1000);
        
        const now = new Date();
        const isDiscountExpired = now > discountDeadline;
        
        let paymentStatusText = isPaid ? 'Ödendi' : 'Ödenmedi';
        if (isPaid && item.paid_amount && item.discounted_amount && item.paid_amount == item.discounted_amount) {
            paymentStatusText = '%25 İndirimli Ödendi';
        }

        return (
            <View style={st.card}>
                <View style={st.cardHeader}>
                    <View style={[st.iconBox, { backgroundColor: isPaid ? '#ECFDF5' : '#FEF2F2' }]}>
                        <Icon name={isPaid ? "check-circle-outline" : "alert-circle-outline"} size={20} color={isPaid ? '#10B981' : '#EF4444'} />
                    </View>
                    <View style={{ flex: 1, paddingLeft: 10, paddingRight: 8 }}>
                        <Text style={st.cardTitle}>{item.penalty_no?.toUpperCase() || '-'}</Text>
                        <Text style={st.cardDesc}>
                            {vehicle?.plate || 'Plaka Yok'} • Şoför: {toTitleCase(item.driver_name) || '-'}
                        </Text>
                    </View>
                    <View style={{ alignItems: 'flex-end' }}>
                        <Text style={[st.amountText, isPaid && { color: '#059669' }]}>
                            {fmtMoney(isPaid && item.paid_amount ? item.paid_amount : item.amount)}
                        </Text>
                        {!isPaid && !isDiscountExpired && (
                            <Text style={st.discountText}>
                                İndirimli: {fmtMoney(item.amount * 0.75)}
                            </Text>
                        )}
                    </View>
                </View>

                {/* Grid Structure - Compact */}
                <View style={st.cardGrid}>
                    <View style={st.gridRow}>
                        <View style={st.gridCol}>
                            <View style={st.gridLabelRow}>
                                <Icon name="calendar-blank-outline" size={12} color="#F59E0B" />
                                <Text style={[st.gridLabel, { color: '#F59E0B' }]}>TARİH</Text>
                            </View>
                            <Text style={[st.gridValue, { color: '#D97706' }]}>
                                {item.date ? new Date(item.date).toLocaleDateString('tr-TR') : '-'} 
                                {item.time ? ` ${item.time}` : ''}
                            </Text>
                        </View>
                        <View style={st.gridDivider} />
                        <View style={st.gridCol}>
                            <View style={st.gridLabelRow}>
                                <Icon name="file-document-outline" size={12} color="#3B82F6" />
                                <Text style={[st.gridLabel, { color: '#3B82F6' }]}>MADDE / YER</Text>
                            </View>
                            <Text style={[st.gridValue, { color: '#2563EB' }]} numberOfLines={1}>
                                {item.article?.toUpperCase() || '-'}
                            </Text>
                            <Text style={[st.gridSubValue, { color: '#60A5FA' }]} numberOfLines={1}>
                                {toTitleCase(item.location) || '-'}
                            </Text>
                        </View>
                    </View>
                    
                    <View style={st.gridHorizontalDivider} />
                    
                    <View style={st.gridRow}>
                        <View style={st.gridCol}>
                            <View style={st.gridLabelRow}>
                                <Icon name="credit-card-outline" size={12} color={isPaid ? '#10B981' : '#EF4444'} />
                                <Text style={[st.gridLabel, { color: isPaid ? '#10B981' : '#EF4444' }]}>ÖDEME DURUMU</Text>
                            </View>
                            <Text style={[st.gridValue, { color: isPaid ? '#059669' : '#DC2626' }]}>
                                {paymentStatusText}
                            </Text>
                            {isPaid && (
                                <Text style={[st.gridSubValue, { color: '#34D399' }]}>
                                    Tarih: {new Date(item.payment_date).toLocaleDateString('tr-TR')}
                                </Text>
                            )}
                        </View>
                        <View style={st.gridDivider} />
                        <View style={[st.gridCol, { justifyContent: 'center' }]}>
                            {/* Documents replacing Delete Button */}
                            <View style={st.docsRow}>
                                {item.traffic_penalty_document ? (
                                    <TouchableOpacity style={st.docBtn} onPress={() => Linking.openURL(item.traffic_penalty_document)}>
                                        <Icon name="file-document-outline" size={12} color="#6366F1" />
                                        <Text style={st.docText}>Ceza</Text>
                                    </TouchableOpacity>
                                ) : null}
                                {item.payment_receipt ? (
                                    <TouchableOpacity style={st.docBtn} onPress={() => Linking.openURL(item.payment_receipt)}>
                                        <Icon name="receipt" size={12} color="#10B981" />
                                        <Text style={st.docText}>Dekont</Text>
                                    </TouchableOpacity>
                                ) : null}
                                {!item.traffic_penalty_document && !item.payment_receipt && (
                                    <Text style={[st.gridSubValue, { color: '#94A3B8', fontStyle: 'italic' }]}>Belge yok</Text>
                                )}
                            </View>
                        </View>
                    </View>
                </View>

                {item.notes ? (
                    <View style={st.notesBox}>
                        <Icon name="information-outline" size={12} color="#64748B" />
                        <Text style={st.notesText}>Not: {toTitleCase(item.notes)}</Text>
                    </View>
                ) : null}

                {/* ── ACTION BUTTONS ── */}
                <View style={[st.actionRow, { marginTop: 16, borderTopWidth: 1, borderTopColor: '#F1F5F9', paddingTop: 16, flexDirection: 'row' }]}>
                    <TouchableOpacity style={[{ flex: 1, marginRight: 8, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingVertical: 10, borderRadius: 12, backgroundColor: '#EFF6FF' }]} onPress={() => openEdit(item)}>
                        <Icon name="pencil-outline" size={16} color="#3B82F6" />
                        <Text style={[{ color: '#3B82F6', fontSize: 12, fontWeight: '800', marginLeft: 4 }]}>Düzenle</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={[{ flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingVertical: 10, borderRadius: 12, backgroundColor: '#FEF2F2' }]} onPress={() => confirmDelete(item.id)}>
                        <Icon name="trash-can-outline" size={16} color="#EF4444" />
                        <Text style={[{ color: '#EF4444', fontSize: 12, fontWeight: '800', marginLeft: 4 }]}>Sil</Text>
                    </TouchableOpacity>
                </View>
            </View>
        );
    };

    return (
        <SafeAreaView style={st.container} edges={['top']}>
            <View style={{ backgroundColor: '#fff', zIndex: 10, paddingBottom: 12 }}>
                <View style={st.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={st.backBtn}>
                        <Icon name="chevron-left" size={26} color="#0F172A" />
                    </TouchableOpacity>
                    <View style={st.headerCenter}>
                        <Text style={st.headerTitle}>Trafik Cezaları</Text>
                        <Text style={st.headerSubtitle}>{vehicle?.plate || 'Ceza Takibi'}</Text>
                    </View>
                    <TouchableOpacity style={st.addHeaderBtn} onPress={openAdd}>
                        <Icon name="plus" size={24} color="#fff" />
                    </TouchableOpacity>
                </View>
            </View>

            <View style={st.filterBar}>
                {[
                    { label: 'Tümü', value: 'all' },
                    { label: 'Ödenenler', value: 'paid' },
                    { label: 'Ödenmeyenler', value: 'unpaid' },
                ].map(chip => (
                    <TouchableOpacity 
                        key={chip.value} 
                        style={[st.filterChip, filter === chip.value && st.filterChipActive]}
                        onPress={() => setFilter(chip.value)}
                    >
                        <Text style={[st.filterChipText, filter === chip.value && st.filterChipTextActive]}>{chip.label}</Text>
                    </TouchableOpacity>
                ))}
            </View>

            {loading ? (
                <View style={st.loader}><ActivityIndicator size="large" color="#EF4444" /></View>
            ) : (
                <FlatList
                    data={filteredData}
                    renderItem={renderItem}
                    keyExtractor={item => item.id.toString()}
                    contentContainerStyle={st.listContent}
                    showsVerticalScrollIndicator={false}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => fetchPenalties(true)} tintColor="#EF4444" />}
                    ListEmptyComponent={<EmptyState title="Ceza Kaydı Yok" message="Bu araç için trafik cezası bulunmuyor." icon="alert-octagon-outline" />}
                />
            )}

            {/* Modal Form */}
            <Modal visible={modalVisible} animationType="slide" transparent>
                <View style={st.modalOverlay}>
                    <View style={st.modalContent}>
                        <View style={st.modalHeader}>
                            <Text style={st.modalTitle}>Yeni Ceza Kaydı Ekle</Text>
                            <TouchableOpacity onPress={() => setModalVisible(false)} style={st.modalClose}>
                                <Icon name="close" size={24} color="#64748B" />
                            </TouchableOpacity>
                        </View>
                        
                        <ScrollView style={{ padding: 20 }}>
                            <View style={{ flexDirection: 'row', gap: 10 }}>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>CEZA NO / SERİ *</Text>
                                    <FormField 
                                        value={formData.penalty_no} 
                                        onChangeText={(t) => setFormData({...formData, penalty_no: t.toUpperCase()})}
                                        placeholder="Örn: MB92600554"
                                        autoCapitalize="characters"
                                    />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>ŞOFÖR ADI *</Text>
                                    <FormField 
                                        value={formData.driver_name} 
                                        onChangeText={(t) => setFormData({...formData, driver_name: toTitleCase(t)})}
                                        placeholder="Ceza kesilen şoför"
                                        autoCapitalize="words"
                                    />
                                </View>
                            </View>

                            <View style={{ flexDirection: 'row', gap: 10, marginTop: 16 }}>
                                <View style={{ flex: 1 }}>
                                    <DatePickerInput 
                                        label="CEZA TARİHİ *" 
                                        value={formData.penalty_date} 
                                        onChange={(d) => setFormData({...formData, penalty_date: d})}
                                    />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>SAAT</Text>
                                    <FormField 
                                        value={formData.penalty_time} 
                                        onChangeText={(t) => setFormData({...formData, penalty_time: t})}
                                        placeholder="10:30"
                                        keyboardType="numeric"
                                    />
                                </View>
                            </View>

                            <View style={{ flexDirection: 'row', gap: 10, marginTop: 16 }}>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>CEZA MADDESİ *</Text>
                                    <FormField 
                                        value={formData.penalty_article} 
                                        onChangeText={(t) => setFormData({...formData, penalty_article: t.toUpperCase()})}
                                        placeholder="73/C HIZ SINIRI"
                                        autoCapitalize="characters"
                                    />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>TUTAR (₺) *</Text>
                                    <FormField 
                                        value={formData.penalty_amount} 
                                        onChangeText={(t) => setFormData({...formData, penalty_amount: t})}
                                        keyboardType="numeric"
                                        placeholder="0.00"
                                    />
                                </View>
                            </View>

                            <Text style={[st.inputLabel, { marginTop: 16 }]}>KONUM / YER</Text>
                            <FormField 
                                value={formData.penalty_location} 
                                onChangeText={(t) => setFormData({...formData, penalty_location: toTitleCase(t)})}
                                placeholder="FURGAN DEDE CADDESİ"
                                autoCapitalize="words"
                            />

                            <View style={{ flexDirection: 'row', gap: 10, marginTop: 16 }}>
                                <View style={{ flex: 1 }}>
                                    <DatePickerInput 
                                        label="ÖDEME TARİHİ (ÖDENDİYSE)" 
                                        value={formData.payment_date} 
                                        onChange={(d) => setFormData({...formData, payment_date: d})}
                                    />
                                </View>
                            </View>

                            {/* Smart Amount Card (Akıllı Tutar Kartı) */}
                            <View style={st.smartCard}>
                                <Text style={st.smartCardTitle}>Akıllı Tutar Kartı</Text>
                                <View style={st.smartCardRow}>
                                    <Text style={st.smartCardLabel}>NORMAL CEZA</Text>
                                    <Text style={st.smartCardValue}>{fmtMoney(formData.penalty_amount || 0)}</Text>
                                </View>
                                <View style={[st.smartCardRow, { backgroundColor: '#ECFDF5', padding: 8, borderRadius: 8, marginTop: 4 }]}>
                                    <Text style={[st.smartCardLabel, { color: '#10B981' }]}>%25 İNDİRİMLİ TUTAR</Text>
                                    <Text style={[st.smartCardValue, { color: '#059669' }]}>{fmtMoney((formData.penalty_amount || 0) * 0.75)}</Text>
                                </View>
                                <Text style={st.smartCardHint}>* %25 indirimli tutar, ceza tarihinden itibaren 1 ay içerisinde ödenmesi durumunda uygulanır.</Text>
                            </View>

                            {/* Document Pickers */}
                            <Text style={[st.inputLabel, { marginTop: 20 }]}>TRAFİK CEZASI BELGESİ</Text>
                            <TouchableOpacity style={st.docUploadBtn} onPress={() => pickDocument('traffic_penalty_document')}>
                                <Icon name="file-upload-outline" size={20} color="#6366F1" />
                                <Text style={st.docUploadText}>
                                    {formData.traffic_penalty_document ? formData.traffic_penalty_document.name : 'Ceza Belgesi Yükle (PDF, JPG)'}
                                </Text>
                            </TouchableOpacity>

                            <Text style={[st.inputLabel, { marginTop: 16 }]}>ÖDEME DEKONTU</Text>
                            <TouchableOpacity style={st.docUploadBtn} onPress={() => pickDocument('payment_receipt')}>
                                <Icon name="receipt" size={20} color="#10B981" />
                                <Text style={st.docUploadText}>
                                    {formData.payment_receipt ? formData.payment_receipt.name : 'Ödeme Dekontu Yükle (PDF, JPG)'}
                                </Text>
                            </TouchableOpacity>

                            <Text style={[st.inputLabel, { marginTop: 16 }]}>NOT / AÇIKLAMA</Text>
                            <FormField 
                                value={formData.notes} 
                                onChangeText={(t) => setFormData({...formData, notes: toTitleCase(t)})}
                                placeholder="Eklemek istediğiniz notlar..."
                                multiline 
                                numberOfLines={2} 
                                style={{ height: 60, textAlignVertical: 'top' }}
                                autoCapitalize="sentences"
                            />

                            <TouchableOpacity style={[st.saveBtn, saving && { opacity: 0.7 }]} onPress={handleSave} disabled={saving}>
                                {saving ? <ActivityIndicator color="#fff" /> : <Text style={st.saveBtnText}>Ceza Kaydını Kaydet</Text>}
                            </TouchableOpacity>
                            <View style={{ height: 40 }} />
                        </ScrollView>
                    </View>
                </View>
            </Modal>
        </SafeAreaView>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F8FAFC' },
    loader: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    
    // Header
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingTop: Platform.OS === 'ios' ? 44 : 24 },
    backBtn: { width: 40, height: 40, borderRadius: 12, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
    headerCenter: { flex: 1, alignItems: 'center' },
    headerTitle: { fontSize: 18, fontWeight: '800', color: '#0F172A' },
    headerSubtitle: { fontSize: 13, color: '#64748B', marginTop: 2, fontWeight: '500' },
    addHeaderBtn: { width: 40, height: 40, borderRadius: 12, backgroundColor: '#EF4444', alignItems: 'center', justifyContent: 'center', shadowColor: '#EF4444', shadowOffset: { width: 0, height: 4 }, shadowopacity: 1, shadowRadius: 8, elevation: 4 },

    filterBar: { flexDirection: 'row', paddingHorizontal: 16, marginVertical: 12, gap: 8 },
    filterChip: { paddingHorizontal: 16, paddingVertical: 10, borderRadius: 12, backgroundColor: '#fff', borderWidth: 1, borderColor: '#E2E8F0', flex: 1, alignItems: 'center' },
    filterChipActive: { backgroundColor: '#EF4444', borderColor: '#EF4444' },
    filterChipText: { fontSize: 13, fontWeight: '700', color: '#64748B' },
    filterChipTextActive: { color: '#fff' },

    listContent: { padding: 16, paddingBottom: 100 },
    
    // Card Styles Matches Soft Premium UI
    card: { backgroundColor: '#fff', borderRadius: 24, padding: 16, marginBottom: 16, borderWidth: 1, borderColor: '#F1F5F9', shadowColor: '#94A3B8', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.08, shadowRadius: 16, elevation: 4 },
    cardHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 16 },
    iconBox: { width: 44, height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
    cardTitle: { fontSize: 16, fontWeight: '800', color: '#0F172A', letterSpacing: 0.2 },
    cardDesc: { fontSize: 13, color: '#64748B', marginTop: 2, fontWeight: '500' },
    amountText: { fontSize: 17, fontWeight: '900', color: '#0F172A' },
    discountText: { fontSize: 10, color: '#10B981', fontWeight: '700', marginTop: 2 },

    // Card Grid Area
    cardGrid: { backgroundColor: '#F8FAFC', borderRadius: 16, padding: 12, borderWidth: 1, borderColor: '#F1F5F9' },
    gridRow: { flexDirection: 'row', alignItems: 'center' },
    gridCol: { flex: 1, paddingVertical: 4, paddingHorizontal: 6 },
    gridDivider: { width: 1, height: '100%', backgroundColor: '#E2E8F0', marginHorizontal: 8 },
    gridHorizontalDivider: { height: 1, backgroundColor: '#E2E8F0', marginVertical: 8 },
    
    gridLabelRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 4, gap: 4 },
    gridLabel: { fontSize: 10, fontWeight: '800', letterSpacing: 0.5 },
    gridValue: { fontSize: 13, fontWeight: '700', color: '#1E293B', marginBottom: 2 },
    gridSubValue: { fontSize: 11, fontWeight: '600' },

    deleteActionBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#fff', borderWidth: 1, borderColor: '#FEE2E2', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 10, gap: 4 },
    deleteActionText: { color: '#EF4444', fontSize: 12, fontWeight: '700' },

    // Documents & Notes
    docsSection: { marginTop: 16, backgroundColor: '#F8FAFC', borderRadius: 12, padding: 12, borderWidth: 1, borderColor: '#F1F5F9' },
    docsHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 8, gap: 6 },
    docsTitle: { fontSize: 11, fontWeight: '800', color: '#8B5CF6', letterSpacing: 0.5 },
    docsRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
    docBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#F8FAFC', paddingHorizontal: 8, paddingVertical: 6, borderRadius: 6, gap: 4, borderWidth: 1, borderColor: '#E2E8F0' },
    docText: { fontSize: 10, fontWeight: '700', color: '#475569' },
    
    notesBox: { flexDirection: 'row', alignItems: 'flex-start', gap: 6, marginTop: 12, paddingHorizontal: 4 },
    notesText: { fontSize: 12, color: '#64748B', fontStyle: 'italic', flex: 1, lineHeight: 18 },

    // Modal Form Styles
    modalOverlay: { flex: 1, backgroundColor: 'rgba(15, 23, 42, 0.4)', justifyContent: 'flex-end' },
    modalContent: { backgroundColor: '#fff', borderTopLeftRadius: 32, borderTopRightRadius: 32, height: '90%', shadowColor: '#000', shadowOffset: { width: 0, height: -10 }, shadowOpacity: 0.1, shadowRadius: 20, elevation: 10 },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 24, paddingTop: 24, paddingBottom: 16, borderBottomWidth: 1, borderBottomColor: '#F1F5F9' },
    modalTitle: { fontSize: 20, fontWeight: '900', color: '#0F172A' },
    modalClose: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
    
    inputLabel: { fontSize: 12, fontWeight: '700', color: '#475569', marginBottom: 6, letterSpacing: 0.5 },
    saveBtn: { backgroundColor: '#EF4444', paddingVertical: 16, borderRadius: 16, alignItems: 'center', marginTop: 24, shadowColor: '#EF4444', shadowOffset: { width: 0, height: 6 }, shadowopacity: 1, shadowRadius: 10, elevation: 5 },
    saveBtnText: { color: '#fff', fontSize: 16, fontWeight: '800' },

    // Smart Card & Documents
    smartCard: { backgroundColor: '#F8FAFC', borderRadius: 12, padding: 12, borderWidth: 1, borderColor: '#E2E8F0', marginTop: 24 },
    smartCardTitle: { fontSize: 14, fontWeight: '800', color: '#1E293B', marginBottom: 12 },
    smartCardRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 },
    smartCardLabel: { fontSize: 11, fontWeight: '800', color: '#64748B' },
    smartCardValue: { fontSize: 14, fontWeight: '800', color: '#0F172A' },
    smartCardHint: { fontSize: 10, color: '#94A3B8', marginTop: 8, fontStyle: 'italic' },
    
    docUploadBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#F8FAFC', padding: 14, borderRadius: 12, borderWidth: 1, borderColor: '#E2E8F0', borderStyle: 'dashed', gap: 10 },
    docUploadText: { fontSize: 13, fontWeight: '600', color: '#475569', flex: 1 }
});
