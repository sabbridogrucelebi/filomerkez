import React, { useState, useEffect, useRef } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, RefreshControl, Modal, TextInput, Animated, Easing, KeyboardAvoidingView, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import api from '../api/axios';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';

export default function ActivityScreen({ navigation }) {
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState(null);
    
    // Pagination states
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);

    // Filter states
    const [filterModal, setFilterModal] = useState(false);
    const [filters, setFilters] = useState({ search: '', module: '', action: '' });
    const [activeFilters, setActiveFilters] = useState({ search: '', module: '', action: '' });

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

    const fetchLogs = async (pageNumber = 1, isRefresh = false, currentFilters = activeFilters) => {
        try {
            if (isRefresh) setRefreshing(true);
            else if (pageNumber === 1) setLoading(true);
            else setLoadingMore(true);

            setError(null);
            
            let query = `/v1/activity-logs?page=${pageNumber}&per_page=20`;
            if (currentFilters.search) query += `&search=${encodeURIComponent(currentFilters.search)}`;
            if (currentFilters.module) query += `&module=${encodeURIComponent(currentFilters.module)}`;
            if (currentFilters.action) query += `&action=${encodeURIComponent(currentFilters.action)}`;

            const response = await api.get(query);

            if (response.data.success) {
                const newLogs = response.data.data;
                const meta = response.data.meta;
                
                if (pageNumber === 1) setLogs(newLogs);
                else setLogs(prev => [...prev, ...newLogs]);

                setHasMore(meta.current_page < meta.last_page);
                setPage(meta.current_page);
            } else {
                setError(response.data.message || 'Veri alınamadı.');
            }
        } catch (err) {
            setError(err.response?.status === 403 ? 'Bu alanı görüntüleme yetkiniz yok.' : 'Bağlantı hatası oluştu.');
        } finally {
            setLoading(false); setRefreshing(false); setLoadingMore(false);
        }
    };

    useEffect(() => { fetchLogs(1); }, []);

    const onRefresh = () => { setHasMore(true); fetchLogs(1, true); };
    const loadMore = () => { if (!loadingMore && hasMore && !loading && !refreshing) fetchLogs(page + 1); };

    const applyFilters = () => {
        setActiveFilters(filters);
        setFilterModal(false);
        fetchLogs(1, false, filters);
    };

    const clearFilters = () => {
        const empty = { search: '', module: '', action: '' };
        setFilters(empty);
        setActiveFilters(empty);
        setFilterModal(false);
        fetchLogs(1, false, empty);
    };

    const getIconDetails = (module, action) => {
        let iconName = 'view-grid';
        
        switch (module) {
            case 'vehicles': iconName = 'car'; break;
            case 'drivers': iconName = 'steering'; break;
            case 'trips': iconName = 'map-marker-path'; break;
            case 'fuels': iconName = 'gas-station'; break;
            case 'maintenances': iconName = 'wrench'; break;
            case 'penalties': iconName = 'alert-octagon'; break;
            case 'documents': iconName = 'file-document'; break;
            case 'customers': iconName = 'domain'; break;
            case 'users': iconName = 'account-group'; break;
        }

        let bg = 'rgba(255,255,255,0.1)', color = '#94A3B8'; // Default Slate

        if (['created', 'image_uploaded', 'document_uploaded'].includes(action)) { bg = 'rgba(16,185,129,0.2)'; color = '#34D399'; } // Emerald
        else if (action === 'updated') { bg = 'rgba(59,130,246,0.2)'; color = '#60A5FA'; } // Blue
        else if (['deleted', 'image_deleted', 'document_deleted'].includes(action)) { bg = 'rgba(239,68,68,0.2)'; color = '#F87171'; } // Red
        else if (action === 'exported') { bg = 'rgba(168,85,247,0.2)'; color = '#C084FC'; } // Purple

        const actionNames = {
            created: 'OLUŞTURULDU',
            updated: 'GÜNCELLENDİ',
            deleted: 'SİLİNDİ',
            exported: 'DIŞA AKTARILDI',
            image_uploaded: 'GÖRSEL EKLENDİ',
            image_deleted: 'GÖRSEL SİLİNDİ',
            document_uploaded: 'BELGE EKLENDİ',
            document_deleted: 'BELGE SİLİNDİ'
        };

        const actionText = actionNames[action] || action.toUpperCase();

        return { icon: iconName, bg, color, actionText };
    };

    const isFiltered = activeFilters.search || activeFilters.module || activeFilters.action;

    const renderItem = ({ item, index }) => {
        const { icon, bg, color, actionText } = getIconDetails(item.module, item.action);
        const isLast = index === logs.length - 1;

        return (
            <View style={st.timelineItem}>
                {!isLast && <View style={st.timelineLine} />}
                
                <View style={[st.timelineNode, { backgroundColor: bg, borderWidth: 1, borderColor: color }]}>
                    <Icon name={icon} size={20} color={color} />
                </View>

                <View style={st.timelineContent}>
                    <BlurView intensity={30} tint="dark" style={st.card}>
                        <View style={st.cardHeader}>
                            <Text style={st.cardTitle}>{item.title}</Text>
                            <View style={[st.badge, { backgroundColor: bg }]}>
                                <Text style={[st.badgeTxt, { color }]}>{actionText}</Text>
                            </View>
                        </View>
                        <Text style={st.cardDesc}>{item.description}</Text>
                        
                        <View style={st.cardFooter}>
                            <View style={st.footerLeft}>
                                <Icon name="account" size={14} color="#94A3B8" />
                                <Text style={st.footerTxt}>{item.user_name}</Text>
                            </View>
                            <View style={st.footerRight}>
                                <Icon name="clock-outline" size={14} color="#94A3B8" />
                                <Text style={st.footerTxt}>{item.created_at_human}</Text>
                            </View>
                        </View>
                    </BlurView>
                </View>
            </View>
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
                    <View style={{ flex: 1, alignItems: 'center' }}>
                        <Text style={st.headerTitle}>Sistem Logları</Text>
                        <Text style={st.headerSubtitle}>Hareket Dökümü</Text>
                    </View>
                    <TouchableOpacity style={st.backBtn} onPress={() => setFilterModal(true)}>
                        <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                        <Icon name={isFiltered ? "filter" : "filter-outline"} size={22} color={isFiltered ? "#38BDF8" : "#FFF"} />
                    </TouchableOpacity>
                </View>

                {error && !loading && logs.length === 0 ? (
                    <View style={st.center}>
                        <Icon name="alert-circle-outline" size={48} color="#F87171" />
                        <Text style={st.errorTxt}>{error}</Text>
                        <TouchableOpacity onPress={() => fetchLogs(1)} style={st.retryBtn}><Text style={st.retryTxt}>Tekrar Dene</Text></TouchableOpacity>
                    </View>
                ) : (
                    <FlatList
                        data={logs}
                        keyExtractor={(i) => i.id.toString()}
                        renderItem={renderItem}
                        contentContainerStyle={st.listContent}
                        showsVerticalScrollIndicator={false}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#3B82F6" />}
                        onEndReached={loadMore}
                        onEndReachedThreshold={0.5}
                        ListEmptyComponent={
                            !loading && (
                                <View style={st.center}>
                                    <Icon name="magnify" size={56} color="#475569" />
                                    <Text style={st.emptyTxt}>Log bulunamadı.</Text>
                                </View>
                            )
                        }
                        ListFooterComponent={
                            loadingMore ? <ActivityIndicator size="small" color="#38BDF8" style={{ marginVertical: 20 }} /> : null
                        }
                    />
                )}

                {/* FILTER MODAL */}
                <Modal visible={filterModal} animationType="slide" transparent={true} onRequestClose={() => setFilterModal(false)}>
                    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={st.modalOverlay}>
                        <TouchableOpacity style={StyleSheet.absoluteFill} onPress={() => setFilterModal(false)} />
                        <BlurView intensity={70} tint="dark" style={st.modalContent}>
                            <View style={st.sheetHandle} />
                            <View style={st.modalHeader}>
                                <Text style={st.modalTitle}>Gelişmiş Filtreler</Text>
                                <TouchableOpacity onPress={() => setFilterModal(false)} style={st.modalCloseBtn}>
                                    <Icon name="close" size={24} color="#F8FAFC" />
                                </TouchableOpacity>
                            </View>

                            <Text style={st.label}>Arama</Text>
                            <TextInput
                                style={st.input}
                                placeholder="Başlık veya içerik..."
                                value={filters.search}
                                onChangeText={(t) => setFilters(p => ({...p, search: t}))}
                                placeholderTextColor="#94A3B8"
                            />

                            <Text style={st.label}>Modül</Text>
                            <View style={st.chipGroup}>
                                {['', 'vehicles', 'trips', 'fuels', 'maintenances', 'penalties'].map((mod, i) => {
                                    const isActive = filters.module === mod;
                                    return (
                                        <TouchableOpacity key={i} style={[st.chip, isActive && st.chipActive]} onPress={() => setFilters(p => ({...p, module: mod}))}>
                                            <Text style={[st.chipTxt, isActive && st.chipTxtActive]}>{mod || 'Tümü'}</Text>
                                        </TouchableOpacity>
                                    );
                                })}
                            </View>

                            <Text style={st.label}>İşlem Tipi</Text>
                            <View style={st.chipGroup}>
                                {[
                                    {val: '', label: 'Tümü'}, 
                                    {val: 'created', label: 'Oluşturma'}, 
                                    {val: 'updated', label: 'Güncelleme'}, 
                                    {val: 'deleted', label: 'Silme'},
                                    {val: 'exported', label: 'Dışa Aktarım'},
                                    {val: 'image_uploaded', label: 'Görsel'},
                                    {val: 'document_uploaded', label: 'Belge'}
                                ].map((act, i) => {
                                    const isActive = filters.action === act.val;
                                    return (
                                        <TouchableOpacity key={i} style={[st.chip, isActive && st.chipActive]} onPress={() => setFilters(p => ({...p, action: act.val}))}>
                                            <Text style={[st.chipTxt, isActive && st.chipTxtActive]}>{act.label}</Text>
                                        </TouchableOpacity>
                                    );
                                })}
                            </View>

                            <View style={st.modalActions}>
                                <TouchableOpacity style={st.clearBtn} onPress={clearFilters}>
                                    <Text style={st.clearBtnTxt}>Temizle</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={st.applyBtn} onPress={applyFilters}>
                                    <LinearGradient colors={['#38BDF8', '#0284C7']} style={StyleSheet.absoluteFillObject} />
                                    <Text style={st.applyBtnTxt}>Uygula</Text>
                                </TouchableOpacity>
                            </View>
                        </BlurView>
                    </KeyboardAvoidingView>
                </Modal>
            </SafeAreaView>
        </View>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    bgBlob1: { position: 'absolute', top: -50, left: -50, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(56,189,248,0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, right: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(139,92,246,0.15)', filter: 'blur(40px)' },
    
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingBottom: 16, paddingTop: 8 },
    backBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    headerTitle: { fontSize: 20, fontWeight: '900', color: '#F8FAFC' },
    headerSubtitle: { fontSize: 13, fontWeight: '600', color: '#38BDF8', marginTop: 2 },
    
    center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 20 },
    errorTxt: { color: '#94A3B8', marginTop: 10, textAlign: 'center' },
    retryBtn: { marginTop: 15, paddingHorizontal: 20, paddingVertical: 10, backgroundColor: '#38BDF8', borderRadius: 8 },
    retryTxt: { color: '#fff', fontWeight: 'bold' },
    emptyTxt: { color: '#94A3B8', fontSize: 16, fontWeight: '500', marginTop: 10 },
    
    listContent: { padding: 16, paddingBottom: 100 },
    
    // Timeline Styles
    timelineItem: { flexDirection: 'row', position: 'relative' },
    timelineLine: { position: 'absolute', left: 19, top: 40, bottom: -10, width: 2, backgroundColor: 'rgba(255,255,255,0.1)', zIndex: 0 },
    timelineNode: { width: 40, height: 40, borderRadius: 20, justifyContent: 'center', alignItems: 'center', zIndex: 1 },
    timelineContent: { flex: 1, marginLeft: 16, marginBottom: 16 },
    
    card: { borderRadius: 16, padding: 16, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 8 },
    cardTitle: { flex: 1, fontSize: 15, fontWeight: '700', color: '#F8FAFC', marginRight: 8 },
    badge: { paddingHorizontal: 6, paddingVertical: 2, borderRadius: 4 },
    badgeTxt: { fontSize: 9, fontWeight: '800' },
    cardDesc: { fontSize: 13, color: '#94A3B8', lineHeight: 18, marginBottom: 12 },
    cardFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)', paddingTop: 10 },
    footerLeft: { flexDirection: 'row', alignItems: 'center', gap: 4 },
    footerRight: { flexDirection: 'row', alignItems: 'center', gap: 4 },
    footerTxt: { fontSize: 11, fontWeight: '600', color: '#64748B' },

    // Modal Styles
    modalOverlay: { flex: 1, justifyContent: 'flex-end', backgroundColor: 'rgba(0,0,0,0.5)' },
    modalContent: { borderTopLeftRadius: 32, borderTopRightRadius: 32, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden', padding: 24, paddingBottom: 40 },
    sheetHandle: { width: 40, height: 4, borderRadius: 2, backgroundColor: 'rgba(255,255,255,0.2)', alignSelf: 'center', marginBottom: 20 },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 },
    modalTitle: { fontSize: 18, fontWeight: '900', color: '#F8FAFC' },
    modalCloseBtn: { width: 36, height: 36, borderRadius: 18, backgroundColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center' },
    
    label: { fontSize: 13, fontWeight: '800', color: '#94A3B8', marginBottom: 8, marginTop: 12, letterSpacing: 0.5 },
    input: { backgroundColor: 'rgba(0,0,0,0.3)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', borderRadius: 16, padding: 14, fontSize: 14, color: '#F8FAFC' },
    
    chipGroup: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    chip: { backgroundColor: 'rgba(255,255,255,0.05)', paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    chipActive: { backgroundColor: 'rgba(56,189,248,0.15)', borderColor: '#38BDF8' },
    chipTxt: { fontSize: 13, fontWeight: '600', color: '#94A3B8' },
    chipTxtActive: { color: '#38BDF8' },

    modalActions: { flexDirection: 'row', gap: 12, marginTop: 30 },
    clearBtn: { flex: 1, paddingVertical: 14, borderRadius: 16, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    clearBtnTxt: { fontSize: 15, fontWeight: '700', color: '#94A3B8' },
    applyBtn: { flex: 2, paddingVertical: 14, borderRadius: 16, alignItems: 'center', overflow: 'hidden' },
    applyBtnTxt: { fontSize: 15, fontWeight: '800', color: '#fff' },
});
