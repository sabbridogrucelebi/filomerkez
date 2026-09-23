import React, { useContext, useEffect, useRef } from 'react';
import { View, Text, StyleSheet, Animated, TouchableOpacity, Image, StatusBar, Easing } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { AuthContext } from '../context/AuthContext';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import { useFocusEffect } from '@react-navigation/native';
import { emoji } from '../emoji';

const menuItems = [
    { id: 1, emoji: 'Travel%20and%20places/House', label: 'Ana Sayfa', sub: 'GENEL BAKIŞ', route: 'HomeTab' },
    { id: 2, emoji: 'Travel%20and%20places/Oncoming%20Automobile', label: 'Araçlar', sub: 'FİLO YÖNETİMİ', route: 'VehiclesTab', permission: 'vehicles.view' },
    { id: 4, emoji: 'People/Construction%20Worker', label: 'Personeller', sub: 'PERSONEL YÖNETİMİ', route: 'Personnel', permission: 'drivers.view' },
    { id: 5, emoji: 'Objects/Hammer%20and%20Wrench', label: 'Bakım / Tamir', sub: 'SERVİS VE BAKIM', route: 'Maintenances', permission: 'maintenances.view' },
    { id: 6, emoji: 'Travel%20and%20places/Fuel%20Pump', label: 'Yakıt', sub: 'YAKIT TAKİBİ', route: 'VehiclesTab', screen: 'Fuels', permission: 'fuels.view' },
    { id: 7, emoji: 'Travel%20and%20places/Police%20Car%20Light', label: 'Trafik Cezaları', sub: 'YASAL VE UYUMLULUK', route: 'Penalties', permission: 'penalties.view' },
    { id: 8, emoji: 'Travel%20and%20places/High-Speed%20Train', label: 'Puantaj / Sefer', sub: 'OPERASYON KAYITLARI', route: 'Trips', permission: 'trips.view' },
    { id: 9, emoji: 'Objects/Money%20with%20Wings', label: 'Maaşlar', sub: 'FİNANSAL KAYITLAR', route: 'Payrolls', permission: 'payrolls.view' },
    { id: 10, emoji: 'Objects/Briefcase', label: 'Müşteriler', sub: 'MÜŞTERİ YÖNETİMİ', route: 'Customers', permission: 'customers.view' },
    { id: 17, emoji: 'Objects/File%20Cabinet', label: 'Şirket Evrakları', sub: 'KURUMSAL BELGELER', route: 'CompanyDocuments', permission: 'company_documents.view' },
    { id: 18, emoji: 'Objects/Open%20Book', label: 'İhaleler', sub: 'İHALE & SÖZLEŞMELER', route: 'Tenders', permission: 'tenders.view' },
    { id: 11, emoji: 'Objects/Chart%20Increasing', label: 'Raporlar', sub: 'ANALİZ MERKEZİ', route: 'Reports', permission: 'reports.view' },
    { id: 13, emoji: 'Objects/Magnifying%20Glass%20Tilted%20Left', label: 'Loglar', sub: 'AKTİVİTE KAYITLARI', route: 'Activity', adminOnly: true },
    { id: 14, emoji: 'Objects/Shield', label: 'Kullanıcılar', sub: 'ERİŞİM KONTROLÜ', route: 'CompanyUsers', adminOnly: true },
    { id: 15, emoji: 'Objects/Gear', label: 'Ayarlar', sub: 'SİSTEM YAPILANDIRMASI', route: 'Settings' },
    { id: 16, emoji: 'Travel%20and%20places/Minibus', label: 'PilotCell', sub: 'ŞOFÖR PANELİ', route: 'PilotCellDriver', permission: 'pilotcell.drive' },
];

