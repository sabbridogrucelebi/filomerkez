import React, { useState, useEffect, useContext, useRef, useCallback } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, Modal, TextInput, Alert, Animated, PanResponder, KeyboardAvoidingView, Platform, Dimensions, ScrollView, Linking, Easing } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import { useFocusEffect } from '@react-navigation/native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { BlurView } from 'expo-blur';
import { Image } from 'expo-image';
import dayjs from 'dayjs';
import api from '../api/axios';
import { emoji } from '../emoji';
import { AuthContext } from '../context/AuthContext';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');

// Custom Swipeable Row
const SwipeableRow = ({ children, onEdit, onDelete }) => {
    const pan = useRef(new Animated.ValueXY()).current;
    const panResponder = useRef(
        PanResponder.create({
            onMoveShouldSetPanResponder: (e, gestureState) => Math.abs(gestureState.dx) > 15 && Math.abs(gestureState.dx) > Math.abs(gestureState.dy),
            onPanResponderMove: (e, gestureState) => {
                if (gestureState.dx < 0 && gestureState.dx > -160) {
                    pan.setValue({ x: gestureState.dx, y: 0 });
                }
            },
            onPanResponderRelease: (e, gestureState) => {
                if (gestureState.dx < -60) {
                    Animated.spring(pan, { toValue: { x: -140, y: 0 }, useNativeDriver: true, tension: 40, friction: 5 }).start();
                } else {
                    Animated.spring(pan, { toValue: { x: 0, y: 0 }, useNativeDriver: true, tension: 40, friction: 5 }).start();
                }
            }
        })
    ).current;

    const close = () => Animated.spring(pan, { toValue: { x: 0, y: 0 }, useNativeDriver: true }).start();

    return (
        <View style={s.swipeContainer}>
            <View style={s.actionButtons}>
                <TouchableOpacity style={[s.actionBtn, { backgroundColor: 'rgba(59,130,246,0.15)', borderColor: 'rgba(59,130,246,0.3)', borderTopLeftRadius: 20, borderBottomLeftRadius: 20 }]} onPress={() => { close(); onEdit(); }}>
                    <Icon name="pencil" size={24} color="#60A5FA" />
                </TouchableOpacity>
                <TouchableOpacity style={[s.actionBtn, { backgroundColor: 'rgba(239,68,68,0.15)', borderColor: 'rgba(239,68,68,0.3)', borderTopRightRadius: 20, borderBottomRightRadius: 20 }]} onPress={() => { close(); onDelete(); }}>
                    <Icon name="delete" size={24} color="#F87171" />
                </TouchableOpacity>
            </View>
            <Animated.View style={[s.swipeContent, { transform: [{ translateX: pan.x }] }]} {...panResponder.panHandlers}>
                {children}
            </Animated.View>
        </View>
    );
};

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

