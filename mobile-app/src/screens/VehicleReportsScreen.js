import React, { useState, useEffect, useRef } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, FlatList, Dimensions, Platform, Alert, Animated } from 'react-native';
import { BlurView } from 'expo-blur';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import api from '../api/axios';

const { width: W } = Dimensions.get('window');

const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 0 }).format(v || 0);

const MONTH_NAMES = [
    'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
    'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'
];

export default function VehicleReportsScreen({ route, navigation }) {
    const { vehicleId, vehicle } = route.params || {};
    
    const [dateObj, setDateObj] = useState(new Date());
    const [loading, setLoading] = useState(true);
    const [reports, setReports] = useState([]);
    const [totals, setTotals] = useState({ morning: 0, evening: 0, income: 0 });

    const fetchReports = async (date) => {
        setLoading(true);
        try {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const monthStr = `${y}-${m}`;
            
            const r = await api.get(`/v1/vehicles/${vehicleId}/reports`, {
                params: { reports_month: monthStr }
            });
            
            setReports(r.data.data.details || []);
            setTotals(r.data.data.totals || { morning: 0, evening: 0, income: 0 });
        } catch (error) {
            console.error('Reports Fetch Error:', error);
            Alert.alert('Hata', 'Rapor verileri alınamadı.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (vehicleId) {
            fetchReports(dateObj);
        } else {
            setLoading(false);
        }
    }, [dateObj]);

    const handlePrevMonth = () => {
        const d = new Date(dateObj);
        d.setMonth(d.getMonth() - 1);
        setDateObj(d);
    };

    const handleNextMonth = () => {
        const d = new Date(dateObj);
        d.setMonth(d.getMonth() + 1);
        setDateObj(d);
    };

    const renderHeader = () => {
        return (
            <View style={st.headerWrap}>
                <View style={st.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={st.backBtn}>
                        <Icon name="arrow-left" size={24} color="#F8FAFC" />
                    </TouchableOpacity>
                    <View style={st.headerTitleBox}>
                        <Text style={st.headerTitle}>Aylık Çalışma Raporu</Text>
                        <Text style={st.headerSub}>{vehicle?.plate || 'Araç'}</Text>
                    </View>
                    <View style={{ width: 44 }} />
                </View>

                {/* Month Picker */}
                <BlurView intensity={30} tint="dark" style={st.monthPicker}>
                    <TouchableOpacity style={st.monthBtn} onPress={handlePrevMonth}>
                        <Icon name="chevron-left" size={24} color="#94A3B8" />
                    </TouchableOpacity>
                    <View style={st.monthDisplay}>
                        <Icon name="calendar-month-outline" size={20} color="#60A5FA" />
                        <Text style={st.monthText}>
                            {MONTH_NAMES[dateObj.getMonth()]} {dateObj.getFullYear()}
                        </Text>
                    </View>
                    <TouchableOpacity style={st.monthBtn} onPress={handleNextMonth}>
                        <Icon name="chevron-right" size={24} color="#94A3B8" />
                    </TouchableOpacity>
                </BlurView>

                {/* Summary Cards */}
                <View style={st.summaryGrid}>
                    {/* Sabah */}
                    <BlurView intensity={30} tint="dark" style={[st.sumCard, { borderLeftColor: '#F59E0B' }]}>
                        <Text style={[st.sumLabel, { color: '#FCD34D' }]}>TOPLAM SABAH SEFERİ</Text>
                        <Text style={[st.sumValue, { color: '#FDE68A' }]}>{totals.morning}</Text>
                        <View style={st.sumFooter}>
                            <View style={[st.sumDot, { backgroundColor: '#FBBF24' }]} />
                            <Text style={[st.sumFooterText, { color: '#FCD34D' }]}>Gidiş / Sabah</Text>
                        </View>
                        <Icon name="weather-sunny" size={60} color="rgba(245, 158, 11, 0.15)" style={st.bgIcon} />
                    </BlurView>

                    {/* Akşam */}
                    <BlurView intensity={30} tint="dark" style={[st.sumCard, { borderLeftColor: '#6366F1' }]}>
                        <Text style={[st.sumLabel, { color: '#A5B4FC' }]}>TOPLAM AKŞAM SEFERİ</Text>
                        <Text style={[st.sumValue, { color: '#C7D2FE' }]}>{totals.evening}</Text>
                        <View style={st.sumFooter}>
                            <View style={[st.sumDot, { backgroundColor: '#818CF8' }]} />
                            <Text style={[st.sumFooterText, { color: '#A5B4FC' }]}>Dönüş / Akşam</Text>
                        </View>
                        <Icon name="weather-night" size={60} color="rgba(99, 102, 241, 0.15)" style={st.bgIcon} />
                    </BlurView>

                    {/* Toplam */}
                    <BlurView intensity={30} tint="dark" style={[st.sumCard, { borderLeftColor: '#10B981' }]}>
                        <Text style={[st.sumLabel, { color: '#6EE7B7' }]}>AYLIK TOPLAM HAKEDİŞ</Text>
                        <Text style={[st.sumValue, { color: '#34D399' }]}>{fmtMoney(totals.income)}</Text>
                        <View style={st.sumFooter}>
                            <View style={[st.sumDot, { backgroundColor: '#34D399' }]} />
                            <Text style={[st.sumFooterText, { color: '#6EE7B7' }]}>Araç Bazlı Ciro</Text>
                        </View>
                        <Icon name="currency-try" size={60} color="rgba(16, 185, 129, 0.15)" style={st.bgIcon} />
                    </BlurView>
                </View>

                <View style={st.listHeader}>
                    <Text style={st.listTitle}>Müşteri / Kurum Analizi</Text>
                </View>
            </View>
        );
    };

    const AnimatedReportRow = ({ item, index }) => {
        const slideAnim = useRef(new Animated.Value(50)).current;
        const opacityAnim = useRef(new Animated.Value(0)).current;

        useEffect(() => {
            Animated.parallel([
                Animated.timing(opacityAnim, { toValue: 1, duration: 400, delay: index * 100, useNativeDriver: true }),
                Animated.spring(slideAnim, { toValue: 0, friction: 6, tension: 40, delay: index * 100, useNativeDriver: true })
            ]).start();
        }, []);

        return (
            <Animated.View style={{ opacity: opacityAnim, transform: [{ translateY: slideAnim }], marginBottom: 12, marginHorizontal: 16 }}>
                <BlurView intensity={30} tint="dark" style={st.rowCard}>
                    <View style={st.rowTop}>
                        <View style={st.rowCustomerIcon}>
                            <Text style={st.rowCustomerInitial}>{item.customer_name.substring(0, 1)}</Text>
                        </View>
                        <View style={st.rowCustomerInfo}>
                            <Text style={st.rowCustomerName}>{item.customer_name}</Text>
                            <Text style={st.rowCustomerSub}>OPERASYON</Text>
                        </View>
                    </View>
                    
                    <View style={st.rowStats}>
                        <View style={st.statBox}>
                            <Text style={st.statBoxLabel}>SABAH</Text>
                            <View style={[st.statBoxBadge, { backgroundColor: 'rgba(245, 158, 11, 0.2)' }]}>
                                <Text style={[st.statBoxValue, { color: '#FCD34D' }]}>{item.morning_count}</Text>
                            </View>
                        </View>
                        <View style={st.statBox}>
                            <Text style={st.statBoxLabel}>AKŞAM</Text>
                            <View style={[st.statBoxBadge, { backgroundColor: 'rgba(99, 102, 241, 0.2)' }]}>
                                <Text style={[st.statBoxValue, { color: '#A5B4FC' }]}>{item.evening_count}</Text>
                            </View>
                        </View>
                        <View style={[st.statBox, { alignItems: 'flex-end', flex: 1 }]}>
                            <Text style={st.statBoxLabel}>TOPLAM KAZANÇ</Text>
                            <Text style={st.statPrice}>{fmtMoney(item.total_price)}</Text>
                        </View>
                    </View>
                </BlurView>
            </Animated.View>
        );
    };

    const renderItem = ({ item, index }) => <AnimatedReportRow item={item} index={index} />;

    return (
        <SafeAreaView style={st.container} edges={['top']}>
            <FlatList
                data={reports}
                keyExtractor={(item, index) => index.toString()}
                ListHeaderComponent={renderHeader}
                renderItem={renderItem}
                contentContainerStyle={st.listContent}
                showsVerticalScrollIndicator={false}
                ListEmptyComponent={
                    !loading && (
                        <View style={st.emptyState}>
                            <Icon name="chart-box-outline" size={48} color="#CBD5E1" />
                            <Text style={st.emptyText}>Bu aya ait operasyon kaydı bulunamadı.</Text>
                        </View>
                    )
                }
            />
            {loading && (
                <View style={st.loadingOverlay}>
                    <ActivityIndicator size="large" color="#3B82F6" />
                </View>
            )}
        </SafeAreaView>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    listContent: { paddingBottom: 120 },
    headerWrap: { paddingHorizontal: 16, paddingTop: Platform.OS === 'android' ? 20 : 30, paddingBottom: 16 },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 20 },
    backBtn: { width: 44, height: 44, borderRadius: 12, backgroundColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center' },
    headerTitleBox: { flex: 1, alignItems: 'center', paddingHorizontal: 10 },
    headerTitle: { fontSize: 18, fontWeight: '800', color: '#F8FAFC', textAlign: 'center', textShadowColor: 'rgba(255,255,255,0.2)', textShadowRadius: 10 },
    headerSub: { fontSize: 13, color: '#94A3B8', textAlign: 'center', fontWeight: '500', marginTop: 2 },
    
    monthPicker: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderRadius: 16, padding: 8, marginBottom: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)', overflow: 'hidden' },
    monthBtn: { width: 40, height: 40, borderRadius: 12, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', justifyContent: 'center' },
    monthDisplay: { flexDirection: 'row', alignItems: 'center', gap: 8 },
    monthText: { fontSize: 16, fontWeight: '700', color: '#F8FAFC' },

    summaryGrid: { gap: 12, marginBottom: 24 },
    sumCard: { padding: 16, borderRadius: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)', overflow: 'hidden', position: 'relative', borderLeftWidth: 5 },
    sumLabel: { fontSize: 11, fontWeight: '800', letterSpacing: 0.5, marginBottom: 8 },
    sumValue: { fontSize: 28, fontWeight: '900', marginBottom: 12 },
    sumFooter: { flexDirection: 'row', alignItems: 'center', gap: 6 },
    sumDot: { width: 6, height: 6, borderRadius: 3 },
    sumFooterText: { fontSize: 12, fontWeight: '600' },
    bgIcon: { position: 'absolute', right: -10, top: -5, opacity: 1, transform: [{ scale: 1.2 }] },

    listHeader: { marginBottom: 12 },
    listTitle: { fontSize: 16, fontWeight: '700', color: '#F8FAFC' },

    rowCard: { borderRadius: 16, padding: 16, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)', overflow: 'hidden' },
    rowTop: { flexDirection: 'row', alignItems: 'center', marginBottom: 16, gap: 12 },
    rowCustomerIcon: { width: 40, height: 40, borderRadius: 12, backgroundColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center' },
    rowCustomerInitial: { fontSize: 16, fontWeight: '800', color: '#F8FAFC' },
    rowCustomerInfo: { flex: 1 },
    rowCustomerName: { fontSize: 15, fontWeight: '700', color: '#F8FAFC' },
    rowCustomerSub: { fontSize: 10, fontWeight: '700', color: '#94A3B8', marginTop: 2, letterSpacing: 0.5 },

    rowStats: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.2)', borderRadius: 12, padding: 12, gap: 16, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    statBox: { gap: 6 },
    statBoxLabel: { fontSize: 10, fontWeight: '700', color: '#94A3B8', letterSpacing: 0.5 },
    statBoxBadge: { paddingHorizontal: 12, paddingVertical: 6, borderRadius: 8, alignSelf: 'flex-start' },
    statBoxValue: { fontSize: 14, fontWeight: '800' },
    statPrice: { fontSize: 16, fontWeight: '800', color: '#34D399', marginTop: 4 },

    loadingOverlay: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(2, 6, 23, 0.7)', justifyContent: 'center', alignItems: 'center', zIndex: 10 },
    emptyState: { alignItems: 'center', paddingVertical: 40 },
    emptyText: { color: '#94A3B8', fontSize: 14, fontWeight: '500', marginTop: 12 }
});
