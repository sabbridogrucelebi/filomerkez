import React, { useContext } from 'react';
import { View, Text, StyleSheet, ScrollView, Dimensions, Platform, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Print from 'expo-print';
import * as Sharing from 'expo-sharing';
import * as FileSystem from 'expo-file-system/legacy';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { Header } from '../components';
import { AuthContext } from '../context/AuthContext';
import dayjs from 'dayjs';
import useStatusBarStyle from '../hooks/useStatusBarStyle';

const { width: W } = Dimensions.get('window');
const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(v || 0);

export default function PayrollDetailScreen({ route, navigation }) {
    useStatusBarStyle('dark-content');
    const { driverData, periodMonth } = route.params || {};
    const { userInfo } = useContext(AuthContext); // FIX: Use userInfo instead of user

    if (!driverData) {
        return (
            <SafeAreaView style={st.container}>
                <Header title="Hakediş Detayı" onBack={() => navigation.goBack()} />
                <View style={st.center}><Text>Veri bulunamadı.</Text></View>
            </SafeAreaView>
        );
    }

    const { driver, calculation, existing } = driverData;
    const c = calculation || {};
    const e = existing || {};

    const monthName = dayjs(periodMonth, 'YYYY-MM').format('MMMM YYYY').toUpperCase();
    
    const base = parseFloat(e.base_salary ?? c.base_salary ?? 0);
    const bank = parseFloat(e.bank_payment ?? 0);
    const extraEarn = parseFloat(c.extra_earnings ?? 0);
    const penalty = parseFloat(e.traffic_penalty ?? 0);
    const advance = parseFloat(e.advance_payment ?? 0);
    const deduc = parseFloat(e.deduction ?? 0);
    const extraB = parseFloat(e.extra_bonus ?? 0);
    const net = e.net_salary != null ? parseFloat(e.net_salary) : (base + extraEarn + extraB - bank - penalty - advance - deduc);
    const gross = base + extraEarn + extraB;

    const companyName = userInfo?.company_name?.toUpperCase() || 'SERVİSPİLOT';

    const generateHTML = () => `
        <html>
        <head>
            <style>
                @page { margin: 20px; }
                * { box-sizing: border-box; }
                html, body { margin: 0; padding: 0; background-color: #fff; width: 100%; }
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #0F172A; padding: 20px; }
                
                .header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 3px solid #0F172A; padding-bottom: 10px; margin-bottom: 20px; }
                .header-left h1 { margin: 0; font-size: 26px; font-weight: 900; }
                .header-left h2 { margin: 5px 0 0 0; font-size: 14px; color: #3B82F6; }
                .header-right { text-align: right; }
                .header-right h2 { margin: 0; font-size: 16px; font-weight: 900; }
                .header-right p { margin: 3px 0 0 0; font-size: 10px; color: #64748B; letter-spacing: 1px; }
                
                .cards { display: flex; gap: 15px; margin-bottom: 20px; }
                .card { flex: 1; border: 1px solid #E2E8F0; border-left: 6px solid #3B82F6; border-radius: 10px; padding: 15px; }
                .card-label { font-size: 10px; font-weight: bold; color: #94A3B8; margin-bottom: 8px; }
                .card-title { font-size: 20px; font-weight: 900; margin-bottom: 8px; }
                .tc { font-size: 12px; color: #64748B; margin-bottom: 10px; }
                .dates { display: flex; gap: 15px; font-size: 12px; font-weight: bold; }
                .text-green { color: #10B981; }
                .text-red { color: #EF4444; }
                
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th { background: #0F172A; color: white; text-align: left; padding: 10px; font-size: 11px; }
                td { border-bottom: 1px solid #F1F5F9; padding: 10px; font-size: 12px; }
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                .fee { color: #3B82F6; font-weight: bold; }
                
                .summary-wrapper { display: flex; justify-content: space-between; }
                .summary-left { flex: 1; padding-right: 30px; }
                .summary-left h3 { font-size: 12px; font-weight: 900; margin-top: 0; }
                .summary-left p { font-size: 11px; color: #64748B; font-style: italic; }
                
                .summary-box { flex: 1.2; border: 2px solid #0F172A; border-radius: 12px; padding: 15px; }
                .s-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 12px; color: #475569; }
                .s-row-bold { font-weight: 900; color: #0F172A; }
                .s-row.green { color: #10B981; }
                .s-divider { border-bottom: 1px solid #E2E8F0; margin: 10px 0; }
                .s-divider-thick { border-bottom: 2px solid #0F172A; margin: 10px 0; }
                
                .net-row { display: flex; justify-content: space-between; align-items: center; }
                .net-label { color: #3B82F6; font-weight: 900; font-size: 14px; }
                .net-val { font-size: 26px; font-weight: 900; color: #0F172A; }
                
                .footer { display: flex; justify-content: space-between; margin-top: 40px; }
                .sign-box { text-align: center; width: 200px; }
                .sign-line { border-bottom: 1px solid #000; margin-bottom: 8px; height: 30px; }
                .sign-title { font-size: 11px; font-weight: bold; margin-bottom: 4px; }
                .sign-name { font-size: 12px; font-weight: 900; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="header-left">
                    <h1>HAKEDİŞ DETAYI</h1>
                    <h2>${monthName} DÖNEMİ PERSONEL HAKEDİŞİ</h2>
                </div>
                <div class="header-right">
                    <h2>${companyName}</h2>
                    <p>PERSONEL HAKEDİŞ DETAYI</p>
                </div>
            </div>
            
            <div class="cards">
                <div class="card">
                    <div class="card-label">PERSONEL KAYITLARI</div>
                    <div class="card-title">${driver.full_name}</div>
                    <div class="tc">TC: ${driver.tc_no || 'Belirtilmedi'}</div>
                    <div class="dates">
                        <span class="text-green">• GİRİŞ: ${driver.start_date ? dayjs(driver.start_date).format('DD.MM.YYYY') : '-'}</span>
                        ${driver.leave_date ? `<span class="text-red">• AYRILIŞ: ${dayjs(driver.leave_date).format('DD.MM.YYYY')}</span>` : ''}
                    </div>
                </div>
                <div class="card" style="text-align: right;">
                    <div class="card-label" style="text-align: right;">AYLIK BAZ MAAŞ</div>
                    <div class="card-title">${fmtMoney(base)}</div>
                    <div class="card-label">${c.work_days} GÜNLÜK KIST MAAŞ HESAPLANDI</div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th style="border-radius: 12px 0 0 12px;">OPERASYONEL GÜZERGAH DETAYI</th>
                        <th class="text-center">SABAH</th>
                        <th class="text-center">AKŞAM</th>
                        <th class="text-right" style="border-radius: 0 12px 12px 0;">HAKEDİŞ</th>
                    </tr>
                </thead>
                <tbody>
                    ${(!c.details || c.details.length === 0) ? `<tr><td colspan="4" class="text-center" style="color: #64748B; font-style: italic;">Ekstra sefer kaydı bulunamadı.</td></tr>` : 
                    c.details.map(d => `
                        <tr>
                            <td><strong>${d.customer_name}</strong><br><span style="color: #64748B; font-size: 12px;">${d.route_name}</span></td>
                            <td class="text-center">${d.morning_count}</td>
                            <td class="text-center">${d.evening_count}</td>
                            <td class="text-right fee">+${fmtMoney(d.total_fee)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            
            <div class="summary-wrapper">
                <div class="summary-left">
                    <h3>EKSTRA ÖDEME & KESİNTİ ANALİZİ</h3>
                    <p>${(e.extra_notes || e.deduction_notes) ? `${e.extra_notes || ''} ${e.deduction_notes || ''}`.trim() : 'Herhangi bir ek ödeme veya kesinti notu bulunmamaktadır.'}</p>
                </div>
                <div class="summary-box">
                    <div class="s-row"><span>Maaş + Seferler:</span><span>+${fmtMoney(base + extraEarn)}</span></div>
                    <div class="s-row green"><span>Ekstra (+):</span><span>+${fmtMoney(extraB)}</span></div>
                    <div class="s-row s-row-bold"><span>TOPLAM BRÜT:</span><span>${fmtMoney(gross)}</span></div>
                    <div class="s-divider"></div>
                    <div class="s-row"><span>Banka Ödemesi:</span><span>-${fmtMoney(bank)}</span></div>
                    <div class="s-row"><span>Trafik Cezası:</span><span>-${fmtMoney(penalty)}</span></div>
                    <div class="s-row"><span>Avans / Ödeme:</span><span>-${fmtMoney(advance)}</span></div>
                    <div class="s-row"><span>Kesinti / İcra:</span><span>-${fmtMoney(deduc)}</span></div>
                    <div class="s-divider-thick"></div>
                    <div class="net-row">
                        <div class="net-label">NET<br>ÖDENECEK</div>
                        <div class="net-val">${fmtMoney(net)}</div>
                    </div>
                </div>
            </div>
            
            <div class="footer">
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-title">YETKİLİ ONAYI</div>
                    <div class="sign-name">${companyName}</div>
                </div>
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-title">PERSONEL İMZA</div>
                    <div class="sign-name">${driver.full_name}</div>
                </div>
            </div>
        </body>
        </html>
    `;

    const handlePrint = async () => {
        try {
            await Print.printAsync({
                html: generateHTML(),
                orientation: Print.Orientation.portrait,
            });
        } catch (error) {
            alert('Yazdırma işlemi sırasında bir hata oluştu.');
            console.log(error);
        }
    };

    const handleShare = async () => {
        try {
            const { uri } = await Print.printToFileAsync({
                html: generateHTML(),
                base64: false
            });
            
            // Safe filename conversion
            const sanitizeStr = (str) => {
                if (!str) return '';
                return str.replace(/Ğ/g, 'G').replace(/ğ/g, 'g')
                    .replace(/Ü/g, 'U').replace(/ü/g, 'u')
                    .replace(/Ş/g, 'S').replace(/ş/g, 's')
                    .replace(/İ/g, 'I').replace(/ı/g, 'i')
                    .replace(/Ö/g, 'O').replace(/ö/g, 'o')
                    .replace(/Ç/g, 'C').replace(/ç/g, 'c')
                    .replace(/[^a-zA-Z0-9]/g, '_');
            };

            const safeName = sanitizeStr(driver.full_name);
            const safeMonth = sanitizeStr(monthName);
            const fileName = `${safeName}_${safeMonth}_Maas_Hakedis.pdf`;
            const newUri = FileSystem.cacheDirectory + fileName;
            
            // Ensure old file is removed if it exists
            try {
                await FileSystem.deleteAsync(newUri, { idempotent: true });
            } catch (e) {}
            
            await FileSystem.copyAsync({
                from: uri,
                to: newUri
            });

            await Sharing.shareAsync(newUri, { 
                UTI: '.pdf', 
                mimeType: 'application/pdf',
                dialogTitle: 'Hakediş Dökümünü Paylaş' 
            });
        } catch (error) {
            alert('Paylaşma işlemi sırasında bir hata oluştu: ' + error.message);
            console.log(error);
        }
    };

    return (
        <SafeAreaView style={st.container}>
            {/* Nav Header (App Controls) */}
            <View style={st.navHeader}>
                <Icon name="chevron-left" size={32} color="#000" onPress={() => navigation.goBack()} />
                <View style={st.headerActions}>
                    <TouchableOpacity style={[st.printBtn, { backgroundColor: '#3B82F6' }]} onPress={handleShare}>
                        <Icon name="share-variant" size={16} color="#fff" />
                        <Text style={st.printBtnText}>Paylaş</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={st.printBtn} onPress={handlePrint}>
                        <Icon name="printer" size={16} color="#fff" />
                        <Text style={st.printBtnText}>Yazdır</Text>
                    </TouchableOpacity>
                </View>
            </View>

            <ScrollView contentContainerStyle={st.scrollContent} showsVerticalScrollIndicator={false}>
                
                {/* A4 Document Header (Content) */}
                <View style={st.docHeader}>
                    <View style={st.docHeaderLeft}>
                        <Text style={st.mainTitle} numberOfLines={1} adjustsFontSizeToFit>HAKEDİŞ DETAYI</Text>
                        <Text style={st.subTitle} numberOfLines={1} adjustsFontSizeToFit>{monthName} DÖNEMİ</Text>
                    </View>
                    <View style={st.docHeaderRight}>
                        <Text style={st.companyName} numberOfLines={1} adjustsFontSizeToFit>{companyName}</Text>
                        <Text style={st.companySub} numberOfLines={1} adjustsFontSizeToFit>PERSONEL HAKEDİŞ DETAYI</Text>
                    </View>
                </View>

                <View style={st.thickDivider} />

                {/* Cards Row */}
                <View style={st.cardsRow}>
                    <View style={st.cardBox}>
                        <View style={st.cardLeftBorder} />
                        <View style={st.cardContent}>
                            <Text style={st.cardLabel} numberOfLines={1} adjustsFontSizeToFit>PERSONEL KAYITLARI</Text>
                            <Text style={st.driverName} numberOfLines={1} adjustsFontSizeToFit>{driver.full_name}</Text>
                            <Text style={st.tcText} numberOfLines={1} adjustsFontSizeToFit>TC: {driver.tc_no || 'Belirtilmedi'}</Text>
                            
                            <View style={st.datesRow}>
                                <View style={st.dateCol}>
                                    <Text style={[st.dateVal, {color: '#10B981'}]} numberOfLines={1} adjustsFontSizeToFit>• GİRİŞ: {driver.start_date ? dayjs(driver.start_date).format('DD.MM.YYYY') : '-'}</Text>
                                    <Text style={[st.dateShift, {color: '#10B981'}]} numberOfLines={1} adjustsFontSizeToFit>({driver.start_shift === 'morning' ? 'SABAH' : (driver.start_shift === 'evening' ? 'AKŞAM' : 'TAM GÜN')})</Text>
                                </View>
                                {driver.leave_date && (
                                    <View style={st.dateCol}>
                                        <Text style={[st.dateVal, {color: '#EF4444'}]} numberOfLines={1} adjustsFontSizeToFit>• AYRILIŞ: {dayjs(driver.leave_date).format('DD.MM.YYYY')}</Text>
                                        <Text style={[st.dateShift, {color: '#EF4444'}]} numberOfLines={1} adjustsFontSizeToFit>({driver.leave_shift === 'morning' ? 'SABAH' : (driver.leave_shift === 'evening' ? 'AKŞAM' : 'TAM GÜN')})</Text>
                                    </View>
                                )}
                            </View>
                        </View>
                    </View>

                    <View style={st.cardBox}>
                        <View style={st.cardLeftBorder} />
                        <View style={[st.cardContent, { alignItems: 'flex-end', justifyContent: 'center' }]}>
                            <Text style={st.cardLabel} numberOfLines={1} adjustsFontSizeToFit>AYLIK BAZ MAAŞ</Text>
                            <Text style={st.baseSalaryVal} numberOfLines={1} adjustsFontSizeToFit>{fmtMoney(base)}</Text>
                            <Text style={st.baseSalaryExp} numberOfLines={1} adjustsFontSizeToFit>{c.work_days} GÜNLÜK KIST MAAŞ</Text>
                        </View>
                    </View>
                </View>

                {/* Trips Table */}
                <View style={st.tableWrap}>
                    <View style={st.tableHeaderRow}>
                        <Text style={[st.thText, { flex: 2 }]} numberOfLines={1} adjustsFontSizeToFit>OPERASYONEL GÜZERGAH</Text>
                        <Text style={[st.thText, { flex: 0.8, textAlign: 'center' }]} numberOfLines={1} adjustsFontSizeToFit>SABAH</Text>
                        <Text style={[st.thText, { flex: 0.8, textAlign: 'center' }]} numberOfLines={1} adjustsFontSizeToFit>AKŞAM</Text>
                        <Text style={[st.thText, { flex: 1.2, textAlign: 'right' }]} numberOfLines={1} adjustsFontSizeToFit>HAKEDİŞ</Text>
                    </View>

                    {(!c.details || c.details.length === 0) ? (
                        <View style={st.noDataBox}><Text style={st.noDataText}>Ekstra sefer kaydı bulunamadı.</Text></View>
                    ) : (
                        c.details.map((detail, idx) => (
                            <View key={idx} style={st.tableRow}>
                                <View style={{ flex: 2, paddingRight: 4 }}>
                                    <Text style={st.tdCustomer} numberOfLines={1} adjustsFontSizeToFit>{detail.customer_name}</Text>
                                    <Text style={st.tdRoute} numberOfLines={1} adjustsFontSizeToFit>{detail.route_name}</Text>
                                </View>
                                <Text style={[st.tdVal, { flex: 0.8, textAlign: 'center' }]} numberOfLines={1} adjustsFontSizeToFit>{detail.morning_count}</Text>
                                <Text style={[st.tdVal, { flex: 0.8, textAlign: 'center' }]} numberOfLines={1} adjustsFontSizeToFit>{detail.evening_count}</Text>
                                <Text style={[st.tdFee, { flex: 1.2, textAlign: 'right' }]} numberOfLines={1} adjustsFontSizeToFit>{fmtMoney(detail.total_fee)}</Text>
                            </View>
                        ))
                    )}
                </View>

                {/* Summary Section */}
                <View style={st.summarySection}>
                    <View style={st.summaryLeft}>
                        <Text style={st.summarySideTitle}>EKSTRA ÖDEME & KESİNTİ ANALİZİ</Text>
                        <View style={st.notesArea}>
                            {(e.extra_notes || e.deduction_notes) ? (
                                <Text style={st.notesText}>{`${e.extra_notes || ''} ${e.deduction_notes || ''}`.trim()}</Text>
                            ) : null}
                        </View>
                    </View>

                    <View style={st.summaryRight}>
                        <View style={st.sBox}>
                            <View style={st.sRow}><Text style={st.sLabel} numberOfLines={1} adjustsFontSizeToFit>Maaş + Seferler:</Text><Text style={st.sVal} numberOfLines={1} adjustsFontSizeToFit>+{fmtMoney(base + extraEarn)}</Text></View>
                            <View style={st.sRow}><Text style={[st.sLabel, {color: '#10B981'}]} numberOfLines={1} adjustsFontSizeToFit>Ekstra (+):</Text><Text style={[st.sVal, {color: '#10B981'}]} numberOfLines={1} adjustsFontSizeToFit>+{fmtMoney(extraB)}</Text></View>
                            
                            <View style={st.sRow}><Text style={st.sLabelBold} numberOfLines={1} adjustsFontSizeToFit>TOPLAM BRÜT:</Text><Text style={st.sValBold} numberOfLines={1} adjustsFontSizeToFit>{fmtMoney(gross)}</Text></View>
                            
                            <View style={st.sDivider} />
                            
                            <View style={st.sRow}><Text style={st.sLabel} numberOfLines={1} adjustsFontSizeToFit>Banka Ödemesi:</Text><Text style={st.sVal} numberOfLines={1} adjustsFontSizeToFit>-{fmtMoney(bank)}</Text></View>
                            <View style={st.sRow}><Text style={st.sLabel} numberOfLines={1} adjustsFontSizeToFit>Trafik Cezası:</Text><Text style={st.sVal} numberOfLines={1} adjustsFontSizeToFit>-{fmtMoney(penalty)}</Text></View>
                            <View style={st.sRow}><Text style={st.sLabel} numberOfLines={1} adjustsFontSizeToFit>Avans / Ödeme:</Text><Text style={st.sVal} numberOfLines={1} adjustsFontSizeToFit>-{fmtMoney(advance)}</Text></View>
                            <View style={st.sRow}><Text style={st.sLabel} numberOfLines={1} adjustsFontSizeToFit>Kesinti / İcra:</Text><Text style={st.sVal} numberOfLines={1} adjustsFontSizeToFit>-{fmtMoney(deduc)}</Text></View>
                            
                            <View style={st.sDividerThick} />

                            <View style={st.netRow}>
                                <Text style={st.netLabel}>NET{"\n"}ÖDENECEK</Text>
                                <Text style={st.netVal} numberOfLines={1} adjustsFontSizeToFit>{fmtMoney(net)}</Text>
                            </View>
                        </View>
                    </View>
                </View>

                {/* Signatures */}
                <View style={st.signaturesRow}>
                    <View style={st.signCol}>
                        <View style={st.signLine} />
                        <Text style={st.signTitle}>YETKİLİ ONAYI</Text>
                        <Text style={st.signName}>{companyName}</Text>
                    </View>
                    <View style={st.signCol}>
                        <View style={st.signLine} />
                        <Text style={st.signTitle}>PERSONEL İMZA</Text>
                        <Text style={st.signName}>{driver.full_name}</Text>
                    </View>
                </View>

            </ScrollView>
        </SafeAreaView>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#FFFFFF' },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    navHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 16, paddingTop: 10, paddingBottom: 5 },
    headerActions: { flexDirection: 'row', gap: 8 },
    printBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#0F172A', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 10, gap: 6 },
    printBtnText: { color: '#fff', fontSize: 11, fontWeight: '800' },
    
    scrollContent: { padding: 20, paddingBottom: 120 },
    
    docHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginTop: 10 },
    docHeaderLeft: { flex: 1, paddingRight: 10 },
    mainTitle: { fontSize: 24, fontWeight: '900', color: '#0F172A', letterSpacing: 0.5 },
    subTitle: { fontSize: 12, fontWeight: '800', color: '#3B82F6', marginTop: 4, letterSpacing: 0.5 },
    
    docHeaderRight: { flex: 1, alignItems: 'flex-end', justifyContent: 'center' },
    companyName: { fontSize: 16, fontWeight: '900', color: '#0F172A', textAlign: 'right' },
    companySub: { fontSize: 9, fontWeight: '700', color: '#94A3B8', marginTop: 2, letterSpacing: 1 },
    
    thickDivider: { height: 4, backgroundColor: '#0F172A', marginTop: 16, marginBottom: 24 },

    cardsRow: { flexDirection: 'column', gap: 16, marginBottom: 24 },
    cardBox: { flexDirection: 'row', backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#E2E8F0', overflow: 'hidden' },
    cardLeftBorder: { width: 6, backgroundColor: '#3B82F6' },
    cardContent: { flex: 1, padding: 16 },
    
    cardLabel: { fontSize: 10, fontWeight: '800', color: '#94A3B8', marginBottom: 6, letterSpacing: 0.5 },
    driverName: { fontSize: 20, fontWeight: '900', color: '#0F172A', marginBottom: 8 },
    tcText: { fontSize: 11, color: '#64748B', marginBottom: 12, fontWeight: '600' },
    
    datesRow: { flexDirection: 'row', gap: 16 },
    dateCol: { flex: 1 },
    dateVal: { fontSize: 11, fontWeight: '800', marginBottom: 2 },
    dateShift: { fontSize: 10, fontWeight: '600' },

    baseSalaryVal: { fontSize: 28, fontWeight: '900', color: '#0F172A', marginBottom: 4 },
    baseSalaryExp: { fontSize: 11, fontWeight: '700', color: '#64748B' },

    tableWrap: { marginBottom: 32 },
    tableHeaderRow: { flexDirection: 'row', backgroundColor: '#0F172A', paddingHorizontal: 12, paddingVertical: 12, borderRadius: 16, marginBottom: 8 },
    thText: { fontSize: 9, fontWeight: '800', color: '#FFFFFF', letterSpacing: 0.5 },
    
    tableRow: { flexDirection: 'row', paddingHorizontal: 12, paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: '#F1F5F9', alignItems: 'center' },
    tdCustomer: { fontSize: 11, fontWeight: '800', color: '#0F172A', marginBottom: 2 },
    tdRoute: { fontSize: 10, color: '#64748B' },
    tdVal: { fontSize: 11, fontWeight: '700', color: '#334155' },
    tdFee: { fontSize: 12, fontWeight: '800', color: '#3B82F6' },
    
    noDataBox: { padding: 20, alignItems: 'center' },
    noDataText: { fontSize: 12, color: '#94A3B8', fontStyle: 'italic' },

    summarySection: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 40, gap: 16 },
    summaryLeft: { flex: 1 },
    summarySideTitle: { fontSize: 11, fontWeight: '900', color: '#0F172A', marginBottom: 12 },
    notesArea: { marginTop: 8 },
    notesText: { fontSize: 11, color: '#64748B', fontStyle: 'italic' },

    summaryRight: { flex: 1.5 },
    sBox: { borderRadius: 16, borderWidth: 2, borderColor: '#0F172A', padding: 12 },
    sRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 },
    sLabel: { flex: 1, fontSize: 10, color: '#475569', fontWeight: '600', paddingRight: 4 },
    sVal: { flex: 1, fontSize: 11, color: '#475569', fontWeight: '700', textAlign: 'right' },
    
    sLabelBold: { flex: 1, fontSize: 11, color: '#0F172A', fontWeight: '900', marginTop: 8, paddingRight: 4 },
    sValBold: { flex: 1, fontSize: 11, color: '#0F172A', fontWeight: '900', marginTop: 8, textAlign: 'right' },
    
    sDivider: { height: 1, backgroundColor: '#E2E8F0', marginVertical: 10 },
    sDividerThick: { height: 2, backgroundColor: '#0F172A', marginVertical: 10 },

    netRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
    netLabel: { fontSize: 11, fontWeight: '900', color: '#3B82F6', lineHeight: 12 },
    netVal: { fontSize: 22, fontWeight: '900', color: '#0F172A', flex: 1, textAlign: 'right', marginLeft: 8 },

    signaturesRow: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 20 },
    signCol: { flex: 1, alignItems: 'center', paddingHorizontal: 10 },
    signLine: { width: '100%', height: 2, backgroundColor: '#000', marginBottom: 8 },
    signTitle: { fontSize: 10, fontWeight: '800', color: '#0F172A', marginBottom: 4 },
    signName: { fontSize: 12, fontWeight: '800', color: '#0F172A', textAlign: 'center' },
});
