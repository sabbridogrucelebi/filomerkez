import React, { useState, useEffect, useContext, useRef, useCallback } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, Animated, Dimensions, TextInput, Modal, Easing, Linking, Alert, RefreshControl, ScrollView } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import { useFocusEffect } from '@react-navigation/native';
import DateTimePicker from '@react-native-community/datetimepicker';
import { BlurView } from 'expo-blur';
import { Image } from 'expo-image';
import dayjs from 'dayjs';
import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import * as WebBrowser from 'expo-web-browser';
import api from '../api/axios';
import { emoji } from '../emoji';
import { AuthContext } from '../context/AuthContext';

const { width: SCREEN_WIDTH } = Dimensions.get('window');
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

export default function PenaltiesScreen({ navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const insets = useSafeAreaInsets();
    
    const [penalties, setPenalties] = useState([]);
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    
    const [filter, setFilter] = useState('all');
    const [searchQuery, setSearchQuery] = useState('');
    const [startDate, setStartDate] = useState(null);
    const [endDate, setEndDate] = useState(null);
    
    const [showFilters, setShowFilters] = useState(false);
    const [showStartPicker, setShowStartPicker] = useState(false);
    const [showEndPicker, setShowEndPicker] = useState(false);

    // Animations
    const scrollY = useRef(new Animated.Value(0)).current;
    const blob1Anim = useRef(new Animated.Value(0)).current;
    const blob2Anim = useRef(new Animated.Value(0)).current;
    const flipAnims = useRef([...Array(100)].map(() => new Animated.Value(0))).current;

    useEffect(() => {
        const loop = Animated.loop(
            Animated.sequence([
                Animated.timing(blob1Anim, { toValue: 1, duration: 9000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(blob1Anim, { toValue: 0, duration: 9000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        );
        const loop2 = Animated.loop(
            Animated.sequence([
                Animated.timing(blob2Anim, { toValue: 1, duration: 11000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(blob2Anim, { toValue: 0, duration: 11000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        );
        loop.start(); loop2.start();
        return () => { loop.stop(); loop2.stop(); };
    }, []);

    const fetchData = async (hideLoader = false) => {
        if (!hideLoader) setLoading(true);
        try {
            const statsRes = await api.get('/v1/penalties/statistics');
            if (statsRes.data?.success) setStats(statsRes.data.data);

            const listRes = await api.get('/v1/penalties', {
                params: {
                    search: searchQuery,
                    date_from: startDate ? dayjs(startDate).format('YYYY-MM-DD') : '',
                    date_to: endDate ? dayjs(endDate).format('YYYY-MM-DD') : ''
                }
            });
            
            if (listRes.data?.success) {
                setPenalties(listRes.data.data || []);
            } else {
                setPenalties([]);
            }

            // Stagger animation
            flipAnims.forEach(a => a.setValue(0));
            Animated.stagger(60, flipAnims.map((anim) => 
                Animated.spring(anim, { toValue: 1, friction: 8, tension: 50, useNativeDriver: true })
            )).start();

        } catch (e) {
            console.error('Fetch penalties error:', e);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useFocusEffect(useCallback(() => { fetchData(true); }, []));

    const handleFilterSubmit = () => {
        fetchData();
        setShowFilters(false);
    };

    const handleClearFilters = () => {
        setSearchQuery('');
        setStartDate(null);
        setEndDate(null);
        setFilter('all');
    };

    const confirmDelete = (id) => {
        if (!hasPermission('penalties.delete')) {
            Alert.alert('Yetki Yok', 'Ceza kaydı silme yetkiniz bulunmuyor.');
            return;
        }
        Alert.alert('Silinecek', 'Bu ceza kaydını silmek istediğinize emin misiniz?', [
            { text: 'Vazgeç', style: 'cancel' },
            { text: 'Sil', style: 'destructive', onPress: async () => {
                try { 
                    await api.delete(`/v1/penalties/${id}`); 
                    fetchData(true); 
                } catch (e) {}
            }}
        ]);
    };

    const handleShare = async (path, prefix) => {
        try {
            const encodedPath = encodeURI(path);
            const url = api.defaults.baseURL.replace('/api', '') + '/storage/' + encodedPath;
            const ext = path.split('.').pop() || 'pdf';
            const filename = `${prefix}_Belgesi.${ext}`;
            const fileUri = FileSystem.cacheDirectory + filename;
            
            const downloadRes = await FileSystem.downloadAsync(url, fileUri);
            
            if (await Sharing.isAvailableAsync()) {
                await Sharing.shareAsync(downloadRes.uri, {
                    dialogTitle: 'Belgeyi Paylaş',
                    mimeType: ext.toLowerCase() === 'pdf' ? 'application/pdf' : `image/${ext}`,
                    UTI: ext.toLowerCase() === 'pdf' ? 'com.adobe.pdf' : 'public.image'
                });
            } else {
                Alert.alert('Hata', 'Cihazınızda paylaşım özelliği desteklenmiyor.');
            }
        } catch (e) {
            console.error('Share error:', e);
        }
    };

    const openDocument = async (path) => {
        try {
            const encodedPath = encodeURI(path);
            const url = api.defaults.baseURL.replace('/api', '') + '/storage/' + encodedPath;
            Linking.openURL(url);
        } catch (e) {
            console.error('Open doc error:', e);
        }
    };

    const filteredData = Array.isArray(penalties) ? penalties.filter(p => {
        if (filter === 'paid') return p.payment_status === 'paid';
        if (filter === 'unpaid') return p.payment_status === 'unpaid';
        return true;
    }) : [];

    const renderHeader = () => (
        <View style={{ marginBottom: 20 }}>
            <View style={s.actionsRow}>
                <TouchableOpacity style={[s.actionBtn, { flex: 2, backgroundColor: showFilters ? 'rgba(225,29,72,0.3)' : 'transparent', borderColor: 'rgba(255,255,255,0.1)' }]} onPress={() => setShowFilters(true)}>
                    <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                    <Icon name="filter-variant" size={20} color="#F8FAFC" />
                    <Text style={{ color: '#F8FAFC', fontWeight: '800', marginLeft: 8 }}>Cezaları Filtrele</Text>
                </TouchableOpacity>
                <TouchableOpacity style={[s.actionBtn, { borderColor: 'rgba(255,255,255,0.1)' }]} onPress={() => { setFilter('all'); fetchData(true); }}>
                    <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                    <Icon name="refresh" size={20} color="#38BDF8" />
                    <Text style={{ color: '#38BDF8', fontWeight: '800', marginLeft: 8 }}>Yenile</Text>
                </TouchableOpacity>
            </View>

            {/* Filter Chips */}
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={s.filterBar}>
                {[
                    { label: 'Tümü', value: 'all' },
                    { label: 'Ödenenler', value: 'paid' },
                    { label: 'Ödenmeyenler', value: 'unpaid' },
                ].map(chip => (
                    <TouchableOpacity 
                        key={chip.value} 
                        style={[s.filterChip, filter === chip.value && { backgroundColor: 'rgba(225,29,72,0.3)', borderColor: '#E11D48' }]}
                        onPress={() => setFilter(chip.value)}
                    >
                        <Text style={[s.filterChipText, filter === chip.value && { color: '#FDA4AF' }]}>{chip.label}</Text>
                    </TouchableOpacity>
                ))}
            </ScrollView>

            {stats && (
                <Animated.ScrollView 
                    horizontal 
                    showsHorizontalScrollIndicator={false} 
                    contentContainerStyle={s.kpiScrollContent}
                    decelerationRate="fast"
                    snapToInterval={SCREEN_WIDTH * 0.45 + 12}
                >
                    <View style={s.statCardContainer}>
                        <BlurView intensity={25} tint="dark" style={[s.statCard, { borderColor: 'rgba(236,72,153,0.3)' }]}>
                            <View style={s.kpiTopRow}>
                                <View style={[s.kpiIconWrap, { backgroundColor: 'rgba(236,72,153,0.15)' }]}><Icon name="file-document-multiple-outline" size={20} color="#F472B6" /></View>
                            </View>
                            <Text style={s.kpiValue} numberOfLines={1} adjustsFontSizeToFit>{stats.totalCount}</Text>
                            <Text style={s.kpiLabel}>Toplam Ceza</Text>
                        </BlurView>
                    </View>
                    <View style={s.statCardContainer}>
                        <BlurView intensity={25} tint="dark" style={[s.statCard, { borderColor: 'rgba(249,115,22,0.3)' }]}>
                            <View style={s.kpiTopRow}>
                                <View style={[s.kpiIconWrap, { backgroundColor: 'rgba(249,115,22,0.15)' }]}><Icon name="alert-octagon-outline" size={20} color="#FB923C" /></View>
                            </View>
                            <Text style={s.kpiValue} numberOfLines={1} adjustsFontSizeToFit>{stats.unpaidCount}</Text>
                            <Text style={s.kpiLabel}>Ödenmemiş</Text>
                        </BlurView>
                    </View>
                    <View style={s.statCardContainer}>
                        <BlurView intensity={25} tint="dark" style={[s.statCard, { borderColor: 'rgba(139,92,246,0.3)' }]}>
                            <View style={s.kpiTopRow}>
                                <View style={[s.kpiIconWrap, { backgroundColor: 'rgba(139,92,246,0.15)' }]}><Icon name="cash-multiple" size={20} color="#A78BFA" /></View>
                            </View>
                            <Text style={s.kpiValue} numberOfLines={1} adjustsFontSizeToFit>{fmtMoney(stats.totalAmount)}</Text>
                            <Text style={s.kpiLabel}>Toplam Tutar</Text>
                        </BlurView>
                    </View>
                    <View style={s.statCardContainer}>
                        <BlurView intensity={25} tint="dark" style={[s.statCard, { borderColor: 'rgba(16,185,129,0.3)' }]}>
                            <View style={s.kpiTopRow}>
                                <View style={[s.kpiIconWrap, { backgroundColor: 'rgba(16,185,129,0.15)' }]}><Icon name="cash-check" size={20} color="#34D399" /></View>
                            </View>
                            <Text style={s.kpiValue} numberOfLines={1} adjustsFontSizeToFit>{fmtMoney(stats.collectableAmount)}</Text>
                            <Text style={s.kpiLabel}>Tahsil Edilebilir</Text>
                        </BlurView>
                    </View>
                </Animated.ScrollView>
            )}
        </View>
    );

    const renderCard = ({ item, index }) => {
        const animIndex = index % 100;
        const flipAnim = flipAnims[animIndex] || new Animated.Value(1);
        
        const isPaid = item.payment_status === 'paid';
        const discountDeadline = item.penalty_date ? new Date(new Date(item.penalty_date).getTime() + 30 * 24 * 60 * 60 * 1000) : new Date();
        const now = new Date();
        const isDiscountExpired = now > discountDeadline;
        
        let paymentStatusText = isPaid ? 'İndirimsiz Ödendi' : 'Ödenmedi';
        let statusColor = isPaid ? '#F43F5E' : '#EF4444'; 

        if (isPaid && item.paid_amount && item.discounted_amount && parseFloat(item.paid_amount) == parseFloat(item.discounted_amount)) {
            paymentStatusText = '%25 İndirimli Ödendi';
            statusColor = '#10B981';
        }

        const animatedStyle = {
            opacity: flipAnim,
            transform: [
                { perspective: 1000 },
                { rotateX: flipAnim.interpolate({ inputRange: [0, 1], outputRange: ['-90deg', '0deg'] }) },
                { translateY: flipAnim.interpolate({ inputRange: [0, 1], outputRange: [50, 0] }) },
                { scale: flipAnim.interpolate({ inputRange: [0, 0.6, 1], outputRange: [0.8, 1.05, 1] }) }
            ]
        };

        return (
            <Animated.View style={[s.cardWrapper, animatedStyle]}>
                <BlurView intensity={25} tint="dark" style={[s.card, { borderLeftWidth: 4, borderLeftColor: statusColor }]}>
                    <View style={s.cardHeader}>
                        {/* Top Row */}
                        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                            <View style={s.plateBadge}>
                                <Text style={s.plateText}>{item.vehicle?.plate || 'Plaka Yok'}</Text>
                            </View>
                            <View style={{ alignItems: 'flex-end' }}>
                                <Text style={[s.amountText, { color: statusColor }]}>
                                    {fmtMoney(isPaid && item.paid_amount ? item.paid_amount : item.penalty_amount)}
                                </Text>
                                {!isPaid && !isDiscountExpired && (
                                    <Text style={s.discountText}>İndirimli: {fmtMoney(item.discounted_amount)}</Text>
                                )}
                            </View>
                        </View>
                        
                        {/* Driver & Number */}
                        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end' }}>
                            <View style={s.cardInfo}>
                                <Text style={s.cardTitle} numberOfLines={1}>{toTitleCase(item.driver_name) || 'Şoför Belirtilmemiş'}</Text>
                                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 4 }}>
                                    <Icon name="barcode" size={14} color="#94A3B8" />
                                    <Text style={s.cardDesc}>No: {item.penalty_no?.toUpperCase() || '-'}</Text>
                                </View>
                            </View>
                            <View style={{ flexDirection: 'row', gap: 12, paddingBottom: 4 }}>
                                {hasPermission('penalties.edit') && (
                                    <TouchableOpacity onPress={() => navigation.navigate('PenaltyForm', { penaltyId: item.id, penalty: item })}>
                                        <Icon name="pencil" size={22} color="#60A5FA" />
                                    </TouchableOpacity>
                                )}
                                {hasPermission('penalties.delete') && (
                                    <TouchableOpacity onPress={() => confirmDelete(item.id)}>
                                        <Icon name="trash-can" size={22} color="#F87171" />
                                    </TouchableOpacity>
                                )}
                            </View>
                        </View>
                    </View>

                    <View style={s.cardGrid}>
                        <View style={s.gridRow}>
                            <View style={s.gridCol}>
                                <Text style={s.gridLabel}>TARİH / SAAT</Text>
                                <Text style={s.gridValue}>{item.penalty_date ? dayjs(item.penalty_date).format('DD.MM.YYYY') : '-'}{item.penalty_time ? ` ${item.penalty_time.substring(0,5)}` : ''}</Text>
                            </View>
                            <View style={s.gridDivider} />
                            <View style={[s.gridCol, { flex: 1.5 }]}>
                                <Text style={[s.gridLabel, { color: '#60A5FA' }]}>MADDE / YER</Text>
                                <Text style={[s.gridValue, { color: '#93C5FD' }]}>{item.penalty_article?.toUpperCase() || '-'}</Text>
                                <Text style={s.gridSubValue} numberOfLines={1}>{toTitleCase(item.penalty_location) || '-'}</Text>
                            </View>
                        </View>
                        <View style={s.gridHDivider} />
                        <View style={s.gridRow}>
                            <View style={s.gridCol}>
                                <Text style={[s.gridLabel, { color: statusColor }]}>ÖDEME DURUMU</Text>
                                <Text style={[s.gridValue, { color: statusColor }]}>{paymentStatusText}</Text>
                                {isPaid && item.payment_date && (
                                    <Text style={[s.gridSubValue, { color: '#34D399' }]}>{dayjs(item.payment_date).format('DD.MM.YYYY')}</Text>
                                )}
                            </View>
                            <View style={s.gridDivider} />
                            <View style={[s.gridCol, { flex: 1.5, justifyContent: 'center' }]}>
                                <View style={s.docsRow}>
                                    {item.traffic_penalty_document && (
                                        <View style={s.docGroup}>
                                            <TouchableOpacity style={s.docBtn} onPress={() => openDocument(item.traffic_penalty_document)}>
                                                <Icon name="file-document-outline" size={14} color="#60A5FA" />
                                                <Text style={s.docText}>Ceza</Text>
                                            </TouchableOpacity>
                                            <TouchableOpacity style={s.shareBtn} onPress={() => handleShare(item.traffic_penalty_document, 'Ceza')}>
                                                <Icon name="share-variant" size={14} color="#94A3B8" />
                                            </TouchableOpacity>
                                        </View>
                                    )}
                                    {item.payment_receipt && (
                                        <View style={[s.docGroup, { marginTop: 6 }]}>
                                            <TouchableOpacity style={s.docBtn} onPress={() => openDocument(item.payment_receipt)}>
                                                <Icon name="receipt" size={14} color="#34D399" />
                                                <Text style={s.docText}>Dekont</Text>
                                            </TouchableOpacity>
                                            <TouchableOpacity style={s.shareBtn} onPress={() => handleShare(item.payment_receipt, 'Dekont')}>
                                                <Icon name="share-variant" size={14} color="#94A3B8" />
                                            </TouchableOpacity>
                                        </View>
                                    )}
                                    {!item.traffic_penalty_document && !item.payment_receipt && (
                                        <Text style={[s.gridSubValue, { color: '#64748B', fontStyle: 'italic' }]}>Belge yüklenmemiş</Text>
                                    )}
                                </View>
                            </View>
                        </View>
                    </View>

                    {item.notes && (
                        <View style={s.notesBox}>
                            <Icon name="information-outline" size={14} color="#FCD34D" />
                            <Text style={s.notesText} numberOfLines={2}>{toTitleCase(item.notes)}</Text>
                        </View>
                    )}
                </BlurView>
            </Animated.View>
        );
    };

    return (
        <View style={s.container}>
            {/* 3D Animated Background */}
            <Animated.View style={[StyleSheet.absoluteFill, { transform: [{ translateY: scrollY.interpolate({ inputRange: [-100, 0, 500], outputRange: [-20, 0, 100], extrapolate: 'clamp' }) }] }]}>
                <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
                <Animated.View style={[s.bgBlob1, { transform: [{ translateY: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[0, 60] }) }, { scale: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[1, 1.25] }) }] }]} />
                <Animated.View style={[s.bgBlob2, { transform: [{ translateX: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[0, -60] }) }, { scale: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[1, 1.3] }) }] }]} />
            </Animated.View>

            <SafeAreaView style={{ flex: 1 }} edges={['top', 'left', 'right']}>
                <View style={s.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={s.headerIconBtn}>
                        <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                        <Icon name="chevron-left" size={26} color="#FFF" />
                    </TouchableOpacity>
                    <View style={{ flex: 1, paddingLeft: 16 }}>
                        <Text style={s.headerTitle}>Trafik Cezaları</Text>
                    </View>
                </View>

                {/* Filter Modal Overlay */}
                <Modal visible={showFilters} transparent animationType="slide">
                    <BlurView intensity={40} tint="dark" style={s.modalOverlay}>
                        <TouchableOpacity style={StyleSheet.absoluteFill} onPress={() => setShowFilters(false)} />
                        <BlurView intensity={50} tint="dark" style={[s.bottomSheet, { paddingBottom: Math.max(insets.bottom, 20) }]}>
                            <View style={s.sheetHandle} />
                            <Text style={s.sheetTitle}>Cezaları Filtrele</Text>
                            
                            <View style={s.fieldWrap}>
                                <Icon name="magnify" size={20} color="#94A3B8" style={{ marginRight: 10 }} />
                                <TextInput style={s.fieldInput} placeholderTextColor="#64748B" placeholder="Ceza no, şoför, plaka..." value={searchQuery} onChangeText={setSearchQuery} />
                            </View>

                            <View style={{ flexDirection: 'row', gap: 12, marginTop: 12 }}>
                                <TouchableOpacity style={[s.fieldWrap, { flex: 1 }]} onPress={() => setShowStartPicker(true)}>
                                    <Icon name="calendar-start" size={20} color="#94A3B8" style={{ marginRight: 10 }} />
                                    <Text style={[s.fieldInput, !startDate && { color: '#64748B' }, { paddingTop: 16 }]}>{startDate ? dayjs(startDate).format('DD.MM.YY') : 'Başlangıç'}</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={[s.fieldWrap, { flex: 1 }]} onPress={() => setShowEndPicker(true)}>
                                    <Icon name="calendar-end" size={20} color="#94A3B8" style={{ marginRight: 10 }} />
                                    <Text style={[s.fieldInput, !endDate && { color: '#64748B' }, { paddingTop: 16 }]}>{endDate ? dayjs(endDate).format('DD.MM.YY') : 'Bitiş'}</Text>
                                </TouchableOpacity>
                            </View>

                            <View style={s.formActions}>
                                <TouchableOpacity style={s.cancelBtn} onPress={handleClearFilters}>
                                    <Text style={s.cancelBtnText}>Temizle</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={s.saveBtn} onPress={handleFilterSubmit}>
                                    <LinearGradient colors={['#E11D48', '#BE185D']} style={StyleSheet.absoluteFillObject} />
                                    <Text style={s.saveBtnText}>Uygula</Text>
                                </TouchableOpacity>
                            </View>
                        </BlurView>
                    </BlurView>
                </Modal>

                {(showStartPicker || showEndPicker) && (
                    <DateTimePicker 
                        value={showStartPicker ? (startDate || new Date()) : (endDate || new Date())} 
                        mode="date" display="default" themeVariant="dark"
                        onChange={(e, selected) => {
                            setShowStartPicker(false); setShowEndPicker(false);
                            if (selected) {
                                if (showStartPicker) setStartDate(selected);
                                else setEndDate(selected);
                            }
                        }} 
                    />
                )}

                {loading ? (
                    <View style={s.loader}><ActivityIndicator size="large" color="#E11D48" /></View>
                ) : (
                    <Animated.FlatList
                        onScroll={Animated.event([{ nativeEvent: { contentOffset: { y: scrollY } } }], { useNativeDriver: true })}
                        scrollEventThrottle={16}
                        data={filteredData}
                        keyExtractor={item => item.id.toString()}
                        renderItem={renderCard}
                        ListHeaderComponent={renderHeader}
                        contentContainerStyle={{ paddingHorizontal: 20, paddingBottom: 100, paddingTop: 10 }}
                        showsVerticalScrollIndicator={false}
                        initialNumToRender={6}
                        maxToRenderPerBatch={6}
                        windowSize={5}
                        removeClippedSubviews={true}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => fetchData(true)} tintColor="#E11D48" />}
                        ListEmptyComponent={
                            <View style={s.empty}>
                                <Image source={emoji('Travel and places/Police Car Light')} style={{width: 64, height: 64, opacity: 0.8}} resizeMode="contain" />
                                <Text style={s.emptyText}>Kriterlere uygun trafik cezası bulunamadı.</Text>
                            </View>
                        }
                    />
                )}
            </SafeAreaView>

            {hasPermission('penalties.create') && (
                <TouchableOpacity style={[s.fab, { bottom: Math.max(insets.bottom, 24) }]} onPress={() => navigation.navigate('PenaltyForm')}>
                    <LinearGradient colors={['#E11D48', '#9F1239']} style={s.fabGradient} start={{x:0, y:0}} end={{x:1, y:1}}>
                        <Icon name="ticket-confirmation-outline" size={24} color="#FFF" />
                        <Icon name="plus" size={14} color="#FFF" style={{ position: 'absolute', top: 12, right: 12 }} />
                    </LinearGradient>
                </TouchableOpacity>
            )}
        </View>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    bgBlob1: { position: 'absolute', top: -100, left: -50, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(225, 29, 72, 0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, right: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(59, 130, 246, 0.12)', filter: 'blur(40px)' },
    
    header: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 20, paddingTop: 10, paddingBottom: 16 },
    headerIconBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    headerTitle: { fontSize: 26, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.5, textShadowColor: 'rgba(255,255,255,0.3)', textShadowOffset: { width: 0, height: 0 }, textShadowRadius: 10 },
    
    actionsRow: { flexDirection: 'row', gap: 10, marginBottom: 16 },
    actionBtn: { flex: 1, height: 44, borderRadius: 14, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, flexDirection: 'row' },

    filterBar: { flexDirection: 'row', gap: 10, paddingBottom: 16 },
    filterChip: { paddingHorizontal: 16, height: 36, borderRadius: 18, backgroundColor: 'rgba(255,255,255,0.05)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center' },
    filterChipText: { fontSize: 13, fontWeight: '800', color: '#94A3B8' },

    kpiScrollContent: { paddingBottom: 16, gap: 12 },
    statCardContainer: { width: SCREEN_WIDTH * 0.45 },
    statCard: { padding: 16, borderRadius: 20, overflow: 'hidden', borderWidth: 1 },
    kpiTopRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 12 },
    kpiIconWrap: { width: 34, height: 34, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
    kpiValue: { fontSize: 18, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.5, marginBottom: 4 },
    kpiLabel: { fontSize: 12, color: '#94A3B8', fontWeight: '600' },

    loader: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingTop: 100 },
    empty: { alignItems: 'center', marginTop: 80 },
    emptyText: { fontSize: 15, color: '#94A3B8', fontWeight: '600', marginTop: 12 },

    cardWrapper: { marginBottom: 16 },
    card: { borderRadius: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    cardHeader: { padding: 20, paddingBottom: 16 },
    plateBadge: { backgroundColor: 'rgba(255,255,255,0.1)', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    plateText: { fontSize: 15, fontWeight: '900', color: '#F8FAFC', letterSpacing: 1 },
    cardInfo: { flex: 1, paddingRight: 16 },
    cardTitle: { fontSize: 16, fontWeight: '800', color: '#F8FAFC', letterSpacing: -0.2, lineHeight: 22 },
    cardDesc: { fontSize: 13, color: '#94A3B8', fontWeight: '600' },
    amountText: { fontSize: 20, fontWeight: '900' },
    discountText: { fontSize: 10, color: '#FCD34D', fontWeight: '800', marginTop: 4 },

    cardGrid: { backgroundColor: 'rgba(0,0,0,0.2)', padding: 16, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)' },
    gridRow: { flexDirection: 'row' },
    gridCol: { flex: 1 },
    gridDivider: { width: 1, backgroundColor: 'rgba(255,255,255,0.1)', marginHorizontal: 12 },
    gridHDivider: { height: 1, backgroundColor: 'rgba(255,255,255,0.1)', marginVertical: 12 },
    gridLabel: { fontSize: 10, fontWeight: '800', color: '#64748B', letterSpacing: 0.5, marginBottom: 4 },
    gridValue: { fontSize: 13, fontWeight: '800', color: '#CBD5E1' },
    gridSubValue: { fontSize: 11, fontWeight: '600', color: '#94A3B8', marginTop: 2 },

    docsRow: { gap: 6 },
    docGroup: { flexDirection: 'row', alignItems: 'center' },
    docBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(255,255,255,0.05)', paddingHorizontal: 8, paddingVertical: 6, borderTopLeftRadius: 8, borderBottomLeftRadius: 8, gap: 6, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', borderRightWidth: 0 },
    docText: { fontSize: 11, fontWeight: '700', color: '#F8FAFC' },
    shareBtn: { backgroundColor: 'rgba(255,255,255,0.1)', paddingHorizontal: 10, paddingVertical: 6, justifyContent: 'center', borderTopRightRadius: 8, borderBottomRightRadius: 8, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },

    notesBox: { flexDirection: 'row', alignItems: 'flex-start', backgroundColor: 'rgba(0,0,0,0.3)', padding: 12, gap: 8, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)' },
    notesText: { flex: 1, fontSize: 12, color: '#94A3B8', fontWeight: '500', fontStyle: 'italic', lineHeight: 18 },

    modalOverlay: { flex: 1, justifyContent: 'flex-end' },
    bottomSheet: { borderTopLeftRadius: 32, borderTopRightRadius: 32, padding: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    sheetHandle: { width: 40, height: 4, borderRadius: 2, backgroundColor: 'rgba(255,255,255,0.2)', alignSelf: 'center', marginBottom: 20 },
    sheetTitle: { fontSize: 22, fontWeight: '900', color: '#F8FAFC', marginBottom: 24 },
    
    fieldWrap: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.2)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', borderRadius: 14, paddingHorizontal: 16, height: 54 },
    fieldInput: { flex: 1, fontSize: 15, color: '#F8FAFC', fontWeight: '600', height: '100%' },
    
    formActions: { flexDirection: 'row', marginTop: 24 },
    cancelBtn: { flex: 1, paddingVertical: 16, borderRadius: 16, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', marginRight: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    cancelBtnText: { color: '#94A3B8', fontSize: 15, fontWeight: '800' },
    saveBtn: { flex: 2, borderRadius: 16, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
    saveBtnText: { color: '#FFF', fontSize: 15, fontWeight: '900' },

    fab: { position: 'absolute', right: 20, borderRadius: 28, shadowColor: '#E11D48', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.5, shadowRadius: 10, elevation: 8 },
    fabGradient: { width: 56, height: 56, borderRadius: 28, alignItems: 'center', justifyContent: 'center' },
});