export default function PersonnelScreen({ navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const insets = useSafeAreaInsets();
    
    const [personnel, setPersonnel] = useState([]);
    const [loading, setLoading] = useState(true);
    const [vehicles, setVehicles] = useState([]);
    
    // KPIs
    const [kpi, setKpi] = useState({ total: 0, active: 0, inactive: 0 });
    
    // Bottom Sheets
    const [showSearch, setShowSearch] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [filterStatus, setFilterStatus] = useState('all');

    const [showForm, setShowForm] = useState(false);
    const [saving, setSaving] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const emptyForm = { full_name: '', tc_no: '', phone: '', email: '', license_class: '', src_type: '', start_shift: 'morning', vehicle_id: '' };
    const [formData, setFormData] = useState(emptyForm);

    const [birthdayPersonnel, setBirthdayPersonnel] = useState([]);
    const [showBirthdayModal, setShowBirthdayModal] = useState(false);
    const [birthdayAlertShown, setBirthdayAlertShown] = useState(false);

    // Animations
    const scrollY = useRef(new Animated.Value(0)).current;
    const blob1Anim = useRef(new Animated.Value(0)).current;
    const blob2Anim = useRef(new Animated.Value(0)).current;
    const flipAnims = useRef([...Array(60)].map(() => new Animated.Value(0))).current;

    useEffect(() => {
        const loop = Animated.loop(
            Animated.sequence([
                Animated.timing(blob1Anim, { toValue: 1, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(blob1Anim, { toValue: 0, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        );
        const loop2 = Animated.loop(
            Animated.sequence([
                Animated.timing(blob2Anim, { toValue: 1, duration: 10000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(blob2Anim, { toValue: 0, duration: 10000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        );
        loop.start();
        loop2.start();
        return () => { loop.stop(); loop2.stop(); };
    }, []);

    const fetchData = async (hideLoader = false) => {
        if (!hideLoader) setLoading(true);
        try {
            const [resPersonnel, resOptions] = await Promise.all([
                api.get('/v1/personnel'),
                hasPermission('drivers.create') || hasPermission('drivers.edit') ? api.get('/v1/personnel/options') : Promise.resolve({data:{data:{vehicles:[]}}})
            ]);
            
            const data = resPersonnel.data.data || [];
            setPersonnel(data);
            
            const activeCount = data.filter(p => p.is_active).length;
            setKpi({ total: data.length, active: activeCount, inactive: data.length - activeCount });

            // Birthdays
            if (!birthdayAlertShown) {
                const today = dayjs();
                const todayStr = today.format('YYYY-MM-DD');
                const lastShownDate = await AsyncStorage.getItem('birthdayModalShownDate');

                if (lastShownDate !== todayStr) {
                    const bDays = data.filter(p => {
                        if (!p.birth_date) return false;
                        const bDate = dayjs(p.birth_date);
                        return bDate.date() === today.date() && bDate.month() === today.month();
                    });
                    if (bDays.length > 0) {
                        setBirthdayPersonnel(bDays);
                        setShowBirthdayModal(true);
                        setBirthdayAlertShown(true);
                        await AsyncStorage.setItem('birthdayModalShownDate', todayStr);
                    }
                } else {
                    setBirthdayAlertShown(true);
                }
            }

            if (resOptions.data.data?.vehicles) {
                setVehicles(resOptions.data.data.vehicles);
            }

            // Trigger Stagger 3D Flip
            flipAnims.forEach(a => a.setValue(0));
            Animated.stagger(80, flipAnims.map((anim) => 
                Animated.spring(anim, { toValue: 1, friction: 7, tension: 40, useNativeDriver: true })
            )).start();

        } catch (e) {
            console.log(e.response?.data || e.message);
        } finally {
            setLoading(false);
        }
    };

    useFocusEffect(useCallback(() => { fetchData(true); }, []));

    const openAddForm = () => { setEditingId(null); setFormData(emptyForm); setShowForm(true); };
    const openEditForm = (item) => {
        setEditingId(item.id);
        setFormData({
            full_name: item.full_name || '', tc_no: item.tc_no || '', phone: item.phone || '', email: item.email || '',
            license_class: item.license_class || '', src_type: item.src_type || '', start_shift: item.start_shift || 'morning',
            vehicle_id: item.vehicle_id ? item.vehicle_id.toString() : ''
        });
        setShowForm(true);
    };

    const handlePhoneChange = (text) => {
        let val = text.replace(/\D/g, '');
        if (val.length > 0 && val[0] !== '0') val = '0' + val;
        let formatted = '';
        if (val.length > 0) formatted += val.substring(0, 1);
        if (val.length > 1) formatted += ' ' + val.substring(1, 4);
        if (val.length > 4) formatted += ' ' + val.substring(4, 7);
        if (val.length > 7) formatted += ' ' + val.substring(7, 9);
        if (val.length > 9) formatted += ' ' + val.substring(9, 11);
        setFormData({...formData, phone: formatted});
    };

    const handleSave = async () => {
        if (!formData.full_name) { Alert.alert('Eksik Bilgi', 'Personel adı zorunludur.'); return; }
        setSaving(true);
        try {
            const payload = { ...formData };
            if (!payload.vehicle_id) payload.vehicle_id = null;
            if (editingId) await api.put(`/v1/personnel/${editingId}`, payload);
            else await api.post('/v1/personnel', payload);
            setShowForm(false);
            fetchData(true);
        } catch (e) {
            Alert.alert('Hata', e.response?.data?.message || 'İşlem başarısız.');
        } finally {
            setSaving(false);
        }
    };

    const confirmDelete = (item) => {
        Alert.alert('Silme Onayı', `"${item.full_name}" adlı personeli silmek istediğinize emin misiniz?`, [
            { text: 'Vazgeç', style: 'cancel' },
            { 
                text: 'Sil', style: 'destructive', 
                onPress: async () => {
                    const prev = [...personnel];
                    setPersonnel(prev.filter(p => p.id !== item.id));
                    try {
                        await api.delete(`/v1/personnel/${item.id}`);
                        fetchData(true);
                    } catch (e) {
                        setPersonnel(prev);
                        Alert.alert('Hata', 'Kayıt silinemedi.');
                    }
                }
            }
        ]);
    };

    const filteredPersonnel = personnel.filter(p => {
        const matchSearch = p.full_name?.toLowerCase().includes(searchQuery.toLowerCase()) || p.tc_no?.includes(searchQuery) || p.vehicle?.plate?.toLowerCase().includes(searchQuery.toLowerCase());
        const matchStatus = filterStatus === 'all' ? true : (filterStatus === 'active' ? p.is_active : !p.is_active);
        return matchSearch && matchStatus;
    }).sort((a, b) => {
        const plateA = (a.vehicle?.plate || 'ZZZZZZ').replace(/\s+/g, '').toUpperCase();
        const plateB = (b.vehicle?.plate || 'ZZZZZZ').replace(/\s+/g, '').toUpperCase();
        
        return plateA.localeCompare(plateB, undefined, { numeric: true, sensitivity: 'base' });
    });

    const renderCard = ({ item, index }) => {
        const animIndex = index % 60;
        const flipAnim = flipAnims[animIndex] || new Animated.Value(1);

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
            <SwipeableRow onEdit={() => openEditForm(item)} onDelete={() => confirmDelete(item)}>
                <Animated.View style={[s.cardWrapper, animatedStyle]}>
                    <TouchableOpacity activeOpacity={0.8} style={{ flex: 1 }} onPress={() => navigation.navigate('PersonnelDetail', { id: item.id, personnel: item })}>
                        <BlurView intensity={25} tint="dark" style={s.card}>
                            <View style={s.cardLeft}>
                                {item.profile_photo_url ? (
                                    <Image source={{ uri: item.profile_photo_url }} style={s.avatar} />
                                ) : (
                                    <LinearGradient colors={['#3B82F6', '#1E40AF']} style={s.premiumIconWrap}>
                                        <Icon name="account-tie" size={26} color="#FFF" />
                                        <View style={s.premiumIconGlow} />
                                    </LinearGradient>
                                )}
                            </View>
                            <View style={s.cardMid}>
                                <Text style={s.cardName} numberOfLines={1}>{item.full_name}</Text>
                                <View style={s.cardSubRow}>
                                    {item.vehicle ? (
                                        <View style={s.plateBadge}>
                                            <Text style={s.plateText}>{item.vehicle.plate}</Text>
                                        </View>
                                    ) : (
                                        <Text style={s.noPlate}>Atanmamış</Text>
                                    )}
                                    <View style={[s.statusBadge, { backgroundColor: item.is_active ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.15)', borderWidth: 1, borderColor: item.is_active ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)' }]}>
                                        <View style={[s.statusDot, { backgroundColor: item.is_active ? '#34D399' : '#F87171', shadowColor: item.is_active ? '#34D399' : '#F87171', shadowOpacity: 1, shadowRadius: 4 }]} />
                                        <Text style={[s.statusText, { color: item.is_active ? '#34D399' : '#F87171' }]}>{item.is_active ? 'Uygun' : 'Pasif'}</Text>
                                    </View>
                                </View>
                            </View>
                            <TouchableOpacity style={s.callBtn} onPress={() => {
                                if (item.phone) Linking.openURL(`tel:${item.phone}`);
                                else Alert.alert('Bilgi', 'Bu personelin telefon numarası kayıtlı değil.');
                            }}>
                                <LinearGradient colors={['rgba(16,185,129,0.2)', 'rgba(16,185,129,0.05)']} style={StyleSheet.absoluteFillObject} />
                                <Icon name="phone" size={22} color="#34D399" />
                            </TouchableOpacity>
                        </BlurView>
                    </TouchableOpacity>
                </Animated.View>
            </SwipeableRow>
        );
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
                    <View style={{ flex: 1 }}>
                        <Text style={s.headerTitle}>Personeller</Text>
                        <Text style={s.headerSub}>Ekibinizi ve sürücülerinizi yönetin</Text>
                    </View>
                    <TouchableOpacity style={s.searchIconBtn} onPress={() => setShowSearch(true)}>
                        <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                        <Icon name="magnify" size={24} color="#FFF" />
                    </TouchableOpacity>
                </View>

                {/* KPI Cards */}
                <View style={s.kpiContainer}>
                    <TouchableOpacity activeOpacity={0.8} style={s.kpiWrapper} onPress={() => setFilterStatus('all')}>
                        <BlurView intensity={20} tint="dark" style={[s.kpiCardFix, filterStatus === 'all' && s.kpiActiveBorder]}>
                            <View style={s.kpiIconWrap}><Image source={emoji('People/Construction Worker')} style={{width: 24, height: 24}} resizeMode="contain" /></View>
                            <Text style={s.kpiValue}>{kpi.total}</Text>
                            <Text style={s.kpiLabel}>Toplam</Text>
                        </BlurView>
                    </TouchableOpacity>
                    
                    <TouchableOpacity activeOpacity={0.8} style={[s.kpiWrapper, { marginHorizontal: 8 }]} onPress={() => setFilterStatus(filterStatus === 'inactive' ? 'all' : 'inactive')}>
                        <BlurView intensity={20} tint="dark" style={[s.kpiCardFix, filterStatus === 'inactive' && { borderColor: '#F59E0B' }]}>
                            <View style={s.kpiIconWrap}><Image source={emoji('Symbols/Prohibited')} style={{width: 24, height: 24}} resizeMode="contain" /></View>
                            <Text style={s.kpiValue}>{kpi.inactive}</Text>
                            <Text style={s.kpiLabel}>Pasif</Text>
                        </BlurView>
                    </TouchableOpacity>

                    <View style={s.kpiWrapper}>
                        <BlurView intensity={20} tint="dark" style={s.kpiCardFix}>
                            <View style={s.kpiIconWrap}><Image source={emoji('Travel and places/Automobile')} style={{width: 24, height: 24}} resizeMode="contain" /></View>
                            <Text style={s.kpiValue}>{personnel.filter(p => p.vehicle_id).length}</Text>
                            <Text style={s.kpiLabel}>Araçlı</Text>
                        </BlurView>
                    </View>
                </View>

                {/* List */}
                {loading ? (
                    <View style={s.loader}><ActivityIndicator size="large" color="#60A5FA" /></View>
                ) : (
                    <Animated.FlatList
                        onScroll={Animated.event([{ nativeEvent: { contentOffset: { y: scrollY } } }], { useNativeDriver: true })}
                        scrollEventThrottle={16}
                        data={filteredPersonnel}
                        keyExtractor={item => item.id.toString()}
                        renderItem={renderCard}
                        contentContainerStyle={{ paddingHorizontal: 20, paddingBottom: 120, paddingTop: 10 }}
                        showsVerticalScrollIndicator={false}
                        ListEmptyComponent={
                            <View style={s.empty}>
                                <Image source={emoji('People/Construction Worker')} style={{width: 64, height: 64, opacity: 0.8}} resizeMode="contain" />
                                <Text style={s.emptyText}>Sonuç bulunamadı.</Text>
                            </View>
                        }
                    />
                )}

                {/* Birthday Alert Modal */}
                <Modal visible={showBirthdayModal} transparent animationType="fade">
                    <BlurView intensity={40} tint="dark" style={s.modalOverlayCenter}>
                        <View style={s.birthdayModal}>
                            <LinearGradient colors={['rgba(219,39,119,0.2)', 'rgba(219,39,119,0.05)']} style={s.birthdayHeader}>
                                <Image source={emoji('Activities/Party Popper')} style={{width: 64, height: 64}} resizeMode="contain" />
                                <Text style={s.birthdayTitle}>Bugün Doğum Günü!</Text>
                            </LinearGradient>
                            <View style={s.birthdayContent}>
                                <Text style={s.birthdayDesc}>Aşağıdaki personellerinizin bugün doğum günü. Onları tebrik etmeyi unutmayın!</Text>
                                {birthdayPersonnel.map(p => (
                                    <View key={p.id} style={s.birthdayItem}>
                                        <View style={s.birthdayIcon}><Image source={emoji('Food and drink/Birthday Cake')} style={{width: 28, height: 28}} resizeMode="contain" /></View>
                                        <Text style={s.birthdayName}>{p.full_name}</Text>
                                    </View>
                                ))}
                                <TouchableOpacity style={s.birthdayBtn} onPress={() => setShowBirthdayModal(false)} activeOpacity={0.8}>
                                    <LinearGradient colors={['#DB2777', '#BE185D']} style={StyleSheet.absoluteFillObject} />
                                    <Text style={s.birthdayBtnText}>Teşekkürler, Kapat</Text>
                                </TouchableOpacity>
                            </View>
                        </View>
                    </BlurView>
                </Modal>
            </SafeAreaView>

            {/* FAB */}
            {hasPermission('drivers.create') && (
                <TouchableOpacity style={[s.fab, { bottom: Math.max(insets.bottom + 80, 100) }]} activeOpacity={0.9} onPress={openAddForm}>
                    <LinearGradient colors={['#8B5CF6', '#4F46E5']} style={s.fabGradient}>
                        <Icon name="account-plus" size={26} color="#FFF" />
                    </LinearGradient>
                </TouchableOpacity>
            )}

            {/* Search/Filter Modal */}
            <Modal visible={showSearch} transparent animationType="slide">
                <BlurView intensity={30} tint="dark" style={s.modalOverlay}>
                    <TouchableOpacity style={StyleSheet.absoluteFill} onPress={() => setShowSearch(false)} />
                    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ width: '100%' }}>
                        <BlurView intensity={50} tint="dark" style={[s.bottomSheet, { paddingBottom: Math.max(insets.bottom, 20) }]}>
                            <View style={s.sheetHandle} />
                            <Text style={s.sheetTitle}>Personel Keşfet</Text>
                            <View style={s.inputRow}>
                                <Icon name="magnify" size={22} color="#94A3B8" />
                                <TextInput style={s.input} placeholderTextColor="#64748B" placeholder="Ad, TC veya Plaka Ara..." value={searchQuery} onChangeText={setSearchQuery} autoFocus />
                            </View>
                            <Text style={s.filterLabel}>Durum Filtresi</Text>
                            <View style={s.filterRow}>
                                {['all', 'active', 'inactive'].map(val => (
                                    <TouchableOpacity key={val} style={[s.filterChip, filterStatus === val && s.filterChipActive]} onPress={() => setFilterStatus(val)}>
                                        <Text style={[s.filterChipText, filterStatus === val && s.filterChipTextActive]}>{val === 'all' ? 'Tümü' : val === 'active' ? 'Uygun' : 'Pasif'}</Text>
                                    </TouchableOpacity>
                                ))}
                            </View>
                            <TouchableOpacity style={s.applyBtn} onPress={() => setShowSearch(false)}>
                                <LinearGradient colors={['#3B82F6', '#2563EB']} style={StyleSheet.absoluteFillObject} />
                                <Text style={s.applyBtnText}>Filtreleri Uygula</Text>
                            </TouchableOpacity>
                        </BlurView>
                    </KeyboardAvoidingView>
                </BlurView>
            </Modal>

            {/* Form Modal */}
            <Modal visible={showForm} transparent animationType="slide">
                <BlurView intensity={40} tint="dark" style={s.modalOverlay}>
                    <TouchableOpacity style={StyleSheet.absoluteFill} onPress={() => setShowForm(false)} />
                    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ width: '100%', flex: 1, justifyContent: 'flex-end' }}>
                        <BlurView intensity={50} tint="dark" style={[s.formSheet, { paddingBottom: Math.max(insets.bottom, 20), maxHeight: SCREEN_HEIGHT * 0.85 }]}>
                            <View style={s.sheetHeader}>
                                <Text style={s.sheetTitle}>{editingId ? 'Personeli Düzenle' : 'Yeni Personel Ekle'}</Text>
                                <TouchableOpacity onPress={() => setShowForm(false)} style={s.closeIcon}>
                                    <Icon name="close" size={22} color="#CBD5E1" />
                                </TouchableOpacity>
                            </View>
                            
                            <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingHorizontal: 20 }}>
                                <Text style={s.sectionTitle}>Kişisel Bilgiler</Text>
                                <View style={s.fieldWrap}>
                                    <Icon name="account-outline" size={20} color="#94A3B8" style={s.fieldIcon} />
                                    <TextInput placeholderTextColor="#64748B" style={s.fieldInput} placeholder="Ad Soyad" value={formData.full_name} onChangeText={t => setFormData({...formData, full_name: t})} />
                                </View>
                                <View style={s.fieldWrap}>
                                    <Icon name="card-account-details-outline" size={20} color="#94A3B8" style={s.fieldIcon} />
                                    <TextInput placeholderTextColor="#64748B" style={s.fieldInput} placeholder="TC Kimlik No" value={formData.tc_no} onChangeText={t => setFormData({...formData, tc_no: t})} keyboardType="number-pad" />
                                </View>
                                <View style={s.fieldWrap}>
                                    <Icon name="phone-outline" size={20} color="#94A3B8" style={s.fieldIcon} />
                                    <TextInput placeholderTextColor="#64748B" style={s.fieldInput} placeholder="Telefon Numarası" value={formData.phone} onChangeText={handlePhoneChange} keyboardType="phone-pad" maxLength={15} />
                                </View>
                                
                                <Text style={s.sectionTitle}>İş Bilgileri</Text>
                                <SelectInput icon="car-outline" placeholder="Araç Seçin (veya Kaldır)" value={formData.vehicle_id} options={[{ label: "Araç Atanmadı", value: "" }, ...vehicles.map(v => ({ label: v.plate, value: v.id.toString() }))]} onSelect={(v) => setFormData({...formData, vehicle_id: v})} />
                                <View style={s.rowFields}>
                                    <View style={[s.fieldWrap, { flex: 1, marginRight: 8 }]}>
                                        <Icon name="card-text-outline" size={20} color="#94A3B8" style={s.fieldIcon} />
                                        <TextInput placeholderTextColor="#64748B" style={s.fieldInput} placeholder="Ehliyet (Örn: B)" value={formData.license_class} onChangeText={t => setFormData({...formData, license_class: t})} />
                                    </View>
                                    <View style={[s.fieldWrap, { flex: 1 }]}>
                                        <Icon name="file-document-outline" size={20} color="#94A3B8" style={s.fieldIcon} />
                                        <TextInput placeholderTextColor="#64748B" style={s.fieldInput} placeholder="SRC (Örn: SRC2)" value={formData.src_type} onChangeText={t => setFormData({...formData, src_type: t})} />
                                    </View>
                                </View>
                                <SelectInput icon="clock-outline" placeholder="Vardiya" value={formData.start_shift} options={[{ label: "Sabah Vardiyası", value: "morning" }, { label: "Akşam Vardiyası", value: "evening" }]} onSelect={(v) => setFormData({...formData, start_shift: v})} />
                                <View style={{ height: 40 }} />
                            </ScrollView>

                            <View style={s.formActions}>
                                <TouchableOpacity style={s.cancelBtn} onPress={() => setShowForm(false)}>
                                    <Text style={s.cancelBtnText}>Vazgeç</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={s.saveBtn} onPress={handleSave} disabled={saving}>
                                    <LinearGradient colors={['#8B5CF6', '#4F46E5']} style={StyleSheet.absoluteFillObject} />
                                    {saving ? <ActivityIndicator color="#FFF" /> : <Text style={s.saveBtnText}>{editingId ? 'Değişiklikleri Kaydet' : 'Personeli Kaydet'}</Text>}
                                </TouchableOpacity>
                            </View>
                        </BlurView>
                    </KeyboardAvoidingView>
                </BlurView>
            </Modal>
        </View>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    bgBlob1: { position: 'absolute', top: -100, right: -100, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(99, 102, 241, 0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, left: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(236, 72, 153, 0.12)', filter: 'blur(40px)' },
    
    header: { flexDirection: 'row', paddingHorizontal: 20, paddingTop: Platform.OS === 'ios' ? 10 : 30, paddingBottom: 20, alignItems: 'center' },
    headerTitle: { fontSize: 30, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.5, textShadowColor: 'rgba(255,255,255,0.3)', textShadowOffset: { width: 0, height: 0 }, textShadowRadius: 10 },
    headerSub: { fontSize: 13, color: '#94A3B8', fontWeight: '500', marginTop: 4 },
    searchIconBtn: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    
    kpiContainer: { paddingHorizontal: 20, paddingBottom: 16, flexDirection: 'row', justifyContent: 'space-between' },
    kpiWrapper: { flex: 1 },
    kpiCardFix: { padding: 14, borderRadius: 20, overflow: 'hidden', justifyContent: 'space-between', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    kpiActiveBorder: { borderColor: '#3B82F6', backgroundColor: 'rgba(59,130,246,0.05)' },
    kpiIconWrap: { width: 32, height: 32, borderRadius: 16, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', justifyContent: 'center', marginBottom: 8 },
    kpiValue: { fontSize: 24, fontWeight: '900', color: '#FFF', letterSpacing: -0.5 },
    kpiLabel: { fontSize: 11, color: '#94A3B8', fontWeight: '600', marginTop: 4 },
    
    loader: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingTop: 100 },
    empty: { alignItems: 'center', marginTop: 80 },
    emptyText: { fontSize: 15, color: '#94A3B8', fontWeight: '500', marginTop: 12 },

    swipeContainer: { marginBottom: 16, borderRadius: 24, backgroundColor: 'transparent' },
    actionButtons: { position: 'absolute', right: 0, top: 0, bottom: 0, flexDirection: 'row', width: 140 },
    actionBtn: { width: 70, height: '100%', alignItems: 'center', justifyContent: 'center', borderWidth: 1 },
    swipeContent: { borderRadius: 24, flexDirection: 'row', alignItems: 'center' },
    
    cardWrapper: { flex: 1 },
    card: { flex: 1, flexDirection: 'row', alignItems: 'center', padding: 16, borderRadius: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    cardLeft: { marginRight: 14 },
    premiumIconWrap: { width: 52, height: 52, borderRadius: 18, alignItems: 'center', justifyContent: 'center', overflow: 'hidden', shadowColor: '#3B82F6', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 1, shadowRadius: 8, elevation: 5 },
    premiumIconGlow: { position: 'absolute', top: -10, right: -10, width: 30, height: 30, borderRadius: 15, backgroundColor: 'rgba(255,255,255,0.2)' },
    avatar: { width: 52, height: 52, borderRadius: 18, borderWidth: 2, borderColor: 'rgba(255,255,255,0.1)' },
    cardMid: { flex: 1, justifyContent: 'center' },
    cardName: { fontSize: 16, fontWeight: '800', color: '#F8FAFC', marginBottom: 6 },
    cardSubRow: { flexDirection: 'row', alignItems: 'center' },
    
    statusBadge: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8 },
    statusDot: { width: 6, height: 6, borderRadius: 3, marginRight: 6 },
    statusText: { fontSize: 10, fontWeight: '900', letterSpacing: 0.5 },
    plateBadge: { backgroundColor: 'rgba(253,224,71,0.15)', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8, borderWidth: 1, borderColor: 'rgba(253,224,71,0.4)', marginRight: 8 },
    plateText: { fontSize: 10, fontWeight: '900', color: '#FDE047', letterSpacing: 0.5 },
    noPlate: { fontSize: 11, color: '#64748B', fontWeight: '600', fontStyle: 'italic', marginRight: 8 },
    
    callBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', marginLeft: 10, borderWidth: 1, borderColor: 'rgba(16,185,129,0.3)' },

    fab: { position: 'absolute', right: 20, borderRadius: 28, shadowColor: '#8B5CF6', shadowOffset: { width: 0, height: 8 }, shadowOpacity: 0.5, shadowRadius: 16, elevation: 10 },
    fabGradient: { width: 56, height: 56, borderRadius: 28, alignItems: 'center', justifyContent: 'center' },

    modalOverlay: { flex: 1, justifyContent: 'flex-end' },
    modalOverlayCenter: { flex: 1, justifyContent: 'center', padding: 20 },
    centerModal: { backgroundColor: 'rgba(15,23,42,0.8)', borderRadius: 24, padding: 24, maxHeight: '80%', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    modalTitle: { fontSize: 18, fontWeight: '800', color: '#F8FAFC', marginBottom: 16 },
    menuItem: { flexDirection: 'row', alignItems: 'center', paddingVertical: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    menuText: { fontSize: 15, fontWeight: '600' },
    
    bottomSheet: { borderTopLeftRadius: 32, borderTopRightRadius: 32, padding: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    sheetHandle: { width: 40, height: 4, borderRadius: 2, backgroundColor: 'rgba(255,255,255,0.2)', alignSelf: 'center', marginBottom: 20 },
    sheetTitle: { fontSize: 22, fontWeight: '900', color: '#F8FAFC', marginBottom: 24 },
    inputRow: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.2)', borderRadius: 16, paddingHorizontal: 16, height: 54, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', marginBottom: 24 },
    input: { flex: 1, marginLeft: 10, fontSize: 15, color: '#F8FAFC', fontWeight: '500' },
    filterLabel: { fontSize: 12, fontWeight: '800', color: '#94A3B8', letterSpacing: 1, textTransform: 'uppercase', marginBottom: 12 },
    filterRow: { flexDirection: 'row', gap: 10, marginBottom: 30 },
    filterChip: { flex: 1, paddingVertical: 14, borderRadius: 14, backgroundColor: 'rgba(255,255,255,0.05)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', alignItems: 'center' },
    filterChipActive: { backgroundColor: 'rgba(59,130,246,0.2)', borderColor: '#3B82F6' },
    filterChipText: { fontSize: 13, fontWeight: '700', color: '#94A3B8' },
    filterChipTextActive: { color: '#60A5FA', fontWeight: '900' },
    applyBtn: { paddingVertical: 16, borderRadius: 16, alignItems: 'center', overflow: 'hidden' },
    applyBtnText: { color: '#FFF', fontSize: 16, fontWeight: '900', letterSpacing: 0.5 },
    
    formSheet: { borderTopLeftRadius: 32, borderTopRightRadius: 32, paddingTop: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    sheetHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 20, marginBottom: 20 },
    closeIcon: { width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', justifyContent: 'center' },
    sectionTitle: { fontSize: 13, fontWeight: '900', color: '#8B5CF6', letterSpacing: 1, textTransform: 'uppercase', marginBottom: 12, marginTop: 10 },
    fieldWrap: {
        flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.2)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', borderRadius: 14, paddingHorizontal: 16, height: 54, marginBottom: 12
    },
    fieldIcon: { marginRight: 12 },
    fieldInput: { flex: 1, fontSize: 15, color: '#F8FAFC', fontWeight: '600', height: '100%' },
    rowFields: { flexDirection: 'row', justifyContent: 'space-between' },
    formActions: { flexDirection: 'row', padding: 20, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)', backgroundColor: 'rgba(0,0,0,0.3)' },
    cancelBtn: { flex: 1, paddingVertical: 16, borderRadius: 16, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', marginRight: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    cancelBtnText: { color: '#94A3B8', fontSize: 15, fontWeight: '800' },
    saveBtn: { flex: 2, borderRadius: 16, alignItems: 'center', overflow: 'hidden', justifyContent: 'center' },
    saveBtnText: { color: '#FFF', fontSize: 15, fontWeight: '900' },

    birthdayModal: { width: '85%', backgroundColor: 'rgba(15,23,42,0.9)', borderRadius: 24, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(219,39,119,0.3)' },
    birthdayHeader: { alignItems: 'center', paddingVertical: 24, borderBottomWidth: 1, borderBottomColor: 'rgba(219,39,119,0.2)' },
    birthdayTitle: { fontSize: 24, fontWeight: '900', color: '#F472B6', marginTop: 12 },
    birthdayContent: { padding: 24 },
    birthdayDesc: { fontSize: 14, color: '#94A3B8', textAlign: 'center', marginBottom: 20, lineHeight: 22, fontWeight: '500' },
    birthdayItem: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(219,39,119,0.1)', padding: 12, borderRadius: 16, marginBottom: 10, borderWidth: 1, borderColor: 'rgba(219,39,119,0.2)' },
    birthdayIcon: { width: 36, height: 36, borderRadius: 18, backgroundColor: 'rgba(219,39,119,0.2)', alignItems: 'center', justifyContent: 'center', marginRight: 12 },
    birthdayName: { fontSize: 16, fontWeight: '800', color: '#FBCFE8' },
    birthdayBtn: { paddingVertical: 16, borderRadius: 16, alignItems: 'center', marginTop: 16, overflow: 'hidden' },
    birthdayBtnText: { fontSize: 16, fontWeight: '900', color: '#FFF' }
});
