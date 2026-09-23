import React, { useState, useEffect, useContext, useRef } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, Alert, RefreshControl, Dimensions, Linking, Platform, Animated, Easing } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import { EmptyState } from '../components';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';

export default function TendersScreen({ navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const [tenders, setTenders] = useState([]);
    const [stats, setStats] = useState({ total: 0, won: 0, lost: 0, evaluating: 0 });
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

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

    const fetchTenders = async (isRefreshing = false) => {
        if (!isRefreshing) setLoading(true);
        try {
            const r = await api.get('/v1/tenders');
            if (r.data.success) {
                setTenders(r.data.data);
                if (r.data.stats) setStats(r.data.stats);
            }
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => {
        const unsubscribe = navigation.addListener('focus', () => {
            fetchTenders();
        });
        return unsubscribe;
    }, [navigation]);

    const confirmDelete = (id) => {
        if (!hasPermission('tenders.delete')) {
            Alert.alert('Yetki Yok', 'Silme yetkiniz yok.');
            return;
        }
        Alert.alert('Silinecek', 'Bu ihale kaydını silmek istediğinize emin misiniz?', [
            { text: 'İptal', style: 'cancel' },
            { text: 'Sil', style: 'destructive', onPress: async () => {
                try {
                    await api.delete(`/v1/tenders/${id}`);
                    fetchTenders();
                } catch(e) {}
            }}
        ]);
    };

    const getStatusStyle = (status) => {
        switch(status) {
            case 'Kazanıldı': return { bg: 'rgba(16,185,129,0.15)', text: '#34D399', dot: '#10B981' };
            case 'Kaybedildi': return { bg: 'rgba(239,68,68,0.15)', text: '#F87171', dot: '#EF4444' };
            case 'Değerlendirmede': return { bg: 'rgba(245,158,11,0.15)', text: '#FBBF24', dot: '#F59E0B' };
            default: return { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8', dot: '#64748B' };
        }
    };

    const formatMoney = (val) => {
        if (!val) return '-';
        return Number(val).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
    };

    const renderItem = ({ item }) => {
        const statusStyle = getStatusStyle(item.status);

        return (
            <BlurView intensity={30} tint="dark" style={st.card}>
                <View style={st.cardHeader}>
                    <View style={[st.statusBadge, { backgroundColor: statusStyle.bg }]}>
                        <View style={[st.statusDot, { backgroundColor: statusStyle.dot }]} />
                        <Text style={[st.statusText, { color: statusStyle.text }]}>{item.status?.toUpperCase()}</Text>
                    </View>
                    <Text style={st.dateText}>{item.tender_date ? new Date(item.tender_date).toLocaleDateString('tr-TR') : '-'}</Text>
                </View>

                <Text style={st.institutionName}>{item.institution_name}</Text>
                {item.tender_registration_number ? <Text style={st.iknText}>İKN: {item.tender_registration_number}</Text> : null}

                <View style={st.infoGrid}>
                    <View style={st.infoBox}>
                        <Text style={st.infoLabel}>ARAÇ İHTİYACI</Text>
                        <Text style={st.infoValue} numberOfLines={2}>{item.vehicle_details || 'Belirtilmedi'}</Text>
                    </View>
                    <View style={st.infoBox}>
                        <Text style={st.infoLabel}>İŞİN SÜRESİ</Text>
                        <Text style={st.infoValue}>{item.duration_days ? `${item.duration_days} Gün` : '-'}</Text>
                    </View>
                </View>

                <View style={st.financeBox}>
                    <View style={st.financeRow}>
                        <Text style={st.financeLabel}>Bizim Teklifimiz</Text>
                        <Text style={[st.financeValue, { color: '#38BDF8' }]}>{formatMoney(item.our_bid)}</Text>
                    </View>
                    <View style={st.divider} />
                    <View style={st.financeRow}>
                        <Text style={st.financeLabel}>Kazanan Firma / Teklif</Text>
                        <View style={{ alignItems: 'flex-end' }}>
                            <Text style={[st.financeValue, { color: '#FBBF24' }]}>{formatMoney(item.winning_amount)}</Text>
                            <Text style={st.winningCompany}>{item.winning_company || 'Bilinmiyor'}</Text>
                        </View>
                    </View>
                </View>

                <View style={st.actionRow}>
                    {item.file_url ? (
                        <TouchableOpacity style={[st.actionBtn, { backgroundColor: 'rgba(56,189,248,0.15)' }]} onPress={() => Linking.openURL(item.file_url)}>
                            <Icon name="file-pdf-box" size={18} color="#38BDF8" />
                            <Text style={[st.actionBtnText, { color: '#38BDF8' }]}>PDF</Text>
                        </TouchableOpacity>
                    ) : (
                        <View style={st.actionBtn} />
                    )}

                    <View style={{ flexDirection: 'row', gap: 8 }}>
                        {hasPermission('tenders.edit') && (
                            <TouchableOpacity style={[st.actionBtn, { backgroundColor: 'rgba(255,255,255,0.1)' }]} onPress={() => navigation.navigate('TenderForm', { tenderId: item.id })}>
                                <Icon name="pencil" size={18} color="#E2E8F0" />
                            </TouchableOpacity>
                        )}
                        {hasPermission('tenders.delete') && (
                            <TouchableOpacity style={[st.actionBtn, { backgroundColor: 'rgba(239,68,68,0.15)' }]} onPress={() => confirmDelete(item.id)}>
                                <Icon name="trash-can-outline" size={18} color="#F87171" />
                            </TouchableOpacity>
                        )}
                    </View>
                </View>
            </BlurView>
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
                        <Text style={st.headerTitle}>İhaleler</Text>
                        <Text style={st.headerSubtitle}>Geçmiş İhale Arşivi</Text>
                    </View>
                    {hasPermission('tenders.create') ? (
                        <TouchableOpacity style={st.addHeaderBtn} onPress={() => navigation.navigate('TenderForm')}>
                            <LinearGradient colors={['#3B82F6', '#2563EB']} style={StyleSheet.absoluteFillObject} />
                            <Icon name="plus" size={24} color="#fff" />
                        </TouchableOpacity>
                    ) : <View style={{ width: 44 }} />}
                </View>

                <BlurView intensity={40} tint="dark" style={st.statsContainer}>
                    <View style={st.statBox}>
                        <Text style={st.statValue}>{stats.total}</Text>
                        <Text style={st.statLabel}>KAYITLI İHALE</Text>
                    </View>
                    <View style={st.statDivider} />
                    <View style={st.statBox}>
                        <Text style={[st.statValue, { color: '#34D399' }]}>{stats.won}</Text>
                        <Text style={st.statLabel}>KAZANILAN</Text>
                    </View>
                    <View style={st.statDivider} />
                    <View style={st.statBox}>
                        <Text style={[st.statValue, { color: '#F87171' }]}>{stats.lost}</Text>
                        <Text style={st.statLabel}>KAYBEDİLEN</Text>
                    </View>
                </BlurView>

                {loading ? (
                    <View style={st.loader}><ActivityIndicator size="large" color="#3B82F6" /></View>
                ) : (
                    <FlatList
                        data={tenders}
                        renderItem={renderItem}
                        keyExtractor={item => item.id.toString()}
                        contentContainerStyle={st.listContent}
                        showsVerticalScrollIndicator={false}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => fetchTenders(true)} tintColor="#3B82F6" />}
                        ListEmptyComponent={<EmptyState title="İhale Bulunamadı" message="Henüz sisteme eklenmiş bir ihale kaydı bulunmuyor." icon="briefcase-outline" />}
                    />
                )}
            </SafeAreaView>
        </View>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    bgBlob1: { position: 'absolute', top: -50, left: -50, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(56,189,248,0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, right: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(59,130,246,0.15)', filter: 'blur(40px)' },
    
    loader: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingBottom: 16, paddingTop: 8 },
    backBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    headerCenter: { flex: 1, alignItems: 'center' },
    headerTitle: { fontSize: 20, fontWeight: '900', color: '#F8FAFC' },
    headerSubtitle: { fontSize: 13, fontWeight: '600', color: '#38BDF8', marginTop: 2 },
    addHeaderBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(59,130,246,0.5)' },
    
    statsContainer: { flexDirection: 'row', marginHorizontal: 16, borderRadius: 20, padding: 16, marginBottom: 8, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    statBox: { flex: 1, alignItems: 'center' },
    statValue: { fontSize: 20, fontWeight: '900', color: '#F8FAFC' },
    statLabel: { fontSize: 10, fontWeight: '800', color: '#94A3B8', marginTop: 4, letterSpacing: 0.5 },
    statDivider: { width: 1, height: '80%', backgroundColor: 'rgba(255,255,255,0.1)', alignSelf: 'center' },

    listContent: { padding: 16, paddingBottom: 120 },
    card: { borderRadius: 24, padding: 20, marginBottom: 16, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    
    cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 },
    statusBadge: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 10, paddingVertical: 5, borderRadius: 10, gap: 6 },
    statusDot: { width: 6, height: 6, borderRadius: 3 },
    statusText: { fontSize: 10, fontWeight: '800', letterSpacing: 0.5 },
    dateText: { fontSize: 12, fontWeight: '700', color: '#94A3B8' },

    institutionName: { fontSize: 17, fontWeight: '900', color: '#F8FAFC', marginBottom: 2 },
    iknText: { fontSize: 12, fontWeight: '600', color: '#94A3B8', marginBottom: 12 },

    infoGrid: { flexDirection: 'row', gap: 12, marginBottom: 16 },
    infoBox: { flex: 1, backgroundColor: 'rgba(0,0,0,0.3)', padding: 10, borderRadius: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    infoLabel: { fontSize: 10, fontWeight: '800', color: '#94A3B8', marginBottom: 4 },
    infoValue: { fontSize: 13, fontWeight: '700', color: '#E2E8F0' },

    financeBox: { backgroundColor: 'rgba(0,0,0,0.3)', borderRadius: 16, padding: 12, marginBottom: 16, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    financeRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
    financeLabel: { fontSize: 12, fontWeight: '700', color: '#94A3B8' },
    financeValue: { fontSize: 15, fontWeight: '900' },
    divider: { height: 1, backgroundColor: 'rgba(255,255,255,0.1)', marginVertical: 8 },
    winningCompany: { fontSize: 11, fontWeight: '600', color: '#94A3B8', marginTop: 2 },

    actionRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingTop: 12, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)' },
    actionBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16, paddingVertical: 8, borderRadius: 10, gap: 6 },
    actionBtnText: { fontSize: 12, fontWeight: '800' }
});