export default function MenuScreen({ navigation }) {
    const { hasPermission, userInfo } = useContext(AuthContext);

    const visibleItems = menuItems.filter(item => {
        if (item.adminOnly) return !!userInfo?.is_company_admin;
        if (!item.permission) return true;
        return hasPermission(item.permission);
    });

    // Animasyon Değerleri
    const scrollY = useRef(new Animated.Value(0)).current;
    const floatAnim = useRef(new Animated.Value(0)).current;
    
    // Her menü öğesi için yeterli sayıda animasyon değeri oluştur
    const flipAnims = useRef([...Array(30)].map(() => new Animated.Value(0))).current;
    const logoAnim = useRef(new Animated.Value(0)).current;

    // Yüzen (Float) animasyonu başlat
    useEffect(() => {
        Animated.loop(
            Animated.sequence([
                Animated.timing(floatAnim, { toValue: 1, duration: 2500, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(floatAnim, { toValue: 0, duration: 2500, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        ).start();
    }, []);

    // Sayfa her odaklandığında menü animasyonlarını tetikle
    useFocusEffect(
        React.useCallback(() => {
            // Sıfırla
            logoAnim.setValue(0);
            visibleItems.forEach((_, i) => flipAnims[i].setValue(0));

            // Başlat
            Animated.parallel([
                Animated.spring(logoAnim, { toValue: 1, friction: 5, tension: 40, useNativeDriver: true }),
                Animated.stagger(60, visibleItems.map((_, i) => 
                    Animated.spring(flipAnims[i], {
                        toValue: 1,
                        friction: 6,
                        tension: 50,
                        useNativeDriver: true
                    })
                ))
            ]).start();
        }, [visibleItems.length])
    );

    // Parallax Arka Plan Dönüşümü
    const bgTransform = {
        transform: [{
            translateY: scrollY.interpolate({
                inputRange: [-100, 0, 500],
                outputRange: [-50, 0, 150],
                extrapolate: 'clamp'
            })
        }]
    };

    const floatTransform = {
        transform: [{
            translateY: floatAnim.interpolate({
                inputRange: [0, 1],
                outputRange: [0, -6]
            })
        }]
    };

    return (
        <View style={s.container}>
            {/* Parallax Arka Plan */}
            <Animated.View style={[StyleSheet.absoluteFill, bgTransform]}>
                <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
                <View style={s.bgBlob1} />
                <View style={s.bgBlob2} />
            </Animated.View>

            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent={true} />
            
            <SafeAreaView style={{ flex: 1 }}>
                
                {/* 3D Animasyonlu Custom Logo Header */}
                <Animated.View style={[
                    s.logoHeader,
                    { 
                        opacity: logoAnim,
                        transform: [
                            { scale: logoAnim.interpolate({ inputRange: [0, 1], outputRange: [0.8, 1] }) },
                            { translateY: logoAnim.interpolate({ inputRange: [0, 1], outputRange: [-20, 0] }) }
                        ] 
                    }
                ]}>
                    <View style={s.logoGlowWrap}>
                        <Image source={require('../../assets/icon.png')} style={s.logoImage} />
                    </View>
                    <Text style={s.companyName}>
                        {userInfo?.company_name 
                            ? userInfo.company_name.split(' ').map(w => w.charAt(0).toLocaleUpperCase('tr-TR') + w.slice(1).toLocaleLowerCase('tr-TR')).join(' ')
                            : 'Filomerkez'}
                    </Text>
                    <Text style={s.companySubName}>YÖNETİM PANELİ</Text>
                </Animated.View>

                <Animated.ScrollView 
                    contentContainerStyle={s.scrollContent} 
                    showsVerticalScrollIndicator={false}
                    onScroll={Animated.event(
                        [{ nativeEvent: { contentOffset: { y: scrollY } } }],
                        { useNativeDriver: true }
                    )}
                    scrollEventThrottle={16}
                >
                    
                    <Text style={s.menuLabel}>ANA MENÜ</Text>

                    <View style={s.listContainer}>
                        {visibleItems.map((item, index) => {
                            // Her kart için 3D Flip Animasyonu
                            const flipRotate = flipAnims[index].interpolate({
                                inputRange: [0, 1],
                                outputRange: ['-90deg', '0deg']
                            });

                            return (
                                <Animated.View key={item.id} style={[
                                    s.listItem3DBase,
                                    {
                                        opacity: flipAnims[index],
                                        transform: [
                                            { perspective: 800 },
                                            { rotateX: flipRotate },
                                            { scale: flipAnims[index].interpolate({ inputRange: [0, 0.5, 1], outputRange: [0.8, 1.05, 1] }) }
                                        ]
                                    }
                                ]}>
                                    <BlurView intensity={30} tint="dark" style={{ borderRadius: 20, overflow: 'hidden' }}>
                                        <TouchableOpacity 
                                            style={s.listItem} 
                                            activeOpacity={0.7}
                                            onPress={() => {
                                                if (item.route && item.screen) {
                                                    navigation.navigate(item.route, { screen: item.screen });
                                                } else if (item.route) {
                                                    navigation.navigate(item.route);
                                                }
                                            }}
                                        >
                                            <Animated.View style={[s.iconWrap, floatTransform]}>
                                                <Image source={emoji(item.emoji)} style={s.emojiIcon} />
                                            </Animated.View>
                                            
                                            <View style={s.textWrap}>
                                                <Text style={s.itemTitle}>{item.label}</Text>
                                                <Text style={s.itemSub} numberOfLines={1}>{item.sub}</Text>
                                            </View>
                                        </TouchableOpacity>
                                    </BlurView>
                                </Animated.View>
                            );
                        })}
                    </View>

                    <View style={{ height: 120 }} />
                </Animated.ScrollView>
            </SafeAreaView>
        </View>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    
    // Arka plan ışıkları
    bgBlob1: { position: 'absolute', top: -50, right: -50, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(59, 130, 246, 0.1)', transform: [{ scale: 1.5 }] },
    bgBlob2: { position: 'absolute', bottom: 100, left: -100, width: 250, height: 250, borderRadius: 125, backgroundColor: 'rgba(139, 92, 246, 0.1)', transform: [{ scale: 1.8 }] },
    
    logoHeader: {
        alignItems: 'center',
        paddingVertical: 30,
        paddingHorizontal: 20,
        marginBottom: 10,
    },
    logoGlowWrap: {
        shadowColor: '#3B82F6',
        shadowOffset: { width: 0, height: 0 },
        shadowOpacity: 0.6,
        shadowRadius: 20,
        elevation: 10,
        marginBottom: 16,
    },
    logoImage: {
        width: 90,
        height: 90,
        borderRadius: 24,
        borderWidth: 2,
        borderColor: 'rgba(255,255,255,0.2)',
    },
    companyName: {
        fontFamily: 'Inter_800ExtraBold',
        fontSize: 22,
        color: '#F8FAFC',
        letterSpacing: 0.5,
        textAlign: 'center',
        marginTop: 4,
        textShadowColor: 'rgba(59, 130, 246, 0.6)',
        textShadowOffset: { width: 0, height: 0 },
        textShadowRadius: 15,
    },
    companySubName: {
        fontFamily: 'Inter_600SemiBold',
        fontSize: 10,
        color: '#94A3B8',
        letterSpacing: 3,
        marginTop: 6,
        textTransform: 'uppercase',
    },

    scrollContent: { paddingHorizontal: 20, paddingTop: 10 },
    
    menuLabel: { 
        fontSize: 13, 
        color: '#94A3B8', 
        fontWeight: '900', 
        letterSpacing: 2, 
        marginBottom: 16,
        marginLeft: 8,
        textShadowColor: 'rgba(0,0,0,0.5)',
        textShadowOffset: { width: 0, height: 1 },
        textShadowRadius: 2,
    },

    listContainer: { gap: 12 },
    
    listItem3DBase: {
        borderRadius: 20,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.5,
        shadowRadius: 10,
        elevation: 8,
    },
    listItem: { 
        flexDirection: 'row', 
        alignItems: 'center', 
        padding: 14, 
        borderRadius: 20,
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.1)',
        backgroundColor: 'rgba(255,255,255,0.03)',
    },
    iconWrap: {
        width: 50,
        height: 50,
        borderRadius: 16,
        backgroundColor: 'rgba(59, 130, 246, 0.15)',
        alignItems: 'center',
        justifyContent: 'center',
        marginRight: 16,
        borderWidth: 1,
        borderColor: 'rgba(59, 130, 246, 0.3)',
        shadowColor: '#3B82F6',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.3,
        shadowRadius: 6,
        elevation: 4,
    },
    emojiIcon: {
        width: 32,
        height: 32,
        resizeMode: 'contain',
    },
    textWrap: {
        flex: 1,
        justifyContent: 'center',
    },
    itemTitle: { 
        fontSize: 17, 
        fontWeight: '800', 
        color: '#F8FAFC', 
        marginBottom: 4,
        letterSpacing: 0.5,
    },
    itemSub: { 
        fontSize: 11, 
        color: '#94A3B8', 
        fontWeight: '700', 
        letterSpacing: 1 
    }
});
