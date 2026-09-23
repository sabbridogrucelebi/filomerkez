import React, { useState, useEffect, useContext, useRef } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, ActivityIndicator, Alert, Modal, TextInput, Platform, Dimensions, KeyboardAvoidingView, Linking, Animated, Easing } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import * as DocumentPicker from 'expo-document-picker';
import * as ImagePicker from 'expo-image-picker';
import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import { Picker } from '@react-native-picker/picker';
import { BlurView } from 'expo-blur';
import { Image } from 'expo-image';
import dayjs from 'dayjs';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');

// Custom Select Modal for dark glassmorphism
const SelectInput = ({ icon, placeholder, value, options, onSelect }) => {
    const [open, setOpen] = useState(false);
    const selected = options.find(o => o.value === value);

    return (
        <>
            <TouchableOpacity style={s.fieldWrap} onPress={() => setOpen(true)} activeOpacity={0.7}>
                <Icon name={icon} size={20} color="#94A3B8" style={s.fieldIcon} />
                <Text style={[s.fieldInput, { color: selected ? '#F8FAFC' : '#64748B', paddingTop: Platform.OS === 'ios' ? 16 : 14 }]}>
                    {selected ? selected.label : placeholder}
                </Text>
                <Icon name="chevron-down" size={20} color="#94A3B8" />
            </TouchableOpacity>

            <Modal visible={open} transparent animationType="fade">
                <BlurView intensity={40} tint="dark" style={s.modalOverlayCenter}>
                    <TouchableOpacity style={StyleSheet.absoluteFill} activeOpacity={1} onPress={() => setOpen(false)} />
                    <View style={s.centerModal}>
                        <LinearGradient colors={['rgba(255,255,255,0.05)', 'rgba(255,255,255,0.01)']} style={StyleSheet.absoluteFillObject} />
                        <Text style={s.modalTitle}>{placeholder}</Text>
                        <ScrollView style={{ maxHeight: 300 }} showsVerticalScrollIndicator={false}>
                            {options.map((opt, i) => (
                                <TouchableOpacity 
                                    key={i} 
                                    style={[s.menuItem, value === opt.value && { backgroundColor: 'rgba(59,130,246,0.1)' }]}
                                    onPress={() => { onSelect(opt.value); setOpen(false); }}
                                >
                                    <Text style={[s.menuText, value === opt.value ? { color: '#60A5FA', fontWeight: '800' } : { color: '#CBD5E1' }]}>{opt.label}</Text>
                                    {value === opt.value && <Icon name="check" size={20} color="#60A5FA" style={{ position: 'absolute', right: 16 }} />}
                                </TouchableOpacity>
                            ))}
                        </ScrollView>
                    </View>
                </BlurView>
            </Modal>
        </>
    );
};

