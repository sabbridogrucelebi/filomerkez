import React, { useState, useEffect, useContext } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, Alert, RefreshControl, Dimensions, Linking, Share, Platform, Modal, ScrollView, Image } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons as Icon } from '@expo/vector-icons';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import { EmptyState, FormField } from '../components';
import DatePickerInput from '../components/DatePickerInput';
import * as ImagePicker from 'expo-image-picker';
import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';

const { width: W } = Dimensions.get('window');

const PRIORITY_MAP = {
    'ruhsat': 1,
    'trafik': 2,
    'sigorta': 2,
    'muayene': 3,
    'egzoz': 4,
    'imm': 5,
    'kasko': 6
};

const getDocumentPriority = (type, title) => {
    const text = ((type || '') + ' ' + (title || '')).toLowerCase();
    let priority = 99;
    for (const [key, val] of Object.entries(PRIORITY_MAP)) {
        if (text.includes(key)) { if (val < priority) priority = val; }
    }
    return priority;
};

export default function VehicleDocumentsScreen({ route, navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const { vehicleId, vehicle } = route.params || {};
    const [documents, setDocuments] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [showArchive, setShowArchive] = useState(false);

    // Form
    const [modalVisible, setModalVisible] = useState(false);
    const [saving, setSaving] = useState(false);
    const [file, setFile] = useState(null);
    const [editingId, setEditingId] = useState(null);
    const [formData, setFormData] = useState({ document_type: '', document_name: '', start_date: '', end_date: '', notes: '' });

    const fetchDocuments = async (isRefreshing = false) => {
        if (!vehicleId) return;
        if (!isRefreshing) setLoading(true);
        try {
            const r = await api.get(`/v1/vehicles/${vehicleId}/documents`);
            if (r.data.success) {
                let docs = r.data.data.documents || [];
                docs.sort((a, b) => {
                    const pA = getDocumentPriority(a.type, a.title);
                    const pB = getDocumentPriority(b.type, b.title);
                    if (pA !== pB) return pA - pB;
                    return new Date(a.end_date || '2099-01-01') - new Date(b.end_date || '2099-01-01');
                });
                setDocuments(docs);
            }
        } catch (e) { console.error(e); } 
        finally { setLoading(false); setRefreshing(false); }
    };

    useEffect(() => { fetchDocuments(); }, [vehicleId]);

    const activeDocs = documents.filter(d => !d.is_expired);
    let archivedDocs = documents.filter(d => d.is_expired);
    archivedDocs.sort((a, b) => new Date(b.end_date || '1900-01-01') - new Date(a.end_date || '1900-01-01'));
    const displayDocs = showArchive ? archivedDocs : activeDocs;

    const pickImage = async () => {
        let result = await ImagePicker.launchImageLibraryAsync({
            mediaTypes: ImagePicker.MediaTypeOptions.Images,
            allowsEditing: true, quality: 0.8,
        });
        if (!result.canceled) setFile(result.assets[0]);
    };

    const openEdit = (item) => {
        if (!hasPermission('documents.edit')) { Alert.alert('Yetki Yok', 'Belge düzenleme yetkiniz yok.'); return; }
        setEditingId(item.id);
        setFormData({
            document_type: item.type || '',
            document_name: item.title || '',
            start_date: item.start_date ? item.start_date.split('T')[0] : '',
            end_date: item.end_date ? item.end_date.split('T')[0] : '',
            notes: item.notes || ''
        });
        setFile(null);
        setModalVisible(true);
    };

    const handleSave = async () => {
        if (!formData.document_type || !formData.document_name) {
            Alert.alert('Eksik Bilgi', 'Belge Türü ve Belge Adı zorunludur.'); return;
        }
        setSaving(true);
        try {
            const data = new FormData();
            data.append('owner_type', 'vehicle');
            data.append('owner_id', vehicleId);
            data.append('document_type', formData.document_type);
            data.append('document_name', formData.document_name);
            if (formData.start_date) data.append('start_date', formData.start_date);
            if (formData.end_date) data.append('end_date', formData.end_date);
            if (formData.notes) data.append('notes', formData.notes);
            
            if (file) {
                const fName = file.uri.split('/').pop();
                const match = /\.(\w+)$/.exec(fName);
                const type = match ? `image/${match[1]}` : `image`;
                data.append('file', { uri: file.uri, name: fName, type });
            }

            if (editingId) {
                data.append('_method', 'PUT');
                await api.post(`/v1/documents/${editingId}`, data, { headers: { 'Content-Type': 'multipart/form-data' }});
            } else {
                await api.post('/v1/documents', data, { headers: { 'Content-Type': 'multipart/form-data' }});
            }
            
            setModalVisible(false);
            setFormData({ document_type: '', document_name: '', start_date: '', end_date: '', notes: '' });
            setFile(null);
            fetchDocuments();
            Alert.alert('Başarılı', 'Belge eklendi.');
        } catch (e) {
            Alert.alert('Hata', 'Kaydedilemedi: ' + (e.response?.data?.message || e.message));
        } finally { setSaving(false); }
    };

    const confirmDelete = (id) => {
        if (!hasPermission('documents.delete')) { Alert.alert('Yetki Yok', 'Silme yetkiniz yok.'); return; }
        Alert.alert('Silinecek', 'Bu belgeyi silmek istediğinize emin misiniz?', [
            { text: 'İptal', style: 'cancel' },
            { text: 'Sil', style: 'destructive', onPress: async () => {
                try { await api.delete(`/v1/documents/${id}`); fetchDocuments(); } catch(e) {}
            }}
        ]);
    };

    const handleView = (url) => {
        if (!url) { Alert.alert('Hata', 'Görüntülenecek dosya bulunamadı.'); return; }
        Linking.openURL(url).catch(() => Alert.alert('Hata', 'Dosya açılamadı.'));
    };

    const handleShare = async (doc) => {
        if (!doc.file_url) { Alert.alert('Hata', 'Paylaşılacak dosya bulunamadı.'); return; }
        try {
            setLoading(true);
            const rawUrl = doc.file_url;
            const fileUrl = encodeURI(rawUrl);
            
            const ext = doc.file_url.split('.').pop() || 'pdf';
            const safeName = (doc.title || `belge_${doc.id}`).replace(/[^a-zA-Z0-9]/g, '_').toLowerCase();
            const localUri = `${FileSystem.cacheDirectory}${safeName}.${ext}`;
            
            const { uri, status } = await FileSystem.downloadAsync(fileUrl, localUri);
            
            if (status !== 200) {
                Alert.alert('Hata', `Dosya sunucudan indirilemedi. (Durum Kodu: ${status})`);
                setLoading(false);
                return;
            }

            if (await Sharing.isAvailableAsync()) {
                await Sharing.shareAsync(uri, { 
                    mimeType: ext === 'pdf' ? 'application/pdf' : 'image/jpeg',
                    dialogTitle: 'Belgeyi Paylaş', 
                    UTI: 'public.item' 
                });
            } else {
                Alert.alert('Bilgi', 'Bu cihazda paylaşım özelliği desteklenmiyor.');
            }
        } catch (error) {
            console.error("Paylaşım hatası:", error);
            Alert.alert('Hata', 'Dosya paylaşılırken bir sorun oluştu. URL geçersiz veya erişilemiyor olabilir.');
        } finally {
            setLoading(false);
        }
    };

    const getStatusInfo = (endDate, isExpired) => {
        if (isExpired) return { color: '#EF4444', bg: '#FEF2F2', text: 'ARŞİVDE / SÜRESİ DOLDU' };
        if (!endDate) return { color: '#94A3B8', bg: '#F1F5F9', text: 'Tanımsız' };
        const diff = Math.ceil((new Date(endDate) - new Date()) / 86400000);
        if (diff < 0) return { color: '#EF4444', bg: '#FEF2F2', text: `${Math.abs(diff)} gün geçti` };
        if (diff <= 30) return { color: '#F59E0B', bg: '#FFFBEB', text: `${diff} gün kaldı` };
        return { color: '#10B981', bg: '#ECFDF5', text: `${diff} gün kaldı` };
    };

    const renderItem = ({ item }) => {
        const status = getStatusInfo(item.end_date, item.is_expired);
        return (
            <View style={st.card}>
                <View style={st.cardTop}>
                    <View style={st.iconBox}>
                        <Icon name="file-document-outline" size={24} color="#3B82F6" />
                    </View>
                    <View style={st.cardInfo}>
                        <Text style={st.docType}>{item.type || 'BELGE'}</Text>
                        <Text style={st.docName}>{item.title || 'İsimsiz Belge'}</Text>
                    </View>
                    <View style={{ flexDirection: 'row' }}>
                        <TouchableOpacity onPress={() => openEdit(item)} style={{ padding: 6 }}>
                            <Icon name="pencil-outline" size={20} color="#3B82F6" />
                        </TouchableOpacity>
                        <TouchableOpacity onPress={() => confirmDelete(item.id)} style={{ padding: 6 }}>
                            <Icon name="trash-can-outline" size={20} color="#EF4444" />
                        </TouchableOpacity>
                    </View>
                </View>

                <View style={{ alignItems: 'flex-start', marginTop: 4, marginBottom: 16 }}>
                    <View style={[st.statusBadge, { backgroundColor: status.bg }]}>
                        <Text style={[st.statusText, { color: status.color }]}>{status.text}</Text>
                    </View>
                </View>

                <View style={st.cardDates}>
                    <View style={st.dateGroup}>
                        <Icon name="calendar-start" size={14} color="#94A3B8" />
                        <View style={{ marginLeft: 6 }}>
                            <Text style={st.dateLabel}>Başlangıç</Text>
                            <Text style={st.dateValue}>{item.start_date ? new Date(item.start_date).toLocaleDateString('tr-TR') : '-'}</Text>
                        </View>
                    </View>
                    <View style={st.dateDivider} />
                    <View style={st.dateGroup}>
                        <Icon name="calendar-end" size={14} color="#94A3B8" />
                        <View style={{ marginLeft: 6 }}>
                            <Text style={st.dateLabel}>Bitiş</Text>
                            <Text style={st.dateValue}>{item.end_date ? new Date(item.end_date).toLocaleDateString('tr-TR') : '-'}</Text>
                        </View>
                    </View>
                </View>

                <View style={st.actionRow}>
                    <TouchableOpacity style={[st.actionBtn, { backgroundColor: '#EFF6FF' }]} onPress={() => handleView(item.file_url)}>
                        <Icon name="eye-outline" size={16} color="#3B82F6" />
                        <Text style={[st.actionText, { color: '#3B82F6' }]}>Görüntüle</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={[st.actionBtn, { backgroundColor: '#ECFDF5' }]} onPress={() => handleView(item.file_url)}>
                        <Icon name="cloud-download-outline" size={16} color="#10B981" />
                        <Text style={[st.actionText, { color: '#10B981' }]}>İndir</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={[st.actionBtn, { backgroundColor: '#FFFBEB' }]} onPress={() => handleShare(item)}>
                        <Icon name="share-variant-outline" size={16} color="#F59E0B" />
                        <Text style={[st.actionText, { color: '#F59E0B' }]}>Paylaş</Text>
                    </TouchableOpacity>
                </View>
            </View>
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
                        <Text style={st.headerTitle}>Belge ve Dökümanlar</Text>
                        <Text style={st.headerSubtitle}>{vehicle?.plate || 'Belge Yönetimi'}</Text>
                    </View>
                    <TouchableOpacity 
                        style={st.addHeaderBtn} 
                        onPress={() => {
                            if(!hasPermission('documents.create')) { Alert.alert('Yetki Yok', 'Yeni belge ekleme yetkiniz yok.'); return; }
                            setEditingId(null);
                            setFormData({ document_type: '', document_name: '', start_date: '', end_date: '', notes: '' });
                            setFile(null);
                            setModalVisible(true);
                        }}
                    >
                        <Icon name="plus" size={24} color="#fff" />
                    </TouchableOpacity>
                </View>
                
                <View style={st.tabsWrapper}>
                    <TouchableOpacity style={[st.tabBtn, !showArchive && st.tabBtnActive]} onPress={() => setShowArchive(false)}>
                        <Text style={[st.tabText, !showArchive && st.tabTextActive]}>Aktif Belgeler</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={[st.tabBtn, showArchive && st.tabBtnActive]} onPress={() => setShowArchive(true)}>
                        <Text style={[st.tabText, showArchive && st.tabTextActive]}>Arşiv Belgeler</Text>
                    </TouchableOpacity>
                </View>
            </View>

            {loading ? (
                <View style={st.loader}><ActivityIndicator size="large" color="#3B82F6" /></View>
            ) : (
                <FlatList
                    data={displayDocs}
                    renderItem={renderItem}
                    keyExtractor={item => item.id.toString()}
                    contentContainerStyle={st.listContent}
                    showsVerticalScrollIndicator={false}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => fetchDocuments(true)} tintColor="#3B82F6" />}
                    ListEmptyComponent={<EmptyState title={showArchive ? "Arşiv Boş" : "Belge Bulunamadı"} message="Kayıtlı belge bulunmuyor." icon="file-document-outline" />}
                />
            )}

            {/* Modal */}
            <Modal visible={modalVisible} animationType="slide" transparent>
                <View style={st.modalOverlay}>
                    <View style={st.modalContent}>
                        <View style={st.modalHeader}>
                            <Text style={st.modalTitle}>Yeni Belge Yükle</Text>
                            <TouchableOpacity onPress={() => setModalVisible(false)} style={st.modalClose}>
                                <Icon name="close" size={24} color="#64748B" />
                            </TouchableOpacity>
                        </View>
                        <ScrollView style={{ padding: 20 }}>
                            <Text style={st.inputLabel}>BELGE TÜRÜ</Text>
                            <FormField value={formData.document_type} onChangeText={t => setFormData({...formData, document_type: t})} placeholder="Örn: Ruhsat, Kasko" />
                            
                            <Text style={st.inputLabel}>BELGE ADI / NUMARASI</Text>
                            <FormField value={formData.document_name} onChangeText={t => setFormData({...formData, document_name: t})} placeholder="Belge adını girin" />

                            <View style={{ flexDirection: 'row', gap: 10 }}>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>BAŞLANGIÇ</Text>
                                    <DatePickerInput value={formData.start_date} onChange={d => setFormData({...formData, start_date: d})} />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Text style={st.inputLabel}>BİTİŞ</Text>
                                    <DatePickerInput value={formData.end_date} onChange={d => setFormData({...formData, end_date: d})} />
                                </View>
                            </View>

                            <Text style={st.inputLabel}>BELGE DOSYASI</Text>
                            <TouchableOpacity style={st.fileBtn} onPress={pickImage}>
                                <Icon name="camera-plus" size={24} color="#3B82F6" />
                                <Text style={st.fileBtnText}>{file ? 'Resim Seçildi' : 'Dosya/Resim Seç'}</Text>
                            </TouchableOpacity>

                            <Text style={st.inputLabel}>NOTLAR</Text>
                            <FormField value={formData.notes} onChangeText={t => setFormData({...formData, notes: t})} placeholder="Açıklama..." multiline numberOfLines={3} style={{ height: 80, textAlignVertical: 'top' }} />
                            
                            <TouchableOpacity style={[st.saveBtn, saving && { opacity: 0.7 }]} onPress={handleSave} disabled={saving}>
                                {saving ? <ActivityIndicator color="#fff" /> : <Text style={st.saveBtnText}>Belgeyi Kaydet</Text>}
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
    addHeaderBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#3B82F6', alignItems: 'center', justifyContent: 'center', shadowColor: '#3B82F6', shadowOffset: { width: 0, height: 4 }, shadowopacity: 1, shadowRadius: 6, elevation: 4 },
    tabsWrapper: { flexDirection: 'row', paddingHorizontal: 16, paddingBottom: 12, borderBottomWidth: 1, borderBottomColor: '#E2E8F0', marginTop: 8 },
    tabBtn: { flex: 1, alignItems: 'center', paddingVertical: 10, borderRadius: 12, backgroundColor: '#F1F5F9', marginHorizontal: 4 },
    tabBtnActive: { backgroundColor: '#EFF6FF' },
    tabText: { fontSize: 13, fontWeight: '700', color: '#64748B' },
    tabTextActive: { color: '#3B82F6' },
    listContent: { padding: 16, paddingBottom: 120 },
    card: { backgroundColor: '#fff', borderRadius: 20, padding: 16, marginBottom: 16, shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.05, shadowRadius: 10, elevation: 2 },
    cardTop: { flexDirection: 'row', alignItems: 'flex-start', marginBottom: 12 },
    iconBox: { width: 44, height: 44, borderRadius: 12, backgroundColor: '#EFF6FF', alignItems: 'center', justifyContent: 'center' },
    cardInfo: { flex: 1, marginLeft: 12, justifyContent: 'center' },
    docType: { fontSize: 11, fontWeight: '800', color: '#3B82F6', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 2 },
    docName: { fontSize: 15, fontWeight: '800', color: '#1E293B', letterSpacing: -0.2 },
    cardDates: { flexDirection: 'row', alignItems: 'center', marginTop: 4, padding: 12, backgroundColor: '#F8FAFC', borderRadius: 12 },
    dateGroup: { flex: 1, flexDirection: 'row', alignItems: 'center' },
    dateDivider: { width: 1, height: 24, backgroundColor: '#E2E8F0', marginHorizontal: 12 },
    dateLabel: { fontSize: 10, fontWeight: '700', color: '#94A3B8', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 2 },
    dateValue: { fontSize: 13, fontWeight: '800', color: '#334155' },
    statusBadge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8 },
    statusText: { fontSize: 11, fontWeight: '800' },
    actionRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 16, borderTopWidth: 1, borderTopColor: '#F1F5F9', paddingTop: 16, gap: 8 },
    actionBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', flex: 1, gap: 6, paddingVertical: 10, borderRadius: 12 },
    actionText: { fontSize: 12, fontWeight: '800' },

    // Modal
    modalOverlay: { flex: 1, backgroundColor: 'rgba(15,23,42,0.6)', justifyContent: 'flex-end' },
    modalContent: { backgroundColor: '#fff', borderTopLeftRadius: 24, borderTopRightRadius: 24, maxHeight: '90%' },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 20, borderBottomWidth: 1, borderBottomColor: '#F1F5F9' },
    modalTitle: { fontSize: 18, fontWeight: '800', color: '#0F172A' },
    modalClose: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
    inputLabel: { fontSize: 11, fontWeight: '800', color: '#64748B', marginTop: 16, marginBottom: 8, marginLeft: 4, letterSpacing: 0.5 },
    fileBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', padding: 16, backgroundColor: '#EFF6FF', borderRadius: 12, borderWidth: 1, borderColor: '#BFDBFE', borderStyle: 'dashed', gap: 10 },
    fileBtnText: { fontSize: 14, fontWeight: '700', color: '#3B82F6' },
    saveBtn: { backgroundColor: '#2563EB', borderRadius: 12, paddingVertical: 16, alignItems: 'center', marginTop: 24 },
    saveBtnText: { color: '#fff', fontSize: 15, fontWeight: '800' },

    // Dummy Tab
    dummyTabBar: { position: 'absolute', bottom: 0, left: 0, right: 0, backgroundColor: '#fff', borderTopWidth: 1, borderTopColor: '#E2E8F0', paddingBottom: Platform.OS === 'ios' ? 20 : 0, flexDirection: 'row', height: Platform.OS === 'ios' ? 85 : 65, alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 10 },
    dummyTab: { flex: 1, alignItems: 'center', justifyContent: 'center', height: '100%' },
    dummyTabLabel: { fontSize: 10, fontWeight: '600', marginTop: 4, color: '#94A3B8' },
    dummyTabCenter: { flex: 1, alignItems: 'center' },
    dummyTabCenterInner: { width: 56, height: 56, borderRadius: 28, backgroundColor: '#2563EB', alignItems: 'center', justifyContent: 'center', marginTop: -35, shadowColor: '#2563EB', shadowOffset: {width:0, height:4}, shadowOpacity:0.3, shadowRadius:8, elevation: 5 },
});
