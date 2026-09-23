import React, { useState, useEffect, useContext, useRef } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, Alert, RefreshControl, Dimensions, Linking, Platform, Modal, ScrollView, Image, Animated, Easing, KeyboardAvoidingView } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import api from '../api/axios';
import { emoji } from '../emoji';
import { AuthContext } from '../context/AuthContext';
import { EmptyState, FormField } from '../components';
import * as DocumentPicker from 'expo-document-picker';
import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';

const { width: W } = Dimensions.get('window');

export default function CompanyDocumentsScreen({ navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const [documents, setDocuments] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    // Bulk selection
    const [selectedIds, setSelectedIds] = useState([]);

    // Form
    const [modalVisible, setModalVisible] = useState(false);
    const [saving, setSaving] = useState(false);
    const [file, setFile] = useState(null);
    const [documentName, setDocumentName] = useState('');

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

    const fetchDocuments = async (isRefreshing = false) => {
        if (!isRefreshing) setLoading(true);
        try {
            const r = await api.get('/v1/company-documents');
            if (r.data.data) {
                setDocuments(r.data.data);
            } else if (r.data.current_page !== undefined) {
                setDocuments(r.data.data);
            } else {
                setDocuments(r.data);
            }
            setSelectedIds([]);
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => {
        fetchDocuments();
    }, []);

    const pickDocument = async () => {
        try {
            const result = await DocumentPicker.getDocumentAsync({
                type: ['application/pdf', 'image/*'],
                copyToCacheDirectory: true,
            });
            if (!result.canceled && result.assets && result.assets.length > 0) {
                setFile(result.assets[0]);
            }
        } catch (err) {
            console.error("Document pick error", err);
        }
    };

    const handleSave = async () => {
        if (!documentName || !file) {
            Alert.alert('Eksik Bilgi', 'Belge adı ve dosya seçimi zorunludur.');
            return;
        }
        setSaving(true);
        try {
            const data = new FormData();
            data.append('document_name', documentName);
            
            const fName = file.name || file.uri.split('/').pop();
            const match = /\.(\w+)$/.exec(fName);
            const type = file.mimeType || (match ? (match[1] === 'pdf' ? 'application/pdf' : `image/${match[1]}`) : `application/octet-stream`);
            
            data.append('file', { uri: file.uri, name: fName, type });

            await api.post('/v1/company-documents', data, { headers: { 'Content-Type': 'multipart/form-data' }});
            
            setModalVisible(false);
            setDocumentName('');
            setFile(null);
            fetchDocuments();
            Alert.alert('Başarılı', 'Şirket evrağı yüklendi.');
        } catch (e) {
            Alert.alert('Hata', 'Kaydedilemedi: ' + (e.response?.data?.message || e.message));
        } finally {
            setSaving(false);
        }
    };

    const confirmDelete = (id) => {
        if (!hasPermission('company_documents.delete')) {
            Alert.alert('Yetki Yok', 'Silme yetkiniz yok.');
            return;
        }
        Alert.alert('Silinecek', 'Bu evrağı silmek istediğinize emin misiniz?', [
            { text: 'İptal', style: 'cancel' },
            { text: 'Sil', style: 'destructive', onPress: async () => {
                try {
                    await api.delete(`/v1/company-documents/${id}`);
                    fetchDocuments();
                } catch(e) {}
            }}
        ]);
    };

    const handleBulkDelete = () => {
        if (!hasPermission('company_documents.delete')) {
            Alert.alert('Yetki Yok', 'Silme yetkiniz yok.');
            return;
        }
        Alert.alert('Toplu Silme', `${selectedIds.length} adet evrağı silmek istediğinize emin misiniz?`, [
            { text: 'İptal', style: 'cancel' },
            { text: 'Sil', style: 'destructive', onPress: async () => {
                try {
                    await api.post('/v1/company-documents/bulk-delete', { ids: selectedIds });
                    fetchDocuments();
                } catch(e) {
                    Alert.alert('Hata', 'Silme işlemi başarısız oldu.');
                }
            }}
        ]);
    };

    const handleShare = async (doc) => {
        if (!doc.file_url) { Alert.alert('Hata', 'Paylaşılacak dosya bulunamadı.'); return; }
        try {
            const rawUrl = doc.file_url;
            const fileUrl = encodeURI(rawUrl);
            const ext = doc.file_url.split('.').pop() || 'pdf';
            const safeName = (doc.document_name || `belge_${doc.id}`).replace(/[^a-zA-Z0-9]/g, '_').toLowerCase();
            const localUri = `${FileSystem.cacheDirectory}${safeName}.${ext}`;
            
            const { uri, status } = await FileSystem.downloadAsync(fileUrl, localUri);
            
            if (status !== 200) {
                Alert.alert('Hata', `Dosya sunucudan indirilemedi. (Durum Kodu: ${status})`);
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
            Alert.alert('Hata', 'Dosya paylaşılırken bir sorun oluştu.');
        }
    };

    const toggleSelection = (id) => {
        if (selectedIds.includes(id)) {
            setSelectedIds(selectedIds.filter(i => i !== id));
        } else {
            setSelectedIds([...selectedIds, id]);
        }
    };

    const getEmojiForType = (type, filePath) => {
        const isPdf = filePath?.toLowerCase().endsWith('.pdf');
        
        if (type === 'Vergi Levhası') return emoji('Objects/Chart Increasing with Yen');
        if (type === 'Sicil Gazetesi') return emoji('Objects/Rolled-Up Newspaper');
        if (type === 'İmza Sirküsü') return emoji('Objects/Fountain Pen');
        if (type === 'Faaliyet Belgesi') return emoji('Objects/Briefcase');

        if (isPdf) return emoji('Objects/Page Facing Up');
        return emoji('Objects/Framed Picture');
    };

    const renderItem = ({ item }) => {
        const isSelected = selectedIds.includes(item.id);

        return (
            <TouchableOpacity 
                activeOpacity={0.8} 
                onPress={() => toggleSelection(item.id)}
                style={{marginBottom: 16}}
            >
                <BlurView intensity={30} tint="dark" style={[st.card, isSelected && st.cardSelected]}>
                    <View style={st.cardTop}>
                        <View style={[st.checkbox, isSelected && st.checkboxSelected]}>
                            {isSelected && <Icon name="check" size={16} color="#0F172A" />}
                        </View>
                        <View style={st.iconBox}>
                            <Image source={getEmojiForType(item.document_type, item.file_path)} style={st.emojiIcon} />
                        </View>
                        <View style={st.cardInfo}>
                            <Text style={st.docType}>{item.document_type || 'Diğer'}</Text>
                            <Text style={st.docName}>{item.document_name}</Text>
                            <Text style={st.dateText}>{item.created_at ? new Date(item.created_at).toLocaleDateString('tr-TR') : ''}</Text>
                        </View>
                    </View>

                    <View style={st.actionRow}>
                        <TouchableOpacity style={[st.actionBtn, { backgroundColor: 'rgba(56,189,248,0.1)' }]} onPress={() => Linking.openURL(item.file_url)}>
                            <Icon name="eye-outline" size={18} color="#38BDF8" />
                            <Text style={[st.actionText, { color: '#38BDF8' }]}>İncele</Text>
                        </TouchableOpacity>
                        <TouchableOpacity style={[st.actionBtn, { backgroundColor: 'rgba(251,191,36,0.1)' }]} onPress={() => handleShare(item)}>
                            <Icon name="share-variant-outline" size={18} color="#FBBF24" />
                            <Text style={[st.actionText, { color: '#FBBF24' }]}>Paylaş</Text>
                        </TouchableOpacity>
                        <TouchableOpacity style={[st.actionBtn, { backgroundColor: 'rgba(248,113,113,0.1)' }]} onPress={() => confirmDelete(item.id)}>
                            <Icon name="trash-can-outline" size={18} color="#F87171" />
                        </TouchableOpacity>
                    </View>
                </BlurView>
            </TouchableOpacity>
        );
    };

    return (
        <View style={st.container}>
            {/* Animated Background */}
            <Animated.View style={StyleSheet.absoluteFill}>
                <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
                <Animated.View style={[st.bgBlob1, { transform: [{ translateY: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[0, 60] }) }] }]} />
                <Animated.View style={[st.bgBlob2, { transform: [{ translateX: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[0, -60] }) }] }]} />
            </Animated.View>

            <SafeAreaView style={{ flex: 1 }} edges={['top']}>
                <View style={st.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={st.backBtn}>
                        <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                        <Icon name="chevron-left" size={28} color="#FFF" />
                    </TouchableOpacity>
                    <View style={st.headerCenter}>
                        <Text style={st.headerTitle}>Şirket Evrakları</Text>
                        <Text style={st.headerSubtitle}>Kurumsal Belgeleriniz</Text>
                    </View>
                    {hasPermission('company_documents.create') ? (
                        <TouchableOpacity style={st.addHeaderBtn} onPress={() => setModalVisible(true)}>
                            <LinearGradient colors={['#8B5CF6', '#6D28D9']} style={StyleSheet.absoluteFillObject} />
                            <Icon name="plus" size={24} color="#fff" />
                        </TouchableOpacity>
                    ) : <View style={{ width: 44 }} />}
                </View>

                {selectedIds.length > 0 && (
                    <BlurView intensity={40} tint="dark" style={st.bulkActionContainer}>
                        <Text style={st.bulkText}>{selectedIds.length} Belge Seçildi</Text>
                        <TouchableOpacity style={st.bulkDeleteBtn} onPress={handleBulkDelete}>
                            <Icon name="trash-can" size={18} color="#fff" />
                            <Text style={st.bulkDeleteText}>Toplu Sil</Text>
                        </TouchableOpacity>
                    </BlurView>
                )}

                {loading ? (
                    <View style={st.loader}><ActivityIndicator size="large" color="#8B5CF6" /></View>
                ) : (
                    <FlatList
                        data={documents}
                        renderItem={renderItem}
                        keyExtractor={item => item.id.toString()}
                        contentContainerStyle={st.listContent}
                        showsVerticalScrollIndicator={false}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => fetchDocuments(true)} tintColor="#8B5CF6" />}
                        ListEmptyComponent={<EmptyState title="Belge Bulunamadı" message="Henüz şirket evrağı yüklenmemiş." icon="folder-open-outline" />}
                    />
                )}

                {/* Upload Modal */}
                <Modal visible={modalVisible} animationType="slide" transparent>
                    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={st.modalOverlay}>
                        <TouchableOpacity style={StyleSheet.absoluteFill} onPress={() => setModalVisible(false)} />
                        <BlurView intensity={70} tint="dark" style={st.modalContent}>
                            <View style={st.sheetHandle} />
                            <View style={st.modalHeader}>
                                <Text style={st.modalTitle}>Yeni Evrak Yükle</Text>
                                <TouchableOpacity onPress={() => setModalVisible(false)} style={st.modalClose}>
                                    <Icon name="close" size={24} color="#F8FAFC" />
                                </TouchableOpacity>
                            </View>
                            <ScrollView style={{ padding: 20 }}>
                                <View style={{ alignItems: 'center', marginBottom: 20 }}>
                                    <Image source={emoji('Objects/Outbox Tray')} style={{ width: 64, height: 64 }} />
                                    <Text style={{ fontSize: 13, color: '#94A3B8', textAlign: 'center', marginTop: 8 }}>PDF veya resim dosyalarınızı yükleyebilirsiniz.</Text>
                                </View>

                                <Text style={st.inputLabel}>EVRAK ADI</Text>
                                <View style={st.inputWrapper}>
                                    <FormField 
                                        value={documentName} 
                                        onChangeText={setDocumentName} 
                                        placeholder="Örn: 2026 Vergi Levhası" 
                                    />
                                </View>
                                
                                <Text style={st.inputLabel}>DOSYA SEÇİMİ</Text>
                                <TouchableOpacity style={st.fileBtn} onPress={pickDocument}>
                                    {file ? (
                                        <>
                                            <Icon name="file-check-outline" size={28} color="#34D399" />
                                            <View style={{ flex: 1, marginLeft: 10 }}>
                                                <Text style={[st.fileBtnText, { color: '#F8FAFC' }]} numberOfLines={1}>{file.name || 'Dosya Seçildi'}</Text>
                                            </View>
                                        </>
                                    ) : (
                                        <>
                                            <Icon name="file-upload-outline" size={28} color="#8B5CF6" />
                                            <Text style={st.fileBtnText}>PDF veya Resim Seç</Text>
                                        </>
                                    )}
                                </TouchableOpacity>

                                <TouchableOpacity style={[st.saveBtn, saving && { opacity: 0.7 }]} onPress={handleSave} disabled={saving}>
                                    <LinearGradient colors={['#8B5CF6', '#6D28D9']} style={StyleSheet.absoluteFillObject} />
                                    {saving ? <ActivityIndicator color="#fff" /> : <Text style={st.saveBtnText}>Evrağı Kaydet</Text>}
                                </TouchableOpacity>
                                <View style={{ height: 40 }} />
                            </ScrollView>
                        </BlurView>
                    </KeyboardAvoidingView>
                </Modal>
            </SafeAreaView>
        </View>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    bgBlob1: { position: 'absolute', top: -50, left: -50, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(139,92,246,0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, right: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(56,189,248,0.15)', filter: 'blur(40px)' },
    
    loader: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingBottom: 16, paddingTop: 8 },
    backBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    headerCenter: { flex: 1, alignItems: 'center' },
    headerTitle: { fontSize: 20, fontWeight: '900', color: '#F8FAFC' },
    headerSubtitle: { fontSize: 13, fontWeight: '600', color: '#A78BFA', marginTop: 2 },
    addHeaderBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(139,92,246,0.5)' },
    
    bulkActionContainer: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginHorizontal: 16, paddingHorizontal: 16, paddingVertical: 12, borderRadius: 16, marginBottom: 10, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    bulkText: { fontSize: 14, fontWeight: '700', color: '#F8FAFC' },
    bulkDeleteBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#EF4444', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 10, gap: 6 },
    bulkDeleteText: { color: '#fff', fontSize: 13, fontWeight: '700' },

    listContent: { padding: 16, paddingBottom: 120 },
    card: { borderRadius: 24, padding: 16, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    cardSelected: { borderColor: '#8B5CF6', backgroundColor: 'rgba(139,92,246,0.1)' },
    cardTop: { flexDirection: 'row', alignItems: 'center', marginBottom: 16 },
    
    checkbox: { width: 24, height: 24, borderRadius: 6, borderWidth: 2, borderColor: 'rgba(255,255,255,0.3)', alignItems: 'center', justifyContent: 'center', marginRight: 12, backgroundColor: 'rgba(0,0,0,0.3)' },
    checkboxSelected: { backgroundColor: '#8B5CF6', borderColor: '#8B5CF6' },
    
    iconBox: { width: 56, height: 56, borderRadius: 16, backgroundColor: 'rgba(0,0,0,0.3)', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    emojiIcon: { width: 36, height: 36 },
    
    cardInfo: { flex: 1, marginLeft: 16 },
    docType: { fontSize: 11, fontWeight: '800', color: '#A78BFA', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 4 },
    docName: { fontSize: 16, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.3, marginBottom: 4 },
    dateText: { fontSize: 12, fontWeight: '600', color: '#94A3B8' },

    actionRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
    actionBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', flex: 1, gap: 6, paddingVertical: 12, borderRadius: 14 },
    actionText: { fontSize: 13, fontWeight: '800' },

    // Modal
    modalOverlay: { flex: 1, justifyContent: 'flex-end', backgroundColor: 'rgba(0,0,0,0.5)' },
    modalContent: { borderTopLeftRadius: 32, borderTopRightRadius: 32, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden', maxHeight: '90%' },
    sheetHandle: { width: 40, height: 4, borderRadius: 2, backgroundColor: 'rgba(255,255,255,0.2)', alignSelf: 'center', marginTop: 12 },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 24, paddingBottom: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    modalTitle: { fontSize: 20, fontWeight: '900', color: '#F8FAFC' },
    modalClose: { width: 44, height: 44, borderRadius: 22, backgroundColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center' },
    
    inputLabel: { fontSize: 12, fontWeight: '800', color: '#94A3B8', marginTop: 16, marginBottom: 8, marginLeft: 4, letterSpacing: 0.5 },
    inputWrapper: { backgroundColor: 'rgba(0,0,0,0.3)', borderRadius: 16, padding: 4, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    fileBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', padding: 20, backgroundColor: 'rgba(139,92,246,0.1)', borderRadius: 20, borderWidth: 2, borderColor: 'rgba(139,92,246,0.3)', borderStyle: 'dashed' },
    fileBtnText: { fontSize: 15, fontWeight: '800', color: '#A78BFA', marginLeft: 10 },
    
    saveBtn: { borderRadius: 20, paddingVertical: 18, alignItems: 'center', marginTop: 28, overflow: 'hidden' },
    saveBtnText: { color: '#fff', fontSize: 16, fontWeight: '900', letterSpacing: 0.5 },
});
