import React, { useState, useEffect, useRef } from 'react';
import { View, Text, StyleSheet, ScrollView, ActivityIndicator, TouchableOpacity, RefreshControl, Dimensions, Animated, Easing } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import Svg, { Circle, G } from 'react-native-svg';
import api from '../api/axios';
import { Header } from '../components';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';

const { width: W } = Dimensions.get('window');
const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(v || 0);
const fmtShort = (v) => {
    if (v >= 1000000) return (v / 1000000).toFixed(1) + 'M₺';
    if (v >= 1000) return (v / 1000).toFixed(1) + 'k₺';
    return v + '₺';
};

export default function ReportsScreen({ navigation }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState(null);

    // Animasyonlar
    const fadeAnim = useRef(new Animated.Value(0)).current;
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

    const fetchData = async (isRefresh = false) => {
        try {
            if (isRefresh) setRefreshing(true); else setLoading(true);
            setError(null);
            const response = await api.get('/v1/reports/summary');
            if (response.data.success) {
                setData(response.data.data);
                Animated.timing(fadeAnim, { toValue: 1, duration: 600, useNativeDriver: true }).start();
            } else {
                setError(response.data.message || 'Veri alınamadı.');
            }
        } catch (err) {
            setError(err.response?.status === 403 ? 'Yetkiniz yok.' : 'Bağlantı hatası.');
        } finally {
            setLoading(false); setRefreshing(false);
        }
    };

    useEffect(() => { fetchData(); }, []);

    if (loading && !refreshing) {
        return (
            <SafeAreaView style={st.container} edges={['top']}>
                <Header title="Raporlar" subtitle="Finansal Analiz & Özet" onBack={() => navigation.goBack()} />
                <View style={st.center}>
                    <ActivityIndicator size="large" color="#3B82F6" />
                </View>
            </SafeAreaView>
        );
    }

    if (error && !data) {
        return (
            <SafeAreaView style={st.container} edges={['top']}>
                <Header title="Raporlar" subtitle="Finansal Analiz & Özet" onBack={() => navigation.goBack()} />
                <View style={st.center}>
                    <Icon name="alert-circle-outline" size={48} color="#EF4444" />
                    <Text style={st.errorTxt}>{error}</Text>
                    <TouchableOpacity onPress={() => fetchData()} style={st.retryBtn}>
                        <Text style={st.retryTxt}>Tekrar Dene</Text>
                    </TouchableOpacity>
                </View>
            </SafeAreaView>
        );
    }

    const { summary, trend } = data || {};
    
    // Doughnut Chart Değerleri
    const f = summary?.breakdown?.fuel || 0;
    const p = summary?.breakdown?.payroll || 0;
    const m = summary?.breakdown?.maintenance || 0;
    const pn = summary?.breakdown?.penalty || 0;
    const totalExp = f + p + m + pn;
    const safeTotal = totalExp > 0 ? totalExp : 1;

    const rad = 50;
    const strokeW = 18;
    const circ = 2 * Math.PI * rad;

    const fp = f / safeTotal;
    const pp = p / safeTotal;
    const mp = m / safeTotal;
    const pnp = pn / safeTotal;

    const fStroke = circ * fp;
    const pStroke = circ * pp;
    const mStroke = circ * mp;
    const pnStroke = circ * pnp;

    const pOffset = circ - fStroke;
    const mOffset = pOffset - pStroke;
    const pnOffset = mOffset - mStroke;

    // Bar Chart Max Değer Bulma
    const maxVal = trend ? Math.max(...trend.map(t => Math.max(t.income, t.expense))) : 1;

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
                        <Text style={st.headerTitle}>Raporlar</Text>
                        <Text style={st.headerSubtitle}>Finansal Analiz & Özet</Text>
                    </View>
                    <View style={{ width: 44 }} />
                </View>
                
                <ScrollView 
                    contentContainerStyle={st.scrollContent} 
                    showsVerticalScrollIndicator={false}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => fetchData(true)} tintColor="#3B82F6" />}
                >
                    <Animated.View style={{ opacity: fadeAnim, gap: 16 }}>
                        
                        {/* ÖZET KARTLARI */}
                        <View style={st.cardsRow}>
                            <BlurView intensity={30} tint="dark" style={st.mainCard}>
                                <LinearGradient colors={['rgba(59,130,246,0.3)', 'rgba(37,99,235,0.1)']} style={StyleSheet.absoluteFillObject} />
                                <Icon name="wallet" size={24} color="#60A5FA" style={st.cardIcon} />
                                <Text style={st.cardLbl}>Toplam Gelir</Text>
                                <Text style={st.cardVal} adjustsFontSizeToFit numberOfLines={1}>{fmtMoney(summary?.income)}</Text>
                            </BlurView>
                            
                            <BlurView intensity={30} tint="dark" style={st.mainCard}>
                                <Icon name="receipt" size={24} color="#F87171" style={st.cardIcon} />
                                <Text style={[st.cardLbl, { color: '#F87171' }]}>Toplam Gider</Text>
                                <Text style={[st.cardVal, { color: '#F8FAFC' }]} adjustsFontSizeToFit numberOfLines={1}>{fmtMoney(summary?.expense)}</Text>
                            </BlurView>
                        </View>

                        <BlurView intensity={40} tint="dark" style={st.fullCard}>
                            <LinearGradient colors={summary?.profit >= 0 ? ['rgba(16,185,129,0.3)', 'transparent'] : ['rgba(239,68,68,0.3)', 'transparent']} style={StyleSheet.absoluteFillObject} />
                            <Icon name={summary?.profit >= 0 ? 'trending-up' : 'trending-down'} size={28} color="rgba(255,255,255,0.2)" style={{position: 'absolute', right: 20, top: 20}} />
                            <Text style={[st.cardLbl, { color: 'rgba(255,255,255,0.7)' }]}>Net Kar / Zarar</Text>
                            <Text style={[st.cardVal, { fontSize: 32 }]} adjustsFontSizeToFit numberOfLines={1}>{fmtMoney(summary?.profit)}</Text>
                        </BlurView>

                        {/* TREND GRAFİĞİ (CUSTOM BAR CHART) */}
                        {trend && trend.length > 0 && (
                            <BlurView intensity={25} tint="dark" style={st.chartBox}>
                                <Text style={st.chartTitle}>Son 6 Aylık Finansal Trend</Text>
                                <View style={st.barArea}>
                                    {trend.map((t, i) => {
                                        const incH = (t.income / maxVal) * 100 || 0;
                                        const expH = (t.expense / maxVal) * 100 || 0;
                                        return (
                                            <View key={i} style={st.barGroup}>
                                                <View style={st.bars}>
                                                    <View style={st.barTrack}>
                                                        <View style={[st.barFill, { height: `${incH}%`, backgroundColor: '#38BDF8' }]} />
                                                    </View>
                                                    <View style={st.barTrack}>
                                                        <View style={[st.barFill, { height: `${expH}%`, backgroundColor: '#F87171' }]} />
                                                    </View>
                                                </View>
                                                <Text style={st.barLabel}>{t.month.split(' ')[0]}</Text>
                                            </View>
                                        );
                                    })}
                                </View>
                                <View style={st.legendRow}>
                                    <View style={st.legendItem}><View style={[st.legendDot, {backgroundColor:'#38BDF8'}]}/><Text style={st.legendTxt}>Gelir</Text></View>
                                    <View style={st.legendItem}><View style={[st.legendDot, {backgroundColor:'#F87171'}]}/><Text style={st.legendTxt}>Gider</Text></View>
                                </View>
                            </BlurView>
                        )}

                        {/* GİDER DAĞILIMI DOUGHNUT */}
                        <BlurView intensity={25} tint="dark" style={st.chartBox}>
                            <Text style={st.chartTitle}>Gider Dağılımı</Text>
                            {totalExp > 0 ? (
                                <View style={st.doughnutArea}>
                                    <View style={st.svgWrap}>
                                        <Svg height="140" width="140" viewBox="0 0 140 140">
                                            <G rotation="-90" origin="70, 70">
                                                {/* Yakıt */}
                                                {f > 0 && <Circle cx="70" cy="70" r={rad} stroke="#F59E0B" strokeWidth={strokeW} fill="transparent" strokeDasharray={circ} strokeDashoffset={0} />}
                                                {/* Maaş */}
                                                {p > 0 && <Circle cx="70" cy="70" r={rad} stroke="#3B82F6" strokeWidth={strokeW} fill="transparent" strokeDasharray={circ} strokeDashoffset={pOffset} />}
                                                {/* Bakım */}
                                                {m > 0 && <Circle cx="70" cy="70" r={rad} stroke="#A855F7" strokeWidth={strokeW} fill="transparent" strokeDasharray={circ} strokeDashoffset={mOffset} />}
                                                {/* Ceza */}
                                                {pn > 0 && <Circle cx="70" cy="70" r={rad} stroke="#EF4444" strokeWidth={strokeW} fill="transparent" strokeDasharray={circ} strokeDashoffset={pnOffset} />}
                                            </G>
                                        </Svg>
                                    </View>
                                    <View style={st.pieLegendWrap}>
                                        <View style={st.pieLegend}><View style={[st.legendDot, {backgroundColor:'#F59E0B'}]}/><Text style={st.pieTxt}>Yakıt</Text><Text style={st.pieVal}>{fmtShort(f)}</Text></View>
                                        <View style={st.pieLegend}><View style={[st.legendDot, {backgroundColor:'#3B82F6'}]}/><Text style={st.pieTxt}>Maaş</Text><Text style={st.pieVal}>{fmtShort(p)}</Text></View>
                                        <View style={st.pieLegend}><View style={[st.legendDot, {backgroundColor:'#A855F7'}]}/><Text style={st.pieTxt}>Bakım</Text><Text style={st.pieVal}>{fmtShort(m)}</Text></View>
                                        <View style={st.pieLegend}><View style={[st.legendDot, {backgroundColor:'#EF4444'}]}/><Text style={st.pieTxt}>Ceza</Text><Text style={st.pieVal}>{fmtShort(pn)}</Text></View>
                                    </View>
                                </View>
                            ) : (
                                <View style={st.emptyPie}><Text style={st.emptyTxt}>Kayıtlı gider bulunmuyor</Text></View>
                            )}
                        </BlurView>

                    </Animated.View>
                </ScrollView>
            </SafeAreaView>
        </View>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    bgBlob1: { position: 'absolute', top: -50, left: -50, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(56,189,248,0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, right: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(167,139,250,0.15)', filter: 'blur(40px)' },
    
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingBottom: 16, paddingTop: 8, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    backBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    headerTitle: { fontSize: 20, fontWeight: '900', color: '#F8FAFC' },
    headerSubtitle: { fontSize: 13, fontWeight: '600', color: '#38BDF8', marginTop: 2 },
    
    center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 20 },
    errorTxt: { color: '#94A3B8', marginTop: 10, textAlign: 'center' },
    retryBtn: { marginTop: 15, paddingHorizontal: 20, paddingVertical: 10, backgroundColor: '#38BDF8', borderRadius: 8 },
    retryTxt: { color: '#fff', fontWeight: 'bold' },
    scrollContent: { padding: 16, paddingBottom: 100 },
    
    cardsRow: { flexDirection: 'row', gap: 12 },
    mainCard: { flex: 1, borderRadius: 16, padding: 16, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    fullCard: { borderRadius: 16, padding: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    cardIcon: { marginBottom: 12 },
    cardLbl: { fontSize: 11, fontWeight: '800', color: '#94A3B8', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 4 },
    cardVal: { fontSize: 22, fontWeight: '900', color: '#F8FAFC' },

    chartBox: { borderRadius: 16, padding: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    chartTitle: { fontSize: 15, fontWeight: '800', color: '#F8FAFC', marginBottom: 20 },
    
    barArea: { flexDirection: 'row', justifyContent: 'space-between', height: 160, alignItems: 'flex-end', borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.1)', paddingBottom: 8 },
    barGroup: { alignItems: 'center', width: (W - 80) / 6 },
    bars: { flexDirection: 'row', gap: 4, height: 130, alignItems: 'flex-end' },
    barTrack: { width: 8, height: '100%', backgroundColor: 'rgba(255,255,255,0.05)', borderRadius: 4, justifyContent: 'flex-end', overflow: 'hidden' },
    barFill: { width: '100%', borderRadius: 4 },
    barLabel: { fontSize: 10, fontWeight: '700', color: '#94A3B8', marginTop: 8 },
    
    legendRow: { flexDirection: 'row', justifyContent: 'center', gap: 16, marginTop: 16 },
    legendItem: { flexDirection: 'row', alignItems: 'center', gap: 6 },
    legendDot: { width: 10, height: 10, borderRadius: 5 },
    legendTxt: { fontSize: 12, fontWeight: '600', color: '#E2E8F0' },

    doughnutArea: { flexDirection: 'row', alignItems: 'center' },
    svgWrap: { width: 140, height: 140 },
    pieLegendWrap: { flex: 1, paddingLeft: 20, gap: 12 },
    pieLegend: { flexDirection: 'row', alignItems: 'center' },
    pieTxt: { flex: 1, fontSize: 13, fontWeight: '600', color: '#94A3B8', marginLeft: 8 },
    pieVal: { fontSize: 13, fontWeight: '800', color: '#F8FAFC' },
    
    emptyPie: { height: 100, justifyContent: 'center', alignItems: 'center' },
    emptyTxt: { color: '#94A3B8', fontSize: 13, fontWeight: '500' }
});
