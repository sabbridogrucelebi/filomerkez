import React, { useContext } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Image, Dimensions, StatusBar } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { AuthContext } from '../context/AuthContext';
import { LinearGradient } from 'expo-linear-gradient';

const getEmojiUrl = (name) => `https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/${name}.png`;

const menuItems = [
    { id: 1, emoji: 'Travel%20and%20places/House', label: 'Ana Sayfa', sub: 'GENEL BAKIŞ', route: 'HomeTab' },
    { id: 2, emoji: 'Travel%20and%20places/Oncoming%20Automobile', label: 'Araçlar', sub: 'FİLO YÖNETİMİ', route: 'VehiclesTab', permission: 'vehicles.view' },
    { id: 3, emoji: 'Travel%20and%20places/Satellite', label: 'Araç Takip', sub: 'CANLI İZLEME', route: 'Tracking', permission: 'vehicles.view' },
    { id: 4, emoji: 'People/Construction%20Worker', label: 'Personeller', sub: 'PERSONEL YÖNETİMİ', route: 'Personnel', permission: 'drivers.view' },
    { id: 5, emoji: 'Objects/Hammer%20and%20Wrench', label: 'Bakım / Tamir', sub: 'SERVİS VE BAKIM', route: 'Maintenances', permission: 'maintenances.view' },
    { id: 6, emoji: 'Travel%20and%20places/Fuel%20Pump', label: 'Yakıt', sub: 'YAKIT TAKİBİ', route: 'VehiclesTab', screen: 'Fuels', permission: 'fuels.view' },
    { id: 7, emoji: 'Travel%20and%20places/Police%20Car%20Light', label: 'Trafik Cezaları', sub: 'YASAL VE UYUMLULUK', route: 'Penalties', permission: 'penalties.view' },
    { id: 8, emoji: 'Travel%20and%20places/High-Speed%20Train', label: 'Puantaj / Sefer', sub: 'OPERASYON KAYITLARI', route: 'Trips', permission: 'trips.view' },
    { id: 9, emoji: 'Objects/Money%20with%20Wings', label: 'Maaşlar', sub: 'FİNANSAL KAYITLAR', route: 'Payrolls', permission: 'payrolls.view' },
    { id: 20, emoji: 'Objects/Receipt', label: 'Muhasebe / Giderler', sub: 'FİNANS YÖNETİMİ', route: 'Finance', permission: 'expenses.view' },
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

    return (
        <View style={s.container}>
            <LinearGradient colors={['#FFFFFF', '#F1F5F9']} style={StyleSheet.absoluteFillObject} />
            <StatusBar barStyle="dark-content" backgroundColor="#FFFFFF" />
            <SafeAreaView style={{ flex: 1 }}>
                
                {/* Custom Logo Header matching Web */}
                <View style={s.logoHeader}>
                    <Image source={require('../../assets/icon.png')} style={s.logoImage} />
                    <Text style={s.companyName}>{userInfo?.company_name ? userInfo.company_name.toLocaleUpperCase('tr-TR') : 'FİLOMERKEZ'}</Text>
                </View>

                <ScrollView contentContainerStyle={s.scrollContent} showsVerticalScrollIndicator={false}>
                    
                    <Text style={s.menuLabel}>ANA MENÜ</Text>

                    <View style={s.listContainer}>
                        {visibleItems.map((item, index) => (
                            <TouchableOpacity 
                                key={item.id} 
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
                                <View style={s.iconWrap}>
                                    <Image source={{ uri: getEmojiUrl(item.emoji) }} style={s.emojiIcon} />
                                </View>
                                
                                <View style={s.textWrap}>
                                    <Text style={s.itemTitle}>{item.label}</Text>
                                    <Text style={s.itemSub} numberOfLines={1}>{item.sub}</Text>
                                </View>
                                
                            </TouchableOpacity>
                        ))}
                    </View>

                    <View style={{ height: 120 }} />
                </ScrollView>
            </SafeAreaView>
        </View>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#FFFFFF' },
    
    logoHeader: {
        alignItems: 'center',
        paddingVertical: 24,
        paddingHorizontal: 20,
        borderBottomWidth: 1,
        borderBottomColor: 'rgba(0,0,0,0.08)',
        marginBottom: 10,
    },
    logoImage: {
        width: 80,
        height: 80,
        borderRadius: 20,
        marginBottom: 12,
    },
    companyName: {
        fontSize: 12,
        fontWeight: '800',
        color: '#334155',
        letterSpacing: 2,
    },

    scrollContent: { paddingHorizontal: 16, paddingTop: 10 },
    
    menuLabel: { 
        fontSize: 12, 
        color: '#94A3B8', 
        fontWeight: '800', 
        letterSpacing: 1.5, 
        marginBottom: 16,
        marginLeft: 8 
    },

    listContainer: {
        gap: 8,
    },
    
    listItem: { 
        flexDirection: 'row', 
        alignItems: 'center', 
        padding: 12, 
        borderRadius: 16,
        backgroundColor: '#F8FAFC',
    },
    iconWrap: {
        width: 44,
        height: 44,
        borderRadius: 12,
        backgroundColor: '#EFF6FF',
        alignItems: 'center',
        justifyContent: 'center',
        marginRight: 16,
        borderWidth: 1,
        borderColor: '#DBEAFE',
    },
    emojiIcon: {
        width: 28,
        height: 28,
        resizeMode: 'contain',
    },
    textWrap: {
        flex: 1,
        justifyContent: 'center',
    },
    itemTitle: { 
        fontSize: 16, 
        fontWeight: '800', 
        color: '#1E293B', 
        marginBottom: 2 
    },
    itemSub: { 
        fontSize: 10, 
        color: '#94A3B8', 
        fontWeight: '700', 
        letterSpacing: 0.5 
    }
});