export default function PersonnelDetailScreen({ route, navigation }) {
    const { id } = route.params;
    const { hasPermission } = useContext(AuthContext);
    const insets = useSafeAreaInsets();

    const [loading, setLoading] = useState(true);
    const [personnel, setPersonnel] = useState(null);
    const [vehicles, setVehicles] = useState([]);
    
    const [activeTab, setActiveTab] = useState('genel');
    const [showArchive, setShowArchive] = useState(false);

    const [showMenu, setShowMenu] = useState(false);
    const [showVehicleModal, setShowVehicleModal] = useState(false);
    const [newVehicleId, setNewVehicleId] = useState('');
    
    const [showDocModal, setShowDocModal] = useState(false);
    const [docTitle, setDocTitle] = useState('');
    const [selectedDoc, setSelectedDoc] = useState(null);
    const [uploading, setUploading] = useState(false);

    // Background Blob Animations
    const blob1Anim = useRef(new Animated.Value(0)).current;
    const blob2Anim = useRef(new Animated.Value(0)).current;
    const scrollY = useRef(new Animated.Value(0)).current;

    // Component Mount Stagger Animations
    const enterAnims = useRef([...Array(20)].map(() => new Animated.Value(0))).current;
    const listAnims = useRef([...Array(50)].map(() => new Animated.Value(0))).current;

    useEffect(() => {
        const loop = Animated.loop(Animated.sequence([
            Animated.timing(blob1Anim, { toValue: 1, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
            Animated.timing(blob1Anim, { toValue: 0, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
        ]));
        const loop2 = Animated.loop(Animated.sequence([
            Animated.timing(blob2Anim, { toValue: 1, duration: 10000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
            Animated.timing(blob2Anim, { toValue: 0, duration: 10000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
        ]));
        loop.start(); loop2.start();
        return () => { loop.stop(); loop2.stop(); };
    }, []);

    const triggerListAnim = () => {
        listAnims.forEach(a => a.setValue(0));
        Animated.stagger(50, listAnims.map(a => Animated.spring(a, { toValue: 1, friction: 8, tension: 50, useNativeDriver: true }))).start();
    };

    useEffect(() => {
        triggerListAnim();
    }, [activeTab, showArchive]);

    const fetchDetail = async () => {
        try {
            const [resDetail, resOptions] = await Promise.all([
                api.get(`/v1/personnel/${id}`),
                hasPermission('drivers.edit') ? api.get('/v1/personnel/options') : Promise.resolve({data:{data:{vehicles:[]}}})
            ]);
            setPersonnel(resDetail.data.data);
            if (resOptions.data.data?.vehicles) setVehicles(resOptions.data.data.vehicles);

            // Stagger main elements
            Animated.stagger(100, enterAnims.map(a => Animated.spring(a, { toValue: 1, friction: 7, tension: 40, useNativeDriver: true }))).start();
            triggerListAnim();
        } catch (e) {
            Alert.alert('Hata', 'Personel detayları alınamadı.');
            navigation.goBack();
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchDetail(); }, []);

    const updateStatus = async (isActive, leaveDate = null) => {
        try {
            await api.put(`/v1/personnel/${id}/status`, { is_active: isActive, leave_date: leaveDate });
            fetchDetail();
            setShowMenu(false);
        } catch (e) { Alert.alert('Hata', 'Durum güncellenemedi.'); }
    };

    const changeVehicle = async () => {
        try {
            await api.put(`/v1/personnel/${id}/vehicle`, { vehicle_id: newVehicleId || null });
            setShowVehicleModal(false);
            fetchDetail();
        } catch (e) { Alert.alert('Hata', 'Araç değiştirilemedi.'); }
    };

    const pickDocument = async (isImage = false) => {
        try {
            if (isImage) {
                const res = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ImagePicker.MediaTypeOptions.Images, allowsEditing: true, quality: 0.8 });
                if (!res.canceled) setSelectedDoc({ uri: res.assets[0].uri, name: 'image.jpg', type: 'image/jpeg' });
            } else {
                const res = await DocumentPicker.getDocumentAsync({ type: '*/*' });
                if (!res.canceled && res.assets && res.assets.length > 0) {
                    setSelectedDoc({ uri: res.assets[0].uri, name: res.assets[0].name, type: res.assets[0].mimeType || 'application/octet-stream' });
                }
            }
        } catch (e) { console.log(e); }
    };

    const uploadFile = async (type) => {
        if (!selectedDoc) return Alert.alert('Hata', 'Lütfen bir dosya seçin.');
        setUploading(true);
        try {
            const fd = new FormData();
            fd.append('document', { uri: selectedDoc.uri, name: selectedDoc.name, type: selectedDoc.type });
            fd.append('type', type);
            fd.append('title', docTitle || selectedDoc.name);

            await api.post(`/v1/personnel/${id}/documents`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
            setDocTitle(''); setSelectedDoc(null); setShowDocModal(false);
            fetchDetail();
        } catch (e) {
            Alert.alert('Hata', 'Dosya yüklenemedi.');
        } finally {
            setUploading(false);
        }
    };

    const deleteDocument = async (docId) => {
        Alert.alert('Emin misiniz?', 'Bu dosyayı silmek istediğinize emin misiniz?', [
            { text: 'Vazgeç', style: 'cancel' },
            { 
                text: 'Sil', style: 'destructive', onPress: async () => {
                    try { await api.delete(`/v1/personnel/${id}/documents/${docId}`); fetchDetail(); } catch (e) { Alert.alert('Hata', 'Silinemedi.'); }
                }
            }
        ]);
    };

    const handleViewDocument = (doc) => {
        const fileUrl = doc.file_path.startsWith('http') ? doc.file_path : `${api.defaults.baseURL.replace('/api', '')}/storage/${doc.file_path}`;
        Linking.openURL(fileUrl).catch(() => Alert.alert('Hata', 'Dosya açılamadı.'));
    };

    const handleShareDocument = async (doc) => {
        try {
            setUploading(true);
            const rawUrl = doc.file_path.startsWith('http') ? doc.file_path : `${api.defaults.baseURL.replace('/api', '')}/storage/${doc.file_path}`;
            const fileUrl = encodeURI(rawUrl);
            const ext = doc.file_path.split('.').pop() || 'pdf';
            const safeName = (doc.document_name || `belge_${doc.id}`).replace(/[^a-zA-Z0-9]/g, '_').toLowerCase();
            const localUri = `${FileSystem.cacheDirectory}${safeName}.${ext}`;
            const { uri, status } = await FileSystem.downloadAsync(fileUrl, localUri);
            
            if (status !== 200) { Alert.alert('Hata', `Sunucu hatası: ${status}`); return; }
            if (await Sharing.isAvailableAsync()) {
                await Sharing.shareAsync(uri, { mimeType: ext === 'pdf' ? 'application/pdf' : 'image/jpeg', dialogTitle: 'Belgeyi Paylaş', UTI: 'public.item' });
            } else { Alert.alert('Bilgi', 'Paylaşım desteklenmiyor.'); }
        } catch (error) {
            Alert.alert('Hata', 'Dosya paylaşılamadı.');
        } finally { setUploading(false); }
    };

    const getStatusInfo = (endDate, archivedAt) => {
        if (archivedAt) return { color: '#EF4444', bg: 'rgba(239, 68, 68, 0.15)', border: 'rgba(239, 68, 68, 0.3)', text: 'ARŞİVDE' };
        if (!endDate) return { color: '#94A3B8', bg: 'rgba(255, 255, 255, 0.05)', border: 'rgba(255, 255, 255, 0.1)', text: 'Tanımsız' };
        const diff = dayjs(endDate).diff(dayjs(), 'day');
        if (diff < 0) return { color: '#EF4444', bg: 'rgba(239, 68, 68, 0.15)', border: 'rgba(239, 68, 68, 0.3)', text: `${Math.abs(diff)} gün geçti` };
        if (diff <= 30) return { color: '#F59E0B', bg: 'rgba(245, 158, 11, 0.15)', border: 'rgba(245, 158, 11, 0.3)', text: `${diff} gün kaldı` };
        return { color: '#10B981', bg: 'rgba(16, 185, 129, 0.15)', border: 'rgba(16, 185, 129, 0.3)', text: `${diff} gün kaldı` };
    };

    if (loading || !personnel) return (
        <View style={s.container}>
            <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
            <ActivityIndicator size="large" color="#60A5FA" style={{ flex: 1 }} />
        </View>
    );

    const isImageFile = (path) => /\.(jpg|jpeg|png|gif|webp)$/i.test(path || '');
    const allDocs = personnel.documents?.filter(d => !isImageFile(d.file_path) && d.document_type !== 'image' && d.document_type?.toLowerCase() !== 'resim') || [];
    const images = personnel.documents?.filter(d => isImageFile(d.file_path) || d.document_type === 'image' || d.document_type?.toLowerCase() === 'resim') || [];
    const payrolls = personnel.payrolls || [];

    const isExpired = (d) => {
        if (d.archived_at) return true;
        if (d.end_date && dayjs(d.end_date).endOf('day').isBefore(dayjs())) return true;
        return false;
    };
    const activeDocs = allDocs.filter(d => !isExpired(d));
    const archivedDocs = allDocs.filter(d => isExpired(d));
    const displayDocs = showArchive ? archivedDocs : activeDocs;

    const formattedSalary = personnel.base_salary ? new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 0 }).format(personnel.base_salary) : '-';

    const render3DStyle = (animIndex) => {
        const anim = listAnims[animIndex % 50] || new Animated.Value(1);
        return {
            opacity: anim,
            transform: [
                { perspective: 800 },
                { rotateX: anim.interpolate({ inputRange: [0, 1], outputRange: ['-90deg', '0deg'] }) },
                { scale: anim.interpolate({ inputRange: [0, 0.5, 1], outputRange: [0.8, 1.05, 1] }) }
            ]
        };
    };

    return (
        <View style={s.container}>
            {/* 3D Animated Background */}
            <Animated.View style={[StyleSheet.absoluteFill, { transform: [{ translateY: scrollY.interpolate({ inputRange: [-100, 0, 500], outputRange: [-20, 0, 100], extrapolate: 'clamp' }) }] }]}>
                <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
                <Animated.View style={[s.bgBlob1, { transform: [{ translateY: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[0, 50] }) }, { scale: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[1, 1.2] }) }] }]} />
                <Animated.View style={[s.bgBlob2, { transform: [{ translateX: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[0, -50] }) }, { scale: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[1, 1.3] }) }] }]} />
            </Animated.View>

            <SafeAreaView style={{ flex: 1 }} edges={['top', 'left', 'right']}>
                {/* Header */}
                <View style={s.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={s.iconBtn}>
                        <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                        <Icon name="chevron-left" size={26} color="#FFF" />
                    </TouchableOpacity>
                    <Text style={s.headerTitle}>Personel Detay</Text>
                    <TouchableOpacity onPress={() => setShowMenu(true)} style={s.iconBtn}>
                        <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                        <Icon name="dots-vertical" size={24} color="#FFF" />
                    </TouchableOpacity>
                </View>

                <Animated.ScrollView 
                    showsVerticalScrollIndicator={false} 
                    contentContainerStyle={{ paddingBottom: 140 }}
                    onScroll={Animated.event([{ nativeEvent: { contentOffset: { y: scrollY } } }], { useNativeDriver: true })}
                    scrollEventThrottle={16}
                >
                    {/* Hero Section */}
                    <Animated.View style={[s.heroWrapper, { opacity: enterAnims[0], transform: [{ translateY: enterAnims[0].interpolate({ inputRange:[0,1], outputRange:[50, 0] }) }, { scale: enterAnims[0].interpolate({ inputRange:[0,1], outputRange:[0.9, 1] }) }] }]}>
                        <BlurView intensity={40} tint="dark" style={s.heroGlass}>
                            <View style={s.heroContent}>
                                <View style={s.avatarContainer}>
                                    {personnel.profile_photo_url ? (
                                        <Image source={{ uri: personnel.profile_photo_url }} style={s.heroAvatar} />
                                    ) : (
                                        <LinearGradient colors={['#3B82F6', '#1E40AF']} style={s.heroAvatar}>
                                            <Icon name="account-tie" size={32} color="#FFF" />
                                        </LinearGradient>
                                    )}
                                    <View style={[s.onlineDot, { backgroundColor: personnel.is_active ? '#34D399' : '#F87171', shadowColor: personnel.is_active ? '#34D399' : '#F87171' }]} />
                                </View>
                                <View style={s.heroInfo}>
                                    <Text style={s.heroName} numberOfLines={1}>{personnel.full_name}</Text>
                                    <Text style={s.heroTc}>{personnel.tc_no || 'TC Kayıtlı Değil'}</Text>
                                    <View style={s.heroBadges}>
                                        <View style={[s.badge, { backgroundColor: personnel.is_active ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.15)', borderColor: personnel.is_active ? 'rgba(16,185,129,0.4)' : 'rgba(239,68,68,0.4)' }]}>
                                            <Text style={[s.badgeText, { color: personnel.is_active ? '#34D399' : '#FCA5A5' }]}>{personnel.is_active ? 'UYGUN' : 'PASİF'}</Text>
                                        </View>
                                        <View style={[s.badge, { backgroundColor: 'rgba(255,255,255,0.05)', borderColor: 'rgba(255,255,255,0.2)' }]}>
                                            <Text style={[s.badgeText, { color: '#E2E8F0' }]}>{personnel.vehicle?.plate || 'Araçsız'}</Text>
                                        </View>
                                    </View>
                                </View>
                            </View>
                        </BlurView>
                    </Animated.View>

                    {/* KPI Cards */}
                    <Animated.View style={{ opacity: enterAnims[1], transform: [{ translateX: enterAnims[1].interpolate({ inputRange:[0,1], outputRange:[100, 0] }) }] }}>
                        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={s.kpiScroll} decelerationRate="fast" snapToInterval={122}>
                            <BlurView intensity={25} tint="dark" style={[s.kpiCard, { borderColor: 'rgba(16,185,129,0.3)' }]}>
                                <View style={[s.kpiIconBox, { backgroundColor: 'rgba(16,185,129,0.15)' }]}><Icon name="cash" size={22} color="#34D399" /></View>
                                <Text style={s.kpiVal} numberOfLines={1} adjustsFontSizeToFit>₺{formattedSalary}</Text>
                                <Text style={s.kpiLabel}>Net Maaş</Text>
                            </BlurView>
                            <BlurView intensity={25} tint="dark" style={[s.kpiCard, { borderColor: 'rgba(59,130,246,0.3)' }]}>
                                <View style={[s.kpiIconBox, { backgroundColor: 'rgba(59,130,246,0.15)' }]}><Icon name="file-document-outline" size={22} color="#60A5FA" /></View>
                                <Text style={s.kpiVal} numberOfLines={1}>{allDocs.length}</Text>
                                <Text style={s.kpiLabel}>Evrak</Text>
                            </BlurView>
                            <BlurView intensity={25} tint="dark" style={[s.kpiCard, { borderColor: 'rgba(139,92,246,0.3)' }]}>
                                <View style={[s.kpiIconBox, { backgroundColor: 'rgba(139,92,246,0.15)' }]}><Icon name="file-chart-outline" size={22} color="#A78BFA" /></View>
                                <Text style={s.kpiVal} numberOfLines={1}>{payrolls.length}</Text>
                                <Text style={s.kpiLabel}>Bordro</Text>
                            </BlurView>
                            <BlurView intensity={25} tint="dark" style={[s.kpiCard, { borderColor: 'rgba(245,158,11,0.3)' }]}>
                                <View style={[s.kpiIconBox, { backgroundColor: 'rgba(245,158,11,0.15)' }]}><Icon name="image-multiple-outline" size={22} color="#FBBF24" /></View>
                                <Text style={s.kpiVal} numberOfLines={1}>{images.length}</Text>
                                <Text style={s.kpiLabel}>Resim</Text>
                            </BlurView>
                        </ScrollView>
                    </Animated.View>

                    {/* Tabs */}
                    <Animated.View style={[s.tabsContainer, { opacity: enterAnims[2], transform: [{ scale: enterAnims[2].interpolate({ inputRange:[0,1], outputRange:[0.9, 1] }) }] }]}>
                        <BlurView intensity={20} tint="dark" style={s.tabsWrap}>
                            {['genel', 'belge', 'maas', 'resim'].map(tab => (
                                <TouchableOpacity key={tab} style={[s.tab, activeTab === tab && s.activeTab]} onPress={() => setActiveTab(tab)} activeOpacity={0.8}>
                                    {activeTab === tab && <LinearGradient colors={['rgba(255,255,255,0.1)', 'rgba(255,255,255,0.02)']} style={StyleSheet.absoluteFillObject} />}
                                    <Text style={[s.tabText, activeTab === tab && s.activeTabText]}>{tab.charAt(0).toUpperCase() + tab.slice(1)}</Text>
                                </TouchableOpacity>
                            ))}
                        </BlurView>
                    </Animated.View>

                    {/* Content */}
                    <View style={s.tabContent}>
                        {activeTab === 'genel' && (
                            <Animated.View style={[{ width: '100%' }, render3DStyle(0)]}>
                                <BlurView intensity={30} tint="dark" style={s.infoCard}>
                                    <InfoRow icon="phone" color="#34D399" label="Telefon" value={personnel.phone || '-'} />
                                    <InfoRow icon="email" color="#60A5FA" label="E-Posta" value={personnel.email || '-'} />
                                    <InfoRow icon="card-account-details-outline" color="#A78BFA" label="Ehliyet Sınıfı" value={personnel.license_class || '-'} />
                                    <InfoRow icon="certificate" color="#FBBF24" label="SRC Türü" value={personnel.src_type || '-'} />
                                    <InfoRow icon="cake-variant-outline" color="#F472B6" label="Doğum Tarihi" value={personnel.birth_date ? dayjs(personnel.birth_date).format('DD.MM.YYYY') : '-'} />
                                    <InfoRow icon="clock-outline" color="#22D3EE" label="Vardiya" value={personnel.start_shift === 'morning' ? 'Sabah' : personnel.start_shift === 'evening' ? 'Akşam' : '-'} />
                                    <InfoRow icon="calendar-check" color="#2DD4BF" label="İşe Giriş" value={personnel.start_date ? dayjs(personnel.start_date).format('DD.MM.YYYY') : '-'} />
                                    <InfoRow icon="map-marker-outline" color="#FB7185" label="Adres" value={personnel.address || '-'} />
                                    <InfoRow icon="text-box-outline" color="#94A3B8" label="Notlar" value={personnel.notes || '-'} noBorder />
                                </BlurView>
                            </Animated.View>
                        )}

                        {activeTab === 'belge' && (
                            <View style={{ width: '100%' }}>
                                <Animated.View style={render3DStyle(0)}>
                                    <TouchableOpacity style={s.uploadBtn} onPress={() => { setSelectedDoc(null); setDocTitle(''); setShowDocModal(true); }} activeOpacity={0.7}>
                                        <BlurView intensity={20} tint="light" style={StyleSheet.absoluteFillObject} />
                                        <View style={s.uploadIconCircle}><Icon name="cloud-upload" size={24} color="#60A5FA" /></View>
                                        <Text style={s.uploadBtnText}>Yeni Belge Yükle</Text>
                                    </TouchableOpacity>
                                </Animated.View>

                                <Animated.View style={[s.subTabsWrapper, render3DStyle(1)]}>
                                    <TouchableOpacity style={[s.subTabBtn, !showArchive && s.subTabBtnActive]} onPress={() => setShowArchive(false)}>
                                        <Text style={[s.subTabText, !showArchive && s.subTabTextActive]}>Aktif Belgeler</Text>
                                    </TouchableOpacity>
                                    <TouchableOpacity style={[s.subTabBtn, showArchive && s.subTabBtnActive]} onPress={() => setShowArchive(true)}>
                                        <Text style={[s.subTabText, showArchive && s.subTabTextActive]}>Arşiv Belgeler</Text>
                                    </TouchableOpacity>
                                </Animated.View>
                                
                                {displayDocs.map((item, idx) => {
                                    const status = getStatusInfo(item.end_date, item.archived_at);
                                    return (
                                        <Animated.View key={item.id} style={[s.docCard, render3DStyle(idx + 2)]}>
                                            <BlurView intensity={25} tint="dark" style={s.glassCardInner}>
                                                <View style={s.docCardTop}>
                                                    <View style={s.docCardIconBox}><Icon name="file-document-outline" size={24} color="#60A5FA" /></View>
                                                    <View style={s.docCardInfo}>
                                                        <Text style={s.docCardType}>{item.document_type || 'BELGE'}</Text>
                                                        <Text style={s.docCardName}>{item.document_name || 'İsimsiz Belge'}</Text>
                                                    </View>
                                                    <TouchableOpacity onPress={() => deleteDocument(item.id)} style={{ padding: 6 }}>
                                                        <Icon name="trash-can-outline" size={22} color="#F87171" />
                                                    </TouchableOpacity>
                                                </View>

                                                <View style={{ alignItems: 'flex-start', marginTop: 4, marginBottom: 16 }}>
                                                    <View style={[s.docStatusBadge, { backgroundColor: status.bg, borderColor: status.border }]}>
                                                        <Text style={[s.docStatusText, { color: status.color }]}>{status.text}</Text>
                                                    </View>
                                                </View>

                                                <View style={s.docCardDates}>
                                                    <View style={s.docDateGroup}>
                                                        <Icon name="calendar-start" size={14} color="#94A3B8" />
                                                        <View style={{ marginLeft: 6 }}>
                                                            <Text style={s.docDateLabel}>Başlangıç</Text>
                                                            <Text style={s.docDateValue}>{item.created_at ? dayjs(item.created_at).format('DD.MM.YYYY') : '-'}</Text>
                                                        </View>
                                                    </View>
                                                    <View style={s.docDateDivider} />
                                                    <View style={s.docDateGroup}>
                                                        <Icon name="calendar-end" size={14} color="#94A3B8" />
                                                        <View style={{ marginLeft: 6 }}>
                                                            <Text style={s.docDateLabel}>Bitiş</Text>
                                                            <Text style={s.docDateValue}>{item.end_date ? dayjs(item.end_date).format('DD.MM.YYYY') : '-'}</Text>
                                                        </View>
                                                    </View>
                                                </View>

                                                <View style={s.docActionRow}>
                                                    <TouchableOpacity style={[s.docActionFullBtn, { backgroundColor: 'rgba(59,130,246,0.1)' }]} onPress={() => handleViewDocument(item)}>
                                                        <Icon name="eye-outline" size={16} color="#60A5FA" />
                                                        <Text style={[s.docActionFullText, { color: '#60A5FA' }]}>Göster</Text>
                                                    </TouchableOpacity>
                                                    <TouchableOpacity style={[s.docActionFullBtn, { backgroundColor: 'rgba(16,185,129,0.1)' }]} onPress={() => handleViewDocument(item)}>
                                                        <Icon name="cloud-download-outline" size={16} color="#34D399" />
                                                        <Text style={[s.docActionFullText, { color: '#34D399' }]}>İndir</Text>
                                                    </TouchableOpacity>
                                                    <TouchableOpacity style={[s.docActionFullBtn, { backgroundColor: 'rgba(245,158,11,0.1)' }]} onPress={() => handleShareDocument(item)}>
                                                        <Icon name="share-variant-outline" size={16} color="#FBBF24" />
                                                        <Text style={[s.docActionFullText, { color: '#FBBF24' }]}>Paylaş</Text>
                                                    </TouchableOpacity>
                                                </View>
                                            </BlurView>
                                        </Animated.View>
                                    );
                                })}
                                {displayDocs.length === 0 && <Animated.View style={[s.emptyState, render3DStyle(2)]}><Icon name="folder-open-outline" size={48} color="#64748B" /><Text style={s.emptyText}>Belge bulunmuyor.</Text></Animated.View>}
                            </View>
                        )}

                        {activeTab === 'maas' && (
                            <View style={{ width: '100%' }}>
                                {payrolls.map((p, idx) => (
                                    <Animated.View key={p.id} style={[s.docCard, render3DStyle(idx)]}>
                                        <BlurView intensity={30} tint="dark" style={s.glassCardInner}>
                                            <View style={s.docCardTop}>
                                                <View style={[s.docCardIconBox, { backgroundColor: 'rgba(16,185,129,0.15)' }]}><Icon name="cash-register" size={24} color="#34D399" /></View>
                                                <View style={s.docCardInfo}>
                                                    <Text style={[s.docCardType, {color: '#34D399'}]}>BORDRO</Text>
                                                    <Text style={s.docCardName}>{dayjs(p.period_month).format('MM/YYYY')}</Text>
                                                </View>
                                                <View style={{ alignItems: 'flex-end', justifyContent: 'center' }}>
                                                    <Text style={{ fontSize: 20, fontWeight: '900', color: '#34D399', textShadowColor: '#34D399', textShadowRadius: 8 }}>₺{p.net_salary}</Text>
                                                    <Text style={{ fontSize: 11, color: '#94A3B8', fontWeight: '700' }}>Net Ödenen</Text>
                                                </View>
                                            </View>
                                            <View style={{ flexDirection: 'row', justifyContent: 'space-between', backgroundColor: 'rgba(255,255,255,0.05)', padding: 12, borderRadius: 16, marginTop: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' }}>
                                                <View>
                                                    <Text style={{ fontSize: 10, color: '#94A3B8', fontWeight: '800', textTransform: 'uppercase' }}>Temel Maaş</Text>
                                                    <Text style={{ fontSize: 14, color: '#F8FAFC', fontWeight: '900' }}>₺{p.base_salary || 0}</Text>
                                                </View>
                                                <View>
                                                    <Text style={{ fontSize: 10, color: '#94A3B8', fontWeight: '800', textTransform: 'uppercase' }}>Avans</Text>
                                                    <Text style={{ fontSize: 14, color: '#F87171', fontWeight: '900' }}>₺{p.advance_payment || 0}</Text>
                                                </View>
                                                <View>
                                                    <Text style={{ fontSize: 10, color: '#94A3B8', fontWeight: '800', textTransform: 'uppercase' }}>Kesinti</Text>
                                                    <Text style={{ fontSize: 14, color: '#F87171', fontWeight: '900' }}>₺{p.deduction || 0}</Text>
                                                </View>
                                            </View>
                                        </BlurView>
                                    </Animated.View>
                                ))}
                                {payrolls.length === 0 && <Animated.View style={[s.emptyState, render3DStyle(0)]}><Icon name="cash-remove" size={48} color="#64748B" /><Text style={s.emptyText}>Maaş bordrosu bulunamadı.</Text></Animated.View>}
                            </View>
                        )}

                        {activeTab === 'resim' && (
                            <View style={{ width: '100%' }}>
                                <Animated.View style={render3DStyle(0)}>
                                    <TouchableOpacity style={[s.uploadBtn, { borderColor: 'rgba(139,92,246,0.4)', backgroundColor: 'transparent' }]} onPress={() => { setSelectedDoc(null); setDocTitle(''); pickDocument(true); }} activeOpacity={0.7}>
                                        <BlurView intensity={20} tint="light" style={StyleSheet.absoluteFillObject} />
                                        <View style={[s.uploadIconCircle, { backgroundColor: 'rgba(139,92,246,0.15)' }]}><Icon name="image-plus" size={24} color="#A78BFA" /></View>
                                        <Text style={[s.uploadBtnText, { color: '#A78BFA' }]}>Yeni Resim Yükle</Text>
                                    </TouchableOpacity>
                                </Animated.View>
                                <View style={s.imageGrid}>
                                    {images.map((img, idx) => (
                                        <Animated.View key={img.id} style={[s.imageCard, render3DStyle(idx + 1)]}>
                                            <Image source={{ uri: img.file_path.startsWith('http') ? img.file_path : `${api.defaults.baseURL.replace('/api', '')}/storage/${img.file_path}` }} style={s.gridImage} />
                                            <TouchableOpacity style={s.deleteImgBtn} onPress={() => deleteDocument(img.id)}>
                                                <BlurView intensity={40} tint="dark" style={StyleSheet.absoluteFillObject} />
                                                <Icon name="close" size={16} color="#FFF" />
                                            </TouchableOpacity>
                                        </Animated.View>
                                    ))}
                                </View>
                                {images.length === 0 && <Animated.View style={[s.emptyState, render3DStyle(1)]}><Icon name="image-off-outline" size={48} color="#64748B" /><Text style={s.emptyText}>Galeri boş.</Text></Animated.View>}
                            </View>
                        )}
                    </View>
                </Animated.ScrollView>
            </SafeAreaView>

            {/* Modals with Glassmorphism */}
            <Modal visible={showMenu} transparent animationType="fade">
                <BlurView intensity={40} tint="dark" style={s.modalOverlay}>
                    <TouchableOpacity style={StyleSheet.absoluteFill} activeOpacity={1} onPress={() => setShowMenu(false)} />
                    <BlurView intensity={50} tint="dark" style={[s.bottomSheet, { paddingBottom: Math.max(insets.bottom, 20) }]}>
                        <View style={s.sheetHandle} />
                        <Text style={s.sheetTitle}>Aksiyon Menüsü</Text>
                        
                        <TouchableOpacity style={s.menuItem} onPress={() => { setShowMenu(false); updateStatus(!personnel.is_active); }}>
                            <View style={[s.menuIconBox, { backgroundColor: personnel.is_active ? 'rgba(239,68,68,0.15)' : 'rgba(16,185,129,0.15)' }]}><Icon name={personnel.is_active ? "account-off-outline" : "account-check-outline"} size={22} color={personnel.is_active ? "#F87171" : "#34D399"} /></View>
                            <Text style={[s.menuText, { color: personnel.is_active ? "#F87171" : "#34D399" }]}>{personnel.is_active ? 'Pasif Yap' : 'Aktif Yap'}</Text>
                        </TouchableOpacity>

                        <TouchableOpacity style={s.menuItem} onPress={() => { setShowMenu(false); updateStatus(false, new Date().toISOString().split('T')[0]); }}>
                            <View style={[s.menuIconBox, { backgroundColor: 'rgba(245,158,11,0.15)' }]}><Icon name="exit-run" size={22} color="#FBBF24" /></View>
                            <Text style={[s.menuText, { color: "#FBBF24" }]}>İşten Ayrıldı Olarak İşaretle</Text>
                        </TouchableOpacity>

                        <TouchableOpacity style={[s.menuItem, { borderBottomWidth: 0 }]} onPress={() => { setShowMenu(false); setShowVehicleModal(true); }}>
                            <View style={[s.menuIconBox, { backgroundColor: 'rgba(59,130,246,0.15)' }]}><Icon name="car-shift-pattern" size={22} color="#60A5FA" /></View>
                            <Text style={[s.menuText, { color: "#60A5FA" }]}>Araç Değiştir / Ata</Text>
                        </TouchableOpacity>
                    </BlurView>
                </BlurView>
            </Modal>

            {/* Vehicle Modal */}
            <Modal visible={showVehicleModal} transparent animationType="slide">
                <BlurView intensity={40} tint="dark" style={s.modalOverlayCenter}>
                    <TouchableOpacity style={StyleSheet.absoluteFill} activeOpacity={1} onPress={() => setShowVehicleModal(false)} />
                    <BlurView intensity={50} tint="dark" style={s.centerModal}>
                        <Text style={s.modalTitle}>Araç Ata</Text>
                        <SelectInput icon="car-outline" placeholder="Araç Seçin (veya Kaldır)" value={newVehicleId} options={[{ label: "Araç Atanmadı", value: "" }, ...vehicles.map(v => ({ label: v.plate, value: v.id.toString() }))]} onSelect={setNewVehicleId} />
                        <View style={s.modalActions}>
                            <TouchableOpacity style={s.cancelBtn} onPress={() => setShowVehicleModal(false)}><Text style={s.cancelBtnText}>İptal</Text></TouchableOpacity>
                            <TouchableOpacity style={s.saveBtn} onPress={changeVehicle}>
                                <LinearGradient colors={['#8B5CF6', '#4F46E5']} style={StyleSheet.absoluteFillObject} />
                                <Text style={s.saveBtnText}>Değiştir</Text>
                            </TouchableOpacity>
                        </View>
                    </BlurView>
                </BlurView>
            </Modal>

            {/* Document Upload Bottom Sheet */}
            <Modal visible={showDocModal} transparent animationType="slide">
                <BlurView intensity={40} tint="dark" style={s.modalOverlay}>
                    <TouchableOpacity style={StyleSheet.absoluteFill} activeOpacity={1} onPress={() => setShowDocModal(false)} />
                    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ width: '100%' }}>
                        <BlurView intensity={50} tint="dark" style={[s.bottomSheet, { paddingBottom: Math.max(insets.bottom, 20) }]}>
                            <View style={s.sheetHandle} />
                            <Text style={s.sheetTitle}>Belge Yükle</Text>

                            <TouchableOpacity style={s.docPickerBtn} onPress={() => pickDocument(false)}>
                                <BlurView intensity={20} tint="light" style={StyleSheet.absoluteFillObject} />
                                <Icon name="file-document-outline" size={40} color="#60A5FA" />
                                <Text style={s.docPickerText}>{selectedDoc ? selectedDoc.name : 'Cihazdan Belge Seç (PDF, Word, vb.)'}</Text>
                            </TouchableOpacity>

                            {selectedDoc && (
                                <View style={s.fieldWrap}>
                                    <TextInput style={s.fieldInput} placeholderTextColor="#94A3B8" placeholder="Belge Adı (İsteğe Bağlı)" value={docTitle} onChangeText={setDocTitle} />
                                </View>
                            )}

                            <View style={s.formActions}>
                                <TouchableOpacity style={s.cancelBtn} onPress={() => { setShowDocModal(false); setSelectedDoc(null); }}><Text style={s.cancelBtnText}>İptal</Text></TouchableOpacity>
                                <TouchableOpacity style={[s.saveBtn, !selectedDoc && { opacity: 0.5 }]} onPress={() => uploadFile(activeTab === 'resim' ? 'image' : 'document')} disabled={uploading || !selectedDoc}>
                                    <LinearGradient colors={['#3B82F6', '#2563EB']} style={StyleSheet.absoluteFillObject} />
                                    {uploading ? <ActivityIndicator color="#FFF" /> : <Text style={s.saveBtnText}>Yükle</Text>}
                                </TouchableOpacity>
                            </View>
                        </BlurView>
                    </KeyboardAvoidingView>
                </BlurView>
            </Modal>
        </View>
    );
}

const InfoRow = ({ icon, color, label, value, noBorder }) => (
    <View style={[s.infoRow, noBorder && { borderBottomWidth: 0 }]}>
        <View style={s.infoLabelWrap}>
            <View style={[s.infoIconWrap, { backgroundColor: `${color}20`, borderColor: `${color}40`, borderWidth: 1 }]}><Icon name={icon} size={18} color={color} /></View>
            <Text style={s.infoLabel}>{label}</Text>
        </View>
        <View style={s.infoValueWrap}>
            <Text style={s.infoValue} numberOfLines={3}>{value}</Text>
        </View>
    </View>
);

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    bgBlob1: { position: 'absolute', top: -100, right: -100, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(99, 102, 241, 0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, left: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(236, 72, 153, 0.12)', filter: 'blur(40px)' },
    
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, paddingTop: Platform.OS === 'ios' ? 10 : 30, paddingBottom: 16 },
    iconBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    headerTitle: { fontSize: 22, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.5, textShadowColor: 'rgba(255,255,255,0.3)', textShadowOffset: { width: 0, height: 0 }, textShadowRadius: 10 },
    
    heroWrapper: { marginHorizontal: 20, marginTop: 10, marginBottom: 24, borderRadius: 28, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    heroGlass: { padding: 24 },
    heroContent: { flexDirection: 'row', alignItems: 'center' },
    avatarContainer: { marginRight: 20, position: 'relative' },
    heroAvatar: { width: 72, height: 72, borderRadius: 36, borderWidth: 2, borderColor: 'rgba(255,255,255,0.2)', alignItems: 'center', justifyContent: 'center' },
    onlineDot: { position: 'absolute', bottom: 2, right: 2, width: 16, height: 16, borderRadius: 8, borderWidth: 2, borderColor: '#0F172A', shadowOffset: { width: 0, height: 0 }, shadowOpacity: 1, shadowRadius: 6 },
    heroInfo: { flex: 1 },
    heroName: { fontSize: 24, fontWeight: '900', color: '#FFF', marginBottom: 6, letterSpacing: -0.5 },
    heroTc: { fontSize: 13, color: '#94A3B8', fontWeight: '600', marginBottom: 12, letterSpacing: 1 },
    heroBadges: { flexDirection: 'row', gap: 8 },
    badge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 10, borderWidth: 1 },
    badgeText: { fontSize: 10, fontWeight: '900', letterSpacing: 0.5 },
    
    kpiScroll: { paddingHorizontal: 20, gap: 12, marginBottom: 24 },
    kpiCard: { width: 110, padding: 16, borderRadius: 24, borderWidth: 1, overflow: 'hidden' },
    kpiIconBox: { width: 36, height: 36, borderRadius: 12, alignItems: 'center', justifyContent: 'center', marginBottom: 16 },
    kpiVal: { fontSize: 20, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.5 },
    kpiLabel: { fontSize: 11, color: '#94A3B8', fontWeight: '700', marginTop: 4 },

    tabsContainer: { paddingHorizontal: 20, marginBottom: 24 },
    tabsWrap: { flexDirection: 'row', borderRadius: 16, padding: 4, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    tab: { flex: 1, paddingVertical: 12, alignItems: 'center', borderRadius: 12, overflow: 'hidden' },
    activeTab: { borderWidth: 1, borderColor: 'rgba(255,255,255,0.15)' },
    tabText: { fontSize: 13, fontWeight: '800', color: '#64748B' },
    activeTabText: { color: '#F8FAFC', fontWeight: '900' },

    tabContent: { paddingHorizontal: 20, alignItems: 'center' },
    infoCard: { width: '100%', borderRadius: 24, padding: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    infoRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    infoLabelWrap: { flexDirection: 'row', alignItems: 'center', marginRight: 16 },
    infoIconWrap: { width: 32, height: 32, borderRadius: 10, alignItems: 'center', justifyContent: 'center', marginRight: 12 },
    infoLabel: { fontSize: 13, color: '#94A3B8', fontWeight: '700' },
    infoValueWrap: { flex: 1, alignItems: 'flex-end' },
    infoValue: { fontSize: 14, color: '#F8FAFC', fontWeight: '800', textAlign: 'right' },

    uploadBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', padding: 20, borderRadius: 20, borderStyle: 'dashed', borderWidth: 1.5, borderColor: 'rgba(96,165,250,0.5)', marginBottom: 20, overflow: 'hidden' },
    uploadIconCircle: { width: 44, height: 44, borderRadius: 22, backgroundColor: 'rgba(96,165,250,0.2)', alignItems: 'center', justifyContent: 'center', marginRight: 12 },
    uploadBtnText: { fontSize: 16, fontWeight: '900', color: '#60A5FA' },
    
    subTabsWrapper: { flexDirection: 'row', paddingBottom: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)', marginBottom: 20 },
    subTabBtn: { flex: 1, alignItems: 'center', paddingVertical: 14, borderRadius: 14, backgroundColor: 'rgba(255,255,255,0.05)', marginHorizontal: 6, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    subTabBtnActive: { backgroundColor: 'rgba(59,130,246,0.15)', borderColor: 'rgba(59,130,246,0.3)' },
    subTabText: { fontSize: 13, fontWeight: '800', color: '#64748B' },
    subTabTextActive: { color: '#60A5FA' },

    docCard: { marginBottom: 16, borderRadius: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    glassCardInner: { padding: 20 },
    docCardTop: { flexDirection: 'row', alignItems: 'flex-start', marginBottom: 12 },
    docCardIconBox: { width: 48, height: 48, borderRadius: 14, backgroundColor: 'rgba(59,130,246,0.15)', alignItems: 'center', justifyContent: 'center' },
    docCardInfo: { flex: 1, marginLeft: 14, justifyContent: 'center' },
    docCardType: { fontSize: 11, fontWeight: '900', color: '#60A5FA', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 4 },
    docCardName: { fontSize: 16, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.2 },
    docCardDates: { flexDirection: 'row', alignItems: 'center', marginTop: 8, padding: 14, backgroundColor: 'rgba(0,0,0,0.2)', borderRadius: 16, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    docDateGroup: { flex: 1, flexDirection: 'row', alignItems: 'center' },
    docDateDivider: { width: 1, height: 24, backgroundColor: 'rgba(255,255,255,0.1)', marginHorizontal: 12 },
    docDateLabel: { fontSize: 10, fontWeight: '800', color: '#94A3B8', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 2 },
    docDateValue: { fontSize: 14, fontWeight: '900', color: '#F8FAFC' },
    docStatusBadge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8, borderWidth: 1 },
    docStatusText: { fontSize: 11, fontWeight: '900', letterSpacing: 0.5 },
    docActionRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 16, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)', paddingTop: 16, gap: 10 },
    docActionFullBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', flex: 1, gap: 6, paddingVertical: 14, borderRadius: 14 },
    docActionFullText: { fontSize: 13, fontWeight: '900' },
    
    imageGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
    imageCard: { width: (SCREEN_WIDTH - 52) / 2, height: 160, borderRadius: 24, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    gridImage: { width: '100%', height: '100%' },
    deleteImgBtn: { position: 'absolute', top: 12, right: 12, width: 32, height: 32, borderRadius: 16, alignItems: 'center', justifyContent: 'center', overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },

    emptyState: { alignItems: 'center', marginTop: 40, paddingVertical: 20 },
    emptyText: { textAlign: 'center', color: '#94A3B8', marginTop: 16, fontSize: 15, fontWeight: '700' },

    modalOverlay: { flex: 1, justifyContent: 'flex-end' },
    modalOverlayCenter: { flex: 1, justifyContent: 'center', padding: 24 },
    bottomSheet: { borderTopLeftRadius: 32, borderTopRightRadius: 32, padding: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    sheetHandle: { width: 40, height: 5, borderRadius: 3, backgroundColor: 'rgba(255,255,255,0.2)', alignSelf: 'center', marginBottom: 24 },
    sheetTitle: { fontSize: 22, fontWeight: '900', color: '#F8FAFC', marginBottom: 24 },
    
    menuItem: { flexDirection: 'row', alignItems: 'center', paddingVertical: 18, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    menuIconBox: { width: 44, height: 44, borderRadius: 14, alignItems: 'center', justifyContent: 'center' },
    menuText: { fontSize: 16, fontWeight: '900', marginLeft: 16 },

    centerModal: { borderRadius: 32, padding: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    modalTitle: { fontSize: 20, fontWeight: '900', color: '#F8FAFC', marginBottom: 20 },
    
    docPickerBtn: { padding: 24, backgroundColor: 'transparent', borderRadius: 24, borderWidth: 1.5, borderColor: 'rgba(96,165,250,0.4)', borderStyle: 'dashed', alignItems: 'center', marginBottom: 24, overflow: 'hidden' },
    docPickerText: { fontSize: 14, color: '#94A3B8', fontWeight: '800', marginTop: 12, textAlign: 'center' },
    
    fieldWrap: { backgroundColor: 'rgba(0,0,0,0.2)', borderRadius: 16, paddingHorizontal: 16, height: 56, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', marginBottom: 20, justifyContent: 'center', flexDirection: 'row', alignItems: 'center' },
    fieldIcon: { marginRight: 12 },
    fieldInput: { flex: 1, fontSize: 15, color: '#F8FAFC', fontWeight: '700', height: '100%' },
    
    formActions: { flexDirection: 'row', gap: 12 },
    modalActions: { flexDirection: 'row', gap: 12, marginTop: 24 },
    cancelBtn: { flex: 1, paddingVertical: 18, borderRadius: 16, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    cancelBtnText: { color: '#94A3B8', fontSize: 15, fontWeight: '900' },
    saveBtn: { flex: 2, borderRadius: 16, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
    saveBtnText: { color: '#FFF', fontSize: 15, fontWeight: '900', letterSpacing: 0.5 }
});
